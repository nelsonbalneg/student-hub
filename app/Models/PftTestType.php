<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['pft_category_id', 'name', 'slug', 'description', 'unit', 'sort_order', 'is_active'])]
class PftTestType extends Model
{
    use SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(PftCategory::class, 'pft_category_id');
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(PftConfiguration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentPftResult::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
