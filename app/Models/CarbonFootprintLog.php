<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'session_id',
    'module',
    'route_name',
    'url',
    'page_title',
    'estimated_data_kb',
    'estimated_energy_kwh',
    'estimated_co2e_grams',
    'duration_seconds',
    'device_type',
    'ip_address',
    'user_agent',
])]
class CarbonFootprintLog extends Model
{
    protected function casts(): array
    {
        return [
            'estimated_data_kb' => 'decimal:2',
            'estimated_energy_kwh' => 'decimal:8',
            'estimated_co2e_grams' => 'decimal:6',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
