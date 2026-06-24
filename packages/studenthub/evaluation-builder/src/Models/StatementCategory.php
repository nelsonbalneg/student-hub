<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class StatementCategory extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'scale_set_id' => 'integer', 'sort_order' => 'integer'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('statement_categories');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('template'), 'template_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany($this->modelClass('statement'), 'category_id');
    }

    public function scaleSet(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('scale_set'), 'scale_set_id');
    }

    public function interpretationRanges(): HasMany
    {
        return $this->hasMany($this->modelClass('interpretation_range'), 'category_id');
    }
}
