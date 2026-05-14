<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'student_dossier_id',
    'document_type',
    'document_code',
    'version',
    'is_required',
    'is_verified',
    'file_path',
    'original_filename',
    'mime_type',
    'file_size',
    'checksum',
    'scan_status',
    'scanned_at',
    'scan_message',
    'uploaded_by',
    'verified_by',
    'verified_at',
    'metadata',
])]
class DossierDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'metadata' => 'array',
            'scanned_at' => 'datetime',
        ];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(StudentDossier::class, 'student_dossier_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
