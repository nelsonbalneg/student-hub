<?php

namespace App\Http\Controllers;

use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\StudentPftResult;
use App\Services\AcademicApiService;
use App\Services\PftAnalyticsService;
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
        $request->user()->can('student-profile.view') || abort(403);

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

    public function store(
        Request $request,
        PftTestType $testType,
        PhysicalFitnessPermissionService $permission,
        AcademicApiService $academicApi,
    ): RedirectResponse
    {
        $request->user()->can('pft.submit') || abort(403);
        abort_unless(
            $permission->canFillUp($request->user()),
            403,
            'You are currently not allowed to submit Physical Fitness Test records.',
        );

        $testType->load([
            'category.component',
            'configurations' => fn ($query) => $query->active()->orderBy('sort_order'),
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

                if ($bmiValue < 18.5) {
                    $interpretation = 'Underweight';
                } elseif ($bmiValue < 25.0) {
                    $interpretation = 'Normal';
                } elseif ($bmiValue < 30.0) {
                    $interpretation = 'Overweight';
                } else {
                    $interpretation = 'Obese';
                }

                $validated['remarks'] = $interpretation;
                $results['remarks'] = $interpretation;
            }
        }

        Log::info('PFT result save payload normalized.', [
            ...$logContext,
            'normalized_result_keys' => array_keys($results),
            'tested_at' => $validated['tested_at'] ?? ($results['date_tested'] ?? null),
            'has_remarks' => filled($validated['remarks'] ?? ($results['remarks'] ?? null)),
        ]);

        $academicContext = $this->academicContext($request, $academicApi, $termId);

        try {
            $result = DB::transaction(function () use ($request, $testType, $validated, $results, $termId, $isDraft, $academicContext): StudentPftResult {
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
