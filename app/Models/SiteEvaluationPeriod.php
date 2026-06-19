<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'evaluation_template_id',
    'title',
    'description',
    'start_date',
    'end_date',
    'max_skips',
    'status',
    'created_by',
    'updated_by',
])]
class SiteEvaluationPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $attributes = [
        'max_skips' => 1,
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'evaluation_template_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'max_skips' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SiteEvaluationSubmission::class);
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(SiteEvaluationDismissal::class);
    }

    public function scopeCurrentlyOpen(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today());
    }
}
