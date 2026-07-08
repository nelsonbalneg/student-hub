<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PftParq extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'term_id',
        'q1',
        'q2',
        'q3',
        'q4',
        'q5',
        'q6',
        'q7',
        'declaration_agreed',
        'medical_clearance_path',
    ];

    protected $casts = [
        'q1' => 'boolean',
        'q2' => 'boolean',
        'q3' => 'boolean',
        'q4' => 'boolean',
        'q5' => 'boolean',
        'q6' => 'boolean',
        'q7' => 'boolean',
        'declaration_agreed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'term_id', 'term_id');
    }

    public function getRequiresClearanceAttribute(): bool
    {
        return $this->q1 || $this->q2 || $this->q3 || $this->q4 || $this->q5 || $this->q6 || $this->q7;
    }
}
