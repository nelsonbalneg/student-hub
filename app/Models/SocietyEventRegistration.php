<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyEventRegistration extends Model
{
    protected $fillable = [
        'society_event_id',
        'student_id',
        'status',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SocietyEvent::class, 'society_event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}