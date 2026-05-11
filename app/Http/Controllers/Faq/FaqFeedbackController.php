<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqFeedback;
use Illuminate\Http\Request;

class FaqFeedbackController extends Controller
{
    public function store(Request $request, Faq $faq)
    {
        $request->validate([
            'is_helpful' => 'required|boolean',
            'feedback' => 'nullable|string|max:500',
        ]);

        FaqFeedback::create([
            'faq_id' => $faq->id,
            'user_id' => $request->user()?->id,
            'is_helpful' => $request->is_helpful,
            'feedback' => $request->feedback,
        ]);

        if ($request->is_helpful) {
            $faq->increment('helpful_count');
        } else {
            $faq->increment('not_helpful_count');
        }

        return back()->with('success', 'Thank you for your feedback!');
    }
}
