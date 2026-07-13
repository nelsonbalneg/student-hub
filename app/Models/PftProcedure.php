<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['pft_test_type_id', 'step_no', 'description', 'is_active'])]
class PftProcedure extends Model
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
            'is_active' => 'boolean',
            'step_no' => 'integer',
        ];
    }
}
