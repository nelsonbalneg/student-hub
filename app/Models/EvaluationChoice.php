<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['statement_id', 'choice_text', 'choice_value', 'score_value', 'sort_order'])]
class EvaluationChoice extends Model
{
    protected function casts(): array
    {
        return [
            'statement_id' => 'integer',
            'score_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatement::class, 'statement_id');
    }
}
