<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'faq_category_id',
        'question',
        'answer',
        'summary',
        'tags',
        'keywords',
        'is_featured',
        'status',
        'visibility',
        'sort_order',
        'view_count',
        'helpful_count',
        'not_helpful_count',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'keywords' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function feedback()
    {
        return $this->hasMany(FaqFeedback::class);
    }
}
