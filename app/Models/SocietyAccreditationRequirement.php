<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocietyAccreditationRequirement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'is_required',
        'is_active',
        'sort_order',
        'applies_to',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(SocietyRequirementSubmission::class, 'requirement_id');
    }
}
