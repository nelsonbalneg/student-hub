<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocietyEvent extends Model
{
    protected $fillable = [
        'society_id',
        'title',
        'description',
        'venue',
        'start_date',
        'end_date',
        'event_type',
        'target_audience',
        'capacity',
        'registration_required',
        'attendance_required',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_required' => 'boolean',
        'attendance_required' => 'boolean',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(SocietyEventRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SocietyEventAttendance::class);
    }
}