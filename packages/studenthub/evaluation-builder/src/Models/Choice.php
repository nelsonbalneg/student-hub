<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class Choice extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['statement_id' => 'integer', 'score_value' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('choices');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('statement'), 'statement_id');
    }
}
