<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'status', 'created_by', 'updated_by'])]
class EvaluationTemplate extends Model
{
    public function categories(): HasMany
    {
        return $this->hasMany(EvaluationStatementCategory::class, 'template_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(EvaluationStatement::class, 'template_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany(EvaluationRatingScale::class, 'template_id');
    }

    public function scaleSets(): HasMany
    {
        return $this->hasMany(EvaluationScaleSet::class, 'template_id');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany(EvaluationScoringRule::class, 'template_id');
    }

    public function interpretationRanges(): HasMany
    {
        return $this->hasMany(EvaluationInterpretationRange::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
