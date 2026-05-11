<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'summary',
        'content',
        'category_id',
        'priority',
        'visibility',
        'publish_at',
        'expire_at',
        'is_pinned',
        'allow_comments',
        'send_notification',
        'status',
        'created_by',
        'updated_by',
        'published_by',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
        'expire_at' => 'datetime',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_pinned' => 'boolean',
        'allow_comments' => 'boolean',
        'send_notification' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($announcement) {
            $announcement->uuid = (string) Str::uuid();
            if (!$announcement->slug) {
                $announcement->slug = Str::slug($announcement->title) . '-' . Str::random(5);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(AnnouncementCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function attachments()
    {
        return $this->hasMany(AnnouncementAttachment::class);
    }

    public function targets()
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
            });
    }
}
