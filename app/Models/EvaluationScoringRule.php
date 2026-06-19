<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template_id', 'category_id', 'statement_id', 'formula_type', 'multiplier', 'status'])]
class EvaluationScoringRule extends Model
{
    protected $attributes = [
        'formula_type' => 'sum',
        'multiplier' => 1,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'category_id' => 'integer',
            'statement_id' => 'integer',
            'multiplier' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatementCategory::class, 'category_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatement::class, 'statement_id');
    }
}
