<?php

namespace App\Services;

use App\Models\EvaluationStatement;
use App\Models\EvaluationTemplate;

class EvaluationTemplatePayloadService
{
    public function build(EvaluationTemplate $template): array
    {
        $template->load([
            'categories' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('id'),
            'statements' => fn ($query) => $query
                ->where('status', 'active')
                ->where('is_visible', true)
                ->with([
                    'choices' => fn ($choiceQuery) => $choiceQuery->orderBy('sort_order')->orderBy('id'),
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
        ]);

        $scaleSets = $template->scaleSets->keyBy('id');
        $defaultScaleSet = $template->scaleSets->firstWhere('is_default', true);
        $categories = $template->categories->map(function ($category) use ($template, $scaleSets, $defaultScaleSet): array {
            $statements = $template->statements
                ->where('category_id', $category->id)
                ->map(fn (EvaluationStatement $statement): array => $this->statement(
                    $statement,
                    $scaleSets->get($statement->scale_set_id)
                        ?? $scaleSets->get($category->scale_set_id)
                        ?? $defaultScaleSet,
                ))
                ->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'statements' => $statements,
            ];
        });
        $uncategorized = $template->statements
            ->whereNull('category_id')
            ->map(fn (EvaluationStatement $statement): array => $this->statement(
                $statement,
                $scaleSets->get($statement->scale_set_id) ?? $defaultScaleSet,
            ))
            ->values();

        if ($uncategorized->isNotEmpty()) {
            $categories->push([
                'id' => null,
                'name' => 'General Questions',
                'description' => null,
                'statements' => $uncategorized,
            ]);
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'categories' => $categories->values(),
        ];
    }

    private function statement(EvaluationStatement $statement, $scaleSet): array
    {
        $scaleOptions = $scaleSet?->ratingScales->map(fn ($scale): array => [
            'id' => $scale->id,
            'label' => $scale->label,
            'value' => (float) $scale->value,
        ])->values() ?? collect();

        if ($scaleOptions->isEmpty() && in_array($statement->statement_type, ['likert', 'rating_scale'], true)) {
            $minimum = (int) ($statement->settings_json['min_value'] ?? 1);
            $maximum = (int) ($statement->settings_json['max_value'] ?? 5);
            $labels = collect(explode(',', (string) ($statement->settings_json['labels'] ?? '')))
                ->map(fn (string $label): string => trim($label))
                ->filter()
                ->values();

            $scaleOptions = collect(range($minimum, $maximum))->map(fn (int $value, int $index): array => [
                'id' => 'fallback-'.$statement->id.'-'.$value,
                'label' => $labels->get($index, (string) $value),
                'value' => $value,
            ]);
        }

        return [
            'id' => $statement->id,
            'statement' => $statement->statement,
            'help_text' => $statement->help_text,
            'statement_type' => $statement->statement_type,
            'is_required' => $statement->is_required,
            'settings_json' => $statement->settings_json,
            'choices' => $statement->choices->map(fn ($choice): array => [
                'id' => $choice->id,
                'label' => $choice->choice_text,
                'value' => $choice->choice_value,
            ])->values(),
            'scale_options' => $scaleOptions->values(),
        ];
    }
}
