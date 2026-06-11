<?php

namespace App\Services;

use App\Models\PftComponent;
use App\Models\SiteAcademicTerm;
use App\Models\StudentPftResult;
use App\Models\User;
use Illuminate\Support\Collection;

class PftAnalyticsService
{
    private const HEALTH_WEIGHT = 0.6;
    private const SKILL_WEIGHT = 0.4;

    public function forStudent(User $user, array $filters = []): array
    {
        $components = $this->components();
        $terms = $this->terms($user);
        $results = $this->results($user, $terms, $filters);
        $latestResults = $this->latestResults($results);
        $history = $this->historyRows($results, $components, $terms);
        $overview = $this->getOverview($components, $terms, $latestResults);
        $semesterComparison = $this->getSemesterComparison($history);
        $radarData = $this->getRadarData($history, $terms);
        $timeline = $this->getTimeline($history);
        $fitnessIndex = $this->calculateFitnessIndex($history, $components, $terms);

        return [
            'overview' => $overview,
            'semesterComparison' => $semesterComparison,
            'radarData' => $radarData,
            'timeline' => $timeline,
            'insights' => $this->generateInsights($overview, $semesterComparison, $fitnessIndex),
            'fitnessIndex' => $fitnessIndex,
            'filters' => $this->filters($components, $terms),
        ];
    }

    public function getOverview(Collection $components, Collection $terms, Collection $latestResults): array
    {
        $activeTerm = $terms->firstWhere('status', 'Active') ?? $terms->first();
        $testTypes = $components->flatMap(
            fn (PftComponent $component) => $component->categories->flatMap->testTypes
        );
        $total = $testTypes->count();
        $completed = $latestResults
            ->where('status', 'completed')
            ->whereIn('pft_test_type_id', $testTypes->pluck('id'))
            ->count();
        $pending = max($total - $completed, 0);
        $completion = $total > 0 ? round(($completed / $total) * 100) : 0;
        $latestDate = $latestResults->pluck('tested_at')->filter()->sort()->last();
        $completionForComponent = function (string $componentName) use ($components, $latestResults): int {
            $component = $components->first(
                fn (PftComponent $item) => str($item->name)->lower()->contains(str($componentName)->lower())
            );

            if (! $component) {
                return 0;
            }

            $testTypeIds = $component->categories->flatMap->testTypes->pluck('id');
            $total = $testTypeIds->count();

            if ($total === 0) {
                return 0;
            }

            return (int) round(($latestResults->where('status', 'completed')->whereIn('pft_test_type_id', $testTypeIds)->count() / $total) * 100);
        };

        return [
            'academicYear' => $activeTerm?->school_year ?? '-',
            'semester' => $activeTerm?->semester ?? '-',
            'totalRequiredTests' => $total,
            'completedTests' => $completed,
            'pendingTests' => $pending,
            'completionPercentage' => $completion,
            'healthRelatedCompletion' => $completionForComponent('health'),
            'skillRelatedCompletion' => $completionForComponent('skill'),
            'latestTestDate' => $latestDate ? $latestDate->toDateString() : '-',
            'overallFitnessStatus' => match (true) {
                $completion === 100 => 'Completed',
                $completion > 0 => 'In Progress',
                default => 'Not Started',
            },
        ];
    }

