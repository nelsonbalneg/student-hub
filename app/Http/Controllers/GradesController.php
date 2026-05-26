<?php

namespace App\Http\Controllers;

use App\Models\GradeViewingRule;
use App\Models\SiteCampus;
use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\StudentEvaluationApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GradesController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly StudentEvaluationApiService $evaluationApi,
        private readonly GetActiveAcademicTermService $activeTermService,
        private readonly GradeComputationService $gradeComputation
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;
        $evaluationCampusId = blank($user->campus_id) ? null : (string) $user->campus_id;

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $campusId = $activeSemester['campusId'] ?: 1;
        $evaluationId = null;

        // Check for grade viewing bypass rule with safe fallback.
        // If table/campus data is not ready yet, do not block grade/evaluation flow.
        $bypassEvaluation = false;
        $showGwaGpa = true;
        if (! blank($user->campus_id)) {
            try {
                $realCampusExists = SiteCampus::where('real_campus_id', (string) $user->campus_id)->exists();

                $gradeViewingRule = GradeViewingRule::whereHas('campus', function ($query) use ($realCampusExists, $user) {
                    if ($realCampusExists) {
                        $query->where('real_campus_id', (string) $user->campus_id);

                        return;
                    }

                    $query->where('id', $user->campus_id);
                })
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                $bypassEvaluation = (bool) $gradeViewingRule?->bypass_evaluation;
                $showGwaGpa = $gradeViewingRule?->show_gwa_gpa ?? true;
            } catch (\Throwable $e) {
                Log::warning('Grade viewing rules lookup failed; using strict grade viewing defaults', [
                    'campus_id' => $user->campus_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);

        $evaluationError = null;
        $evaluationLookup = [];
        $lockGradesDueToEvaluationVerificationFailure = false;
        $activeTerm = $this->activeTermService->execute($user->campus_id);
        $activeTermId = $activeTerm?->term_id;

        if (! $bypassEvaluation && $studentNo && $evaluationCampusId && $tenantId) {
            try {
                Log::info('Starting grade evaluation verification', [
                    'user_id' => $user->id,
                    'student_no' => $studentNo,
                    'campus_id' => $evaluationCampusId,
                    'tenant_id' => $tenantId,
                    'active_term_id' => $activeTermId,
                ]);

                $evaluationId = $this->evaluationApi->findStudentByStudentNo($studentNo, $evaluationCampusId, $tenantId);

                if ($evaluationId) {
                    $details = $this->evaluationApi->getStudentEvaluationDetails($evaluationId);
                    $evaluationLookup = $this->evaluationApi->buildEvaluationLookup($details);

                    Log::info('Finished grade evaluation verification lookup', [
                        'user_id' => $user->id,
                        'student_no' => $studentNo,
                        'evaluation_id' => $evaluationId,
                        'matched_subject_count' => count($evaluationLookup),
                    ]);
                } else {
                    $lockGradesDueToEvaluationVerificationFailure = true;
                    $evaluationError = 'Faculty evaluation status could not be verified. Grades are locked until verification is available.';
                }

                if ($evaluationId && empty($evaluationLookup)) {
                    $lockGradesDueToEvaluationVerificationFailure = true;
                    $evaluationError = 'Faculty evaluation status could not be verified. Grades are locked until verification is available.';
                }
            } catch (\Exception $e) {
                Log::error('Evaluation API error in GradesController', [
                    'student_no' => $studentNo,
                    'message' => $e->getMessage(),
                ]);
                $lockGradesDueToEvaluationVerificationFailure = true;
                $evaluationError = 'Faculty evaluation status could not be verified. Grades are locked until verification is available.';
            }
        } elseif (! $bypassEvaluation) {
            Log::warning('Skipping grade evaluation verification because required context is missing', [
                'user_id' => $user->id,
                'student_no' => $studentNo,
                'campus_id' => $evaluationCampusId,
                'tenant_id' => $tenantId,
            ]);

            $lockGradesDueToEvaluationVerificationFailure = true;
            $evaluationError = 'Faculty evaluation status could not be verified. Grades are locked until verification is available.';
        }

        // Enrich grade data with evaluation status
        if (! empty($gradeReport['data']) && is_array($gradeReport['data'])) {
            $gradeReport['data'] = array_map(function ($term) use ($evaluationLookup, $evaluationId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure) {
                if (! isset($term['grades']) || ! is_array($term['grades'])) {
                    return $term;
                }

                $termId = $term['termId'] ?? $term['term_id'] ?? null;

                $term['grades'] = array_map(function ($grade) use ($evaluationLookup, $evaluationId, $termId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure) {
                    // Match the termId and subjectId/courseId
                    // Use the termId from the parent term if not in the grade record
                    $rowTermId = $grade['termId'] ?? $grade['term_id'] ?? $termId;

                    // ONLY trap if the term matches the ACTIVE TERM in site settings
                    if ($activeTermId && (string) $rowTermId !== (string) $activeTermId) {
                        $grade['requires_evaluation'] = false;

                        return $grade;
                    }

                    if ($lockGradesDueToEvaluationVerificationFailure) {
                        $grade['requires_evaluation'] = true;
                        $grade['evaluation_status'] = 'Verification Unavailable';

                        return $this->maskEvaluationLockedGradeFields($grade);
                    }

                    $courseId = $grade['courseId'] ?? $grade['course_id'] ?? $grade['subjectId'] ?? $grade['subject_id'] ?? null;

                    $key = "{$rowTermId}-{$courseId}";
                    $eval = $evaluationLookup[$key] ?? null;

                    $grade['requires_evaluation'] = $eval ? $eval['requires_evaluation'] : false;
                    $grade['evaluation_status'] = $eval['status'] ?? 'Not Found';

                    if ($eval) {
                        $grade['evaluation_period_id'] = $eval['evaluation_period_id'];
                        $grade['subject_for_evaluation_id'] = $eval['subject_for_evaluation_id'];
                        $grade['subject_id'] = $eval['subject_id'];
                        $grade['subject_title'] = $eval['subject_title'];
                        $grade['pending_evaluations'] = $eval['pending_evaluations'];
                        $grade['evaluated_evaluations'] = $eval['evaluated_evaluations'];
                        $grade['faculty_names'] = $eval['faculty_names'];

                        // Build the evaluation payload for the frontend
                        $grade['evaluation_payload'] = array_merge($eval['evaluation_payload'], [
                            'studentId' => $evaluationId,
                        ]);
                    }

                    if ($grade['requires_evaluation']) {
                        $grade = $this->maskEvaluationLockedGradeFields($grade);
                    }

                    return $grade;
                }, $term['grades']);

                return $term;
            }, $gradeReport['data']);
        }

        if (! empty($gradeReport['data']) && is_array($gradeReport['data'])) {
            $computedGrades = $this->gradeComputation->computeForTerms($gradeReport['data']);
            $gradeReport['data'] = $computedGrades['terms'];
            $gradeReport['computed_summary'] = $computedGrades['overall'];
        }

        return Inertia::render('Grades/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
                'bypass_evaluation' => $bypassEvaluation,
                'show_gwa_gpa' => $showGwaGpa,
                'evaluation_id' => $evaluationId,
            ],
            'gradeReport' => $gradeReport,
            'evaluation_error' => $evaluationError,
        ]);
    }

    public function submitEvaluation(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'templateSurveyId' => ['required'],
            'studentId' => ['nullable'],
            'evaluationId' => ['required'],
            'code' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'studentNo' => ['nullable', 'string'],
            'studentName' => ['nullable', 'string'],
            'subjectId' => ['required'],
            'schedId' => ['nullable'],
            'campusId' => ['nullable'],
            'termId' => ['nullable'],
            'isLaboratory' => ['required', 'boolean'],
            'surveyAnswers' => ['required', 'array', 'min:1'],
            'surveyAnswers.*.sortOrder' => ['required'],
            'surveyAnswers.*.templateQuestionId' => ['required'],
            'surveyAnswers.*.questionType' => ['required'],
            'surveyAnswers.*.questionStatement' => ['required', 'string'],
            'surveyAnswers.*.description' => ['nullable', 'string'],
            'surveyAnswers.*.starCount' => ['nullable'],
            'surveyAnswers.*.starRating' => ['nullable'],
            'surveyAnswers.*.shortAnswer' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            Log::warning('Grades evaluation validation failed', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $payload = [
            'templateSurveyId' => $validated['templateSurveyId'],
            'studentId' => $validated['studentId'] ?? '',
            'evaluationId' => (string) $validated['evaluationId'],
            'code' => $validated['code'] ?? '',
            'name' => $validated['name'] ?? '',
            'description' => '',
            'studentNo' => $validated['studentNo'],
            'studentName' => $validated['studentName'],
            'subjectId' => $validated['subjectId'],
            'schedId' => $validated['schedId'] ?? '',
            'campusId' => $validated['campusId'] ?? '',
            'termId' => $validated['termId'],
            'scheduleId' => $validated['schedId'] ?? '',
            'isLaboratory' => $validated['isLaboratory'],
            'surveyAnswers' => array_map(static function (array $item): array {
                return [
                    'sortOrder' => $item['sortOrder'],
                    'templateQuestionId' => $item['templateQuestionId'],
                    'questionType' => $item['questionType'],
                    'questionStatement' => $item['questionStatement'],
                    'description' => $item['description'] ?? '',
                    'shortAnswer' => $item['shortAnswer'] ?? '',
                    'longAnswer' => '',
                    'selectionItems' => '',
                    'selectedItem' => '',
                    'starCount' => $item['starCount'] ?? 0,
                    'starRating' => (int) ($item['starRating'] ?? 0),
                    'multipleChoiceSelections' => '',
                    'multipleChoiceAnswer' => '',
                    'allowMultipleSelections' => true,
                ];
            }, $validated['surveyAnswers']),
        ];

        $result = $this->evaluationApi->submitSurvey($payload);
        Log::info('Grades evaluation submit attempt', [
            'payload' => $payload,
            'result' => $result,
        ]);

        if (! ($result['ok'] ?? false)) {
            Log::warning('Grades evaluation submit failed', [
                'message' => $result['message'] ?? null,
                'payload' => $payload,
            ]);

            throw ValidationException::withMessages([
                'evaluation_submit' => $result['message'] ?? 'Unable to submit your evaluation. Please try again.',
            ]);
        }

        Log::info('Grades evaluation submit success', [
            'evaluation_id' => $validated['evaluationId'] ?? null,
            'template_survey_id' => $validated['templateSurveyId'] ?? null,
            'student_no' => $validated['studentNo'] ?? null,
        ]);

        return redirect()->route('grades.index')->with('success', 'Evaluation submitted successfully.');
    }

    private function maskEvaluationLockedGradeFields(array $grade): array
    {
        foreach ([
            'midTerm',
            'midterm',
            'mid_term',
            'final',
            'final_exam',
            'finalGrade',
            'final_grade',
            'grade',
            'rating',
        ] as $key) {
            if (array_key_exists($key, $grade)) {
                $grade[$key] = 'LOCKED';
            }
        }

        return $grade;
    }
}
