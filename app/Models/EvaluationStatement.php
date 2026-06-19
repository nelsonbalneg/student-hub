<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'template_id',
    'category_id',
    'original_item_number',
    'statement',
    'help_text',
    'statement_type',
    'is_required',
    'weight',
    'is_visible',
    'scoring_enabled',
    'is_read_only',
    'settings_json',
    'sort_order',
    'status',
])]
class EvaluationStatement extends Model
{
    protected $attributes = [
        'weight' => 0,
        'is_visible' => true,
        'scoring_enabled' => true,
        'is_read_only' => false,
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'integer',
            'category_id' => 'integer',
            'original_item_number' => 'integer',
            'is_required' => 'boolean',
            'weight' => 'decimal:2',
            'is_visible' => 'boolean',
            'scoring_enabled' => 'boolean',
            'is_read_only' => 'boolean',
            'settings_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EvaluationStatementCategory::class, 'category_id');
    }

    public function ratingScales(): HasMany
    {
        return $this->hasMany(EvaluationRatingScale::class, 'statement_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(EvaluationChoice::class, 'statement_id');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany(EvaluationScoringRule::class, 'statement_id');
    }
}
