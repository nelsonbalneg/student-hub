<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_id',
        'user_id',
        'is_helpful',
        'feedback',
    ];

    public function faq()
    {
        return $this->belongsTo(Faq::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
