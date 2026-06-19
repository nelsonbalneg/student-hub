<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_evaluation_period_id',
    'evaluation_template_id',
    'user_id',
    'answers_json',
    'submitted_at',
])]
class SiteEvaluationSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'site_evaluation_period_id' => 'integer',
            'evaluation_template_id' => 'integer',
            'user_id' => 'integer',
            'answers_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SiteEvaluationPeriod::class, 'site_evaluation_period_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
