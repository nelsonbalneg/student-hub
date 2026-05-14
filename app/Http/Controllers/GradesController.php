<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use App\Services\StudentEvaluationApiService;
use App\Services\GetActiveAcademicTermService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class GradesController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly StudentEvaluationApiService $evaluationApi,
        private readonly GetActiveAcademicTermService $activeTermService
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $campusId = $activeSemester['campusId'] ?: 1;
        $evaluationId = null;
        
        // Check for grade viewing bypass rule with safe fallback.
        // If table/campus data is not ready yet, do not block grade/evaluation flow.
        $bypassEvaluation = false;
        if (!blank($user->campus_id)) {
            try {
                $bypassEvaluation = \App\Models\GradeViewingRule::where('site_campus_id', $user->campus_id)
                    ->where('is_active', true)
                    ->where('bypass_evaluation', true)
                    ->exists();
            } catch (\Throwable $e) {
                Log::warning('Grade viewing rules lookup failed; defaulting bypass_evaluation to false', [
                    'campus_id' => $user->campus_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);
        
        $evaluationError = null;
        $evaluationLookup = [];
        $activeTerm = $this->activeTermService->execute($user->campus_id);
        $activeTermId = $activeTerm?->term_id;

        if (!$bypassEvaluation && $studentNo) {
            try {
                $evaluationId = $this->evaluationApi->findStudentByStudentNo($studentNo);
                
                if ($evaluationId) {
                    $details = $this->evaluationApi->getStudentEvaluationDetails($evaluationId);
                    $evaluationLookup = $this->evaluationApi->buildEvaluationLookup($details);
                }
            } catch (\Exception $e) {
                Log::error('Evaluation API error in GradesController', [
                    'student_no' => $studentNo,
                    'message' => $e->getMessage()
                ]);
                $evaluationError = 'Faculty evaluation status could not be verified. Please try again later.';
            }
        }

        // Enrich grade data with evaluation status
        if (!empty($gradeReport['data']) && is_array($gradeReport['data'])) {
            $gradeReport['data'] = array_map(function ($term) use ($evaluationLookup, $evaluationId, $activeTermId) {
                if (!isset($term['grades']) || !is_array($term['grades'])) {
                    return $term;
                }

                $termId = $term['termId'] ?? $term['term_id'] ?? null;

                $term['grades'] = array_map(function ($grade) use ($evaluationLookup, $evaluationId, $termId, $activeTermId) {
                    // Match the termId and subjectId/courseId
                    // Use the termId from the parent term if not in the grade record
                    $rowTermId = $grade['termId'] ?? $grade['term_id'] ?? $termId;
                    
                    // ONLY trap if the term matches the ACTIVE TERM in site settings
                    if ($activeTermId && (string) $rowTermId !== (string) $activeTermId) {
                        $grade['requires_evaluation'] = false;
                        return $grade;
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
                        $grade['faculty_names'] = $eval['faculty_names'];
                        
                        // Build the evaluation payload for the frontend
                        $grade['evaluation_payload'] = array_merge($eval['evaluation_payload'], [
                            'studentId' => $evaluationId,
                        ]);
                    }

                    return $grade;
                }, $term['grades']);

                return $term;
            }, $gradeReport['data']);
        }

        return Inertia::render('Grades/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
                'bypass_evaluation' => $bypassEvaluation,
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

        if (!($result['ok'] ?? false)) {
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

        return back()->with('success', 'Evaluation submitted successfully.');
    }
}
