<?php

namespace App\Http\Controllers\Admin\Reporting;

use App\Http\Controllers\Controller;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\StudentPftResult;
use App\Services\AcademicApiService;
use App\Services\PftInterpretationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PftResultController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100];

    /**
     * @var array<int, string>
     */
    private const ORDER_COLUMNS = [
        1 => 'student_name',
        2 => 'term_id',
        3 => 'campus_id',
        4 => 'college_id',
        5 => 'section_id',
        6 => 'section_name',
        7 => 'year_level_id',
        8 => 'test_count',
        9 => 'latest_tested_at',
        10 => 'latest_created_at',
    ];

    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly PftInterpretationService $interpretationService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $filters = $this->filters($request, false);

        return Inertia::render('Reporting/PftResult', [
            'filters' => $filters,
            'selectedOptions' => $this->selectedOptions($filters),
            'pageSizeOptions' => self::PAGE_SIZES,
            'canExport' => $request->user()->can('reporting.export'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        if (! $this->hasRequiredResultFilters($filters)) {
            return response()->json([
                'draw' => $request->integer('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $baseQuery = $this->filteredQuery($filters);
        $recordsTotal = StudentPftResult::query()
            ->where('campus_id', $filters['campus_id'])
            ->where('term_id', $filters['term_id'])
            ->select('user_id', 'term_id')
            ->groupBy('user_id', 'term_id')
            ->get()
            ->count();

        $query = clone $baseQuery;
        $this->applySearch($query, $request->input('search.value'));
        $recordsFiltered = $this->groupedCount(clone $query);
        $summary = $this->tableSummary(clone $query);

        $start = max($request->integer('start'), 0);
        $length = $this->dataTablesLength($request);

        $groupRows = $this->groupedQuery($query);
        $this->applyGroupedOrdering($groupRows, $request);

        $groups = $groupRows
            ->skip($start)
            ->take($length)
            ->get();

        $latestRows = StudentPftResult::query()
            ->with([
                'testType.category.component',
                'testType.interpretationRules' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
                'user:id,name,email,student_no',
            ])
            ->whereIn('id', $groups->pluck('latest_id')->filter()->values())
            ->get()
            ->keyBy('id');

        $details = $this->detailsForGroups($latestRows->values());

        $labels = $this->drawerLabels($filters);
        $termComparison = $this->termComparison($filters);

        $rows = $groups
            ->map(function ($group, int $index) use ($latestRows, $details, $labels, $termComparison, $start): array {
                $latest = $latestRows->get($group->latest_id);

                return $this->groupedTableRow(
                    $latest,
                    $details->get($this->groupKey((int) $group->user_id, (string) $group->term_id), collect()),
                    $start + $index + 1,
                    (int) $group->test_count,
                    $labels,
                    $termComparison,
                );
            })
            ->filter()
            ->values();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'summary' => $summary,
            'data' => $rows,
        ]);
    }

    public function analyticsPage(Request $request): InertiaResponse
    {
        $filters = $this->analyticsFilters($request);

        return Inertia::render('Reporting/PftAnalytics', [
            'filters' => $filters,
            'selectedOptions' => $this->selectedOptions($filters),
            'pageSizeOptions' => self::PAGE_SIZES,
            'canExport' => $request->user()->can('reporting.export'),
        ]);
    }

    public function analyticsData(Request $request): JsonResponse
    {
        $filters = $this->analyticsFilters($request);
        $query = $this->filteredAnalyticsQuery($filters);
        $labels = $this->drawerLabels($filters);

        // Calculate executive stats
        $totalStudentsTested = (clone $query)->distinct('user_id')->count('user_id');
        $totalComponents = \App\Models\PftComponent::active()->count();
        $totalTestTypes = \App\Models\PftTestType::active()->count();
        $totalCampuses = (clone $query)->whereNotNull('campus_id')->distinct('campus_id')->count('campus_id');
        $totalColleges = (clone $query)->whereNotNull('college_id')->distinct('college_id')->count('college_id');
        $totalSections = (clone $query)->whereNotNull('section_id')->distinct('section_id')->count('section_id');

        $studentsIntervention = (clone $query)
            ->whereIn('color_class', ['red', 'rose', 'orange', 'amber', 'needs improvement', 'poor', 'obese', 'underweight'])
            ->distinct('user_id')
            ->count('user_id');

        $studentsTarget = (clone $query)
            ->whereIn('color_class', ['emerald', 'green', 'lime', 'blue', 'normal', 'good', 'excellent', 'very good'])
            ->distinct('user_id')
            ->count('user_id');

        // Campuses List
        $campuses = (clone $query)
            ->selectRaw('campus_id, COUNT(DISTINCT user_id) as total_students, COUNT(*) as total_results')
            ->groupBy('campus_id')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->campus_id,
                'name' => $row->campus_id,
                'total_students' => (int) $row->total_students,
                'total_results' => (int) $row->total_results,
            ]);

        // Rules & Stats
        $rules = \App\Models\PftInterpretationRule::active()->with('testType.category.component')->get();
        $classificationStats = (clone $query)
            ->selectRaw('pft_test_type_id, classification, COUNT(DISTINCT user_id) as total')
            ->groupBy('pft_test_type_id', 'classification')
            ->get()
            ->groupBy(fn ($row) => $row->pft_test_type_id . '::' . $row->classification);

        $classifications = $rules->map(function ($rule) use ($classificationStats, $query) {
            $key = $rule->pft_test_type_id . '::' . ($rule->classification ?: $rule->label);
            $count = isset($classificationStats[$key]) ? (int) $classificationStats[$key]->first()->total : 0;
            
            $testTypeTotal = (clone $query)
                ->where('pft_test_type_id', $rule->pft_test_type_id)
                ->distinct('user_id')
                ->count('user_id');
                
            $percentage = $testTypeTotal > 0 ? round(($count / $testTypeTotal) * 100, 1) : 0;

            return [
                'id' => $rule->id,
                'test_type_id' => $rule->pft_test_type_id,
                'test_type' => $rule->testType?->name,
                'component' => $rule->testType?->category?->component?->name,
                'classification' => $rule->classification ?: $rule->label,
                'interpretation' => $rule->interpretation,
                'suggested_intervention' => $rule->suggested_intervention,
                'color_class' => $rule->color_class ?: $rule->color,
                'student_count' => $count,
                'percentage' => $percentage,
            ];
        });

        // Interventions (Priority List)
        $interventions = $classifications
            ->filter(fn ($item) => filled($item['suggested_intervention']) && $item['student_count'] > 0)
            ->map(function ($item) {
                $priority = 'Normal';
                $priorityWeight = 0;
                $color = strtolower($item['color_class']);
                if (in_array($color, ['red', 'rose', 'poor', 'obese', 'underweight'])) {
                    $priority = 'High';
                    $priorityWeight = 3;
                } elseif (in_array($color, ['orange', 'needs improvement'])) {
                    $priority = 'Medium';
                    $priorityWeight = 2;
                } elseif (in_array($color, ['amber', 'yellow', 'fair', 'average'])) {
                    $priority = 'Low';
                    $priorityWeight = 1;
                }

                return [
                    'classification' => $item['classification'],
                    'test_type' => $item['test_type'],
                    'component' => $item['component'],
                    'suggested_intervention' => $item['suggested_intervention'],
                    'student_count' => $item['student_count'],
                    'percentage' => $item['percentage'],
                    'priority' => $priority,
                    'priority_weight' => $priorityWeight,
                    'color_class' => $item['color_class'],
                ];
            })
            ->sortByDesc('priority_weight')
            ->values();

        // Components List
        $components = \App\Models\PftComponent::active()
            ->with(['categories.testTypes'])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($comp) use ($query) {
                $compResults = (clone $query)
                    ->whereHas('testType.category', fn ($q) => $q->where('pft_component_id', $comp->id))
                    ->get();
                    
                $classifications = $compResults->groupBy('classification')
                    ->map(fn ($group) => $group->count());

                $totalStudents = (clone $query)
                    ->whereHas('testType.category', fn ($q) => $q->where('pft_component_id', $comp->id))
                    ->distinct('user_id')
                    ->count('user_id');

                $categories = $comp->categories->map(function ($cat) use ($query) {
                    $catResults = (clone $query)
                        ->whereHas('testType', fn ($q) => $q->where('pft_category_id', $cat->id))
                        ->get();
                        
                    $catClassifications = $catResults->groupBy('classification')
                        ->map(fn ($group) => $group->count());

                    $catStudents = (clone $query)
                        ->whereHas('testType', fn ($q) => $q->where('pft_category_id', $cat->id))
                        ->distinct('user_id')
                        ->count('user_id');

                    $testTypes = $cat->testTypes->map(function ($type) use ($query) {
                        $typeResults = (clone $query)
                            ->where('pft_test_type_id', $type->id)
                            ->get();
                            
                        $typeClassifications = $typeResults->groupBy('classification')
                            ->map(fn ($group) => $group->count());

                        return [
                            'id' => $type->id,
                            'name' => $type->name,
                            'total_results' => $typeResults->count(),
                            'classifications' => $typeClassifications,
                        ];
                    });

                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'total_results' => $catResults->count(),
                        'total_students' => $catStudents,
                        'classifications' => $catClassifications,
                        'test_types' => $testTypes,
                    ];
                });

                return [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'total_results' => $compResults->count(),
                    'total_students' => $totalStudents,
                    'classifications' => $classifications,
                    'categories' => $categories,
                ];
            });

        // Test Types List
        $testTypes = \App\Models\PftTestType::active()
            ->with('category.component')
            ->get()
            ->map(function ($type) use ($query) {
                $typeResults = (clone $query)
                    ->where('pft_test_type_id', $type->id)
                    ->get();
                    
                $classifications = $typeResults->groupBy('classification')
                    ->map(fn ($group) => $group->count());

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'component_id' => $type->category?->pft_component_id,
                    'total_results' => $typeResults->count(),
                    'classifications' => $classifications,
                ];
            });

        // College Comparison (Grouped Bar Chart data)
        $collegeComparison = (clone $query)
            ->selectRaw('college_id, AVG(CASE
                WHEN color_class IN (\'emerald\', \'green\', \'excellent\') THEN 100
                WHEN color_class IN (\'lime\', \'very good\', \'good\') THEN 80
                WHEN color_class IN (\'blue\', \'normal\', \'fair\') THEN 60
                WHEN color_class IN (\'amber\', \'average\') THEN 50
                WHEN color_class IN (\'orange\', \'needs improvement\') THEN 40
                WHEN color_class IN (\'red\', \'rose\', \'poor\', \'obese\', \'underweight\') THEN 20
                ELSE 50
            END) as score')
            ->whereNotNull('college_id')
            ->groupBy('college_id')
            ->get()
            ->map(fn ($row) => [
                'college' => $row->college_id,
                'score' => round((float) $row->score, 1),
            ]);

        // Section Comparison (Grouped Bar Chart data)
        $sectionComparison = (clone $query)
            ->selectRaw('section_name, section_id, AVG(CASE
                WHEN color_class IN (\'emerald\', \'green\', \'excellent\') THEN 100
                WHEN color_class IN (\'lime\', \'very good\', \'good\') THEN 80
                WHEN color_class IN (\'blue\', \'normal\', \'fair\') THEN 60
                WHEN color_class IN (\'amber\', \'average\') THEN 50
                WHEN color_class IN (\'orange\', \'needs improvement\') THEN 40
                WHEN color_class IN (\'red\', \'rose\', \'poor\', \'obese\', \'underweight\') THEN 20
                ELSE 50
            END) as score')
            ->whereNotNull('section_id')
            ->groupBy('section_name', 'section_id')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'section' => $row->section_name ?: $row->section_id,
                'score' => round((float) $row->score, 1),
            ]);

        // Term Trends (Line Chart data)
        $termTrends = (clone $query)
            ->selectRaw('term_id, AVG(CASE
                WHEN color_class IN (\'emerald\', \'green\', \'excellent\') THEN 100
                WHEN color_class IN (\'lime\', \'very good\', \'good\') THEN 80
                WHEN color_class IN (\'blue\', \'normal\', \'fair\') THEN 60
                WHEN color_class IN (\'amber\', \'average\') THEN 50
                WHEN color_class IN (\'orange\', \'needs improvement\') THEN 40
                WHEN color_class IN (\'red\', \'rose\', \'poor\', \'obese\', \'underweight\') THEN 20
                ELSE 50
            END) as score')
            ->groupBy('term_id')
            ->orderBy('term_id')
            ->get()
            ->map(fn ($row) => [
                'term' => $row->term_id,
                'score' => round((float) $row->score, 1),
            ]);

        // Component Radar Chart data
        $componentRadar = (clone $query)
            ->join('pft_test_types', 'pft_test_types.id', '=', 'student_pft_results.pft_test_type_id')
            ->join('pft_categories', 'pft_categories.id', '=', 'pft_test_types.pft_category_id')
            ->join('pft_components', 'pft_components.id', '=', 'pft_categories.pft_component_id')
            ->selectRaw('pft_components.name as component')
            ->selectRaw('AVG(CASE
                WHEN color_class IN (\'emerald\', \'green\', \'excellent\') THEN 100
                WHEN color_class IN (\'lime\', \'very good\', \'good\') THEN 80
                WHEN color_class IN (\'blue\', \'normal\', \'fair\') THEN 60
                WHEN color_class IN (\'amber\', \'average\') THEN 50
                WHEN color_class IN (\'orange\', \'needs improvement\') THEN 40
                WHEN color_class IN (\'red\', \'rose\', \'poor\', \'obese\', \'underweight\') THEN 20
                ELSE 50
            END) as score')
            ->groupBy('pft_components.name')
            ->get()
            ->map(fn ($row) => [
                'component' => $row->component,
                'score' => round((float) $row->score, 1),
            ]);

        // Classification Distributions (Overall Bar Chart data)
        $classificationsDist = (clone $query)
            ->selectRaw('classification, color_class, COUNT(DISTINCT user_id) as total')
            ->whereNotNull('classification')
            ->groupBy('classification', 'color_class')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'classification' => $row->classification,
                'color_class' => $row->color_class,
                'total' => (int) $row->total,
            ]);

        // Component Performance Distribution (Stacked Bar Chart data)
        $componentPerformance = (clone $query)
            ->join('pft_test_types', 'pft_test_types.id', '=', 'student_pft_results.pft_test_type_id')
            ->join('pft_categories', 'pft_categories.id', '=', 'pft_test_types.pft_category_id')
            ->join('pft_components', 'pft_components.id', '=', 'pft_categories.pft_component_id')
            ->selectRaw('pft_components.name as component, classification, COUNT(DISTINCT user_id) as total')
            ->whereNotNull('classification')
            ->groupBy('pft_components.name', 'classification')
            ->get()
            ->map(fn ($row) => [
                'component' => $row->component,
                'classification' => $row->classification,
                'total' => (int) $row->total,
            ]);

        $collegeComponentProfiles = blank($filters['college_id'] ?? null)
            ? $this->collegeComponentProfiles($query->get(), $labels['colleges'] ?? [])
            : [];

        return response()->json([
            'campuses' => $campuses,
            'components' => $components,
            'test_types' => $testTypes,
            'classifications' => $classifications,
            'interventions' => $interventions,
            'students' => [],
            'executive_stats' => [
                'total_students' => $totalStudentsTested,
                'total_components' => $totalComponents,
                'total_test_types' => $totalTestTypes,
                'total_campuses' => $totalCampuses,
                'total_colleges' => $totalColleges,
                'total_sections' => $totalSections,
                'requiring_intervention' => $studentsIntervention,
                'target_performance' => $studentsTarget,
            ],
            'college_comparison' => $collegeComparison,
            'section_comparison' => $sectionComparison,
            'term_trends' => $termTrends,
            'component_radar' => $componentRadar,
            'overall_distribution' => $classificationsDist,
            'component_distribution' => $componentPerformance,
            'college_component_profiles' => $collegeComponentProfiles,
        ]);
    }

    public function analyticsDrilldown(Request $request): JsonResponse
    {
        $filters = $this->analyticsFilters($request);
        $classification = $request->input('classification');
        $labels = $this->drilldownLabels();

        $query = $this->filteredAnalyticsQuery($filters)
            ->with(['user', 'testType.category.component'])
            ->when($classification, fn ($q, $v) => $q->where('student_pft_results.classification', $v));

        $search = $request->input('search.value') ?? $request->input('search');
        if (filled($search)) {
            $search = addcslashes((string) $search, '%_\\');
            $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('student_no', 'like', "%{$search}%");
                })
                ->orWhere('student_pft_results.section_name', 'like', "%{$search}%")
                ->orWhere('student_pft_results.remarks', 'like', "%{$search}%")
                ->orWhere('student_pft_results.classification', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = $query->count();

        if ($request->boolean('report')) {
            $start = max($request->integer('start', 0), 0);
            $length = $request->integer('length', 10);
            if ($length === -1) {
                $length = 500;
            }

            $columnIndex = $request->integer('order.0.column', 0);
            $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

            if ($columnIndex === 1) {
                $query->join('users', 'users.id', '=', 'student_pft_results.user_id')
                    ->select('student_pft_results.*')
                    ->orderBy('users.name', $direction);
            } else {
                $query->orderBy('student_pft_results.campus_id')
                    ->orderBy('student_pft_results.college_id')
                    ->orderBy('student_pft_results.user_id')
                    ->orderBy('student_pft_results.tested_at', $direction)
                    ->orderBy('student_pft_results.id', 'desc');
            }

            $rows = $query->get();
            $data = $rows->map(fn (StudentPftResult $row): array => $this->drilldownRowPayload($row, $labels));

            return response()->json([
                'draw' => $request->integer('draw'),
                'recordsTotal' => StudentPftResult::count(),
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        }

        $nodeLevel = (string) $request->input('node_level', 'campus');

        return match ($nodeLevel) {
            'college' => response()->json([
                'level' => 'college',
                'data' => $this->collegeDrilldownNodes(
                    $query,
                    $labels,
                    (string) $request->input('campus_id', ''),
                ),
            ]),
            'student' => response()->json([
                'level' => 'student',
                'data' => $this->studentDrilldownNodes(
                    $query,
                    $labels,
                    (string) $request->input('campus_id', ''),
                    (string) $request->input('college_id', ''),
                ),
            ]),
            'detail' => response()->json([
                'level' => 'detail',
                'data' => $this->studentDetailRows(
                    $query,
                    $labels,
                    (string) $request->input('campus_id', ''),
                    (string) $request->input('college_id', ''),
                    (string) $request->input('user_id', ''),
                ),
            ]),
            default => response()->json([
                'level' => 'campus',
                'recordsFiltered' => $recordsFiltered,
                'data' => $this->campusDrilldownNodes($query, $labels),
            ]),
        };
    }

    public function exportDrilldownExcel(Request $request): StreamedResponse
    {
        $filters = $this->analyticsFilters($request);
        $classification = $request->input('classification');
        $labels = $this->drilldownLabels();
        
        $rows = $this->filteredAnalyticsQuery($filters)
            ->with(['user', 'testType.category.component'])
            ->when($classification, fn ($q, $v) => $q->where('student_pft_results.classification', $v))
            ->orderBy('student_pft_results.campus_id')
            ->orderBy('student_pft_results.college_id')
            ->orderBy('student_pft_results.user_id')
            ->orderBy('student_pft_results.tested_at', 'desc')
            ->get();
            
        $filename = 'pft-drilldown-export-'.now()->format('Ymd-His').'.csv';
        
        return ResponseFactory::streamDownload(function () use ($rows, $labels): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student Number',
                'Student Name',
                'Campus',
                'College',
                'Section',
                'Year Level',
                'Component',
                'Test Type',
                'Raw Result',
                'Classification',
                'Remarks',
                'Test Date',
            ]);
            
            foreach ($rows as $row) {
                $payload = $this->drilldownRowPayload($row, $labels);

                fputcsv($handle, [
                    $payload['student_no'],
                    $payload['student_name'],
                    $payload['campus_name'],
                    $payload['college_name'],
                    $payload['section'],
                    $payload['year_level'],
                    $payload['component'],
                    $payload['test_type'],
                    $payload['raw_result'],
                    $payload['classification'],
                    $payload['remarks'],
                    $payload['test_date'],
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyticsFilters(Request $request): array
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'string'],
            'term_id' => ['nullable', 'string'],
            'college_id' => ['nullable', 'string'],
            'section_id' => ['nullable', 'string'],
            'component_id' => ['nullable', 'integer'],
            'test_type_id' => ['nullable', 'integer'],
            'year_level_id' => ['nullable', 'string'],
            'sex' => ['nullable', 'string'],
        ]);

        return array_filter($validated, fn ($value): bool => filled($value));
    }

    private function filteredAnalyticsQuery(array $filters): Builder
    {
        return StudentPftResult::query()
            ->when($filters['campus_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.campus_id', $v))
            ->when($filters['term_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.term_id', $v))
            ->when($filters['college_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.college_id', $v))
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.section_id', $v))
            ->when($filters['year_level_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.year_level_id', $v))
            ->when($filters['sex'] ?? null, fn ($q, $v) => $q->where('student_pft_results.sex', $v))
            ->when($filters['test_type_id'] ?? null, fn ($q, $v) => $q->where('student_pft_results.pft_test_type_id', $v))
            ->when($filters['component_id'] ?? null, fn ($q, $v) => $q->whereHas('testType.category', fn ($sq) => $sq->where('pft_component_id', $v)));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        abort_unless($this->hasRequiredResultFilters($filters), 422, 'Campus and Academic Term are required.');

        $rows = $this->filteredQuery($filters)
            ->orderByDesc('tested_at')
            ->orderByDesc('id')
            ->get();

        $filename = 'pft-results-'.$filters['term_id'].'-'.now()->format('Ymd-His').'.csv';

        return ResponseFactory::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                '#',
                'Tested Date',
                'Term',
                'Campus',
                'College',
                'Section ID',
                'Section Name',
                'Year Level',
                'PFT Test Type',
                'Results',
                'Remarks',
                'Status',
                'Created At',
            ]);

            foreach ($rows as $index => $row) {
                fputcsv($handle, $this->exportRow($row, $index + 1));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $filters = $this->filters($request);
        abort_unless($this->hasRequiredResultFilters($filters), 422, 'Campus and Academic Term are required.');

        $rows = $this->filteredQuery($filters)
            ->orderByDesc('tested_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (StudentPftResult $row, int $index): array => $this->tableRow($row, $index + 1));

        $html = view('pdf.reporting-pft-result', [
            'filters' => $filters,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->landscape()
            ->margins(8, 8, 8, 8)
            ->showBackground()
            ->noSandbox()
            ->pdf();

        $filename = 'pft-results-'.$filters['term_id'].'-'.now()->format('Ymd-His').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function filterCampuses(Request $request): JsonResponse
    {
        $validated = $this->select2Filters($request, [
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = SiteCampus::query()
            ->select(['id', 'real_campus_id', 'campus_name'])
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $search = addcslashes($search, '%_\\');
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('campus_name', 'like', "%{$search}%")
                        ->orWhere('real_campus_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('campus_name');

        return $this->select2FromQuery($query, $validated['page'] ?? 1, fn (SiteCampus $campus): array => [
            'id' => (string) ($campus->real_campus_id ?: $campus->id),
            'text' => trim(($campus->real_campus_id ? "{$campus->real_campus_id} - " : '').$campus->campus_name),
        ]);
    }

    public function filterTerms(Request $request): JsonResponse
    {
        $validated = $this->select2Filters($request, [
            'campus_id' => ['required', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $campus = $this->campusFor((string) $validated['campus_id']);
        abort_unless($campus, 404);

        $query = SiteAcademicTerm::query()
            ->where('site_campus_id', $campus->id)
            ->whereNotNull('term_id')
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $search = addcslashes($search, '%_\\');
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('term_id', 'like', "%{$search}%")
                        ->orWhere('school_year', 'like', "%{$search}%")
                        ->orWhere('semester', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        return $this->select2FromQuery($query, $validated['page'] ?? 1, fn (SiteAcademicTerm $term): array => [
            'id' => (string) $term->term_id,
            'text' => $this->termLabel((string) $term->term_id, $term),
        ]);
    }

    public function filterColleges(Request $request): JsonResponse
    {
        $validated = $this->select2Filters($request, [
            'campus_id' => ['required', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tenantId = $request->user()?->tenant_id;
        $colleges = collect($this->academicApi->getColleges($tenantId)['data'] ?? [])
            ->filter(fn (array $college): bool => (string) ($college['campusId'] ?? '') === (string) $validated['campus_id'])
            ->when($validated['q'] ?? null, function (Collection $collection, string $search): Collection {
                return $collection->filter(function (array $college) use ($search): bool {
                    $label = $this->collegeLabel($college);

                    return Str::contains(Str::lower($label), Str::lower($search));
                });
            })
            ->sortBy(fn (array $college): string => $this->collegeLabel($college))
            ->values();

        return $this->select2FromCollection($colleges, $validated['page'] ?? 1, fn (array $college): array => [
            'id' => (string) ($college['collegeId'] ?? ''),
            'text' => $this->collegeLabel($college),
        ]);
    }

    public function filterSections(Request $request): JsonResponse
    {
        $validated = $this->select2Filters($request, [
            'campus_id' => ['required', 'string'],
            'term_id' => ['required', 'string'],
            'college_id' => ['required', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = StudentPftResult::query()
            ->select('section_id', 'section_name')
            ->where('campus_id', $validated['campus_id'])
            ->where('term_id', $validated['term_id'])
            ->where('college_id', $validated['college_id'])
            ->whereNotNull('section_id')
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $search = addcslashes($search, '%_\\');
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('section_id', 'like', "%{$search}%")
                        ->orWhere('section_name', 'like', "%{$search}%");
                });
            })
            ->groupBy('section_id', 'section_name')
            ->orderBy('section_name')
            ->orderBy('section_id');

        return $this->select2FromQuery($query, $validated['page'] ?? 1, fn (StudentPftResult $row): array => [
            'id' => (string) $row->section_id,
            'text' => filled($row->section_name)
                ? "{$row->section_id} - {$row->section_name}"
                : (string) $row->section_id,
        ]);
    }

    public function filterPftTestTypes(Request $request): JsonResponse
    {
        $validated = $this->select2Filters($request, [
            'campus_id' => ['nullable', 'string'],
            'term_id' => ['nullable', 'string'],
            'college_id' => ['nullable', 'string'],
            'section_id' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = PftTestType::query()
            ->whereHas('results', fn (Builder $query) => $this->applyOptionFilters($query, $validated))
            ->when($validated['q'] ?? null, function (Builder $query, string $search): void {
                $search = addcslashes($search, '%_\\');
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name');

        return $this->select2FromQuery($query, $validated['page'] ?? 1, fn (PftTestType $type): array => [
            'id' => (string) $type->id,
            'text' => $type->name,
        ]);
    }

    /**
     * @return array{campus_id?: string|null, term_id?: string|null, college_id?: string|null, section_id?: string|null, test_type_id?: string|null}
     */
    private function filters(Request $request, bool $requireTerm = true): array
    {
        $validated = $request->validate([
            'campus_id' => [$requireTerm ? 'required' : 'nullable', 'string'],
            'term_id' => [$requireTerm ? 'required' : 'nullable', 'string'],
            'college_id' => ['nullable', 'string'],
            'section_id' => ['nullable', 'string'],
            'test_type_id' => ['nullable', 'integer'],
        ]);

        return array_filter($validated, fn ($value): bool => filled($value));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return StudentPftResult::query()
            ->with([
                'testType.category.component',
                'testType.interpretationRules' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
                'user:id,name,email,student_no',
            ])
            ->when($filters['term_id'] ?? null, fn (Builder $query, string $termId) => $query->where('student_pft_results.term_id', $termId))
            ->when($filters['campus_id'] ?? null, fn (Builder $query, string $campusId) => $query->where('student_pft_results.campus_id', $campusId))
            ->when($filters['college_id'] ?? null, fn (Builder $query, string $collegeId) => $query->where('student_pft_results.college_id', $collegeId))
            ->when($filters['section_id'] ?? null, fn (Builder $query, string $sectionId) => $query->where('student_pft_results.section_id', $sectionId))
            ->when($filters['test_type_id'] ?? null, fn (Builder $query, string|int $testTypeId) => $query->where('student_pft_results.pft_test_type_id', $testTypeId));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasRequiredResultFilters(array $filters): bool
    {
        return filled($filters['campus_id'] ?? null) && filled($filters['term_id'] ?? null);
    }

    private function applySearch(Builder $query, mixed $search): void
    {
        if (blank($search)) {
            return;
        }

        $search = addcslashes((string) $search, '%_\\');

        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('student_pft_results.term_id', 'like', "%{$search}%")
                ->orWhere('student_pft_results.campus_id', 'like', "%{$search}%")
                ->orWhere('student_pft_results.college_id', 'like', "%{$search}%")
                ->orWhere('student_pft_results.section_id', 'like', "%{$search}%")
                ->orWhere('student_pft_results.section_name', 'like', "%{$search}%")
                ->orWhere('student_pft_results.year_level_id', 'like', "%{$search}%")
                ->orWhere('student_pft_results.remarks', 'like', "%{$search}%")
                ->orWhere('student_pft_results.status', 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('student_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('testType', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
        });
    }

    private function groupedCount(Builder $query): int
    {
        return $query
            ->withoutEagerLoads()
            ->select('user_id', 'term_id')
            ->groupBy('user_id', 'term_id')
            ->get()
            ->count();
    }

    private function groupedQuery(Builder $query): Builder
    {
        return $query
            ->withoutEagerLoads()
            ->leftJoin('users', 'users.id', '=', 'student_pft_results.user_id')
            ->selectRaw('student_pft_results.user_id')
            ->selectRaw('student_pft_results.term_id')
            ->selectRaw('MAX(student_pft_results.id) as latest_id')
            ->selectRaw('COUNT(*) as test_count')
            ->selectRaw('MAX(student_pft_results.tested_at) as latest_tested_at')
            ->selectRaw('MAX(student_pft_results.created_at) as latest_created_at')
            ->selectRaw('MAX(student_pft_results.campus_id) as campus_id')
            ->selectRaw('MAX(student_pft_results.college_id) as college_id')
            ->selectRaw('MAX(student_pft_results.section_id) as section_id')
            ->selectRaw('MAX(student_pft_results.section_name) as section_name')
            ->selectRaw('MAX(student_pft_results.year_level_id) as year_level_id')
            ->selectRaw('MAX(users.name) as student_name')
            ->selectRaw('MAX(users.student_no) as student_no')
            ->groupBy('student_pft_results.user_id', 'student_pft_results.term_id');
    }

    private function applyGroupedOrdering(Builder $query, Request $request): void
    {
        $columnIndex = $request->integer('order.0.column', 1);
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $column = self::ORDER_COLUMNS[$columnIndex] ?? 'latest_tested_at';

        $query->orderBy($column, $direction)->orderByDesc('latest_id');
    }

    private function applyOrdering(Builder $query, Request $request): void
    {
        $columnIndex = $request->integer('order.0.column', 1);
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $column = self::ORDER_COLUMNS[$columnIndex] ?? 'tested_at';

        if ($column === 'pft_test_types.name') {
            $query
                ->leftJoin('pft_test_types', 'pft_test_types.id', '=', 'student_pft_results.pft_test_type_id')
                ->select('student_pft_results.*')
                ->orderBy('pft_test_types.name', $direction);

            return;
        }

        $query->orderBy($column, $direction)->orderByDesc('id');
    }

    private function dataTablesLength(Request $request): int
    {
        $length = $request->integer('length', 10);

        if ($length === -1) {
            return 500;
        }

        return in_array($length, self::PAGE_SIZES, true) ? $length : 10;
    }

    private function tableRow(StudentPftResult $row, int $number): array
    {
        return [
            'number' => $number,
            'tested_date' => $row->tested_at?->toDateString(),
            'term' => $row->term_id,
            'campus' => $row->campus_id,
            'college' => $row->college_id,
            'section_id' => $row->section_id,
            'section_name' => $row->section_name,
            'year_level' => $row->year_level_id,
            'pft_test_type' => $row->testType?->name,
            'results' => $this->resultLines($row),
            'remarks' => $row->remarks,
            'status' => Str::headline((string) $row->status),
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }

    private function groupedTableRow(?StudentPftResult $row, Collection $details, int $number, int $testCount, array $labels, array $termComparison): array
    {
        if (! $row) {
            return [];
        }

        return [
            'number' => $number,
            'user_id' => $row->user_id,
            'student_name' => $row->user?->name ?? 'User #'.$row->user_id,
            'student_no' => $row->user?->student_no,
            'student_email' => $row->user?->email,
            'term' => $row->term_id,
            'term_label' => $labels['term'] ?? $row->term_id,
            'campus' => $row->campus_id,
            'campus_label' => $labels['campus'] ?? $row->campus_id,
            'college' => $row->college_id,
            'college_label' => $labels['colleges'][(string) $row->college_id] ?? $row->college_id,
            'section_id' => $row->section_id,
            'section_name' => $row->section_name,
            'year_level' => $row->year_level_id,
            'test_count' => $testCount,
            'latest_tested_date' => $row->tested_at?->toDateString(),
            'latest_created_at' => $row->created_at?->toDateTimeString(),
            'details' => $details->map(fn (StudentPftResult $detail): array => $this->detailRow($detail))->values(),
            'current_analytics' => $this->currentDrawerAnalytics($details),
            'term_comparison' => $termComparison,
            'result_comparisons' => $this->resultComparisons($details, $termComparison['result_averages'] ?? []),
            'interpretation_comparisons' => $this->interpretationComparisons($details, $termComparison['interpretation_by_test_type'] ?? []),
            'radar_profile' => $this->radarProfile($details, $termComparison['normalized_scores'] ?? []),
        ];
    }

    private function drawerLabels(array $filters): array
    {
        $labels = [
            'campus' => null,
            'term' => null,
            'colleges' => [],
        ];

        $campus = $this->campusFor((string) ($filters['campus_id'] ?? ''));
        if ($campus) {
            $labels['campus'] = $campus->campus_name;

            $term = SiteAcademicTerm::query()
                ->where('site_campus_id', $campus->id)
                ->where('term_id', $filters['term_id'] ?? null)
                ->first();

            if ($term) {
                $labels['term'] = $this->termLabel((string) $term->term_id, $term);
            }
        }

        $labels['colleges'] = collect($this->academicApi->getColleges(auth()->user()?->tenant_id)['data'] ?? [])
            ->filter(fn (array $college): bool => (string) ($college['campusId'] ?? '') === (string) ($filters['campus_id'] ?? ''))
            ->mapWithKeys(fn (array $college): array => [
                (string) ($college['collegeId'] ?? '') => $this->collegeLabel($college),
            ])
            ->filter()
            ->all();

        return $labels;
    }

    private function drilldownLabels(): array
    {
        $campuses = SiteCampus::query()
            ->select(['id', 'real_campus_id', 'campus_name'])
            ->get()
            ->flatMap(function (SiteCampus $campus): array {
                $label = $campus->campus_name;
                $keys = [
                    (string) $campus->id,
                ];

                if (filled($campus->real_campus_id)) {
                    $keys[] = (string) $campus->real_campus_id;
                }

                return collect($keys)
                    ->filter()
                    ->mapWithKeys(fn (string $key): array => [$key => $label])
                    ->all();
            })
            ->all();

        $colleges = collect($this->academicApi->getColleges(auth()->user()?->tenant_id)['data'] ?? [])
            ->mapWithKeys(fn (array $college): array => [
                (string) ($college['collegeId'] ?? '') => $this->collegeLabel($college),
            ])
            ->filter()
            ->all();

        return [
            'campuses' => $campuses,
            'colleges' => $colleges,
        ];
    }

    /**
     * @return array<int, array{id: string, key: string, campus_id: string, campus_name: string, total_students: int, total_results: int}>
     */
    private function campusDrilldownNodes(Builder $query, array $labels): array
    {
        return (clone $query)
            ->withoutEagerLoads()
            ->selectRaw('student_pft_results.campus_id as campus_id')
            ->selectRaw('COUNT(*) as total_results')
            ->selectRaw('COUNT(DISTINCT student_pft_results.user_id) as total_students')
            ->groupBy('student_pft_results.campus_id')
            ->orderBy('student_pft_results.campus_id')
            ->get()
            ->map(function ($row) use ($labels): array {
                $campusId = (string) ($row->campus_id ?? 'unassigned');

                return [
                    'id' => $campusId,
                    'key' => $campusId,
                    'campus_id' => $campusId,
                    'campus_name' => $labels['campuses'][$campusId] ?? ($campusId === 'unassigned' ? 'Unassigned Campus' : $campusId),
                    'total_students' => (int) $row->total_students,
                    'total_results' => (int) $row->total_results,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, key: string, campus_id: string, campus_name: string, college_id: string, college_name: string, total_students: int, total_results: int}>
     */
    private function collegeDrilldownNodes(Builder $query, array $labels, string $campusId): array
    {
        return (clone $query)
            ->withoutEagerLoads()
            ->when(filled($campusId), fn (Builder $query) => $query->where('student_pft_results.campus_id', $campusId))
            ->selectRaw('student_pft_results.campus_id as campus_id')
            ->selectRaw('student_pft_results.college_id as college_id')
            ->selectRaw('COUNT(*) as total_results')
            ->selectRaw('COUNT(DISTINCT student_pft_results.user_id) as total_students')
            ->groupBy('student_pft_results.campus_id', 'student_pft_results.college_id')
            ->orderBy('student_pft_results.college_id')
            ->get()
            ->map(function ($row) use ($labels): array {
                $campusId = (string) ($row->campus_id ?? 'unassigned');
                $collegeId = (string) ($row->college_id ?? 'unassigned');

                return [
                    'id' => $campusId.'::'.$collegeId,
                    'key' => $campusId.'::'.$collegeId,
                    'campus_id' => $campusId,
                    'campus_name' => $labels['campuses'][$campusId] ?? ($campusId === 'unassigned' ? 'Unassigned Campus' : $campusId),
                    'college_id' => $collegeId,
                    'college_name' => $labels['colleges'][$collegeId] ?? ($collegeId === 'unassigned' ? 'Unassigned College' : $collegeId),
                    'total_students' => (int) $row->total_students,
                    'total_results' => (int) $row->total_results,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, key: string, campus_id: string, campus_name: string, college_id: string, college_name: string, user_id: int, student_key: string, student_no: string|null, student_name: string, section: string, year_level: string, total_results: int}>
     */
    private function studentDrilldownNodes(Builder $query, array $labels, string $campusId, string $collegeId): array
    {
        return (clone $query)
            ->withoutEagerLoads()
            ->with('user:id,name,student_no')
            ->when(filled($campusId), fn (Builder $query) => $query->where('student_pft_results.campus_id', $campusId))
            ->when(filled($collegeId), fn (Builder $query) => $query->where('student_pft_results.college_id', $collegeId))
            ->selectRaw('student_pft_results.campus_id as campus_id')
            ->selectRaw('student_pft_results.college_id as college_id')
            ->selectRaw('student_pft_results.user_id as user_id')
            ->selectRaw('MAX(student_pft_results.section_name) as section_name')
            ->selectRaw('MAX(student_pft_results.section_id) as section_id')
            ->selectRaw('MAX(student_pft_results.year_level_id) as year_level_id')
            ->selectRaw('COUNT(*) as total_results')
            ->groupBy('student_pft_results.campus_id', 'student_pft_results.college_id', 'student_pft_results.user_id')
            ->orderByDesc('total_results')
            ->orderBy('student_pft_results.user_id')
            ->get()
            ->map(function ($row) use ($labels): array {
                $campusId = (string) ($row->campus_id ?? 'unassigned');
                $collegeId = (string) ($row->college_id ?? 'unassigned');
                $studentKey = (string) $row->user_id;

                return [
                    'id' => $campusId.'::'.$collegeId.'::'.$studentKey,
                    'key' => $campusId.'::'.$collegeId.'::'.$studentKey,
                    'campus_id' => $campusId,
                    'campus_name' => $labels['campuses'][$campusId] ?? ($campusId === 'unassigned' ? 'Unassigned Campus' : $campusId),
                    'college_id' => $collegeId,
                    'college_name' => $labels['colleges'][$collegeId] ?? ($collegeId === 'unassigned' ? 'Unassigned College' : $collegeId),
                    'user_id' => (int) $row->user_id,
                    'student_key' => $studentKey,
                    'student_no' => $row->user?->student_no,
                    'student_name' => $row->user?->name ?? ('User #'.(string) $row->user_id),
                    'section' => (string) ($row->section_name ?: $row->section_id ?: '-'),
                    'year_level' => (string) ($row->year_level_id ?: '-'),
                    'total_results' => (int) $row->total_results,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentDetailRows(Builder $query, array $labels, string $campusId, string $collegeId, string $userId): array
    {
        return (clone $query)
            ->when(filled($campusId), fn (Builder $query) => $query->where('student_pft_results.campus_id', $campusId))
            ->when(filled($collegeId), fn (Builder $query) => $query->where('student_pft_results.college_id', $collegeId))
            ->when(filled($userId), fn (Builder $query) => $query->where('student_pft_results.user_id', $userId))
            ->orderByDesc('student_pft_results.tested_at')
            ->orderByDesc('student_pft_results.id')
            ->get()
            ->map(fn (StudentPftResult $row): array => $this->drilldownRowPayload($row, $labels))
            ->values()
            ->all();
    }

    private function drilldownRowPayload(StudentPftResult $row, array $labels): array
    {
        $campusName = $labels['campuses'][(string) $row->campus_id] ?? ($row->campus_id ?: 'Unassigned Campus');
        $collegeName = $labels['colleges'][(string) $row->college_id] ?? ($row->college_id ?: 'Unassigned College');
        $results = json_decode($row->getRawOriginal('results_json'), true) ?? [];
        $rawLines = collect($results)
            ->except(['interpretation', 'interpretation_color'])
            ->map(fn ($val, $key) => Str::headline((string) $key) . ': ' . $val)
            ->implode(', ');

        return [
            'user_id' => $row->user_id,
            'student_no' => $row->user?->student_no,
            'student_name' => $row->user?->name,
            'campus_id' => $row->campus_id,
            'campus' => $campusName,
            'campus_name' => $campusName,
            'college_id' => $row->college_id,
            'college' => $collegeName,
            'college_name' => $collegeName,
            'section' => $row->section_name ?: $row->section_id,
            'year_level' => $row->year_level_id,
            'component' => $row->testType?->category?->component?->name,
            'test_type' => $row->testType?->name,
            'raw_result' => $rawLines,
            'classification' => $row->classification,
            'remarks' => $row->remarks,
            'test_date' => $row->tested_at?->toDateString(),
            'student_key' => $this->groupKey((int) $row->user_id, (string) $row->term_id),
        ];
    }

    private function detailRow(StudentPftResult $row): array
    {
        return [
            'id' => $row->id,
            'tested_date' => $row->tested_at?->toDateString(),
            'pft_test_type' => $row->testType?->name,
            'category' => $row->testType?->category?->name,
            'component' => $row->testType?->category?->component?->name,
            'results' => $this->resultLines($row),
            'interpretation' => $this->resultInterpretation($row),
            'remarks' => $row->remarks,
            'status' => Str::headline((string) $row->status),
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }

    private function detailsForGroups(Collection $latestRows): Collection
    {
        if ($latestRows->isEmpty()) {
            return collect();
        }

        $pairs = $latestRows
            ->map(fn (StudentPftResult $row): array => [
                'user_id' => $row->user_id,
                'term_id' => $row->term_id,
            ])
            ->unique(fn (array $pair): string => $this->groupKey((int) $pair['user_id'], (string) $pair['term_id']))
            ->values();

        $details = StudentPftResult::query()
            ->with([
                'testType.category.component',
                'testType.interpretationRules' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id'),
            ])
            ->where(function (Builder $query) use ($pairs): void {
                $pairs->each(function (array $pair) use ($query): void {
                    $query->orWhere(function (Builder $query) use ($pair): void {
                        $query
                            ->where('user_id', $pair['user_id'])
                            ->where('term_id', $pair['term_id']);
                    });
                });
            })
            ->orderByDesc('tested_at')
            ->orderByDesc('created_at')
            ->get();

        return $details->groupBy(fn (StudentPftResult $row): string => $this->groupKey((int) $row->user_id, (string) $row->term_id));
    }

    private function groupKey(int $userId, string $termId): string
    {
        return $userId.'|'.$termId;
    }

    private function currentDrawerAnalytics(Collection $details): array
    {
        $interpretationAnalytics = $this->interpretationAnalytics($details);

        return [
            'total_tests' => $details->count(),
            'completed' => $details->where('status', 'completed')->count(),
            'draft' => $details->where('status', 'draft')->count(),
            'numeric_tests' => $details->filter(fn (StudentPftResult $row): bool => $this->primaryNumericResult($row) !== null)->count(),
            'interpreted' => $interpretationAnalytics['interpreted'],
            'unclassified' => $interpretationAnalytics['unclassified'],
            'interpretations' => $interpretationAnalytics['distribution'],
            'component_interpretations' => $interpretationAnalytics['components'],
            'components' => $details
                ->groupBy(fn (StudentPftResult $row): string => $row->testType?->category?->component?->name ?? 'Uncategorized')
                ->map(fn (Collection $rows, string $component): array => [
                    'label' => $component,
                    'value' => $rows->count(),
                ])
                ->values()
                ->all(),
            'bmi' => $this->averageBmi($details),
        ];
    }

    private function resultComparisons(Collection $details, array $termAverages): array
    {
        return $details
            ->map(function (StudentPftResult $row) use ($termAverages): ?array {
                $value = $this->primaryNumericResult($row);
                if ($value === null) {
                    return null;
                }

                $testType = $row->testType?->name ?? 'PFT Test';
                $termAverage = $termAverages[$testType] ?? null;

                return [
                    'label' => $testType,
                    'component' => $row->testType?->category?->component?->name ?? 'Uncategorized',
                    'category' => $row->testType?->category?->name ?? 'Uncategorized',
                    'student_value' => round($value, 2),
                    'term_average' => $termAverage,
                    'difference' => $termAverage === null ? null : round($value - (float) $termAverage, 2),
                    'unit' => $row->testType?->unit,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function interpretationComparisons(Collection $details, array $termDistributionByTestType): array
    {
        return $details
            ->map(function (StudentPftResult $row) use ($termDistributionByTestType): array {
                $interpretation = $this->resultInterpretation($row);
                $testType = $row->testType?->name ?? 'PFT Test';
                $termDistribution = $termDistributionByTestType[$testType] ?? [];

                return [
                    'label' => $testType,
                    'component' => $row->testType?->category?->component?->name ?? 'Uncategorized',
                    'category' => $row->testType?->category?->name ?? 'Uncategorized',
                    'student_label' => $interpretation['label'] ?? 'Unclassified',
                    'student_color' => $interpretation['color'] ?? 'slate',
                    'term_distribution' => $termDistribution,
                ];
            })
            ->values()
            ->all();
    }

    private function interpretationAnalytics(Collection $rows): array
    {
        $classifiedRows = $rows->map(fn (StudentPftResult $row): array => [
            'row' => $row,
            'interpretation' => $this->resultInterpretation($row),
        ]);

        $interpreted = $classifiedRows->filter(fn (array $item): bool => $item['interpretation'] !== null)->count();

        return [
            'interpreted' => $interpreted,
            'unclassified' => $rows->count() - $interpreted,
            'distribution' => $this->interpretationDistribution($classifiedRows),
            'components' => $this->componentInterpretationDistribution($classifiedRows),
            'test_types' => $this->testTypeInterpretationSummary($classifiedRows),
            'by_test_type' => $classifiedRows
                ->groupBy(fn (array $item): string => $item['row']->testType?->name ?? 'PFT Test')
                ->map(fn (Collection $items): array => $this->interpretationDistribution($items))
                ->all(),
        ];
    }

    private function interpretationDistribution(Collection $classifiedRows): array
    {
        return $classifiedRows
            ->groupBy(fn (array $item): string => $item['interpretation']['label'] ?? 'Unclassified')
            ->map(function (Collection $items, string $label): array {
                $interpretation = $items->first()['interpretation'] ?? null;

                return [
                    'label' => $label,
                    'value' => $items->count(),
                    'color' => $interpretation['color'] ?? 'slate',
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function componentInterpretationDistribution(Collection $classifiedRows): array
    {
        return $classifiedRows
            ->groupBy(fn (array $item): string => $item['row']->testType?->category?->component?->name ?? 'Uncategorized')
            ->map(function (Collection $items, string $component): array {
                $distribution = $this->interpretationDistribution($items);
                $dominant = $distribution[0] ?? ['label' => 'Unclassified', 'value' => 0, 'color' => 'slate'];

                return [
                    'label' => $component,
                    'value' => $items->count(),
                    'dominant_label' => $dominant['label'],
                    'dominant_color' => $dominant['color'],
                    'interpretations' => $distribution,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function testTypeInterpretationSummary(Collection $classifiedRows): array
    {
        return $classifiedRows
            ->groupBy(fn (array $item): string => $item['row']->testType?->name ?? 'PFT Test')
            ->map(function (Collection $items, string $testType): array {
                $distribution = $this->interpretationDistribution($items);
                $dominant = $distribution[0] ?? ['label' => 'Unclassified', 'value' => 0, 'color' => 'slate'];

                return [
                    'label' => $testType,
                    'value' => $items->count(),
                    'dominant_label' => $dominant['label'],
                    'dominant_color' => $dominant['color'],
                    'interpretations' => $distribution,
                ];
            })
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->all();
    }

    private function hierarchyAnalytics(Collection $rows): array
    {
        $classifiedRows = $rows->map(fn (StudentPftResult $row): array => [
            'row' => $row,
            'interpretation' => $this->resultInterpretation($row),
        ]);

        return $classifiedRows
            ->groupBy(fn (array $item): string => $item['row']->testType?->category?->component?->name ?? 'Uncategorized')
            ->map(function (Collection $componentRows, string $component): array {
                return [
                    'label' => $component,
                    'value' => $componentRows->count(),
                    'students' => $this->studentCountForClassifiedRows($componentRows),
                    'interpretations' => $this->interpretationDistribution($componentRows),
                    'categories' => $componentRows
                        ->groupBy(fn (array $item): string => $item['row']->testType?->category?->name ?? 'Uncategorized')
                        ->map(function (Collection $categoryRows, string $category): array {
                            return [
                                'label' => $category,
                                'value' => $categoryRows->count(),
                                'students' => $this->studentCountForClassifiedRows($categoryRows),
                                'interpretations' => $this->interpretationDistribution($categoryRows),
                                'test_types' => $categoryRows
                                    ->groupBy(fn (array $item): string => $item['row']->testType?->name ?? 'PFT Test')
                                    ->map(function (Collection $testTypeRows, string $testType): array {
                                        return [
                                            'label' => $testType,
                                            'value' => $testTypeRows->count(),
                                            'students' => $this->studentCountForClassifiedRows($testTypeRows),
                                            'interpretations' => $this->interpretationDistribution($testTypeRows),
                                        ];
                                    })
                                    ->sortBy('label')
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->sortBy('label')
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function studentCountForClassifiedRows(Collection $classifiedRows): int
    {
        return $classifiedRows
            ->map(fn (array $item): string => $this->groupKey((int) $item['row']->user_id, (string) $item['row']->term_id))
            ->unique()
            ->count();
    }

    private function resultInterpretation(StudentPftResult $row): ?array
    {
        if (! $row->testType) {
            return null;
        }

        $results = json_decode($row->getRawOriginal('results_json'), true) ?? [];

        return $this->interpretationService->interpret($row->testType, $results);
    }

    private function averageBmi(Collection $rows): ?float
    {
        $values = $rows
            ->map(function (StudentPftResult $row): ?float {
                $data = json_decode($row->getRawOriginal('results_json'), true) ?? [];
                $value = $data['bmi'] ?? null;

                return is_numeric($value) ? (float) $value : null;
            })
            ->filter()
            ->values();

        return $values->isEmpty() ? null : round((float) $values->avg(), 2);
    }

    private function termComparison(array $filters): array
    {
        if (! $this->hasRequiredResultFilters($filters)) {
            return [
                'total_tests' => 0,
                'students' => 0,
                'completed' => 0,
                'draft' => 0,
                'numeric_tests' => 0,
                'interpreted' => 0,
                'unclassified' => 0,
                'components' => [],
                'test_types' => [],
                'interpretations' => [],
                'component_interpretations' => [],
                'interpretation_by_test_type' => [],
                'bmi_average' => null,
                'result_averages' => [],
                'normalized_scores' => [],
            ];
        }

        $rows = $this->filteredQuery($filters)->get();
        $interpretationAnalytics = $this->interpretationAnalytics($rows);

        return [
            'total_tests' => $rows->count(),
            'students' => $rows
                ->map(fn (StudentPftResult $row): string => $this->groupKey((int) $row->user_id, (string) $row->term_id))
                ->unique()
                ->count(),
            'completed' => $rows->where('status', 'completed')->count(),
            'draft' => $rows->where('status', 'draft')->count(),
            'numeric_tests' => $rows->filter(fn (StudentPftResult $row): bool => $this->primaryNumericResult($row) !== null)->count(),
            'interpreted' => $interpretationAnalytics['interpreted'],
            'unclassified' => $interpretationAnalytics['unclassified'],
            'components' => $rows
                ->groupBy(fn (StudentPftResult $row): string => $row->testType?->category?->component?->name ?? 'Uncategorized')
                ->map(fn (Collection $rows, string $component): array => [
                    'label' => $component,
                    'value' => $rows->count(),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'test_types' => $rows
                ->groupBy(fn (StudentPftResult $row): string => $row->testType?->name ?? 'PFT Test')
                ->map(fn (Collection $rows, string $testType): array => [
                    'label' => $testType,
                    'value' => $rows->count(),
                ])
                ->sortByDesc('value')
                ->take(8)
                ->values()
                ->all(),
            'interpretations' => $interpretationAnalytics['distribution'],
            'component_interpretations' => $interpretationAnalytics['components'],
            'interpretation_by_test_type' => $interpretationAnalytics['by_test_type'],
            'bmi_average' => $this->averageBmi($rows),
            'result_averages' => $rows
                ->groupBy(fn (StudentPftResult $row): string => $row->testType?->name ?? 'PFT Test')
                ->map(function (Collection $rows): ?float {
                    $values = $rows
                        ->map(fn (StudentPftResult $row): ?float => $this->primaryNumericResult($row))
                        ->filter()
                        ->values();

                    return $values->isEmpty() ? null : round((float) $values->avg(), 2);
                })
                ->filter(fn (?float $value): bool => $value !== null)
                ->all(),
            'normalized_scores' => $this->normalizedScores($rows),
        ];
    }

    private function normalizedScores(Collection $rows): array
    {
        return $rows
            ->map(function (StudentPftResult $row): ?array {
                $interpretation = $this->resultInterpretation($row);
                if (! $interpretation) {
                    return null;
                }

                return [
                    'test_type_id' => $row->pft_test_type_id,
                    'test_type' => $row->testType?->name ?? 'PFT Test',
                    'category' => $row->testType?->category?->name ?? 'Uncategorized',
                    'interpretation' => $interpretation['label'],
                    'color' => $interpretation['color'],
                    'score' => $this->interpretationScore($interpretation),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function radarProfile(Collection $details, array $interpretationScores): array
    {
        $studentScores = $details
            ->map(function (StudentPftResult $row): ?array {
                $interpretation = $this->resultInterpretation($row);
                if (! $interpretation) {
                    return null;
                }

                return [
                    'category' => $row->testType?->category?->name ?? 'Uncategorized',
                    'score' => $this->interpretationScore($interpretation),
                ];
            })
            ->filter()
            ->values();

        $termScores = collect($interpretationScores);
        $axes = $termScores
            ->pluck('category')
            ->merge($studentScores->pluck('category'))
            ->unique()
            ->values();

        return [
            'labels' => $axes->all(),
            'currentLabel' => 'Student',
            'previousLabel' => 'Term Average',
            'current' => $axes
                ->map(fn (string $axis): int => (int) round($studentScores->where('category', $axis)->avg('score')))
                ->all(),
            'previous' => $axes
                ->map(fn (string $axis): int => (int) round($termScores->where('category', $axis)->avg('score')))
                ->all(),
        ];
    }

    private function interpretationScore(array $interpretation): int
    {
        return match ($interpretation['color'] ?? null) {
            'emerald', 'green' => 100,
            'lime' => 80,
            'amber' => 55,
            'orange' => 40,
            'red', 'rose' => 20,
            default => 50,
        };
    }

    private function primaryNumericResult(StudentPftResult $row): ?float
    {
        $data = json_decode($row->getRawOriginal('results_json'), true) ?? [];

        foreach (['score', 'bmi', 'value', 'result'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (float) $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function exportRow(StudentPftResult $row, int $number): array
    {
        $tableRow = $this->tableRow($row, $number);

        return [
            $tableRow['number'],
            $tableRow['tested_date'],
            $tableRow['term'],
            $tableRow['campus'],
            $tableRow['college'],
            $tableRow['section_id'],
            $tableRow['section_name'],
            $tableRow['year_level'],
            $tableRow['pft_test_type'],
            collect($tableRow['results'])->map(fn (array $line): string => "{$line['label']}: {$line['value']}")->implode("\n"),
            $tableRow['remarks'],
            $tableRow['status'],
            $tableRow['created_at'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function resultLines(StudentPftResult $row): array
    {
        $data = json_decode($row->getRawOriginal('results_json'), true) ?? [];

        return collect($data)
            ->map(fn (mixed $value, string|int $key): array => [
                'key' => (string) $key,
                'label' => Str::headline((string) $key),
                'value' => $this->formatResultValue($value),
            ])
            ->values()
            ->all();
    }

    private function formatResultValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value) || $value === null) {
            return filled($value) ? (string) $value : '-';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '-';
    }

    private function termLabel(string $termId, ?SiteAcademicTerm $term): string
    {
        if (! $term) {
            return $termId;
        }

        $status = $term->status ? " ({$term->status})" : '';

        return trim("{$term->school_year} {$term->semester}{$status}") ?: $termId;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyOptionFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['term_id'] ?? null, fn (Builder $query, string $termId) => $query->where('term_id', $termId))
            ->when($filters['campus_id'] ?? null, fn (Builder $query, string $campusId) => $query->where('campus_id', $campusId))
            ->when($filters['college_id'] ?? null, fn (Builder $query, string $collegeId) => $query->where('college_id', $collegeId))
            ->when($filters['section_id'] ?? null, fn (Builder $query, string $sectionId) => $query->where('section_id', $sectionId));
    }

    private function tableSummary(Builder $query): array
    {
        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN classification IS NOT NULL THEN 1 ELSE 0 END) as interpreted')
            ->selectRaw('SUM(CASE WHEN classification IS NULL THEN 1 ELSE 0 END) as unclassified')
            ->selectRaw('COUNT(DISTINCT pft_test_type_id) as test_types')
            ->selectRaw('COUNT(DISTINCT user_id) as students')
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'interpreted' => (int) ($summary->interpreted ?? 0),
            'unclassified' => (int) ($summary->unclassified ?? 0),
            'test_types' => (int) ($summary->test_types ?? 0),
            'students' => (int) ($summary->students ?? 0),
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'stats' => [
                'total' => 0,
                'completed' => 0,
                'draft' => 0,
                'interpreted' => 0,
                'unclassified' => 0,
                'test_types' => 0,
                'students' => 0,
                'sections' => 0,
            ],
            'interpretations' => [],
            'componentInterpretations' => [],
            'testTypeInterpretations' => [],
            'hierarchy' => [],
            'status' => [],
            'testTypes' => [],
            'campuses' => [],
            'colleges' => [],
            'yearLevels' => [],
            'bmi' => [
                'average' => null,
                'distribution' => [
                    ['label' => 'Underweight', 'value' => 0],
                    ['label' => 'Normal', 'value' => 0],
                    ['label' => 'Overweight', 'value' => 0],
                    ['label' => 'Obese', 'value' => 0],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function select2Filters(Request $request, array $rules): array
    {
        return $request->validate($rules);
    }

    private function select2FromQuery(Builder $query, int $page, callable $mapper): JsonResponse
    {
        $perPage = 20;
        $items = $query
            ->skip((max($page, 1) - 1) * $perPage)
            ->take($perPage + 1)
            ->get();

        return response()->json([
            'results' => $items->take($perPage)->map($mapper)->values(),
            'pagination' => [
                'more' => $items->count() > $perPage,
            ],
        ]);
    }

    private function select2FromCollection(Collection $collection, int $page, callable $mapper): JsonResponse
    {
        $perPage = 20;
        $offset = (max($page, 1) - 1) * $perPage;
        $items = $collection->slice($offset)->take($perPage + 1)->values();

        return response()->json([
            'results' => $items->take($perPage)->map($mapper)->filter(fn (array $item): bool => filled($item['id']))->values(),
            'pagination' => [
                'more' => $items->count() > $perPage,
            ],
        ]);
    }

    private function campusFor(string $campusId): ?SiteCampus
    {
        return SiteCampus::query()
            ->where('real_campus_id', $campusId)
            ->orWhere('id', $campusId)
            ->first(['id', 'real_campus_id', 'campus_name', 'campus_logo_path']);
    }

    private function reportLogoPath(array $filters): ?string
    {
        $campus = $this->campusFor((string) ($filters['campus_id'] ?? ''));

        if ($campus?->campus_logo_path) {
            $logoPath = public_path('storage/'.$campus->campus_logo_path);

            if (is_file($logoPath)) {
                return $logoPath;
            }
        }

        $fallbackPath = public_path('favicon.png');

        return is_file($fallbackPath) ? $fallbackPath : null;
    }

    private function collegeLabel(array $college): string
    {
        $code = trim((string) ($college['collegeCode'] ?? ''));
        $name = trim((string) ($college['collegeName'] ?? ''));

        return trim($code.' - '.$name, ' -') ?: (string) ($college['collegeId'] ?? 'College');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array{id: string, text: string}|null>
     */
    private function selectedOptions(array $filters): array
    {
        return [
            'campus' => $this->selectedCampus($filters['campus_id'] ?? null),
            'term' => $this->selectedTerm($filters['campus_id'] ?? null, $filters['term_id'] ?? null),
            'college' => $this->selectedCollege($filters['campus_id'] ?? null, $filters['college_id'] ?? null),
            'section' => $this->selectedSection($filters),
            'testType' => $this->selectedTestType($filters['test_type_id'] ?? null),
        ];
    }

    private function selectedCampus(?string $campusId): ?array
    {
        if (blank($campusId)) {
            return null;
        }

        $campus = $this->campusFor($campusId);

        return $campus ? [
            'id' => (string) ($campus->real_campus_id ?: $campus->id),
            'text' => trim(($campus->real_campus_id ? "{$campus->real_campus_id} - " : '').$campus->campus_name),
        ] : null;
    }

    private function selectedTerm(?string $campusId, ?string $termId): ?array
    {
        if (blank($campusId) || blank($termId)) {
            return null;
        }

        $campus = $this->campusFor($campusId);
        if (! $campus) {
            return null;
        }

        $term = SiteAcademicTerm::query()
            ->where('site_campus_id', $campus->id)
            ->where('term_id', $termId)
            ->first();

        return $term ? [
            'id' => (string) $term->term_id,
            'text' => $this->termLabel((string) $term->term_id, $term),
        ] : null;
    }

    private function selectedCollege(?string $campusId, ?string $collegeId): ?array
    {
        if (blank($campusId) || blank($collegeId)) {
            return null;
        }

        $college = collect($this->academicApi->getColleges(auth()->user()?->tenant_id)['data'] ?? [])
            ->first(fn (array $college): bool => (string) ($college['campusId'] ?? '') === (string) $campusId
                && (string) ($college['collegeId'] ?? '') === (string) $collegeId);

        return $college ? [
            'id' => (string) $collegeId,
            'text' => $this->collegeLabel($college),
        ] : null;
    }

    private function selectedSection(array $filters): ?array
    {
        if (blank($filters['campus_id'] ?? null) || blank($filters['term_id'] ?? null) || blank($filters['college_id'] ?? null) || blank($filters['section_id'] ?? null)) {
            return null;
        }

        $row = StudentPftResult::query()
            ->where('campus_id', $filters['campus_id'])
            ->where('term_id', $filters['term_id'])
            ->where('college_id', $filters['college_id'])
            ->where('section_id', $filters['section_id'])
            ->first(['section_id', 'section_name']);

        return $row ? [
            'id' => (string) $row->section_id,
            'text' => filled($row->section_name) ? "{$row->section_id} - {$row->section_name}" : (string) $row->section_id,
        ] : null;
    }

    private function selectedTestType(string|int|null $testTypeId): ?array
    {
        if (blank($testTypeId)) {
            return null;
        }

        $testType = PftTestType::query()->find($testTypeId, ['id', 'name']);

        return $testType ? [
            'id' => (string) $testType->id,
            'text' => $testType->name,
        ] : null;
    }

    private function groupCounts(Builder $query, string $column, string $label): array
    {
        return $query
            ->withoutEagerLoads()
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => Str::headline((string) ($row->label ?: $label)),
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function testTypeCounts(Builder $query): array
    {
        return $query
            ->withoutEagerLoads()
            ->join('pft_test_types', 'pft_test_types.id', '=', 'student_pft_results.pft_test_type_id')
            ->selectRaw('pft_test_types.name as label, COUNT(*) as total')
            ->groupBy('pft_test_types.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function bmiAnalytics(Builder $query): array
    {
        $values = $query
            ->withoutEagerLoads()
            ->get(['results_json'])
            ->map(function (StudentPftResult $row): ?float {
                $data = json_decode($row->getRawOriginal('results_json'), true) ?? [];
                $value = $data['bmi'] ?? null;

                return is_numeric($value) ? (float) $value : null;
            })
            ->filter()
            ->values();

        return [
            'average' => $values->isEmpty() ? null : round((float) $values->avg(), 2),
            'distribution' => [
                ['label' => 'Underweight', 'value' => $values->filter(fn (float $value): bool => $value < 18.5)->count()],
                ['label' => 'Normal', 'value' => $values->filter(fn (float $value): bool => $value >= 18.5 && $value < 25)->count()],
                ['label' => 'Overweight', 'value' => $values->filter(fn (float $value): bool => $value >= 25 && $value < 30)->count()],
                ['label' => 'Obese', 'value' => $values->filter(fn (float $value): bool => $value >= 30)->count()],
            ],
        ];
    }

    public function exportAnalyticsPdf(Request $request): Response
    {
        $filters = $this->filters($request);
        abort_unless($this->hasRequiredResultFilters($filters), 422, 'Campus and Academic Term are required.');

        $query = $this->filteredQuery($filters);
        $rows = $query->get();
        $hierarchy = $this->hierarchyAnalytics($rows);
        $labels = $this->drawerLabels($filters);
        $interpretationAnalytics = $this->interpretationAnalytics($rows);
        $collegeGroups = blank($filters['college_id'] ?? null)
            ? $this->collegeHierarchyGroups($rows, $labels)
            : [];

        $html = view('pdf.reporting-pft-analytics', [
            'filters' => $filters,
            'labels' => $labels,
            'hierarchy' => $hierarchy,
            'collegeGroups' => $collegeGroups,
            'interpretationAnalytics' => $interpretationAnalytics,
            'logoPath' => $this->reportLogoPath($filters),
            'generatedAt' => now(),
        ])->render();

        $pdf = Browsershot::html($html)
            ->format('A4')
            ->portrait()
            ->margins(12, 12, 12, 12)
            ->showBackground()
            ->noSandbox()
            ->pdf();

        $filename = 'pft-analytics-'.$filters['term_id'].'-'.now()->format('Ymd-His').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function collegeHierarchyGroups(Collection $rows, array $labels): array
    {
        return $rows
            ->groupBy(fn (StudentPftResult $row): string => filled($row->college_id) ? (string) $row->college_id : 'unassigned')
            ->map(function (Collection $collegeRows, string $collegeId) use ($labels): array {
                return [
                    'id' => $collegeId === 'unassigned' ? null : $collegeId,
                    'label' => $collegeId === 'unassigned'
                        ? 'Unassigned College'
                        : ($labels['colleges'][$collegeId] ?? $collegeId),
                    'results' => $collegeRows->count(),
                    'students' => $collegeRows
                        ->map(fn (StudentPftResult $row): string => $this->groupKey((int) $row->user_id, (string) $row->term_id))
                        ->unique()
                        ->count(),
                    'hierarchy' => $this->hierarchyAnalytics($collegeRows),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function collegeComponentProfiles(Collection $rows, array $collegeLabels): array
    {
        return $rows
            ->groupBy(fn (StudentPftResult $row): string => filled($row->college_id) ? (string) $row->college_id : 'unassigned')
            ->map(function (Collection $collegeRows, string $collegeId) use ($collegeLabels): array {
                return [
                    'id' => $collegeId,
                    'label' => $collegeId === 'unassigned'
                        ? 'Unassigned College'
                        : ($collegeLabels[$collegeId] ?? $collegeId),
                    'results' => $collegeRows->count(),
                    'students' => $collegeRows
                        ->map(fn (StudentPftResult $row): string => $this->groupKey((int) $row->user_id, (string) $row->term_id))
                        ->unique()
                        ->count(),
                    'hierarchy' => $this->hierarchyAnalytics($collegeRows),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }
}
