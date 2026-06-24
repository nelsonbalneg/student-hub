<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class Statement extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    protected $attributes = ['weight' => 0, 'is_visible' => true, 'scoring_enabled' => true, 'is_read_only' => false];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer', 'category_id' => 'integer', 'scale_set_id' => 'integer',
            'original_item_number' => 'integer', 'is_required' => 'boolean', 'weight' => 'decimal:2',
            'is_visible' => 'boolean', 'scoring_enabled' => 'boolean', 'is_read_only' => 'boolean',
            'settings_json' => 'array', 'sort_order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return $this->evaluationTable('statements');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('template'), 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('category'), 'category_id');
    }

    public function scaleSet(): BelongsTo
    {
        return $this->belongsTo($this->modelClass('scale_set'), 'scale_set_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany($this->modelClass('choice'), 'statement_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany($this->modelClass('rating_scale'), 'statement_id');
    }
}
