<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'session_id',
    'current_url',
    'current_route',
    'current_module',
    'page_title',
    'ip_address',
    'user_agent',
    'browser',
    'device_type',
    'last_activity_at',
    'logged_in_at',
    'logged_out_at',
    'status',
])]
class UserActivitySession extends Model
{
    public const STATUS_ONLINE = 'online';

    public const STATUS_IDLE = 'idle';

    public const STATUS_OFFLINE = 'offline';

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
