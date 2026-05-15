<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyOfficer extends Model
{
    protected $fillable = [
        'society_id',
        'accreditation_request_id',
        'academic_year_id',
        'student_id',
        'student_identifier',
        'full_name',
        'year_course_section',
        'permanent_address',
        'usm_email',
        'contact_no',
        'school_year',
        'semester',
        'position',
        'rank_order',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function accreditationRequest(): BelongsTo
    {
        return $this->belongsTo(SocietyAccreditationRequest::class, 'accreditation_request_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'academic_year_id');
    }
}
