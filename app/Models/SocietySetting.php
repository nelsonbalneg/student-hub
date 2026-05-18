<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietySetting extends Model
{
    protected $fillable = [
        'society_id',
        'key',
        'value',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }
}