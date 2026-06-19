<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['template_id', 'scale_set_id', 'name', 'description', 'sort_order', 'status'])]
class EvaluationStatementCategory extends Model
{
    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'scale_set_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(EvaluationStatement::class, 'category_id');
    }

    public function scaleSet(): BelongsTo
    {
        return $this->belongsTo(EvaluationScaleSet::class, 'scale_set_id');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany(EvaluationScoringRule::class, 'category_id');
    }

    public function interpretationRanges(): HasMany
    {
        return $this->hasMany(EvaluationInterpretationRange::class, 'category_id');
    }
}
