<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template_id', 'scale_set_id', 'statement_id', 'value', 'label', 'interpretation', 'sort_order', 'status'])]
class EvaluationRatingScale extends Model
{
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'scale_set_id' => 'integer',
            'statement_id' => 'integer',
            'value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatement::class, 'statement_id');
    }

    public function scaleSet(): BelongsTo
    {
        return $this->belongsTo(EvaluationScaleSet::class, 'scale_set_id');
    }
}
