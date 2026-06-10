<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['pft_test_type_id', 'field_name', 'field_label', 'field_type', 'options', 'placeholder', 'help_text', 'is_required', 'sort_order', 'is_active'])]
class PftConfiguration extends Model
{
    use SoftDeletes;

    public function testType(): BelongsTo
    {
        return $this->belongsTo(PftTestType::class, 'pft_test_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
