<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class InterpretationRange extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected $attributes = ['sort_order' => 0, 'status' => 'active'];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'category_id' => 'integer', 'min_value' => 'decimal:2', 'max_value' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('interpretation_ranges');
    }
}
