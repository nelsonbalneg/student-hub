<?php

namespace App\Http\Controllers;

use App\Enums\GradeAccessScope;
use App\Enums\GradeEvaluationMode;
use App\Models\GradeViewingRule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\GradeViewingRuleResolver;
use App\Services\PhysicalFitnessPermissionService;
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
        private readonly GradeComputationService $gradeComputation,
        private readonly PhysicalFitnessPermissionService $physicalFitnessPermission,
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

        $bypassEvaluation = false;
        $evaluationUserOverride = false;
        $showGwaGpa = false;
        $gradeEvaluationMode = GradeEvaluationMode::DisabledHidden;
        $accessScope = GradeAccessScope::AllGrades;
        $ruleCampusId = null;
        $policyTermId = null;
        $activeRules = collect();
        $ruleResolver = new GradeViewingRuleResolver;
        if (! blank($user->campus_id)) {
            try {
                $realCampusExists = SiteCampus::where('real_campus_id', (string) $user->campus_id)->exists();

                $activeRules = GradeViewingRule::with('selectedAcademicTerm')->withCount(['overrideUsers as has_evaluation_override' => fn ($query) => $query->whereKey($user->id)])->whereHas('campus', function ($query) use ($realCampusExists, $user) {
                    if ($realCampusExists) {
                        $query->where('real_campus_id', (string) $user->campus_id);

                        return;
                    }

                    $query->where('id', $user->campus_id);
                })
                    ->where('is_active', true)
                    ->latest()->orderByDesc('id')
                    ->get();
                $gradeViewingRule = $activeRules->first();

                if ($gradeViewingRule !== null) {
                    $gradeEvaluationMode = GradeEvaluationMode::tryFrom($gradeViewingRule?->grade_evaluation_mode ?? '') ?? GradeEvaluationMode::EnabledRequired;
                    $ruleCampusId = $gradeViewingRule?->site_campus_id;
                    $policyTermId = $gradeViewingRule->selectedAcademicTerm?->term_id;
                    $accessScope = GradeAccessScope::tryFrom($gradeViewingRule?->access_scope ?? '') ?? GradeAccessScope::CurrentTerm;
                    $bypassEvaluation = (bool) $gradeViewingRule?->bypass_evaluation;
                    $showGwaGpa = $gradeViewingRule?->show_gwa_gpa ?? true;
                    $evaluationUserOverride = (bool) $gradeViewingRule->has_evaluation_override;
                }
            } catch (\Throwable $e) {
                Log::warning('Grade viewing rules lookup failed; hiding grades by default', [
                    'campus_id' => $user->campus_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);

        $evaluationError = null;
        $evaluationErrorType = null; // 'connectivity' | 'no_data' | 'missing_context'
        $evaluationLookup = [];
        $lockGradesDueToEvaluationVerificationFailure = false;
        $activeTerm = $ruleCampusId !== null
            ? SiteAcademicTerm::where('site_campus_id', $ruleCampusId)->where('status', 'Active')->first()
            : $this->activeTermService->execute($user->campus_id);
        $activeTermId = $activeTerm?->term_id;

        $needsVerification = $activeRules->contains(fn ($rule) => (bool) $rule->has_evaluation_override || (GradeEvaluationMode::tryFrom($rule->grade_evaluation_mode ?? '') ?? GradeEvaluationMode::EnabledRequired)->verificationRequired((bool) $rule->bypass_evaluation));
        $verificationCanLock = $activeRules->contains(fn ($rule) => (GradeEvaluationMode::tryFrom($rule->grade_evaluation_mode ?? '') ?? GradeEvaluationMode::EnabledRequired)->verificationRequired((bool) $rule->bypass_evaluation) && (GradeEvaluationMode::tryFrom($rule->grade_evaluation_mode ?? '') ?? GradeEvaluationMode::EnabledRequired)->gradesLocked((bool) $rule->bypass_evaluation, true));
        $showGwaGpa = $activeRules->contains(fn ($rule) => $rule->show_gwa_gpa ?? true);

        if ($needsVerification && $studentNo && $evaluationCampusId && $tenantId) {
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
                    $evaluationErrorType = 'connectivity';
                    $evaluationError = 'The faculty evaluation service could not be reached. Your grades are temporarily locked until the service is available again.';
                }

                if ($evaluationId && empty($evaluationLookup)) {
                    $lockGradesDueToEvaluationVerificationFailure = true;
                    $evaluationErrorType = 'no_data';
                    $evaluationError = 'The evaluation service returned no data for your account. Your grades are temporarily locked. Please try again later or contact the registrar.';
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $isConnectivity = str_contains($message, 'cURL') ||
                    str_contains($message, 'Connection') ||
                    str_contains($message, 'timed out') ||
                    str_contains($message, 'SSL') ||
                    str_contains($message, 'Could not resolve') ||
                    str_contains($message, 'Failed to connect');

                Log::error('Evaluation API error in GradesController', [
                    'student_no' => $studentNo,
                    'message' => $message,
                    'type' => $isConnectivity ? 'connectivity' : 'unknown',
                ]);
                $lockGradesDueToEvaluationVerificationFailure = true;
                $evaluationErrorType = $isConnectivity ? 'connectivity' : 'no_data';
                $evaluationError = $isConnectivity
                    ? 'The faculty evaluation service is currently unreachable (network or SSL issue). Your grades are temporarily locked. Please try again in a few minutes.'
                    : 'An unexpected error occurred while verifying your evaluation status. Your grades are temporarily locked. Please contact the registrar if this persists.';
            }
        } elseif ($needsVerification) {
            Log::warning('Skipping grade evaluation verification because required context is missing', [
                'user_id' => $user->id,
                'student_no' => $studentNo,
                'campus_id' => $evaluationCampusId,
                'tenant_id' => $tenantId,
            ]);

            $lockGradesDueToEvaluationVerificationFailure = true;
            $evaluationErrorType = 'missing_context';
            $evaluationError = 'Your account is missing required information (campus or tenant). Please contact your registrar or system administrator to complete your account setup.';
        }

        if (! $verificationCanLock) {
            $evaluationError = null;
            $evaluationErrorType = null;
        }

        // Enrich grade data with evaluation status
        if (! empty($gradeReport['data']) && is_array($gradeReport['data'])) {
            $gradeReport['data'] = array_map(function ($term) use ($evaluationLookup, $evaluationId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure, $gradeEvaluationMode, $bypassEvaluation, $evaluationUserOverride, $activeRules, $ruleResolver) {
                if (! isset($term['grades']) || ! is_array($term['grades'])) {
                    return $term;
                }

                $termId = $term['termId'] ?? $term['term_id'] ?? null;
                $termRule = $ruleResolver->resolve($activeRules, $termId, $activeTermId);
                $term['show_gwa_gpa'] = $termRule?->show_gwa_gpa ?? ($activeRules->isNotEmpty());

                $term['grades'] = array_map(function ($grade) use ($evaluationLookup, $evaluationId, $termId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure, $gradeEvaluationMode, $bypassEvaluation, $evaluationUserOverride, $activeRules, $ruleResolver) {
                    $rowTermId = $grade['termId'] ?? $grade['term_id'] ?? $termId;
                    $rowRule = $ruleResolver->resolve($activeRules, $rowTermId, $activeTermId);
                    $grade['evaluation_in_scope'] = $rowRule !== null || $activeRules->isEmpty();
                    $gradeEvaluationMode = $rowRule ? (GradeEvaluationMode::tryFrom($rowRule->grade_evaluation_mode ?? '') ?? GradeEvaluationMode::EnabledRequired) : GradeEvaluationMode::DisabledHidden;
                    $bypassEvaluation = (bool) $rowRule?->bypass_evaluation;
                    $evaluationUserOverride = (bool) $rowRule?->has_evaluation_override;
                    $grade['grade_evaluation_mode'] = $gradeEvaluationMode->value;
                    $grade['evaluation_enabled'] = $evaluationUserOverride || $gradeEvaluationMode->evaluationEnabled($bypassEvaluation);
                    $grade['applied_rule_id'] = $rowRule?->id;
                    if (! $grade['evaluation_in_scope']) {
                        $courseId = $grade['courseId'] ?? $grade['course_id'] ?? $grade['subjectId'] ?? $grade['subject_id'] ?? null;
                        $eval = $evaluationLookup["{$rowTermId}-{$courseId}"] ?? null;
                        $grade['evaluation_completed'] = $eval === null ? null : (! $eval['requires_evaluation'] && (! empty($eval['evaluated_evaluations']) || strtolower(trim($eval['status'] ?? '')) === 'evaluated'));
                        $grade['evaluation_status'] = $eval['status'] ?? 'Verification Unavailable';
                        $grade['requires_evaluation'] = false;

                        return $grade;
                    }

                    if (! $evaluationUserOverride && ! $gradeEvaluationMode->verificationRequired($bypassEvaluation)) {
                        $grade['requires_evaluation'] = $gradeEvaluationMode->gradesLocked($bypassEvaluation, false);
                        $grade['evaluation_status'] = $grade['requires_evaluation'] ? 'Grade Viewing Disabled' : 'Evaluation Disabled';

                        return $grade['requires_evaluation'] ? $this->maskEvaluationLockedGradeFields($grade) : $grade;
                    }

                    if ($lockGradesDueToEvaluationVerificationFailure && $gradeEvaluationMode->gradesLocked($bypassEvaluation, true)) {
                        $grade['requires_evaluation'] = true;
                        $grade['evaluation_status'] = 'Verification Unavailable';

                        return $this->maskEvaluationLockedGradeFields($grade);
                    }

                    $courseId = $grade['courseId'] ?? $grade['course_id'] ?? $grade['subjectId'] ?? $grade['subject_id'] ?? null;

                    $key = "{$rowTermId}-{$courseId}";
                    $eval = $evaluationLookup[$key] ?? null;

                    $needsEvaluation = $eval ? $eval['requires_evaluation'] : false;
                    if ($gradeEvaluationMode === GradeEvaluationMode::DisabledEvaluated) {
                        $needsEvaluation = ! $eval || $needsEvaluation || (empty($eval['evaluated_evaluations']) && strtolower(trim($eval['status'] ?? '')) !== 'evaluated');
                    }
                    $grade['requires_evaluation'] = $gradeEvaluationMode->gradesLocked($bypassEvaluation, $needsEvaluation);
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
                'evaluation_user_override' => $evaluationUserOverride,
                'grade_evaluation_mode' => $gradeEvaluationMode->value,
                'access_scope' => $accessScope->value,
                'policy_term_id' => $policyTermId,
                'show_gwa_gpa' => $showGwaGpa,
                'evaluation_id' => $evaluationId,
            ],
            'gradeReport' => $gradeReport,
            'evaluation_error' => $evaluationError,
            'evaluation_error_type' => $evaluationErrorType,
            'active_term_id' => $activeTermId,
            'physical_fitness_shortcut_enabled' => $this->physicalFitnessPermission->gradesShortcutEnabled(),
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
            'reeExam',
            'reExam',
            'reexam',
            're_exam',
            'reExamination',
            're_examination',
        ] as $key) {
            if (array_key_exists($key, $grade)) {
                $grade[$key] = 'LOCKED';
            }
        }

        return $grade;
    }
}
