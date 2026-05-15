<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Society extends Model
{
    protected $fillable = [
        'name',
        'full_name',
        'acronym',
        'abbreviation',
        'category',
        'society_type',
        'college',
        'college_unit',
        'campus',
        'description',
        'facebook_page_url',
        'status',
        'created_by',
        'adviser_name',
        'adviser_email',
        'president_name',
        'contact_email',
        'contact_number',
        'mission',
        'vision',
        'objectives',
        'logo_path',
        'membership_requirements',
    ];

    public function accreditations(): HasMany
    {
        return $this->hasMany(SocietyAccreditation::class);
    }

    public function accreditationRequests(): HasMany
    {
        return $this->hasMany(SocietyAccreditationRequest::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(SocietyOfficer::class);
    }

    public function advisers(): HasMany
    {
        return $this->hasMany(SocietyAdviser::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SocietyMember::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SocietyMembership::class);
    }

    public function bylaws(): HasMany
    {
        return $this->hasMany(SocietyBylaw::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(SocietyAnnouncement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SocietyEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SocietyDocument::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SocietySetting::class);
    }
}
