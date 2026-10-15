<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeViewingRule extends Model
{
    protected $table = 'site_grade_viewing_rules';

    protected $fillable = [
        'site_campus_id',
        'rule_name',
        'grade_evaluation_mode',
        'access_scope',
        'site_academic_term_id',
        'bypass_evaluation',
        'show_gwa_gpa',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bypass_evaluation' => 'boolean',
        'show_gwa_gpa' => 'boolean',
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

    public function overrideUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grade_rule_user_overrides')->withPivot('granted_by')->withTimestamps();
    }

    public function selectedAcademicTerm(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'site_academic_term_id');
    }
}
