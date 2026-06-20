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
    'description',
    'audience',
    'campus_id',
])]
class ClearanceType extends Model
{
    use HasFactory, SoftDeletes;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_INDIVIDUAL = 'individual';

    public function campus(): BelongsTo
    {
        return $this->belongsTo(SiteCampus::class, 'campus_id');
    }

    public function clearanceUpdates(): HasMany
    {
        return $this->hasMany(ClearanceUpdate::class);
    }

    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'clearance_type_offices');
    }
}
