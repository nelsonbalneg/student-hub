<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_evaluation_period_id', 'user_id', 'skip_count', 'dismissed_at'])]
class SiteEvaluationDismissal extends Model
{
    protected $attributes = ['skip_count' => 1];

    protected function casts(): array
    {
        return [
            'site_evaluation_period_id' => 'integer',
            'user_id' => 'integer',
            'skip_count' => 'integer',
            'dismissed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SiteEvaluationPeriod::class, 'site_evaluation_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
