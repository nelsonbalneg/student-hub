<?php

namespace StudentHub\EvaluationBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use StudentHub\EvaluationBuilder\Models\Concerns\UsesEvaluationTables;

class Template extends Model
{
    use UsesEvaluationTables;

    protected $guarded = [];

    public function getTable(): string
    {
        return $this->evaluationTable('templates');
    }

    public function categories(): HasMany
    {
        return $this->hasMany($this->modelClass('category'), 'template_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany($this->modelClass('statement'), 'template_id');
    }

    public function scaleSets(): HasMany
    {
        return $this->hasMany($this->modelClass('scale_set'), 'template_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany($this->modelClass('rating_scale'), 'template_id');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany($this->modelClass('scoring_rule'), 'template_id');
    }

    public function interpretationRanges(): HasMany
    {
        return $this->hasMany($this->modelClass('interpretation_range'), 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('evaluation-builder.user_model'), 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('evaluation-builder.user_model'), 'updated_by');
    }
}
