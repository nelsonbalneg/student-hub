<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyAccreditationLog extends Model
{
    protected $fillable = [
        'society_id',
        'accreditation_request_id',
        'user_id',
        'action',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
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
