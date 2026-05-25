<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GradeComputationService
{
    public function computeForTerms(array $terms): array
    {
        $allIncludedSubjects = collect();

        $terms = array_map(function (array $term) use (&$allIncludedSubjects): array {
            $grades = $this->gradesForTerm($term);
            $includedSubjects = $this->includedSubjects($grades);
            $termComputation = $this->computeWeightedAverage($includedSubjects);

            $allIncludedSubjects = $allIncludedSubjects->merge($includedSubjects);

            return array_merge($term, [
                'computed_gpa' => $termComputation['value'],
                'computed_gpa_display' => $termComputation['display'],
                'computed_counted_units' => $termComputation['units'],
                'computed_counted_units_display' => $this->formatUnits($termComputation['units']),
                'computed_included_subject_count' => $includedSubjects->count(),
                'computed_excluded_subject_count' => $grades->count() - $includedSubjects->count(),
            ]);
        }, $terms);

        $overallComputation = $this->computeWeightedAverage($allIncludedSubjects);

        return [
            'terms' => $terms,
            'overall' => [
                'average_gwa' => $overallComputation['value'],
                'average_gwa_display' => $overallComputation['display'],
                'counted_units' => $overallComputation['units'],
                'counted_units_display' => $this->formatUnits($overallComputation['units']),
                'included_subject_count' => $allIncludedSubjects->count(),
                'excluded_keywords' => $this->excludedKeywords(),
                'note' => 'NSTP, Thesis, Capstone, OJT, and Practicum subjects are excluded from GPA computation.',
            ],
        ];
    }

    public function includedSubjects(Collection $subjects): Collection
    {
        return $subjects
            ->filter(fn (array $subject): bool => $this->shouldIncludeSubject($subject))
            ->values();
    }

    public function shouldIncludeSubject(array $subject): bool
    {
        if ($this->hasExcludedKeyword($subject)) {
            return false;
        }

        if ($this->hasInvalidGradeStatus($subject)) {
            return false;
        }

        if (! $this->hasCompletedGradeStatus($subject)) {
            return false;
        }

        return $this->numericGrade($subject) !== null
            && $this->units($subject) > 0;
    }

    private function computeWeightedAverage(Collection $subjects): array
    {
        $totalWeighted = $subjects->sum(function (array $subject): float {
            return (float) $this->numericGrade($subject) * $this->units($subject);
        });
        $totalUnits = $subjects->sum(fn (array $subject): float => $this->units($subject));
        $value = $totalUnits > 0 ? round($totalWeighted / $totalUnits, 4) : 0.0;

        return [
            'value' => $value,
            'display' => number_format($value, 4),
            'units' => $totalUnits,
        ];
    }

    private function gradesForTerm(array $term): Collection
    {
        $grades = $term['grades'] ?? [];

        return collect(is_array($grades) ? $grades : [])
            ->filter(fn ($grade): bool => is_array($grade))
            ->values();
    }

    private function hasExcludedKeyword(array $subject): bool
    {
        $courseName = Str::upper(implode(' ', array_filter([
            $this->firstFilled($subject, ['courseCode', 'course_code', 'subjectCode', 'subject_code', 'code']),
            $this->firstFilled($subject, ['courseTitle', 'course_title', 'courseDescription', 'course_description', 'subjectDescription', 'subject_description', 'description', 'title']),
        ], fn ($value): bool => filled($value))));

        return collect($this->excludedKeywords())
            ->contains(fn (string $keyword): bool => str_contains($courseName, $keyword));
    }

    private function hasInvalidGradeStatus(array $subject): bool
    {
        $values = collect([
            $this->gradeValue($subject),
            $this->gradeStatusValue($subject),
        ])
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => Str::upper(trim((string) $value)));

        return $values->contains(function (string $value): bool {
            return in_array($value, ['IP', 'IN PROGRESS', 'INC', 'INCOMPLETE', 'DRP', 'DROP', 'DROPPED'], true);
        });
    }

    private function hasCompletedGradeStatus(array $subject): bool
    {
        $status = $this->gradeStatusValue($subject);

        if (blank($status)) {
            return true;
        }

        $normalized = Str::upper(trim((string) $status));

        return str_contains($normalized, 'PASS')
            || str_contains($normalized, 'COMPLETED')
            || str_contains($normalized, 'EVALUATED')
            || str_contains($normalized, 'POSTED');
    }

    private function numericGrade(array $subject): ?float
    {
        $grade = $this->gradeValue($subject);

        return is_numeric($grade) ? (float) $grade : null;
    }

    private function gradeValue(array $subject): mixed
    {
        return $this->firstFilled($subject, ['finalGrade', 'final_grade', 'grade', 'rating']);
    }

    private function gradeStatusValue(array $subject): mixed
    {
        return $this->firstFilled($subject, ['remarks', 'remark', 'status']);
    }

    private function units(array $subject): float
    {
        $units = $this->firstFilled($subject, ['unit', 'units', 'creditUnits', 'credit_units', 'credits']);

        return is_numeric($units) ? (float) $units : 0.0;
    }

    private function firstFilled(array $subject, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $subject) && filled($subject[$key])) {
                return $subject[$key];
            }
        }

        return null;
    }

    private function formatUnits(float $units): string
    {
        return number_format($units, 4);
    }

    private function excludedKeywords(): array
    {
        return collect(config('grades.gpa_excluded_keywords', []))
            ->map(fn ($keyword): string => Str::upper(trim((string) $keyword)))
            ->filter()
            ->values()
            ->all();
    }
}
