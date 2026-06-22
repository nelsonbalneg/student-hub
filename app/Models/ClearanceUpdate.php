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
    'reference_code',
    'semester_id',
    'clearance_type_id',
    'title',
    'description',
    'purpose',
    'start_date',
    'end_date',
    'status',
    'created_by',
    'published_at',
    'closed_at',
])]
class ClearanceUpdate extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ClearanceType::class, 'clearance_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function offices(): HasMany
    {
        return $this->hasMany(ClearanceUpdateOffice::class);
    }

    public function studentClearances(): HasMany
    {
        return $this->hasMany(StudentSemesterClearance::class);
    }

    public function applications(): HasMany
    {
        return $this->studentClearances();
    }

    public function accountabilities(): HasMany
    {
        return $this->hasMany(ClearanceAccountability::class);
    }

    public function targetedStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clearance_update_students', 'clearance_update_id', 'student_id')
            ->withTimestamps();
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(ClearanceAccountabilityUpload::class);
    }
}