    public function getSemesterComparison(Collection $history): array
    {
        return $history
            ->filter(fn (array $row) => is_numeric($row['numericValue']))
            ->groupBy('testTypeId')
            ->map(function (Collection $rows): array {
                $sorted = $rows->sortBy('sortDate')->values();
                $current = $sorted->last();
                $previous = $sorted->count() > 1 ? $sorted->get($sorted->count() - 2) : null;
                $difference = $previous ? round($current['numericValue'] - $previous['numericValue'], 2) : null;

                return [
                    'testTypeId' => $current['testTypeId'],
                    'testType' => $current['testType'],
                    'component' => $current['component'],
                    'category' => $current['category'],
                    'unit' => $current['unit'],
                    'current' => $current['numericValue'],
                    'previous' => $previous['numericValue'] ?? null,
                    'difference' => $difference,
                    'percentageChange' => $previous && (float) $previous['numericValue'] !== 0.0
                        ? round(($difference / abs($previous['numericValue'])) * 100, 1)
                        : null,
                    'series' => $sorted->map(fn (array $row) => [
                        'label' => "{$row['academicYear']} {$row['semester']}",
                        'value' => $row['numericValue'],
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    public function getRadarData(Collection $history, Collection $terms): array
    {
        $currentTerm = $terms->firstWhere('status', 'Active') ?? $terms->first();
        $previousTerm = $terms->skipUntil(fn ($term) => $term->term_id === $currentTerm?->term_id)->skip(1)->first();
        $axes = $history->pluck('category')->unique()->values();

        return [
            'labels' => $axes,
            'currentLabel' => $currentTerm ? "{$currentTerm->school_year} {$currentTerm->semester}" : 'Current Semester',
            'previousLabel' => $previousTerm ? "{$previousTerm->school_year} {$previousTerm->semester}" : 'Previous Semester',
            'current' => $this->radarScoresForTerm($history, $axes, $currentTerm?->term_id),
            'previous' => $this->radarScoresForTerm($history, $axes, $previousTerm?->term_id),
        ];
    }

    public function getTimeline(Collection $history): array
    {
        return $history
            ->sortByDesc('sortDate')
            ->take(50)
            ->values()
            ->all();
    }

    public function generateInsights(array $overview, array $comparison, array $fitnessIndex): array
    {
        $insights = [];

        if ($overview['pendingTests'] > 0) {
            $insights[] = "{$overview['pendingTests']} required tests are still incomplete.";
        }

        foreach (collect($comparison)->take(5) as $trend) {
            if ($trend['percentageChange'] === null) {
                continue;
            }

            $direction = $trend['percentageChange'] >= 0 ? 'improved' : 'decreased';
            $insights[] = "{$trend['testType']} {$direction} by ".abs($trend['percentageChange']).'% compared to the previous semester.';
        }

        $insights[] = "Overall Fitness Index is {$fitnessIndex['score']} / 100, rated {$fitnessIndex['rating']}.";

        if ($overview['completionPercentage'] === 100) {
            $insights[] = 'All required physical fitness tests are completed.';
        }

        return $insights;
    }

    public function calculateFitnessIndex(Collection $history, Collection $components, Collection $terms): array
    {
        $currentTerm = $terms->firstWhere('status', 'Active') ?? $terms->first();
        $currentRows = $history->where('termId', $currentTerm?->term_id);
        $scoreFor = fn (string $name): float => (float) $currentRows
            ->filter(fn (array $row) => str($row['component'])->lower()->contains($name))
            ->avg('normalizedScore');
        $health = $scoreFor('health');
        $skill = $scoreFor('skill');
        $score = (int) round(($health * self::HEALTH_WEIGHT) + ($skill * self::SKILL_WEIGHT));

        return [
            'score' => $score,
            'rating' => match (true) {
                $score >= 90 => 'Excellent',
                $score >= 80 => 'Very Good',
                $score >= 70 => 'Good',
                $score >= 60 => 'Fair',
                default => 'Needs Improvement',
            },
            'weights' => ['healthRelated' => 60, 'skillRelated' => 40],
        ];
    }

    public function normalizeScore(float|int|null $score, Collection $peerScores): int
    {
        if ($score === null || $peerScores->isEmpty()) {
            return 0;
        }

        $min = (float) $peerScores->min();
        $max = (float) $peerScores->max();

        if ($max === $min) {
            return 100;
        }

        return (int) round((($score - $min) / ($max - $min)) * 100);
    }

    private function components(): Collection
    {
        return PftComponent::query()
            ->active()
            ->with([
                'categories' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                'categories.testTypes' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                'categories.testTypes.configurations' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('field_label'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function terms(User $user): Collection
    {
        $savedTermIds = StudentPftResult::query()
            ->where('user_id', $user->id)
            ->pluck('term_id')
            ->filter()
            ->unique();

        return SiteAcademicTerm::query()
            ->select(['id', 'site_campus_id', 'school_year', 'semester', 'term_id', 'status', 'start_date', 'end_date'])
            ->whereNotNull('term_id')
            ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (string) $user->campus_id))
            ->where(function ($query) use ($savedTermIds): void {
                $query->where('status', 'Active')
                    ->when($savedTermIds->isNotEmpty(), fn ($query) => $query->orWhereIn('term_id', $savedTermIds));
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    private function results(User $user, Collection $terms, array $filters): Collection
    {
        return StudentPftResult::query()
            ->with(['testType.category.component'])
            ->where('user_id', $user->id)
            ->whereIn('term_id', $terms->pluck('term_id')->filter())
            ->when($filters['term_id'] ?? null, fn ($query, $termId) => $query->where('term_id', $termId))
            ->when($filters['component_id'] ?? null, fn ($query, $componentId) => $query->whereHas(
                'testType.category.component',
                fn ($query) => $query->whereKey($componentId),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->whereHas(
                'testType.category',
                fn ($query) => $query->whereKey($categoryId),
            ))
            ->when($filters['test_type_id'] ?? null, fn ($query, $testTypeId) => $query->where('pft_test_type_id', $testTypeId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('tested_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('tested_at', '<=', $date))
            ->latest('tested_at')
            ->latest('id')
            ->get();
    }

    private function latestResults(Collection $results): Collection
    {
        return $results
            ->sortByDesc(fn (StudentPftResult $result) => (($result->tested_at?->timestamp ?? 0) * 1_000_000) + $result->id)
            ->unique(fn (StudentPftResult $result): string => "{$result->term_id}:{$result->pft_test_type_id}")
            ->values();
    }

    private function historyRows(Collection $results, Collection $components, Collection $terms): Collection
    {
        $scoresByTestType = $results
            ->groupBy('pft_test_type_id')
            ->map(fn (Collection $items) => $items->map(fn (StudentPftResult $result) => $this->numericValue($result))->filter(fn ($value) => $value !== null)->values());

        return $results->map(function (StudentPftResult $result) use ($terms, $scoresByTestType): array {
            $term = $terms->firstWhere('term_id', $result->term_id);
            $numeric = $this->numericValue($result);

            return [
                'id' => $result->id,
                'termId' => $result->term_id,
                'academicYear' => $term?->school_year ?? '-',
                'semester' => $term?->semester ?? '-',
                'component' => $result->testType?->category?->component?->name ?? '-',
                'category' => $result->testType?->category?->name ?? '-',
                'testTypeId' => $result->pft_test_type_id,
                'testType' => $result->testType?->name ?? '-',
                'unit' => $result->testType?->unit,
                'result' => $this->displayValue($result),
                'rating' => $result->results_json['rating'] ?? $result->remarks ?? '-',
                'status' => $result->status ?? 'completed',
                'dateTested' => optional($result->tested_at)->toDateString() ?? optional($result->updated_at)->toDateString(),
                'sortDate' => optional($result->tested_at ?? $result->updated_at)->toDateString() ?? '',
                'numericValue' => $numeric,
                'normalizedScore' => $this->normalizeScore($numeric, $scoresByTestType->get($result->pft_test_type_id, collect())),
            ];
        });
    }

    private function numericValue(StudentPftResult $result): ?float
    {
        foreach (['score', 'bmi', 'time', 'distance', 'repetitions', 'count'] as $field) {
            $value = $result->results_json[$field] ?? null;

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        foreach ($result->results_json ?? [] as $value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function displayValue(StudentPftResult $result): string
    {
        $value = $this->numericValue($result);

        if ($value !== null) {
            return trim($value.' '.($result->testType?->unit ?? ''));
        }

        $first = collect($result->results_json)->first(fn ($value) => filled($value));

        return is_scalar($first) ? (string) $first : '-';
    }

    private function radarScoresForTerm(Collection $history, Collection $axes, ?string $termId): array
    {
        return $axes
            ->map(fn (string $axis): int => (int) round($history
                ->where('termId', $termId)
                ->where('category', $axis)
                ->avg('normalizedScore')))
            ->all();
    }

    private function filters(Collection $components, Collection $terms): array
    {
        return [
            'academicYears' => $terms->pluck('school_year')->unique()->values(),
            'semesters' => $terms->pluck('semester')->unique()->values(),
            'terms' => $terms->map(fn ($term) => [
                'termId' => $term->term_id,
                'label' => "{$term->school_year} {$term->semester}",
            ])->values(),
            'components' => $components->map(fn (PftComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'categories' => $component->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'testTypes' => $category->testTypes->map(fn ($testType) => [
                        'id' => $testType->id,
                        'name' => $testType->name,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
