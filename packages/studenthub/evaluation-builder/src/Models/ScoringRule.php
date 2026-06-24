<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class ScoringRule extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected $attributes = ['formula_type' => 'sum', 'multiplier' => 1, 'status' => 'active'];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'category_id' => 'integer', 'statement_id' => 'integer', 'multiplier' => 'decimal:2'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('scoring_rules');
    }
}
