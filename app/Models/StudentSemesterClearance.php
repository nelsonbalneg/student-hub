<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'clearance_update_id',
    'semester_id',
    'student_id',
    'reference_no',
    'status',
    'applied_at',
    'cleared_at',
    'completed_at',
    'remarks',
])]
class StudentSemesterClearance extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_CLEARED = 'cleared';
    public const STATUS_NOT_CLEARED = 'not_cleared';
    public const STATUS_WITH_ACCOUNTABILITY = 'with_accountability';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_COMPLETED = 'completed';

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'cleared_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function clearanceUpdate(): BelongsTo
    {
        return $this->belongsTo(ClearanceUpdate::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ClearanceLog::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(ClearanceCertificate::class);
    }
}
