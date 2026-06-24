<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class RatingScale extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'scale_set_id' => 'integer', 'statement_id' => 'integer', 'value' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('rating_scales');
    }

    public function scaleSet(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('scale_set'), 'scale_set_id');
    }
}
