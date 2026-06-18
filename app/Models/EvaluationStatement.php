<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['template_id', 'category_id', 'statement', 'statement_type', 'is_required', 'sort_order', 'status'])]
class EvaluationStatement extends Model
{
    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'sort_order' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatementCategory::class, 'category_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany(EvaluationRatingScale::class, 'statement_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(EvaluationChoice::class, 'statement_id');
    }
}
