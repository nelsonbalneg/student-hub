<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationScheduleImport extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'error_messages' => 'array',
    ];

    public function examinationSchedule(): BelongsTo
    {
        return $this->belongsTo(ExaminationSchedule::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
