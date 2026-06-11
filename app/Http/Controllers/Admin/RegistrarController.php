<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeViewingRule;
use App\Models\SiteCampus;
use App\Models\SsoCampus;
use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\RegistrarApiService;
use App\Services\StudentEvaluationApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegistrarController extends Controller
{
    public function studentProfile(): Response
    {
        return Inertia::render('Registrar/Placeholder', [
            'title' => 'Student Profile',
            'description' => 'Registrar student profile tools will be available here.',
        ]);
    }

    public function reportOfGrades(Request $request): Response
    {
        return Inertia::render('Registrar/ReportOfGrades', [
            'campuses' => $this->campuses(),
            'filters' => [
                'student_no' => $request->old('student_no', ''),
                'campus_id' => $request->old('campus_id', ''),
            ],
            'result' => session('registrar_grade_report'),
            'error' => session('registrar_grade_report_error'),
        ]);
    }

    public function searchReportOfGrades(
        Request $request,
        RegistrarApiService $registrarApi,
        GradeComputationService $gradeComputation,
        StudentEvaluationApiService $evaluationApi,
        GetActiveAcademicTermService $activeTermService,
        AcademicApiService $academicApi,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'student_no' => ['required', 'string', 'max:50'],
            'campus_id' => [
                'required',
                Rule::exists('sso_sqlsrv.campuses', 'id'),
            ],
        ]);

        $campus = SsoCampus::query()->findOrFail($validated['campus_id']);
        $tenantId = $campus->tenantId();

        if (! $tenantId) {
            return back()
                ->withInput()
                ->with('registrar_grade_report_error', 'The selected campus has no tenant ID configured.');
        }

        $report = $registrarApi->getStudentGradeReport($validated['student_no'], $tenantId);

        if ($report['error']) {
            return back()
                ->withInput()
                ->with('registrar_grade_report_error', $report['error']);
        }

        $evaluationCampusId = (string) $validated['campus_id'];
        $studentNo = $validated['student_no'];

        // Check for grade viewing bypass rule with safe fallback.
        // If table/campus data is not ready yet, do not block grade/evaluation flow.
        $bypassEvaluation = false;
        $showGwaGpa = true;
        try {
            $realCampusExists = SiteCampus::where('real_campus_id', $evaluationCampusId)->exists();

            $gradeViewingRule = GradeViewingRule::whereHas('campus', function ($query) use ($realCampusExists, $evaluationCampusId) {
                if ($realCampusExists) {
                    $query->where('real_campus_id', $evaluationCampusId);

                    return;
                }

                $query->where('id', $evaluationCampusId);
            })
                ->where('is_active', true)
                ->latest()
                ->first();

            $bypassEvaluation = (bool) $gradeViewingRule?->bypass_evaluation;
            $showGwaGpa = $gradeViewingRule?->show_gwa_gpa ?? true;
        } catch (Throwable $e) {
            Log::warning('Grade viewing rules lookup failed in RegistrarController; using strict grade viewing defaults', [
                'campus_id' => $evaluationCampusId,
                'message' => $e->getMessage(),
            ]);
        }

        $evaluationError = null;
        $evaluationErrorType = null; // 'connectivity' | 'no_data' | 'missing_context'
        $evaluationLookup = [];
        $lockGradesDueToEvaluationVerificationFailure = false;
        $evaluationId = null;

        $activeTerm = $activeTermService->execute($validated['campus_id']);
        $activeTermId = $activeTerm?->term_id;

        if (! $bypassEvaluation && $studentNo && $evaluationCampusId && $tenantId) {
            try {
                Log::info('Starting registrar grade evaluation verification', [
                    'student_no' => $studentNo,
                    'campus_id' => $evaluationCampusId,
                    'tenant_id' => $tenantId,
                    'active_term_id' => $activeTermId,
                ]);

                $evaluationId = $evaluationApi->findStudentByStudentNo($studentNo, $evaluationCampusId, $tenantId);

                if ($evaluationId) {
                    $details = $evaluationApi->getStudentEvaluationDetails($evaluationId);
                    $evaluationLookup = $evaluationApi->buildEvaluationLookup($details);

                    Log::info('Finished registrar grade evaluation verification lookup', [
                        'student_no' => $studentNo,
                        'evaluation_id' => $evaluationId,
                        'matched_subject_count' => count($evaluationLookup),
                    ]);
                } else {
                    $lockGradesDueToEvaluationVerificationFailure = true;
                    $evaluationErrorType = 'connectivity';
                    $evaluationError = 'The faculty evaluation service could not be reached. Grades are temporarily locked until the service is available again.';
                }

                if ($evaluationId && empty($evaluationLookup)) {
                    $lockGradesDueToEvaluationVerificationFailure = true;
                    $evaluationErrorType = 'no_data';
                    $evaluationError = 'The evaluation service returned no data for this account. Grades are temporarily locked.';
                }
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $isConnectivity = str_contains($message, 'cURL') ||
                    str_contains($message, 'Connection') ||
                    str_contains($message, 'timed out') ||
                    str_contains($message, 'SSL') ||
                    str_contains($message, 'Could not resolve') ||
                    str_contains($message, 'Failed to connect');

                Log::error('Evaluation API error in RegistrarController', [
                    'student_no' => $studentNo,
                    'message' => $message,
                    'type' => $isConnectivity ? 'connectivity' : 'unknown',
                ]);
                $lockGradesDueToEvaluationVerificationFailure = true;
                $evaluationErrorType = $isConnectivity ? 'connectivity' : 'no_data';
                $evaluationError = $isConnectivity
                    ? 'The faculty evaluation service is currently unreachable (network or SSL issue). Grades are temporarily locked.'
                    : 'An unexpected error occurred while verifying the evaluation status. Grades are temporarily locked.';
            }
        } elseif (! $bypassEvaluation) {
            Log::warning('Skipping registrar grade evaluation verification because required context is missing', [
                'student_no' => $studentNo,
                'campus_id' => $evaluationCampusId,
                'tenant_id' => $tenantId,
            ]);

            $lockGradesDueToEvaluationVerificationFailure = true;
            $evaluationErrorType = 'missing_context';
            $evaluationError = 'This account is missing required information (campus or tenant).';
        }

        // Enrich grade data with evaluation status
        if (! empty($report['data']) && is_array($report['data'])) {
            $report['data'] = array_map(function ($term) use ($evaluationLookup, $evaluationId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure) {
                if (! isset($term['grades']) || ! is_array($term['grades'])) {
                    return $term;
                }

                $termId = $term['termId'] ?? $term['term_id'] ?? null;

                $term['grades'] = array_map(function ($grade) use ($evaluationLookup, $evaluationId, $termId, $activeTermId, $lockGradesDueToEvaluationVerificationFailure) {
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

                        if ($evaluationId) {
                            $grade['evaluation_payload'] = array_merge($eval['evaluation_payload'], [
                                'studentId' => $evaluationId,
                            ]);
                        }
                    }

                    if ($grade['requires_evaluation']) {
                        $grade = $this->maskEvaluationLockedGradeFields($grade);
                    }

                    return $grade;
                }, $term['grades']);

                return $term;
            }, $report['data']);
        }

        $profile = null;
        try {
            $profileResponse = $academicApi->profileForStudent($validated['student_no'], $tenantId);
            if (empty($profileResponse['error'])) {
                $profile = $profileResponse['data'];
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to load student profile in RegistrarController', [
                'student_no' => $validated['student_no'],
                'message' => $e->getMessage(),
            ]);
        }

        $computedReport = $gradeComputation->computeForTerms($this->sortTermsMostRecent($report['data']));

        return to_route('admin.registrar.report-of-grades.index')
            ->withInput($validated)
            ->with('registrar_grade_report', [
                'student_no' => $validated['student_no'],
                'campus' => [
                    'id' => $campus->getKey(),
                    'name' => $campus->displayName(),
                    'tenant_id' => $tenantId,
                ],
                'terms' => $computedReport['terms'],
                'summary' => $computedReport['overall'],
                'bypass_evaluation' => $bypassEvaluation,
                'show_gwa_gpa' => $showGwaGpa,
                'evaluation_error' => $evaluationError,
                'evaluation_error_type' => $evaluationErrorType,
                'profile' => $profile,
            ]);
    }

    public function tagGraduatingStudent(): Response
    {
        return Inertia::render('Registrar/Placeholder', [
            'title' => 'Tag Graduating Student',
            'description' => 'Graduating student tagging tools will be available here.',
        ]);
    }

    /**
     * @return array<int, array{id: int|string, name: string, tenant_id: int|null}>
     */
    private function campuses(): array
    {
        try {
            return SsoCampus::query()
                ->get()
                ->sortBy(fn (SsoCampus $campus): string => $campus->displayName())
                ->map(fn (SsoCampus $campus): array => [
                    'id' => $campus->getKey(),
                    'name' => $campus->displayName(),
                    'tenant_id' => $campus->tenantId(),
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to load SSO campuses for registrar module.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $terms
     * @return array<int, mixed>
     */
    private function sortTermsMostRecent(array $terms): array
    {
        return collect($terms)
            ->sortByDesc(function ($term): int {
                if (! is_array($term)) {
                    return 0;
                }

                $termId = $term['termId']
                    ?? $term['term_id']
                    ?? data_get($term, 'term.termId')
                    ?? data_get($term, 'term.term_id')
                    ?? 0;

                return is_numeric($termId) ? (int) $termId : 0;
            })
            ->values()
            ->all();
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
