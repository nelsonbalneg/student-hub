<?php

namespace App\Http\Controllers;

use App\Models\PftDataPrivacyConsent;
use App\Models\PftHealthQuestionnaire;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\StudentPftResult;
use App\Services\AcademicApiService;
use App\Services\PftAnalyticsService;
use App\Services\PftInterpretationService;
use App\Services\PhysicalFitnessPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use Throwable;

class StudentPftResultController extends Controller
{
    public function analytics(Request $request, PftAnalyticsService $analytics): JsonResponse
    {
        $request->user()->can('pft.view') || abort(403);

        $filters = $request->validate([
            'term_id' => ['nullable', 'string'],
            'component_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'test_type_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return response()->json($analytics->forStudent($request->user(), $filters));
    }

    public function storeConsent(Request $request): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        $validated = $request->validate([
            'term_id' => ['required', 'string'],
        ]);

        $termId = (string) $validated['term_id'];
        $termExists = SiteAcademicTerm::query()
            ->where('term_id', $termId)
            ->where('status', 'Active')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $request->user()->campus_id))
            ->exists();

        if (! $termExists) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected academic term is not active for your campus.',
            ]);
        }

        PftDataPrivacyConsent::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'term_id' => $termId,
            ],
            [
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        Log::info('PFT data privacy consent recorded.', [
            'user_id' => $request->user()->id,
            'term_id' => $termId,
        ]);

        return to_route('student-profile.index')
            ->with('success', 'Data privacy consent recorded.');
    }

    public function storeHealthQuestionnaire(Request $request): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        $validated = $request->validate([
            'term_id' => ['required', 'string'],
            'civil_status' => ['nullable', 'string', 'max:255'],
            'household_monthly_income' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'has_medical_condition' => ['required', 'boolean'],
            'medical_condition_details' => ['nullable', 'string', 'max:1000'],
            'has_medication' => ['required', 'boolean'],
            'medication_details' => ['nullable', 'string', 'max:1000'],
            'smoking_status' => ['nullable', 'string', 'max:255'],
            'alcohol_consumption' => ['nullable', 'string', 'max:255'],
            'specific_conditions' => ['nullable', 'array'],
            'specific_conditions.*' => ['string', 'max:255'],
            'other_condition' => ['nullable', 'string', 'max:255'],
            'declaration_agreed' => ['accepted'],
            'medical_clearance' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $termId = (string) $validated['term_id'];
        $termExists = SiteAcademicTerm::query()
            ->where('term_id', $termId)
            ->where('status', 'Active')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $request->user()->campus_id))
            ->exists();

        if (! $termExists) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected academic term is not active for your campus.',
            ]);
        }

        $medicalClearancePath = null;
        if ($request->hasFile('medical_clearance')) {
            $medicalClearancePath = $request->file('medical_clearance')->store('pft-clearances', 'public');
        }

        $dataToStore = Arr::except($validated, ['term_id', 'declaration_agreed', 'medical_clearance']);
        if ($medicalClearancePath) {
            $dataToStore['medical_clearance_path'] = $medicalClearancePath;
        }

        PftHealthQuestionnaire::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'term_id' => $termId,
            ],
            $dataToStore
        );

        Log::info('PFT health questionnaire recorded.', [
            'user_id' => $request->user()->id,
            'term_id' => $termId,
            'medical_clearance_uploaded' => (bool)$medicalClearancePath,
        ]);

        return to_route('student-profile.index')
            ->with('success', 'Health questionnaire recorded.');
    }

    public function uploadMedicalClearance(Request $request, PftHealthQuestionnaire $questionnaire): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        if ($questionnaire->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this health questionnaire.');
        }

        $request->validate([
            'medical_clearance' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('medical_clearance')) {
            $path = $request->file('medical_clearance')->store('pft-clearances', 'public');
            
            $questionnaire->update([
                'medical_clearance_path' => $path,
            ]);

            Log::info('PFT medical clearance uploaded via separate endpoint.', [
                'user_id' => $request->user()->id,
                'term_id' => $questionnaire->term_id,
                'questionnaire_id' => $questionnaire->id,
            ]);
        }

        return to_route('student-profile.index')->with('success', 'Medical clearance uploaded successfully.');
    }

    public function uploadParqMedicalClearance(Request $request, \App\Models\PftParq $parq): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        if ($parq->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to this PAR-Q record.');
        }

        $request->validate([
            'medical_clearance' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($request->hasFile('medical_clearance')) {
            $path = $request->file('medical_clearance')->store('pft-clearances', 'public');
            
            $parq->update([
                'medical_clearance_path' => $path,
            ]);

            Log::info('PFT PAR-Q medical clearance uploaded via separate endpoint.', [
                'user_id' => $request->user()->id,
                'term_id' => $parq->term_id,
                'parq_id' => $parq->id,
            ]);
        }

        return to_route('student-profile.index')->with('success', 'Medical clearance uploaded successfully.');
    }

    public function storeParq(Request $request): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);

        $validated = $request->validate([
            'term_id' => ['required', 'string'],
            'q1' => ['required', 'boolean'],
            'q2' => ['required', 'boolean'],
            'q3' => ['required', 'boolean'],
            'q4' => ['required', 'boolean'],
            'q5' => ['required', 'boolean'],
            'q6' => ['required', 'boolean'],
            'q7' => ['required', 'boolean'],
            'declaration_agreed' => ['accepted'],
            'medical_clearance' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $termId = (string) $validated['term_id'];
        $termExists = SiteAcademicTerm::query()
            ->where('term_id', $termId)
            ->where('status', 'Active')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $request->user()->campus_id))
            ->exists();

        if (! $termExists) {
            throw ValidationException::withMessages([
                'term_id' => 'The selected academic term is not active for your campus.',
            ]);
        }

        $medicalClearancePath = null;
        if ($request->hasFile('medical_clearance')) {
            $medicalClearancePath = $request->file('medical_clearance')->store('pft-clearances', 'public');
        }

        $dataToStore = Arr::except($validated, ['term_id', 'declaration_agreed', 'medical_clearance']);
        $dataToStore['declaration_agreed'] = true;
        
        if ($medicalClearancePath) {
            $dataToStore['medical_clearance_path'] = $medicalClearancePath;
        }

        \App\Models\PftParq::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'term_id' => $termId,
            ],
            $dataToStore
        );

        Log::info('PFT PAR-Q recorded.', [
            'user_id' => $request->user()->id,
            'term_id' => $termId,
            'medical_clearance_uploaded' => (bool)$medicalClearancePath,
        ]);

        return to_route('student-profile.index')
            ->with('success', 'PAR-Q recorded successfully.');
    }

    public function store(
        Request $request,
        PftTestType $testType,
        PhysicalFitnessPermissionService $permission,
        AcademicApiService $academicApi,
        PftInterpretationService $interpretationService,
    ): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);
        abort_unless(
            $permission->canFillUp($request->user()),
            403,
            'You are currently not allowed to submit Physical Fitness Test records.',
        );

        $submittedTermId = (string) $request->input('term_id', '');
        $hasConsent = PftDataPrivacyConsent::query()
            ->where('user_id', $request->user()->id)
            ->where('term_id', $submittedTermId)
            ->exists();

        if (! $hasConsent) {
            throw ValidationException::withMessages([
                'term_id' => 'You must accept the data privacy consent before submitting results for this term.',
            ]);
        }

        $hasQuestionnaire = PftHealthQuestionnaire::query()
            ->where('user_id', $request->user()->id)
            ->where('term_id', $submittedTermId)
            ->exists();

        if (! $hasQuestionnaire) {
            throw ValidationException::withMessages([
                'term_id' => 'You must complete the health questionnaire before submitting results for this term.',
            ]);
        }

        $hasParq = \App\Models\PftParq::query()
            ->where('user_id', $request->user()->id)
            ->where('term_id', $submittedTermId)
            ->exists();

        if (! $hasParq) {
            throw ValidationException::withMessages([
                'term_id' => 'You must complete the PAR-Q before submitting results for this term.',
            ]);
        }

        $testType->load([
            'category.component',
            'configurations' => fn ($query) => $query->active()->orderBy('sort_order'),
            'interpretationRules' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
        ]);

        abort_unless($testType->is_active && $testType->category?->is_active !== false, 404);

        $isDraft = $request->boolean('is_draft');
        $logContext = [
            'user_id' => $request->user()->id,
            'test_type_id' => $testType->id,
            'test_type_slug' => $testType->slug,
            'term_id' => $request->input('term_id'),
            'status' => $isDraft ? 'draft' : 'completed',
            'result_keys' => array_keys((array) $request->input('results', [])),
        ];

        Log::info('PFT result save request received.', $logContext);

        $rules = [
            'term_id' => ['required', 'string'],
            'is_draft' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'tested_at' => ['nullable', 'date'],
            'results' => ['required', 'array'],
        ];

        foreach ($testType->configurations as $configuration) {
            $fieldRules = $configuration->is_required && ! $isDraft ? ['required'] : ['nullable'];
            $fieldRules[] = match ($configuration->field_type) {
                'number' => 'integer',
                'decimal' => 'numeric',
                'date' => 'date',
                'checkbox' => 'boolean',
                default => 'string',
            };
            $rules["results.{$configuration->field_name}"] = $fieldRules;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            Log::warning('PFT result save validation failed.', [
                ...$logContext,
                'errors' => $validator->errors()->toArray(),
            ]);

            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $termId = (string) $validated['term_id'];
        $activeTermExists = SiteAcademicTerm::query()
            ->where('term_id', $termId)
            ->where('status', 'Active')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $request->user()->campus_id))
            ->exists();

        if (! $activeTermExists) {
            Log::warning('PFT result save blocked by inactive or missing term.', $logContext);

            throw ValidationException::withMessages([
                'term_id' => 'The selected academic term is not active for your campus.',
            ]);
        }

        $allowedFields = $testType->configurations->pluck('field_name')->all();
        $results = collect($validated['results'])
            ->only($allowedFields)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        if ($testType->slug === 'bmi-test') {
            $height = isset($results['height']) ? floatval($results['height']) : null;
            $weight = isset($results['weight']) ? floatval($results['weight']) : null;
            if ($height > 0 && $weight > 0) {
                $heightInMeters = $height / 100;
                $bmiValue = round($weight / ($heightInMeters * $heightInMeters), 2);
                $results['bmi'] = $bmiValue;

            }
        }

        $interpretation = $interpretationService->interpret($testType, $results);
        if ($interpretation) {
            $validated['remarks'] = $interpretation['label'];
            $results['interpretation'] = $interpretation['label'];
            $results['interpretation_color'] = $interpretation['color'];
        }

        Log::info('PFT result save payload normalized.', [
            ...$logContext,
            'normalized_result_keys' => array_keys($results),
            'tested_at' => $validated['tested_at'] ?? ($results['date_tested'] ?? null),
            'has_remarks' => filled($validated['remarks'] ?? ($results['remarks'] ?? null)),
        ]);

        $academicContext = $this->academicContext($request, $academicApi, $termId);

        try {
            $result = DB::transaction(function () use ($request, $testType, $validated, $results, $termId, $isDraft, $academicContext, $interpretation): StudentPftResult {
                return StudentPftResult::query()->updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'pft_test_type_id' => $testType->id,
                        'term_id' => $termId,
                    ],
                    [
                        'status' => $isDraft ? 'draft' : 'completed',
                        ...$academicContext,
                        'results_json' => $results,
                        'remarks' => $validated['remarks'] ?? ($results['remarks'] ?? null),
                        'tested_at' => $validated['tested_at'] ?? ($results['date_tested'] ?? null),
                        'classification' => $interpretation ? ($interpretation['classification'] ?? $interpretation['label']) : null,
                        'interpretation' => $interpretation ? ($interpretation['interpretation'] ?? null) : null,
                        'suggested_intervention' => $interpretation ? ($interpretation['suggested_intervention'] ?? null) : null,
                        'color_class' => $interpretation ? ($interpretation['color_class'] ?? $interpretation['color'] ?? null) : null,
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ],
                );
            });
        } catch (Throwable $exception) {
            Log::error('PFT result database save failed.', [
                ...$logContext,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('PFT result saved to database.', [
            ...$logContext,
            'student_pft_result_id' => $result->id,
            'was_recently_created' => $result->wasRecentlyCreated,
            'saved_status' => $result->status,
        ]);

        return to_route('student-profile.index')
            ->with('success', $isDraft ? 'Physical fitness draft saved.' : 'Physical fitness result saved.');
    }

    /**
     * @return array{college_id: string|null, campus_id: string|null, year_level_id: string|null, section_id: string|null, section_name: string|null, tenant_id: string|null}
     */
    private function academicContext(Request $request, AcademicApiService $academicApi, string $termId): array
    {
        $user = $request->user();
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;
        $studentNo = $academicApi->studentNumberFor($user);
        $registrationResult = $academicApi->registrationFromAllForStudentTerm($studentNo, $termId, $tenantId);
        $registration = is_array($registrationResult['data'] ?? null)
            ? $registrationResult['data']
            : [];

        if ($registrationResult['error'] ?? null) {
            Log::warning('PFT result academic context registration lookup failed.', [
                'user_id' => $user->id,
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'error' => $registrationResult['error'],
            ]);
        }

        $sex = null;
        try {
            if ($studentNo) {
                $profileResult = $academicApi->profileForStudent($studentNo, $tenantId);
                $sex = $profileResult['data']['gender'] ?? null;
            }
        } catch (Throwable $e) {
            Log::warning('PFT result sex lookup failed: ' . $e->getMessage());
        }

        $context = [
            'college_id' => $this->stringValue($registration, [
                'collegeId',
                'college_id',
                'collegeID',
                'CollegeID',
            ]),
            'campus_id' => $this->stringValue($registration, [
                'campusId',
                'campus_id',
                'campusID',
                'CampusID',
            ]) ?? (blank($user->campus_id) ? null : (string) $user->campus_id),
            'year_level_id' => $this->stringValue($registration, [
                'yearLevelId',
                'year_level_id',
                'yearLevel.yearLevelId',
                'yearLevel.year_level_id',
            ]),
            'section_id' => $this->stringValue($registration, [
                'classSectionId',
                'class_section_id',
                'sectionId',
                'section_id',
                'sectionID',
                'SectionID',
            ]),
            'section_name' => $this->stringValue($registration, [
                'classSection.sectionName',
                'classSection.section_name',
                'classSection.section',
                'sectionName',
                'section_name',
                'section',
                'sectionCode',
                'section_code',
                'classSection',
                'class_section',
            ]),
            'tenant_id' => $tenantId,
            'sex' => $sex,
        ];

        Log::info('PFT result academic context resolved.', [
            'user_id' => $user->id,
            'term_id' => $termId,
            ...$context,
        ]);

        return $context;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function stringValue(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($source, $key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
