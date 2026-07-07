<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'term_id',
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
])]
class PftHealthQuestionnaire extends Model
{
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
        ];
    }
}
