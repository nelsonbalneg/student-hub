<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Faq\FaqResource;
use App\Http\Resources\Faq\FaqCategoryResource;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Http\Requests\Faq\StoreFaqRequest;
use App\Http\Requests\Faq\UpdateFaqRequest;
use Inertia\Inertia;

class FaqController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Faq::class);

        $faqs = Faq::query()
            ->with(['category', 'creator'])
            ->when($request->search, function($query, $search) {
                $query->where('question', 'like', "%{$search}%")
                      ->orWhere('summary', 'like', "%{$search}%");
            })
            ->when($request->category_id, function($query, $categoryId) {
                $query->where('faq_category_id', $categoryId);
            })
            ->when($request->status, function($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('sort_order')
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('Faq/Admin/Index', [
            'faqs' => FaqResource::collection($faqs),
            'categories' => FaqCategoryResource::collection(FaqCategory::all()),
            'filters' => $request->only(['search', 'category_id', 'status', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Faq::class);

        return Inertia::render('Faq/Admin/Create', [
            'categories' => FaqCategoryResource::collection(FaqCategory::where('is_active', true)->get()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFaqRequest $request)
    {
        $this->authorize('create', Faq::class);

        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        
        if ($validated['status'] === 'published' && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        Faq::create($validated);

        return redirect()->route('faqs.manage.faqs.index')->with('success', 'FAQ created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Faq $faq)
    {
        $this->authorize('update', $faq);

        return Inertia::render('Faq/Admin/Edit', [
            'faq' => new FaqResource($faq->load('category')),
            'categories' => FaqCategoryResource::collection(FaqCategory::where('is_active', true)->get()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFaqRequest $request, Faq $faq)
    {
        $this->authorize('update', $faq);

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        if ($validated['status'] === 'published' && !$faq->published_at) {
            $validated['published_at'] = now();
        }

        $faq->update($validated);

        return redirect()->route('faqs.manage.faqs.index')->with('success', 'FAQ updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Faq $faq)
    {
        $this->authorize('delete', $faq);

        $faq->delete();

        return redirect()->route('faqs.manage.faqs.index')->with('success', 'FAQ deleted successfully.');
    }
}
