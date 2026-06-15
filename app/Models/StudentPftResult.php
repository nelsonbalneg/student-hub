<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'pft_test_type_id',
    'term_id',
    'college_id',
    'campus_id',
    'year_level_id',
    'section_id',
    'section_name',
    'tenant_id',
    'status',
    'results_json',
    'remarks',
    'tested_at',
    'sex',
    'classification',
    'interpretation',
    'suggested_intervention',
    'color_class',
    'created_by',
    'updated_by',
])]
class StudentPftResult extends Model
{
    protected $table = 'student_pft_results';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testType(): BelongsTo
    {
        return $this->belongsTo(PftTestType::class, 'pft_test_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'results_json' => 'array',
            'tested_at' => 'date',
        ];
    }
}
