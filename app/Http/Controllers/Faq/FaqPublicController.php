<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use App\Http\Resources\Faq\FaqResource;
use App\Http\Resources\Faq\FaqCategoryResource;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqSearchLog;
use Inertia\Inertia;
use Illuminate\Http\Request;

class FaqPublicController extends Controller
{
    public function index(Request $request)
    {
        $query = Faq::query()
            ->where('status', 'published')
            ->with('category')
            ->orderBy('sort_order');

        // Filter by visibility based on user role
        $user = $request->user();
        if ($user) {
            if ($user->hasRole('student')) {
                $query->whereIn('visibility', ['public', 'students']);
            } elseif ($user->hasRole('employee')) {
                $query->whereIn('visibility', ['public', 'employees']);
            }
        } else {
            $query->where('visibility', 'public');
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('question', 'like', "%{$request->search}%")
                  ->orWhere('summary', 'like', "%{$request->search}%")
                  ->orWhere('keywords', 'like', "%{$request->search}%");
            });
            
            // Log search
            FaqSearchLog::create([
                'keyword' => $request->search,
                'result_count' => $query->count(),
                'user_id' => $user?->id
            ]);
        }

        if ($request->category) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        $faqs = $query->paginate(15)->withQueryString();
        
        $categories = FaqCategory::where('is_active', true)
            ->whereIn('visibility', ['public', 'students']) // Simplified for students
            ->get();

        $featuredFaqs = Faq::with('category')->where('is_featured', true)
            ->where('status', 'published')
            ->take(5)
            ->get();

        return Inertia::render('Faq/Index', [
            'faqs' => FaqResource::collection($faqs),
            'categories' => FaqCategoryResource::collection($categories),
            'featuredFaqs' => FaqResource::collection($featuredFaqs),
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(Faq $faq)
    {
        if ($faq->status !== 'published') {
            abort(404);
        }

        $faq->increment('view_count');

        $relatedFaqs = Faq::where('faq_category_id', $faq->faq_category_id)
            ->where('id', '!=', $faq->id)
            ->where('status', 'published')
            ->take(3)
            ->get();

        return Inertia::render('Faq/Show', [
            'faq' => new FaqResource($faq->load('category')),
            'relatedFaqs' => FaqResource::collection($relatedFaqs),
        ]);
    }
}
