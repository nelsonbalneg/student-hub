<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyEventAttendance extends Model
{
    protected $fillable = [
        'society_event_id',
        'student_id',
        'society_id',
        'time_in',
        'time_out',
        'attendance_status',
        'remarks',
        'encoded_by',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SocietyEvent::class, 'society_event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}