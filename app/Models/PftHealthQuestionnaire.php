<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'term_id',
    'test_phase',
    'civil_status',
    'household_monthly_income',
    'father_occupation',
    'mother_occupation',
    'has_medical_condition',
    'medical_condition_details',
    'has_medication',
    'medication_details',
    'smoking_status',
    'alcohol_consumption',
    'specific_conditions',
    'other_condition',
    'medical_clearance_path',
    'clearance_granted',
    'clearance_granted_by',
    'clearance_granted_at',
])]
class PftHealthQuestionnaire extends Model
{
    protected $attributes = ['test_phase' => 'pre'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_medical_condition' => 'boolean',
            'has_medication' => 'boolean',
            'specific_conditions' => 'array',
            'clearance_granted' => 'boolean',
            'clearance_granted_at' => 'datetime',
        ];
    }
}
