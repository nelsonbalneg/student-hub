<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\PftComponent;
use App\Models\SiteAcademicTerm;
use App\Models\StudentPftResult;
use App\Models\Training;
use App\Services\AcademicApiService;
use App\Services\CeeStudentRequirementService;
use App\Services\EvaluationTemplatePayloadService;
use App\Services\PhysicalFitnessPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly CeeStudentRequirementService $ceeRequirements,
        private readonly PhysicalFitnessPermissionService $pftPermission,
        private readonly EvaluationTemplatePayloadService $evaluationTemplates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;
        $campusId = $user->campus_id;
        $canViewPft = $user->can('pft.view');
        $canSubmitPft = $canViewPft && $user->can('pft.submit');
        $canFillUpPft = $canSubmitPft && $this->pftPermission->canFillUp($user);
        $savedPftTermIds = StudentPftResult::query()
            ->where('user_id', $user->id)
            ->when(! $canViewPft, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $canFillUpPft, fn ($query) => $query->where('status', 'completed'))
            ->whereNotNull('term_id')
            ->pluck('term_id')
            ->filter()
            ->map(fn ($termId): string => (string) $termId)
            ->unique()
            ->values();
        $pftTerms = SiteAcademicTerm::query()
            ->select(['id', 'site_campus_id', 'school_year', 'semester', 'term_id', 'status', 'start_date', 'end_date'])
            ->whereNotNull('term_id')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $campusId))
            ->when(
                $canFillUpPft,
                fn ($query) => $query->where(function ($query) use ($savedPftTermIds): void {
                    $query->where('status', 'Active')
                        ->when(
                            $savedPftTermIds->isNotEmpty(),
                            fn ($query) => $query->orWhereIn('term_id', $savedPftTermIds),
                        );
                }),
                fn ($query) => $savedPftTermIds->isNotEmpty()
                    ? $query->whereIn('term_id', $savedPftTermIds)
                    : $query->whereRaw('1 = 0'),
            )
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
        $pftTermIds = $pftTerms->pluck('term_id')->filter()->map(fn ($termId): string => (string) $termId)->all();
        $ccdCaresPeriods = CcdCaresEvaluationPeriod::query()
            ->whereIn('status', [
                CcdCaresEvaluationPeriod::STATUS_ACTIVE,
                CcdCaresEvaluationPeriod::STATUS_CLOSED,
            ])
            ->with([
                'template',
                'submissions' => fn ($query) => $query->where('student_id', $user->id),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('StudentProfile/Index', [
            'profile' => $this->academicApi->profileForStudent($studentNo, $tenantId),
            'ceeDocuments' => $this->ceeRequirements->forStudent($studentNo, $campusId),
            'lookups' => $this->academicApi->profileLookups(),
            'achievements' => Achievement::where('user_id', $user->id)->latest('date_received')->get(),
            'trainings' => Training::where('user_id', $user->id)->latest('date_from')->get(),
            'physicalFitness' => [
                'components' => PftComponent::query()
                    ->active()
                    ->when(! $canViewPft, fn ($query) => $query->whereRaw('1 = 0'))
                    ->with([
                        'categories' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                        'categories.testTypes' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                        'categories.testTypes.configurations' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('field_label'),
                        'categories.testTypes.interpretationRules' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
                'results' => StudentPftResult::query()
                    ->where('user_id', $user->id)
                    ->when(! $canViewPft, fn ($query) => $query->whereRaw('1 = 0'))
                    ->when(! $canFillUpPft, fn ($query) => $query->where('status', 'completed'))
                    ->when(
                        $pftTermIds !== [],
                        fn ($query) => $query->whereIn('term_id', $pftTermIds),
                        fn ($query) => $query->whereRaw('1 = 0'),
                    )
                    ->get()
                    ->keyBy(fn (StudentPftResult $result): string => "{$result->term_id}:{$result->pft_test_type_id}"),
                'terms' => $pftTerms,
                'canView' => $canViewPft,
                'canSubmit' => $canSubmitPft,
                'canFillUp' => $canFillUpPft,
            ],
            'ccdCares' => [
                'assessments' => $ccdCaresPeriods->map(function (CcdCaresEvaluationPeriod $period): array {
                    $submission = $period->submissions->first();

                    return [
                        'period' => [
                            'id' => $period->id,
                            'title' => $period->title,
                            'description' => $period->description,
                            'start_date' => $period->start_date->toDateString(),
                            'end_date' => $period->end_date->toDateString(),
                            'status' => $period->status,
                            'is_open' => $period->status === CcdCaresEvaluationPeriod::STATUS_ACTIVE
                                && $period->start_date->lte(today())
                                && $period->end_date->gte(today()),
                        ],
                        'template' => $this->evaluationTemplates->build($period->template),
                        'submission' => $submission ? [
                            'submitted_at' => $submission->submitted_at,
                            'answers' => $submission->answers_json,
                            'interpretation_results' => $submission->getInterpretationResults(),
                        ] : null,
                    ];
                })->values(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;
        $campusId = (int) ($user->campus_id ?? 0);

        if (blank($studentNo) || blank($tenantId) || $campusId <= 0) {
            return back()->with('error', 'Missing student context for profile update.');
        }

        $payload = $request->validate([
            'gender' => ['required', 'string'],
            'height' => ['nullable'],
            'weight' => ['nullable'],
            'bloodType' => ['nullable', 'string', 'max:20'],
            'placeOfBirth' => ['nullable', 'string', 'max:255'],
            'mobileNo' => ['nullable', 'string', 'max:30'],
            'telNo' => ['nullable', 'string', 'max:30'],
            'permProvince' => ['nullable', 'string', 'max:255'],
            'permTownCity' => ['nullable', 'string', 'max:255'],
            'permBarangay' => ['nullable', 'string', 'max:255'],
            'permAddress' => ['nullable', 'string', 'max:255'],
            'permStreet' => ['nullable', 'string', 'max:255'],
            'resProvince' => ['nullable', 'string', 'max:255'],
            'resTownCity' => ['nullable', 'string', 'max:255'],
            'resBarangay' => ['nullable', 'string', 'max:255'],
            'resAddress' => ['nullable', 'string', 'max:255'],
            'resStreet' => ['nullable', 'string', 'max:255'],
            'civilStatusId' => ['nullable'],
            'religionId' => ['nullable'],
            'nationalityId' => ['nullable'],
            'tribeId' => ['nullable'],
            'permZipCode' => ['nullable'],
            'resZipCode' => ['nullable'],
            'guardian' => ['nullable', 'string', 'max:255'],
            'guardianAddress' => ['nullable', 'string', 'max:255'],
            'guardianTelNo' => ['nullable', 'string', 'max:50'],
            'guardianEmail' => ['nullable', 'string', 'max:255'],
            'emergencyContact' => ['nullable', 'string', 'max:255'],
            'emergencyAddress' => ['nullable', 'string', 'max:255'],
            'emergencyMobileNo' => ['nullable', 'string', 'max:50'],
            'emergencyTelNo' => ['nullable', 'string', 'max:50'],
            'father' => ['nullable', 'string', 'max:255'],
            'fatherOccupation' => ['nullable', 'string', 'max:255'],
            'fatherCompany' => ['nullable', 'string', 'max:255'],
            'fatherCompanyAddress' => ['nullable', 'string', 'max:255'],
            'fatherTelNo' => ['nullable', 'string', 'max:50'],
            'fatherEmail' => ['nullable', 'string', 'max:255'],
            'fatherBirthDate' => ['nullable', 'string', 'max:50'],
            'fatherEducAttain' => ['nullable', 'string', 'max:255'],
            'mother' => ['nullable', 'string', 'max:255'],
            'motherOccupation' => ['nullable', 'string', 'max:255'],
            'motherCompany' => ['nullable', 'string', 'max:255'],
            'motherCompanyAddress' => ['nullable', 'string', 'max:255'],
            'motherTelNo' => ['nullable', 'string', 'max:50'],
            'motherEmail' => ['nullable', 'string', 'max:255'],
            'motherBirthDate' => ['nullable', 'string', 'max:50'],
            'motherEducAttain' => ['nullable', 'string', 'max:255'],
            'elemSchool' => ['nullable', 'string', 'max:255'],
            'elemAddress' => ['nullable', 'string', 'max:255'],
            'elemInclDates' => ['nullable', 'string', 'max:100'],
            'elemAwardHonor' => ['nullable', 'string', 'max:255'],
            'hsSchool' => ['nullable', 'string', 'max:255'],
            'hsAddress' => ['nullable', 'string', 'max:255'],
            'hsInclDates' => ['nullable', 'string', 'max:100'],
            'hsAwardHonor' => ['nullable', 'string', 'max:255'],
            'vocational' => ['nullable', 'string', 'max:255'],
            'vocationalAddress' => ['nullable', 'string', 'max:255'],
            'vocationalInclDates' => ['nullable', 'string', 'max:100'],
            'collegeSchool' => ['nullable', 'string', 'max:255'],
            'collegeDegree' => ['nullable', 'string', 'max:255'],
            'collegeAddress' => ['nullable', 'string', 'max:255'],
            'collegeInclDates' => ['nullable', 'string', 'max:100'],
            'shsTrack' => ['nullable', 'string', 'max:255'],
            'shsSchool' => ['nullable', 'string', 'max:255'],
            'shsIncldates' => ['nullable', 'string', 'max:100'],
            'shsAwardsHonors' => ['nullable', 'string', 'max:255'],
            'studentType' => ['nullable', 'string', 'max:255'],
            'studentCategory' => ['nullable', 'string', 'max:255'],
            'firstGenerationStudent' => ['nullable', 'string', 'max:20'],
            'isGida' => ['nullable', 'string', 'max:20'],
            'descGida' => ['nullable', 'string', 'max:255'],
            'isBelongToFarmer' => ['nullable', 'string', 'max:20'],
            'isRebelReturnee' => ['nullable', 'string', 'max:20'],
            'familySize' => ['nullable'],
            'ipMember' => ['nullable'],
            'ipMemberTribe' => ['nullable', 'string', 'max:255'],
            'pwdMember' => ['nullable'],
            'pwdMemberId' => ['nullable', 'string', 'max:255'],
            'pwdCategory' => ['nullable', 'string', 'max:255'],
            'soloParent' => ['nullable'],
            'soloParentId' => ['nullable', 'string', 'max:255'],
            'raisedBySoloParent' => ['nullable', 'string', 'max:20'],
            'lastSchoolAttendedType' => ['nullable'],
            'fatherNatureOfWork' => ['nullable'],
            'motherNatureOfWork' => ['nullable'],
            'fatherEducationalAttainment' => ['nullable', 'string', 'max:255'],
            'motherEducationalAttainment' => ['nullable', 'string', 'max:255'],
            'fatherEmploymentStatus' => ['nullable'],
            'motherEmploymentStatus' => ['nullable'],
            'isAdm' => ['nullable', 'string', 'max:20'],
            'admSchool' => ['nullable', 'string', 'max:255'],
            'admSchoolYear' => ['nullable', 'string', 'max:100'],
            'isAls' => ['nullable', 'string', 'max:20'],
            'alsSchool' => ['nullable', 'string', 'max:255'],
            'alsSchoolYear' => ['nullable', 'string', 'max:100'],
            'fatherIncomeFrom' => ['nullable'],
            'fatherIncomeTo' => ['nullable'],
            'motherIncomeFrom' => ['nullable'],
            'motherIncomeTo' => ['nullable'],
            'fatherCitizenship' => ['nullable', 'string', 'max:255'],
            'motherCitizenship' => ['nullable', 'string', 'max:255'],
            'noofBrothers' => ['nullable'],
            'noofSisters' => ['nullable'],
            'isIllegitimate' => ['nullable'],
        ]);

        foreach ([
            'height', 'weight', 'civilStatusId', 'religionId', 'nationalityId', 'tribeId',
            'permZipCode', 'resZipCode', 'familySize', 'lastSchoolAttendedType',
            'fatherNatureOfWork', 'motherNatureOfWork', 'fatherEmploymentStatus', 'motherEmploymentStatus',
            'fatherIncomeFrom', 'fatherIncomeTo', 'motherIncomeFrom', 'motherIncomeTo', 'noofBrothers', 'noofSisters',
        ] as $intField) {
            if (array_key_exists($intField, $payload) && $payload[$intField] !== '' && $payload[$intField] !== null) {
                $payload[$intField] = (int) $payload[$intField];
            }
        }

        foreach (['ipMember', 'pwdMember', 'soloParent', 'isIllegitimate'] as $boolField) {
            if (array_key_exists($boolField, $payload) && $payload[$boolField] !== null && $payload[$boolField] !== '') {
                $payload[$boolField] = filter_var($payload[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        $shsTrackMap = [
            'Academic - STEM' => 'Academic - STEM (Science, Technology, Engineering, and Mathematics)',
            'Academic - ABM' => 'Academic - ABM (Accountancy, Business, and Management)',
            'Academic - HUMSS' => 'Academic - HUMSS (Humanities and Social Sciences)',
            'Academic - GAS' => 'Academic - GAS (General Academic Strand)',
            'TVL - ICT' => 'TVL - ICT (Information and Communication Technology)',
            'TVL - HE' => 'TVL - HE (Home Economics)',
            'TVL - IA' => 'TVL - IA (Industrial Arts)',
        ];
        if (! empty($payload['shsTrack']) && isset($shsTrackMap[$payload['shsTrack']])) {
            $payload['shsTrack'] = $shsTrackMap[$payload['shsTrack']];
        }

        // Avoid sending null/blank strings that overwrite valid existing API values.
        // Keep explicit false/0 values.
        $payload = array_filter($payload, static function ($value) {
            if ($value === null) {
                return false;
            }

            return ! (is_string($value) && trim($value) === '');
        });

        $result = $this->academicApi->updateProfileForStudent($studentNo, $campusId, $tenantId, $payload);

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Unable to save profile.');
        }

        return back()->with('success', 'Your profile has been successfully updated.');
    }
}
