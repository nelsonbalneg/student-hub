<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Faq\FaqCategoryResource;
use App\Models\FaqCategory;
use App\Http\Requests\Faq\StoreFaqCategoryRequest;
use App\Http\Requests\Faq\UpdateFaqCategoryRequest;
use Inertia\Inertia;
use Illuminate\Support\Str;

class FaqCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', FaqCategory::class);

        $categories = FaqCategory::query()
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->withCount('faqs')
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('Faq/Categories/Index', [
            'categories' => FaqCategoryResource::collection($categories),
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFaqCategoryRequest $request)
    {
        $this->authorize('create', FaqCategory::class);

        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['name']);
        $validated['created_by'] = $request->user()->id;

        FaqCategory::create($validated);

        return redirect()->route('faqs.manage.categories.index')->with('success', 'FAQ Category created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFaqCategoryRequest $request, FaqCategory $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validated();
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return redirect()->route('faqs.manage.categories.index')->with('success', 'FAQ Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FaqCategory $category)
    {
        $this->authorize('delete', $category);

        if ($category->faqs()->count() > 0) {
            return redirect()->route('faqs.manage.categories.index')->with('error', 'Cannot delete category with associated FAQs.');
        }

        $category->delete();

        return redirect()->route('faqs.manage.categories.index')->with('success', 'FAQ Category deleted successfully.');
    }
}
