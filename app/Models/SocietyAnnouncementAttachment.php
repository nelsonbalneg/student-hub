<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyAnnouncementAttachment extends Model
{
    protected $fillable = [
        'society_announcement_id',
        'file_path',
        'file_name',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(SocietyAnnouncement::class, 'society_announcement_id');
    }
}