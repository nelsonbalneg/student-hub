<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyMember extends Model
{
    protected $fillable = [
        'society_id',
        'accreditation_request_id',
        'user_id',
        'student_id',
        'full_name',
        'year_course_section',
        'usm_email',
        'contact_no',
        'membership_type',
        'status',
        'joined_at',
        'school_year',
        'semester',
    ];

    protected $casts = [
        'joined_at' => 'date',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function accreditationRequest(): BelongsTo
    {
        return $this->belongsTo(SocietyAccreditationRequest::class, 'accreditation_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
