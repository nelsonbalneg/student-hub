<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'template_id',
    'category_id',
    'min_value',
    'max_value',
    'interpretation',
    'sort_order',
    'status',
])]
class EvaluationInterpretationRange extends Model
{
    protected $attributes = [
        'sort_order' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'category_id' => 'integer',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'sort_order' => 'integer',
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
}
