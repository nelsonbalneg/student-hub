<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\PftCategory;
use App\Models\PftDataPrivacyConsent;
use App\Models\PftHealthQuestionnaire;
use App\Models\PftParq;
use App\Models\User;
use App\Models\PftComponent;
use App\Models\PftConfiguration;
use App\Models\PftInterpretationRule;
use App\Models\PftMedicalCondition;
use App\Models\PftProcedure;
use App\Models\PftTestSchedule;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\SiteSetting;
use App\Models\StudentPftResult;
use App\Services\PhysicalFitnessPermissionService;
use App\Services\SiteSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PhysicalFitnessConfigurationController extends Controller
{
    private const FIELD_TYPES = ['text', 'number', 'decimal', 'select', 'radio', 'checkbox', 'date', 'time', 'textarea'];

    public function __construct(
        private readonly PhysicalFitnessPermissionService $physicalFitnessPermission,
        private readonly SiteSettingService $siteSettings,
    ) {}

    public function index(Request $request): Response
    {
        $requestedTab = $request->string('tab')->value();
        $activeTab = in_array($requestedTab, ['configuration', 'settings'], true)
            ? $requestedTab
            : 'configuration';

        return Inertia::render('SiteSettings/PhysicalFitness/Configuration', [
            'activeTab' => $activeTab,
            'scheduleRows' => fn (): array => $this->scheduleRows(),
            'scheduleToday' => fn (): string => PftTestSchedule::localToday(),
            'components' => $this->configurationTree(includeInactive: true),
            'fieldTypes' => self::FIELD_TYPES,
            'medicalConditions' => PftMedicalCondition::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'physicalFitnessSetting' => [
                'enabled' => $this->physicalFitnessPermission->gradesShortcutEnabled(),
                'permission' => SiteSetting::query()
                    ->where('key', PhysicalFitnessPermissionService::SETTING_KEY)
                    ->value('value') ?? PhysicalFitnessPermissionService::PERMISSION_PE_PATHFIT_ONLY,
                'options' => [
                    [
                        'label' => 'PE/PATHFIT Students Only',
                        'value' => PhysicalFitnessPermissionService::PERMISSION_PE_PATHFIT_ONLY,
                    ],
                    [
                        'label' => 'All Students',
                        'value' => PhysicalFitnessPermissionService::PERMISSION_ALL_STUDENTS,
                    ],
                ],
            ],
            'branding' => $this->siteSettings->branding(),
            'can' => [
                'create' => request()->user()->can('pft.configuration.create'),
                'update' => request()->user()->can('pft.configuration.update'),
                'delete' => request()->user()->can('pft.configuration.delete'),
                'managePhysicalFitnessPermission' => request()->user()->can('pft.permission.manage'),
            ],
            'medicalConditions' => PftMedicalCondition::query()
                ->orderBy('sort_order')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function storeTestSchedules(\App\Http\Requests\StorePftTestScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $term = SiteAcademicTerm::query()->with('campus')->findOrFail($data['site_academic_term_id']);

        // updateOrCreate keyed on (term, phase) plus the unique index guarantees one schedule
        // per campus term and test type. Only schedule rows are touched; results are never modified.
        $existed = DB::transaction(function () use ($data): bool {
            $existed = PftTestSchedule::query()
                ->where('site_academic_term_id', $data['site_academic_term_id'])
                ->lockForUpdate()
                ->exists();

            foreach (PftTestSchedule::PHASES as $phase) {
                PftTestSchedule::query()->updateOrCreate(
                    ['site_academic_term_id' => $data['site_academic_term_id'], 'phase' => $phase],
                    ['opens_on' => $data[$phase.'_opens_on'], 'closes_on' => $data[$phase.'_closes_on']],
                );
            }

            return $existed;
        });

        $label = trim(($term->campus?->campus_name ? $term->campus->campus_name.' Â· ' : '').$term->school_year.', '.$term->semester);

        return back()->with('success', $existed
            ? "Pre-test and Post-test schedules updated for {$label}."
            : "Pre-test and Post-test schedules saved for {$label}.");
    }

    public function destroyTestSchedules(Request $request, SiteAcademicTerm $term): RedirectResponse
    {
        $request->user()->can('pft.permission.manage') || abort(403);

        $term->loadMissing('campus');

        if (blank($term->term_id) || blank($term->campus?->real_campus_id)) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule has no linked campus or academic term. Link them before deleting related data.',
            ]);
        }

        $paths = DB::transaction(function () use ($term): array {
            $campusId = (string) $term->campus->real_campus_id;
            $userIds = User::query()->where('campus_id', $campusId)->select('id');
            $paths = [];

            foreach ([PftHealthQuestionnaire::class, PftParq::class, PftDataPrivacyConsent::class] as $model) {
                $query = $model::query()->where('term_id', (string) $term->term_id)->whereIn('user_id', $userIds);

                if ($model !== PftDataPrivacyConsent::class) {
                    $paths = [...$paths, ...$query->pluck('medical_clearance_path')->filter()->all()];
                }

                $query->delete();
            }

            StudentPftResult::query()
                ->where('term_id', (string) $term->term_id)
                ->where(function ($query) use ($campusId, $userIds): void {
                    $query->where('campus_id', $campusId)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('campus_id')->whereIn('user_id', $userIds));
                })
                ->delete();
            PftTestSchedule::query()->where('site_academic_term_id', $term->id)->delete();

            return array_unique($paths);
        });

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }

        $label = trim(($term->campus?->campus_name ? $term->campus->campus_name.' · ' : '').$term->school_year.', '.$term->semester);

        return back()->with('success', "Schedules and related Physical Fitness data deleted for {$label}.");
    }

    /**
     * One row per campus academic term with Pre-test/Post-test windows and campus-local statuses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRows(): array
    {
        $today = PftTestSchedule::localToday();
        $schedules = PftTestSchedule::query()
            ->get()
            ->keyBy(fn (PftTestSchedule $schedule): string => $schedule->site_academic_term_id.':'.$schedule->phase);

        return SiteAcademicTerm::query()
            ->with('campus:id,campus_name,real_campus_id')
            ->orderByDesc('school_year')
            ->orderBy('semester')
            ->get()
            ->map(function (SiteAcademicTerm $term) use ($schedules, $today): array {
                $phases = collect(PftTestSchedule::PHASES)->mapWithKeys(function (string $phase) use ($schedules, $term, $today): array {
                    $schedule = $schedules->get($term->id.':'.$phase);

                    return [$phase => [
                        'opens_on' => $schedule?->opens_on?->toDateString(),
                        'closes_on' => $schedule?->closes_on?->toDateString(),
                        'status' => PftTestSchedule::statusFor($schedule, $today),
                    ]];
                });

                $updatedAt = collect(PftTestSchedule::PHASES)
                    ->map(fn (string $phase) => $schedules->get($term->id.':'.$phase)?->updated_at)
                    ->filter()
                    ->max();

                $isScheduled = $phases->contains(fn (array $phase): bool => $phase['status'] !== PftTestSchedule::STATUS_NOT_SCHEDULED);

                return [
                    'site_academic_term_id' => (int) $term->id,
                    'site_campus_id' => (int) $term->site_campus_id,
                    'campus_name' => $term->campus?->campus_name,
                    'school_year' => $term->school_year,
                    'semester' => $term->semester,
                    'term_status' => $term->status,
                    'pre' => $phases['pre'],
                    'post' => $phases['post'],
                    'is_scheduled' => $isScheduled,
                    'updated_at' => $updatedAt?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    public function updateSiteSettings(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);

        $validated = $request->validate([
            'fitness_intelligence_name' => ['required', 'string', 'max:120'],
            'fitness_intelligence_tagline' => ['nullable', 'string', 'max:180'],
            'fitness_intelligence_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:3072'],
            'remove_fitness_intelligence_logo' => ['nullable', 'boolean'],
        ]);

        $settings = $this->siteSettings->all();
        $userId = $request->user()->id;

        foreach (['fitness_intelligence_name', 'fitness_intelligence_tagline'] as $key) {
            $this->siteSettings->set($key, $validated[$key] ?? null, 'string', $userId);
        }

        $this->syncFitnessLogo(
            $request,
            (bool) ($validated['remove_fitness_intelligence_logo'] ?? false),
            $settings['fitness_intelligence_logo'] ?? null,
            $userId,
        );

        return redirect()
            ->route('site-settings.physical-fitness.configuration.index', ['tab' => 'site-settings'])
            ->with('success', 'Fitness Intelligence site settings updated.');
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);
        $validated = $this->validateComponent($request);

        DB::transaction(fn () => PftComponent::query()->create([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT component created.');
    }

    public function updateComponent(Request $request, PftComponent $component): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);
        $validated = $this->validateComponent($request, $component);

        DB::transaction(fn () => $component->update([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT component updated.');
    }

    public function destroyComponent(Request $request, PftComponent $component): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(function () use ($component): void {
            if ($component->categories()->whereHas('testTypes.results')->exists()) {
                $component->update(['is_active' => false]);

                return;
            }

            $component->delete();
        });

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT component removed or deactivated.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);
        $validated = $this->validateCategory($request);

        DB::transaction(fn () => PftCategory::query()->create([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT category created.');
    }

    public function updateCategory(Request $request, PftCategory $category): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);
        $validated = $this->validateCategory($request, $category);

        DB::transaction(fn () => $category->update([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT category updated.');
    }

    public function destroyCategory(Request $request, PftCategory $category): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(function () use ($category): void {
            if ($category->testTypes()->whereHas('results')->exists()) {
                $category->update(['is_active' => false]);

                return;
            }

            $category->delete();
        });

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT category removed or deactivated.');
    }

    public function storeTestType(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);
        $validated = $this->validateTestType($request);

        DB::transaction(fn () => PftTestType::query()->create([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT test type created.');
    }

    public function updateTestType(Request $request, PftTestType $testType): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);
        $validated = $this->validateTestType($request, $testType);

        DB::transaction(fn () => $testType->update([
            ...$validated,
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
        ]));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT test type updated.');
    }

    public function destroyTestType(Request $request, PftTestType $testType): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(function () use ($testType): void {
            if ($testType->results()->exists()) {
                $testType->update(['is_active' => false]);

                return;
            }

            $testType->delete();
        });

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT test type removed or deactivated.');
    }

    public function storeConfiguration(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);

        DB::transaction(fn () => PftConfiguration::query()->create($this->validateConfiguration($request)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT field created.');
    }

    public function updateConfiguration(Request $request, PftConfiguration $configuration): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);

        DB::transaction(fn () => $configuration->update($this->validateConfiguration($request, $configuration)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT field updated.');
    }

    public function destroyConfiguration(Request $request, PftConfiguration $configuration): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(function () use ($configuration): void {
            if ($configuration->testType()->whereHas('results')->exists()) {
                $configuration->update(['is_active' => false]);

                return;
            }

            $configuration->delete();
        });

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT field removed or deactivated.');
    }

    public function storeInterpretationRule(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);

        DB::transaction(fn () => PftInterpretationRule::query()->create($this->validateInterpretationRule($request)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT interpretation rule created.');
    }

    public function updateInterpretationRule(Request $request, PftInterpretationRule $rule): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);

        DB::transaction(fn () => $rule->update($this->validateInterpretationRule($request)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT interpretation rule updated.');
    }

    public function destroyInterpretationRule(Request $request, PftInterpretationRule $rule): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(fn () => $rule->delete());

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT interpretation rule deleted.');
    }

    public function storeProcedure(Request $request): RedirectResponse
    {
        $request->user()->can('pft.configuration.create') || abort(403);

        DB::transaction(fn () => PftProcedure::query()->create($this->validateProcedure($request)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT procedure step created.');
    }

    public function updateProcedure(Request $request, PftProcedure $procedure): RedirectResponse
    {
        $request->user()->can('pft.configuration.update') || abort(403);

        DB::transaction(fn () => $procedure->update($this->validateProcedure($request)));

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT procedure step updated.');
    }

    public function destroyProcedure(Request $request, PftProcedure $procedure): RedirectResponse
    {
        $request->user()->can('pft.configuration.delete') || abort(403);

        DB::transaction(fn () => $procedure->delete());

        return to_route('site-settings.physical-fitness.configuration.index')->with('success', 'PFT procedure step deleted.');
    }

    private function syncFitnessLogo(Request $request, bool $remove, ?string $currentPath, int $userId): void
    {
        if ($request->hasFile('fitness_intelligence_logo')) {
            $file = $request->file('fitness_intelligence_logo');

            if ($file instanceof UploadedFile && $file->isValid()) {
                $tempPath = $file->getPathname();

                if (! $tempPath || ! file_exists($tempPath)) {
                    throw ValidationException::withMessages([
                        'fitness_intelligence_logo' => 'The uploaded logo could not be processed. Please try again or choose a different file.',
                    ]);
                }

                $directory = 'branding/fitness-intelligence';
                File::ensureDirectoryExists(Storage::disk('public')->path($directory));

                $filename = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'png');
                $file->move(Storage::disk('public')->path($directory), $filename);
                $path = $directory.'/'.$filename;

                $this->siteSettings->set('fitness_intelligence_logo', $path, 'file', $userId);

                if ($currentPath) {
                    Storage::disk('public')->delete($currentPath);
                }
            }

            return;
        }

        if ($remove && $currentPath) {
            Storage::disk('public')->delete($currentPath);
            $this->siteSettings->set('fitness_intelligence_logo', null, 'file', $userId);
        }
    }

    private function validateProcedure(Request $request): array
    {
        return $request->validate([
            'pft_test_type_id' => ['required', 'exists:pft_test_types,id'],
            'step_no' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateComponent(Request $request, ?PftComponent $component = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pft_components', 'slug')->ignore($component)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateCategory(Request $request, ?PftCategory $category = null): array
    {
        return $request->validate([
            'pft_component_id' => ['required', 'exists:pft_components,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateTestType(Request $request, ?PftTestType $testType = null): array
    {
        return $request->validate([
            'pft_category_id' => ['required', 'exists:pft_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateConfiguration(Request $request, ?PftConfiguration $configuration = null): array
    {
        $validated = $request->validate([
            'pft_test_type_id' => ['required', 'exists:pft_test_types,id'],
            'field_name' => ['required', 'string', 'max:255'],
            'field_label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(self::FIELD_TYPES)],
            'options' => ['nullable'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (is_string($validated['options'] ?? null)) {
            $options = collect(preg_split('/\r\n|\r|\n|,/', $validated['options']))
                ->map(fn (string $option): string => trim($option))
                ->filter()
                ->values()
                ->all();
            $validated['options'] = $options === [] ? null : $options;
        }

        return $validated;
    }

    private function validateInterpretationRule(Request $request): array
    {
        $request->merge([
            'min_value' => blank($request->input('min_value')) ? null : $request->input('min_value'),
            'max_value' => blank($request->input('max_value')) ? null : $request->input('max_value'),
        ]);

        return $request->validate([
            'pft_test_type_id' => ['required', 'exists:pft_test_types,id'],
            'field_name' => ['required', 'string', 'max:255'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'label' => ['required', 'string', 'max:255'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'color' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    private function configurationTree(bool $includeInactive = false): array
    {
        return PftComponent::query()
            ->with([
                'categories' => fn ($query) => $query->when(! $includeInactive, fn ($query) => $query->active())->orderBy('sort_order')->orderBy('name'),
                'categories.testTypes' => fn ($query) => $query->when(! $includeInactive, fn ($query) => $query->active())->withCount('results')->orderBy('sort_order')->orderBy('name'),
                'categories.testTypes.configurations' => fn ($query) => $query->when(! $includeInactive, fn ($query) => $query->active())->orderBy('sort_order')->orderBy('field_label'),
                'categories.testTypes.interpretationRules' => fn ($query) => $query->when(! $includeInactive, fn ($query) => $query->active())->orderBy('sort_order')->orderBy('id'),
                'categories.testTypes.procedures' => fn ($query) => $query->when(! $includeInactive, fn ($query) => $query->active())->orderBy('step_no')->orderBy('id'),
            ])
            ->when(! $includeInactive, fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
