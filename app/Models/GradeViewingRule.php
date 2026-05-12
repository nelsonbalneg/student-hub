<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeViewingRule extends Model
{
    protected $table = 'site_grade_viewing_rules';

    protected $fillable = [
        'site_campus_id',
        'rule_name',
        'bypass_evaluation',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bypass_evaluation' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(SiteCampus::class, 'site_campus_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GradeViewingLog::class, 'rule_id');
    }
}
