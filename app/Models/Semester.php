<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'external_id',
    'campus_id',
    'campus_name',
    'academic_year',
    'term',
    'start_date',
    'end_date',
    'is_active',
])]
class Semester extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function clearanceUpdates(): HasMany
    {
        return $this->hasMany(ClearanceUpdate::class);
    }

    public function studentClearances(): HasMany
    {
        return $this->hasMany(StudentSemesterClearance::class);
    }
}
