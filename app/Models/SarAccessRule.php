<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SarAccessRule extends Model
{
    protected $fillable = ['site_academic_term_id', 'is_enabled', 'updated_by'];

    protected $casts = ['is_enabled' => 'boolean', 'has_user_override' => 'boolean'];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(SiteAcademicTerm::class, 'site_academic_term_id');
    }

    public function overrideUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sar_rule_user_overrides')->withPivot('granted_by')->withTimestamps();
    }
}
