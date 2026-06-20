<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteCampus extends Model
{
    protected $fillable = [
        'campus_name',
        'campus_address',
        'campus_logo_path',
        'real_campus_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function academicTerms(): HasMany
    {
        return $this->hasMany(SiteAcademicTerm::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class, 'campus_id');
    }

    public function clearanceTypes(): HasMany
    {
        return $this->hasMany(ClearanceType::class, 'campus_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
