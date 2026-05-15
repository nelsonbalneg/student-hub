<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'actor_name',
    'actor_email',
    'module',
    'action',
    'description',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'route_name',
    'url',
    'method',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
