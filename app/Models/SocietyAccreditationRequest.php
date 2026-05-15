<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocietyAccreditationRequest extends Model
{
    protected $fillable = [
        'society_id',
        'accreditation_request_no',
        'semester',
        'school_year',
        'mode_of_submission',
        'date_received',
        'received_checked_by',
        'submitted_by_name',
        'submitted_by_position',
        'submitted_by_signature',
        'status',
        'remarks',
        'submitted_at',
        'returned_at',
        'reopened_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'date_received' => 'date',
        'submitted_at' => 'datetime',
        'returned_at' => 'datetime',
        'reopened_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_checked_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(SocietyOfficer::class, 'accreditation_request_id');
    }

    public function advisers(): HasMany
    {
        return $this->hasMany(SocietyAdviser::class, 'accreditation_request_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SocietyMember::class, 'accreditation_request_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SocietyRequirementSubmission::class, 'accreditation_request_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SocietyAccreditationLog::class, 'accreditation_request_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'approved';
    }
}
