<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'campus_id',
    'code',
    'description',
])]
class Office extends Model
{
    use HasFactory, SoftDeletes;

    public function campus(): BelongsTo
    {
        return $this->belongsTo(SiteCampus::class, 'campus_id');
    }

    public function clearanceUpdates(): HasMany
    {
        return $this->hasMany(ClearanceUpdateOffice::class);
    }

    public function accountabilities(): HasMany
    {
        return $this->hasMany(ClearanceAccountability::class);
    }

    public function clearanceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ClearanceType::class, 'clearance_type_offices');
    }
}
