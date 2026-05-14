<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'student_id',
    'dossier_number',
    'transaction_type',
    'status',
    'priority',
    'current_owner_id',
    'intake_date',
    'completion_due_at',
    'released_at',
    'archived_at',
    'created_by',
    'updated_by',
    'approved_by',
    'approved_at',
    'approval_remarks',
])]
class StudentDossier extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FOR_INTAKE_REVIEW = 'for_intake_review';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FOR_SUPERVISOR_APPROVAL = 'for_supervisor_approval';
    public const STATUS_RELEASED = 'released';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_ON_HOLD = 'on_hold';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_FOR_INTAKE_REVIEW],
            self::STATUS_FOR_INTAKE_REVIEW => [self::STATUS_ACTIVE, self::STATUS_INCOMPLETE],
            self::STATUS_INCOMPLETE => [self::STATUS_FOR_INTAKE_REVIEW],
            self::STATUS_ACTIVE => [self::STATUS_FOR_SUPERVISOR_APPROVAL, self::STATUS_ON_HOLD],
            self::STATUS_ON_HOLD => [self::STATUS_ACTIVE],
            self::STATUS_FOR_SUPERVISOR_APPROVAL => [self::STATUS_RELEASED, self::STATUS_ON_HOLD],
            self::STATUS_RELEASED => [self::STATUS_ARCHIVED],
            self::STATUS_ARCHIVED => [],
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_keys(self::allowedTransitions());
    }

    /**
     * @return list<string>
     */
    public static function priorities(): array
    {
        return [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];
    }

    protected function casts(): array
    {
        return [
            'intake_date' => 'date',
            'completion_due_at' => 'datetime',
            'released_at' => 'datetime',
            'archived_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DossierDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DossierStatusHistory::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DossierAssignment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DossierNote::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DossierAccessLog::class);
    }
}
