<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['pft_component_id', 'name', 'slug', 'description', 'sort_order', 'is_active'])]
class PftCategory extends Model
{
    use SoftDeletes;

    public function component(): BelongsTo
    {
        return $this->belongsTo(PftComponent::class, 'pft_component_id');
    }

    public function testTypes(): HasMany
    {
        return $this->hasMany(PftTestType::class);
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
