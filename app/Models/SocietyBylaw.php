<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyBylaw extends Model
{
    protected $fillable = [
        'society_id',
        'academic_year_id',
        'title',
        'version',
        'file_path',
        'status',
        'submitted_by',
        'reviewed_by',
        'remarks',
        'effective_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}