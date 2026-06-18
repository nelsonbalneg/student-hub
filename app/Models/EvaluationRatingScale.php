<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template_id', 'statement_id', 'value', 'label', 'interpretation', 'sort_order'])]
class EvaluationRatingScale extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatement::class, 'statement_id');
    }
}
