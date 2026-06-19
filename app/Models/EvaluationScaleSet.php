<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['template_id', 'name', 'description', 'is_default', 'status', 'sort_order'])]
class EvaluationScaleSet extends Model
{
    protected $attributes = [
        'is_default' => false,
        'status' => 'active',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany(EvaluationRatingScale::class, 'scale_set_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EvaluationStatementCategory::class, 'scale_set_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(EvaluationStatement::class, 'scale_set_id');
    }
}
