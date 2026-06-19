<?php

namespace App\Services;

use App\Models\CcdCaresEvaluationSubmission;
use App\Models\EvaluationStatement;
use App\Models\EvaluationTemplate;
use Illuminate\Support\Collection;

class CcdCaresEvaluationAnalysisService
{
    public function prepareTemplate(EvaluationTemplate $template): EvaluationTemplate
    {
        return $template->load([
            'categories' => fn ($query) => $query
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id'),
            'statements' => fn ($query) => $query
                ->where('status', 'active')
                ->where('is_visible', true)
                ->with([
                    'choices' => fn ($choiceQuery) => $choiceQuery
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'scaleSet.ratingScales' => fn ($scaleQuery) => $scaleQuery
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'ratingScales' => fn ($scaleQuery) => $scaleQuery
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('id'),
            'scaleSets' => fn ($query) => $query
                ->where('status', 'active')
                ->with([
                    'ratingScales' => fn ($scaleQuery) => $scaleQuery
                        ->where('status', 'active')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ]),
            'scoringRules' => fn ($query) => $query->where('status', 'active'),
            'interpretationRanges' => fn ($query) => $query
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);
    }

    public function submission(CcdCaresEvaluationSubmission $submission, EvaluationTemplate $template): array
    {
        return [
            'id' => $submission->id,
            'answers' => $submission->answers_json ?? [],
            'submitted_at' => $submission->submitted_at?->toDateTimeString(),
            'student' => [
                'id' => $submission->student->id,
                'name' => $submission->student->name,
                'email' => $submission->student->email,
                'student_no' => $submission->student->student_no,
                'campus_name' => $submission->student->campus_name,
            ],
            'interpretations' => $this->interpretations($submission->answers_json ?? [], $template),
        ];
    }

    public function analytics(Collection $submissions, EvaluationTemplate $template): array
    {
        $interpretations = collect();

        foreach ($submissions as $submission) {
            foreach ($this->interpretations($submission->answers_json ?? [], $template) as $result) {
                $interpretations->push($result);
            }
        }

        return [
            'total_submissions' => $submissions->count(),
            'campuses' => $submissions
                ->countBy(fn (CcdCaresEvaluationSubmission $submission): string => $submission->student->campus_name ?: 'Not specified')
                ->sortDesc()
                ->map(fn (int $count, string $campus): array => [
                    'campus' => $campus,
                    'count' => $count,
                ])
                ->values(),
            'categories' => $template->categories->map(function ($category) use ($interpretations): array {
                $results = $interpretations->where('category_id', $category->id);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'average_score' => round((float) $results->avg('score'), 2),
                    'distribution' => $results
                        ->countBy('interpretation')
                        ->sortDesc()
                        ->map(fn (int $count, string $interpretation): array => [
                            'interpretation' => $interpretation,
                            'count' => $count,
                        ])
                        ->values(),
                ];
            })->values(),
        ];
    }

    public function templatePayload(EvaluationTemplate $template): array
    {
        $defaultScaleSet = $template->scaleSets->firstWhere('is_default', true);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'categories' => $template->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'statements' => $template->statements
                    ->where('category_id', $category->id)
                    ->map(fn (EvaluationStatement $statement): array => $this->statementPayload($statement, $defaultScaleSet))
                    ->values(),
            ])->values(),
        ];
    }

    public function interpretations(array $answers, EvaluationTemplate $template): array
    {
        return $template->categories->map(function ($category) use ($answers, $template): array {
            $score = $template->statements
                ->where('category_id', $category->id)
                ->sum(function (EvaluationStatement $statement) use ($answers, $template, $category): float {
                    if (! $statement->scoring_enabled) {
                        return 0;
                    }

                    $answer = $answers[$statement->id] ?? null;
                    $baseScore = $this->answerScore($statement, $answer);
                    $rule = $template->scoringRules->firstWhere('statement_id', $statement->id)
                        ?? $template->scoringRules
                            ->where('category_id', $category->id)
                            ->firstWhere('statement_id', null);

                    return $baseScore * (float) ($rule?->multiplier ?? 1);
                });
            $range = $template->interpretationRanges
                ->where('category_id', $category->id)
                ->first(fn ($range): bool => $score >= (float) $range->min_value && $score <= (float) $range->max_value);

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'score' => round($score, 2),
                'interpretation' => $range?->interpretation ?? 'Not classified',
                'suggested_intervention' => $range?->suggested_intervention,
            ];
        })->values()->all();
    }

    private function answerScore(EvaluationStatement $statement, mixed $answer): float
    {
        if ($answer === null || $answer === '') {
            return 0;
        }

        if (in_array($statement->statement_type, ['likert', 'rating_scale', 'numeric_score'], true)) {
            return is_numeric($answer) ? (float) $answer : 0;
        }

        if (in_array($statement->statement_type, ['multiple_choice', 'yes_no'], true)) {
            return (float) ($statement->choices->firstWhere('choice_value', $answer)?->score_value ?? 0);
        }

        if ($statement->statement_type === 'checkbox' && is_array($answer)) {
            return collect($answer)->sum(
                fn ($value): float => (float) ($statement->choices->firstWhere('choice_value', $value)?->score_value ?? 0),
            );
        }

        return 0;
    }

    private function statementPayload(EvaluationStatement $statement, $defaultScaleSet): array
    {
        $scales = $statement->ratingScales;

        if ($scales->isEmpty()) {
            $scales = $statement->scaleSet?->ratingScales ?? $defaultScaleSet?->ratingScales ?? collect();
        }

        return [
            'id' => $statement->id,
            'statement' => $statement->statement,
            'help_text' => $statement->help_text,
            'statement_type' => $statement->statement_type,
            'choices' => $statement->choices->map(fn ($choice): array => [
                'id' => $choice->id,
                'label' => $choice->choice_text,
                'value' => $choice->choice_value,
                'score' => $choice->score_value === null ? null : (float) $choice->score_value,
            ])->values(),
            'scale_options' => $scales->map(fn ($scale): array => [
                'id' => $scale->id,
                'label' => $scale->label,
                'value' => (float) $scale->value,
                'interpretation' => $scale->interpretation,
            ])->values(),
        ];
    }
}
