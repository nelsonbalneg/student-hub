<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeViewingRule;
use App\Models\SiteCampus;
use App\Models\SsoCampus;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use App\Services\AcademicApiService;
use App\Services\CeeStudentRequirementService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\RegistrarApiService;
use App\Services\StudentEvaluationApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
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
        if ($request->boolean('reset')) {
            $request->session()->forget([
                'registrar_grade_report_filters',
                'registrar_grade_report',
                'registrar_grade_report_error',
            ]);
        }

        $filters = $request->session()->get('registrar_grade_report_filters', [
            'student_no' => '',
            'campus_id' => '',
        ]);

        return Inertia::render('Registrar/ReportOfGrades', [
            'campuses' => $this->campuses(),
            'filters' => [
                'student_no' => $request->old('student_no', $filters['student_no'] ?? ''),
                'campus_id' => $request->old('campus_id', $filters['campus_id'] ?? ''),
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
        CeeStudentRequirementService $ceeRequirements,
    ): RedirectResponse {
        $validated = $request->validate([
            'student_no' => ['required', 'string', 'max:50'],
            'campus_id' => [
                'required',
                Rule::exists('sso_sqlsrv.campuses', 'id'),
            ],
        ]);

        $request->session()->put('registrar_grade_report_filters', $validated);

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
        } catch (Throwable $e) {
            Log::warning('Unable to load student profile in RegistrarController', [
                'student_no' => $validated['student_no'],
                'message' => $e->getMessage(),
            ]);
        }

        $curriculum = $academicApi->curriculumForStudent($validated['student_no'], $activeTermId, (string) $tenantId);
        $ceeDocuments = $ceeRequirements->forStudent($validated['student_no'], $validated['campus_id']);

        $computedReport = $gradeComputation->computeForTerms($this->sortTermsMostRecent($report['data']));
        $siteCampus = SiteCampus::query()
            ->where(function ($query) use ($validated) {
                $query
                    ->where('real_campus_id', (string) $validated['campus_id'])
                    ->orWhere('id', $validated['campus_id']);
            })
            ->first();
        $clearanceApplications = $this->clearanceApplications($validated['student_no'], $request);

        $request->session()->put('registrar_grade_report', [
            'student_no' => $validated['student_no'],
            'campus' => [
                'id' => $campus->getKey(),
                'name' => $campus->displayName(),
                'tenant_id' => $tenantId,
                'address' => $siteCampus?->campus_address,
                'logo_url' => $siteCampus?->campus_logo_path ? '/storage/'.$siteCampus->campus_logo_path : null,
            ],
            'terms' => $computedReport['terms'],
            'summary' => $computedReport['overall'],
            'bypass_evaluation' => $bypassEvaluation,
            'show_gwa_gpa' => $showGwaGpa,
            'evaluation_error' => $evaluationError,
            'evaluation_error_type' => $evaluationErrorType,
            'profile' => $profile,
            'curriculum' => $curriculum,
            'ceeDocuments' => $ceeDocuments,
            'clearanceApplications' => $clearanceApplications,
        ]);
        $request->session()->forget('registrar_grade_report_error');

        return to_route('admin.registrar.report-of-grades.index')
            ->withInput($validated);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clearanceApplications(string $studentNo, Request $request): array
    {
        $student = User::query()
            ->where('student_no', $studentNo)
            ->first();

        if (! $student) {
            return [];
        }

        return StudentSemesterClearance::query()
            ->whereBelongsTo($student, 'student')
            ->with([
                'semester:id,academic_year,term',
                'clearanceUpdate:id,clearance_type_id,reference_code,title,start_date,end_date',
                'clearanceUpdate.type:id,name',
                'clearanceUpdate.accountabilities' => fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->whereNull('parent_id')
                    ->select(['id', 'clearance_update_id', 'student_id', 'status', 'amount']),
            ])
            ->latest('applied_at')
            ->get()
            ->map(function (StudentSemesterClearance $clearance) use ($request): array {
                $accountabilities = $clearance->clearanceUpdate->accountabilities;
                $pendingAccountabilities = $accountabilities->where('status', 'pending');

                return [
                    'id' => $clearance->id,
                    'reference_no' => $clearance->reference_no,
                    'status' => $clearance->status,
                    'applied_at' => $clearance->applied_at?->toIso8601String(),
                    'cleared_at' => $clearance->cleared_at?->toIso8601String(),
                    'completed_at' => $clearance->completed_at?->toIso8601String(),
                    'remarks' => $clearance->remarks,
                    'clearance' => [
                        'reference_code' => $clearance->clearanceUpdate->reference_code,
                        'title' => $clearance->clearanceUpdate->title,
                        'type' => $clearance->clearanceUpdate->type->name,
                        'start_date' => $clearance->clearanceUpdate->start_date?->toDateString(),
                        'end_date' => $clearance->clearanceUpdate->end_date?->toDateString(),
                    ],
                    'semester' => [
                        'academic_year' => $clearance->semester->academic_year,
                        'term' => $clearance->semester->term,
                    ],
                    'accountabilities' => [
                        'total' => $accountabilities->count(),
                        'pending' => $pendingAccountabilities->count(),
                        'outstanding_amount' => (float) $pendingAccountabilities->sum('amount'),
                    ],
                    'can_view' => $request->user()->can('view', $clearance),
                ];
            })
            ->values()
            ->all();
    }

    public function printCurriculum(
        Request $request,
        AcademicApiService $academicApi,
        GetActiveAcademicTermService $activeTermService,
    ): \Symfony\Component\HttpFoundation\Response {
        $validated = $request->validate([
            'student_no' => ['required', 'string', 'max:50'],
            'campus_id' => [
                'required',
                Rule::exists('sso_sqlsrv.campuses', 'id'),
            ],
        ]);

        $campus = SsoCampus::query()->findOrFail($validated['campus_id']);
        $tenantId = $campus->tenantId();

        abort_if(! $tenantId, 422, 'The selected campus has no tenant ID configured.');

        $activeTerm = $activeTermService->execute($validated['campus_id']);
        $activeTermId = $activeTerm?->term_id;
        $sessionResult = session('registrar_grade_report');
        $useSessionResult = is_array($sessionResult)
            && ($sessionResult['student_no'] ?? null) === $validated['student_no']
            && (string) data_get($sessionResult, 'campus.id') === (string) $validated['campus_id'];

        $profile = $useSessionResult ? ($sessionResult['profile'] ?? null) : null;

        if (! $profile) {
            try {
                $profileResponse = $academicApi->profileForStudent($validated['student_no'], $tenantId);
                $profile = empty($profileResponse['error']) ? $profileResponse['data'] : null;
            } catch (Throwable $e) {
                Log::warning('Unable to load student profile while printing registrar curriculum', [
                    'student_no' => $validated['student_no'],
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $curriculum = $useSessionResult
            ? ($sessionResult['curriculum'] ?? null)
            : $academicApi->curriculumForStudent($validated['student_no'], $activeTermId, (string) $tenantId);

        if (! is_array($curriculum)) {
            $curriculum = [
                'data' => [],
                'error' => 'No curriculum data is currently available.',
            ];
        }

        $siteCampus = SiteCampus::query()
            ->where(function ($query) use ($validated) {
                $query
                    ->where('real_campus_id', (string) $validated['campus_id'])
                    ->orWhere('id', $validated['campus_id']);
            })
            ->first();

        $processedCurriculum = $this->buildCurriculumPrintData($curriculum['data'] ?? []);
        $html = view('pdf.registrar-curriculum', [
            'studentNo' => $validated['student_no'],
            'studentName' => $this->profileFullName($profile) ?: $validated['student_no'],
            'profile' => $profile,
            'campus' => [
                'name' => $campus->displayName(),
                'address' => $siteCampus?->campus_address,
                'logo_url' => $siteCampus?->campus_logo_path ? public_path('storage/'.$siteCampus->campus_logo_path) : null,
            ],
            'activeTerm' => $activeTerm,
            'curriculum' => $curriculum,
            'years' => $processedCurriculum['years'],
            'totals' => $processedCurriculum['totals'],
            'generatedAt' => now(),
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->noSandbox()
            ->pdf();

        $filename = Str::slug($validated['student_no'].' curriculum').'.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
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

    /**
     * @return array{years: array<int, array{year: string, semesters: array<int, array{semester: string, rows: array<int, array<string, mixed>>, units: float|int}>}>, totals: array{subjects: int, lecture_units: float|int, lab_units: float|int, units: float|int, years: int, semesters: int}}
     */
    private function buildCurriculumPrintData(mixed $data): array
    {
        $groups = [];

        if (is_array($data) && isset($data['yearAndLevel']) && is_array($data['yearAndLevel'])) {
            foreach ($data['yearAndLevel'] as $group) {
                if (! is_array($group)) {
                    continue;
                }

                $description = (string) ($group['yearTermDesc'] ?? 'Other');
                [$year, $semester] = array_pad(explode(' - ', $description, 2), 2, 'Other');
                $rows = collect($group['subjects'] ?? [])
                    ->filter(fn ($row): bool => is_array($row))
                    ->values()
                    ->all();

                $groups[$year ?: 'Other'][$semester ?: 'Other'] = $rows;
            }
        } else {
            foreach ($this->curriculumRowsFromData($data) as $row) {
                $year = $this->curriculumPick($row, [
                    'yearLevel',
                    'year_level',
                    'year',
                    'yearLevelName',
                    'year_level_name',
                    'yearLevelId',
                    'year_level_id',
                ]);

                $semester = $this->curriculumPick($row, [
                    'semester',
                    'semesterName',
                    'semester_name',
                    'term',
                    'semester_id',
                    'semesterId',
                ]);

                $groups[$this->curriculumYearLabel($year)][$this->curriculumSemesterLabel($semester)][] = $row;
            }
        }

        ksort($groups, SORT_NATURAL);

        $years = [];
        foreach ($groups as $year => $semesters) {
            ksort($semesters, SORT_NATURAL);

            $years[] = [
                'year' => $year,
                'semesters' => collect($semesters)
                    ->map(fn (array $rows, string $semester): array => [
                        'semester' => $semester,
                        'rows' => collect($rows)
                            ->map(fn (array $row): array => $this->curriculumPrintRow($row))
                            ->values()
                            ->all(),
                        'units' => collect($rows)->sum(fn (array $row): float|int => $this->curriculumSubjectUnits($row)),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $rows = collect($years)
            ->flatMap(fn (array $year): array => $year['semesters'])
            ->flatMap(fn (array $semester): array => $semester['rows']);

        return [
            'years' => $years,
            'totals' => [
                'subjects' => $rows->count(),
                'lecture_units' => $rows->sum('lecture_units'),
                'lab_units' => $rows->sum('lab_units'),
                'units' => $rows->sum('units'),
                'years' => count($years),
                'semesters' => collect($years)->sum(fn (array $year): int => count($year['semesters'])),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function curriculumRowsFromData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return collect($data)
                ->filter(fn ($row): bool => is_array($row))
                ->values()
                ->all();
        }

        foreach (['curriculum', 'data', 'subjects', 'records', 'items', 'details', 'curriculumDetails', 'list'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->curriculumRowsFromData($data[$key]);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function curriculumPick(array $row, array $keys, string $fallback = '-'): string
    {
        foreach ($keys as $key) {
            $value = data_get($row, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : $fallback;
            }
        }

        return $fallback;
    }

    private function curriculumYearLabel(string $year): string
    {
        return match ($year) {
            '-', '0' => 'Other',
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
            default => $year,
        };
    }

    private function curriculumSemesterLabel(string $semester): string
    {
        return match ($semester) {
            '-', '0' => 'Other',
            '1' => '1st Semester',
            '2' => '2nd Semester',
            '3' => 'Summer',
            default => $semester,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function curriculumPrintRow(array $row): array
    {
        $lectureUnits = $this->curriculumNumericPick($row, [
            'acadUnits',
            'academicUnits',
            'academic_units',
            'lecUnits',
            'lectureUnits',
            'lecture_units',
            'lec_units',
        ]) ?? 0;
        $labUnits = $this->curriculumNumericPick($row, [
            'labUnits',
            'laboratoryUnits',
            'laboratory_units',
            'lab_units',
        ]) ?? 0;

        return [
            'code' => $this->curriculumPick($row, ['subjectCode', 'courseCode', 'course_code', 'subject_code', 'code', 'subjectId', 'subject_id']),
            'description' => $this->curriculumPick($row, ['subjectDesc', 'courseTitle', 'course_title', 'courseDescription', 'course_description', 'subjectDescription', 'subject_description', 'description', 'title', 'subjectName', 'subject_name']),
            'lecture_units' => $lectureUnits,
            'lab_units' => $labUnits,
            'units' => $this->curriculumSubjectUnits($row),
            'prerequisites' => $this->curriculumPrerequisites($row),
            'taken_status' => $this->curriculumTakenStatus($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function curriculumSubjectUnits(array $row): float|int
    {
        $lectureUnits = $this->curriculumNumericPick($row, ['acadUnits', 'academicUnits', 'academic_units', 'lecUnits', 'lectureUnits', 'lecture_units', 'lec_units']) ?? 0;
        $labUnits = $this->curriculumNumericPick($row, ['labUnits', 'laboratoryUnits', 'laboratory_units', 'lab_units']) ?? 0;

        if ($lectureUnits > 0 || $labUnits > 0) {
            return $lectureUnits + $labUnits;
        }

        return $this->curriculumNumericPick($row, ['totalUnits', 'total_units', 'totalCreditUnits', 'total_credit_units', 'unit', 'units', 'creditUnits', 'credit_units', 'credits', 'units_load', 'unitsLoad']) ?? 0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function curriculumNumericPick(array $row, array $keys): float|int|null
    {
        foreach ($keys as $key) {
            $value = data_get($row, $key);

            if ($value !== null && $value !== '' && is_numeric($value)) {
                $number = (float) $value;

                return floor($number) === $number ? (int) $number : $number;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function curriculumPrerequisites(array $row): array
    {
        $items = data_get($row, 'preRequisites') ?? data_get($row, 'pre_requisites');

        if (is_array($items) && $items !== []) {
            return collect($items)
                ->map(fn ($item): ?string => is_array($item) ? $this->curriculumPick($item, ['subjectCode', 'courseCode', 'subjectId', 'subject_id', 'prerequisiteSubjectId', 'prerequisite_subject_id']) : null)
                ->filter()
                ->values()
                ->all();
        }

        $value = $this->curriculumPick($row, ['preReqs', 'prerequisites', 'pre_requisites', 'prereq', 'pre_req', 'preRequisite', 'pre_requisite'], '');

        if ($value === '') {
            return [];
        }

        return collect(preg_split('/[,\\n;\\/]+/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function curriculumTakenStatus(array $row): string
    {
        $finalGrade = $this->curriculumPick($row, ['finalGrade', 'final_grade', 'grade', 'rating']);
        $finalRemarks = $this->curriculumPick($row, ['finalRemarks', 'final_remarks', 'remarks', 'remark'], '');

        if ($finalGrade !== '-' && Str::lower(trim($finalRemarks)) === 'failed') {
            return 'Failed';
        }

        foreach (['subjectTaken', 'subject_taken', 'taken', 'isTaken'] as $key) {
            $value = data_get($row, $key);

            if ($value !== null && $value !== '') {
                return filter_var($value, FILTER_VALIDATE_BOOL) || (is_numeric($value) && (int) $value === 1)
                    ? 'Taken'
                    : '';
            }
        }

        return '';
    }

    private function profileFullName(mixed $profile): string
    {
        if (! is_array($profile)) {
            return '';
        }

        return collect([
            $profile['firstName'] ?? null,
            $profile['middleInitial'] ?? null,
            $profile['lastName'] ?? null,
            $profile['extName'] ?? null,
        ])
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => trim((string) $value))
            ->implode(' ');
    }
}
