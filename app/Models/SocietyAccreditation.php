<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyAccreditation extends Model
{
    protected $fillable = [
        'society_id',
        'academic_year_id',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'valid_from',
        'valid_until',
        'remarks',
        'certificate_path',
        'constitution_bylaws_path',
        'list_of_officers_path',
        'supporting_documents_path',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'academic_year_id');
    }
}