<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
    /** @use HasFactory<\Database\Factories\FaqCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'visibility',
        'is_active',
        'created_by',
    ];

    public function faqs()
    {
        return $this->hasMany(Faq::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
