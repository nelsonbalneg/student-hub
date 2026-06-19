<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ccd_cares_evaluation_period_id',
    'evaluation_template_id',
    'student_id',
    'answers_json',
    'submitted_at',
])]
class CcdCaresEvaluationSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'ccd_cares_evaluation_period_id' => 'integer',
            'evaluation_template_id' => 'integer',
            'student_id' => 'integer',
            'answers_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(CcdCaresEvaluationPeriod::class, 'ccd_cares_evaluation_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function getInterpretationResults(): array
    {
        $template = $this->template;
        if (!$template) {
            return [];
        }

        // Load categories, statements, choices, scoring rules, and interpretation ranges
        $template->load([
            'categories' => fn ($query) => $query->where('status', 'active'),
            'statements' => fn ($query) => $query->where('status', 'active')->with('choices'),
            'scoringRules' => fn ($query) => $query->where('status', 'active'),
            'interpretationRanges' => fn ($query) => $query->where('status', 'active'),
        ]);

        $answers = $this->answers_json ?? [];
        $results = [];

        foreach ($template->categories as $category) {
            $categoryStatements = $template->statements->where('category_id', $category->id);
            $categoryScore = 0;

            foreach ($categoryStatements as $statement) {
                // Get answer value for this statement
                $answerVal = $answers[$statement->id] ?? null;
                if ($answerVal === null) {
                    continue;
                }

                $statementScore = 0;

                // Calculate base score based on statement type
                if (in_array($statement->statement_type, ['likert', 'rating_scale', 'numeric_score'], true)) {
                    $statementScore = (float) $answerVal;
                } elseif (in_array($statement->statement_type, ['multiple_choice', 'yes_no'], true)) {
                    $choice = $statement->choices->firstWhere('choice_value', $answerVal);
                    $statementScore = $choice ? (float) $choice->score_value : 0;
                } elseif ($statement->statement_type === 'checkbox') {
                    if (is_array($answerVal)) {
                        foreach ($answerVal as $val) {
                            $choice = $statement->choices->firstWhere('choice_value', $val);
                            $statementScore += $choice ? (float) $choice->score_value : 0;
                        }
                    }
                }

                // Apply scoring rules
                $scoringRule = $template->scoringRules
                    ->where('statement_id', $statement->id)
                    ->first()
                    ?? $template->scoringRules
                        ->where('category_id', $category->id)
                        ->whereNull('statement_id')
                        ->first();

                $multiplier = $scoringRule ? (float) $scoringRule->multiplier : 1.0;
                $categoryScore += $statementScore * $multiplier;
            }

            // Find matching interpretation range
            $matchedRange = $template->interpretationRanges
                ->where('category_id', $category->id)
                ->filter(fn ($range) => $categoryScore >= (float) $range->min_value && $categoryScore <= (float) $range->max_value)
                ->first();

            $results[] = [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'score' => $categoryScore,
                'interpretation' => $matchedRange ? $matchedRange->interpretation : 'N/A',
                'suggested_intervention' => $matchedRange ? $matchedRange->suggested_intervention : null,
            ];
        }

        return $results;
    }
}
