<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeViewingLog extends Model
{
    protected $table = 'site_grade_viewing_logs';

    protected $fillable = [
        'rule_id',
        'user_id',
        'action',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'json',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(GradeViewingRule::class, 'rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
