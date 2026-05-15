<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyAdviser extends Model
{
    protected $fillable = [
        'society_id',
        'accreditation_request_id',
        'full_name',
        'college_unit',
        'usm_email',
        'signature',
        'commitment_form_accepted',
        'commitment_date',
        'commitment_acknowledgements',
        'school_year',
        'semester',
    ];

    protected $casts = [
        'commitment_form_accepted' => 'boolean',
        'commitment_date' => 'date',
        'commitment_acknowledgements' => 'array',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function accreditationRequest(): BelongsTo
    {
        return $this->belongsTo(SocietyAccreditationRequest::class, 'accreditation_request_id');
    }
}
