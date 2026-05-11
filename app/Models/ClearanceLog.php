<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_semester_clearance_id',
    'clearance_update_id',
    'student_id',
    'office_id',
    'action',
    'old_status',
    'new_status',
    'remarks',
    'performed_by',
])]
class ClearanceLog extends Model
{
    use HasFactory;

    public function studentClearance(): BelongsTo
    {
        return $this->belongsTo(StudentSemesterClearance::class, 'student_semester_clearance_id');
    }

    public function clearanceUpdate(): BelongsTo
    {
        return $this->belongsTo(ClearanceUpdate::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
