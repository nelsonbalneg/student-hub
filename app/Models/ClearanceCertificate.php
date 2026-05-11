<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_semester_clearance_id',
    'certificate_no',
    'verification_hash',
    'pdf_path',
    'issued_at',
    'issued_by',
])]
class ClearanceCertificate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function studentClearance(): BelongsTo
    {
        return $this->belongsTo(StudentSemesterClearance::class, 'student_semester_clearance_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
