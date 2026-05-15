<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyRequirementSubmission extends Model
{
    protected $fillable = [
        'accreditation_request_id',
        'requirement_id',
        'file_path',
        'original_file_name',
        'remarks',
        'status',
        'submitted_by',
        'submitted_at',
        'checked_by',
        'checked_at',
        'resubmission_count',
        'resubmission_history',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'checked_at' => 'datetime',
        'resubmission_history' => 'array',
    ];

    public function accreditationRequest(): BelongsTo
    {
        return $this->belongsTo(SocietyAccreditationRequest::class, 'accreditation_request_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(SocietyAccreditationRequirement::class, 'requirement_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
