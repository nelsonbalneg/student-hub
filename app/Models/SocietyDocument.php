<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyDocument extends Model
{
    protected $fillable = [
        'society_id',
        'title',
        'file_path',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }
}