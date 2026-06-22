<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AnnouncementCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('announcement.manage-categories');

        $categories = AnnouncementCategory::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate(10);

        return Inertia::render('Announcements/Categories/Index', [
            'categories' => $this->transformPaginator($categories),
        ]);
    }

    protected function transformPaginator($paginator)
    {
        return [
            'data' => $paginator->items(),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('announcement.manage-categories');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:announcement_categories,name',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        AnnouncementCategory::create($validated);

        return to_route('announcements.categories.index')->with('success', 'Category created successfully.');
    }

    public function update(Request $request, AnnouncementCategory $category)
    {
        $this->authorize('announcement.manage-categories');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:announcement_categories,name,' . $category->id,
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return to_route('announcements.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(AnnouncementCategory $category)
    {
        $this->authorize('announcement.manage-categories');

        if ($category->announcements()->count() > 0) {
            return to_route('announcements.categories.index')->with('error', 'Cannot delete category with associated announcements.');
        }

        $category->delete();

        return to_route('announcements.categories.index')->with('success', 'Category deleted successfully.');
    }
}
