<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['template_id', 'name', 'description', 'sort_order', 'status'])]
class EvaluationStatementCategory extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(EvaluationStatement::class, 'category_id');
    }
}
