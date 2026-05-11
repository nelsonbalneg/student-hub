<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'evaluation_period_id',
    'student_id',
    'student_no',
    'intent',
    'remarks',
    'status',
    'registrar_feedback',
    'evaluated_by',
    'evaluated_at',
    'done_by',
    'done_at',
])]
class EvaluationRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_EVALUATION = 'under_evaluation';
    public const STATUS_NEEDS_ACTION = 'needs_action';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EvaluationFeedback::class);
    }
}
