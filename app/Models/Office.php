<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'code',
    'description',
])]
class Office extends Model
{
    use HasFactory, SoftDeletes;

    public function clearanceUpdates(): HasMany
    {
        return $this->hasMany(ClearanceUpdateOffice::class);
    }

    public function accountabilities(): HasMany
    {
        return $this->hasMany(ClearanceAccountability::class);
    }
}
