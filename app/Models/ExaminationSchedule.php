<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExaminationSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'examination_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'academic_term_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(SiteCampus::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ExaminationScheduleImport::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExaminationScheduleSubject::class);
    }
}
