<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class ScaleSet extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected $attributes = ['is_default' => false, 'status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'is_default' => 'boolean', 'sort_order' => 'integer'];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('scale_sets');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('template'), 'template_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany($this->modelClass('rating_scale'), 'scale_set_id');
    }
}
