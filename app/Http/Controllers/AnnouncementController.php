<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Services\AnnouncementService;
use App\Jobs\SendAnnouncementNotifications;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class AnnouncementController extends Controller
{
    protected $service;

    public function __construct(AnnouncementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();
        $isSuperAdmin = $this->isSuperAdmin($user);

        $query = Announcement::with(['category', 'creator'])
            ->withCount('attachments');

        if ($request->search) {
            $query->where('title', 'like', "%{$request->search}%")
                ->orWhere('summary', 'like', "%{$request->search}%");
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($isSuperAdmin && $request->status) {
            $query->where('status', $request->status);
        }

        if ($isSuperAdmin && $request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->sort_by) {
            $query->orderBy($request->sort_by, $request->sort_order ?? 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return Inertia::render('Announcements/Index', [
            'announcements' => $this->transformPaginator($query->paginate(10)->withQueryString(), $isSuperAdmin),
            'categories' => AnnouncementCategory::where('is_active', true)->get(),
            'filters' => $request->only(['search', 'category_id', 'status', 'priority']),
            'isSuperAdmin' => $isSuperAdmin,
            'can' => $this->announcementPermissions($user),
        ]);
    }

    protected function transformPaginator(LengthAwarePaginator $paginator, bool $includeAdminMetadata): array
    {
        return [
            'data' => collect($paginator->items())
                ->map(fn (Announcement $announcement): array => $this->transformAnnouncement($announcement, $includeAdminMetadata))
                ->all(),
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

    public function create()
    {
        $this->authorize('create', Announcement::class);

        return Inertia::render('Announcements/Create', [
            'categories' => AnnouncementCategory::where('is_active', true)->get(),
            'roles' => Role::all(),
            // In a real app, these would come from lookup tables
            'offices' => [], 
            'departments' => [],
            'campuses' => [],
        ]);
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $announcement = $this->service->createAnnouncement($request->validated(), $request->user());

        return redirect()->route('announcements.index')
            ->with('success', 'Announcement created successfully.');
    }

    public function show(Announcement $announcement)
    {
        $this->authorize('view', $announcement);

        $user = request()->user();
        $isSuperAdmin = $this->isSuperAdmin($user);

        $announcement->load(
            $isSuperAdmin
                ? ['category', 'creator', 'updater', 'publisher', 'attachments', 'targets.role']
                : ['category', 'attachments']
        );

        return Inertia::render('Announcements/Show', [
            'announcement' => $this->transformAnnouncementDetails($announcement, $isSuperAdmin),
            'isSuperAdmin' => $isSuperAdmin,
            'can' => $this->announcementPermissions($user),
        ]);
    }

    public function edit(Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        return Inertia::render('Announcements/Edit', [
            'announcement' => $announcement->load(['category', 'attachments', 'targets']),
            'categories' => AnnouncementCategory::where('is_active', true)->get(),
            'roles' => Role::all(),
        ]);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $this->service->updateAnnouncement($announcement, $request->validated(), $request->user());

        return redirect()->route('announcements.index')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        $this->service->logActivity(auth()->user(), 'Deleted', "Deleted announcement: {$announcement->title}", $announcement);

        return redirect()->route('announcements.index')
            ->with('success', 'Announcement deleted successfully.');
    }

    public function publish(Announcement $announcement)
    {
        $this->authorize('publish', $announcement);

        $announcement->update([
            'status' => 'published',
            'published_by' => auth()->id(),
            'published_at' => now(),
        ]);

        if ($announcement->send_notification) {
            SendAnnouncementNotifications::dispatch($announcement);
        }

        $this->service->logActivity(auth()->user(), 'Published', "Published announcement: {$announcement->title}", $announcement);

        return back()->with('success', 'Announcement published successfully.');
    }

    public function archive(Announcement $announcement)
    {
        $this->authorize('archive', $announcement);

        $announcement->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $this->service->logActivity(auth()->user(), 'Archived', "Archived announcement: {$announcement->title}", $announcement);

        return back()->with('success', 'Announcement archived successfully.');
    }

    private function transformAnnouncement(Announcement $announcement, bool $includeAdminMetadata): array
    {
        $data = [
            'id' => $announcement->id,
            'uuid' => $announcement->uuid,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'category' => $announcement->category,
            'is_pinned' => $announcement->is_pinned,
            'published_at' => $announcement->published_at,
            'publish_at' => $announcement->publish_at,
            'created_at' => $announcement->created_at,
            'publication_date' => $announcement->published_at ?? $announcement->publish_at ?? $announcement->created_at,
            'attachments_count' => $announcement->attachments_count,
        ];

        if (! $includeAdminMetadata) {
            return $data;
        }

        return [
            ...$data,
            'priority' => $announcement->priority,
            'visibility' => $announcement->visibility,
            'status' => $announcement->status,
            'creator' => $announcement->creator,
        ];
    }

    private function transformAnnouncementDetails(Announcement $announcement, bool $includeAdminMetadata): array
    {
        $data = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'content' => $announcement->content,
            'is_pinned' => $announcement->is_pinned,
            'published_at' => $announcement->published_at,
            'publish_at' => $announcement->publish_at,
            'created_at' => $announcement->created_at,
            'publication_date' => $announcement->published_at ?? $announcement->publish_at ?? $announcement->created_at,
            'category' => $announcement->category,
            'attachments' => $announcement->attachments,
        ];

        if (! $includeAdminMetadata) {
            return $data;
        }

        return [
            ...$data,
            'priority' => $announcement->priority,
            'visibility' => $announcement->visibility,
            'status' => $announcement->status,
            'creator' => $announcement->creator,
            'targets' => $announcement->targets,
        ];
    }

    private function announcementPermissions($user): array
    {
        $isSuperAdmin = $this->isSuperAdmin($user);

        return [
            'createAnnouncements' => $user->can('create', Announcement::class),
            'editAnnouncements' => $isSuperAdmin && $this->hasAnnouncementPermission($user, 'edit'),
            'publishAnnouncements' => $isSuperAdmin && $this->hasAnnouncementPermission($user, 'publish'),
            'archiveAnnouncements' => $isSuperAdmin && $this->hasAnnouncementPermission($user, 'archive'),
            'deleteAnnouncements' => $isSuperAdmin && $this->hasAnnouncementPermission($user, 'delete'),
        ];
    }

    private function isSuperAdmin($user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('Super Admin');
    }

    private function hasAnnouncementPermission($user, string $action): bool
    {
        return $user->can("announcement.{$action}") || $user->can("announcements.{$action}");
    }
}
