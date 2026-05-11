<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\Clearance\ClearanceAccountabilityResource;
use App\Http\Resources\Clearance\ClearanceUpdateResource;
use App\Models\ClearanceAccountability;
use App\Models\ClearanceUpdate;
use App\Models\Office;
use App\Services\ClearanceAccountabilityService;
use App\Services\ClearanceUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceAccountabilityController extends Controller
{
    public function center(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        
        // Ensure user has an office tagged if not Super Admin
        if (!$isSuperAdmin && !$user->office_id) {
            abort(403, 'You are not tagged to any office. Please contact the administrator.');
        }

        $updates = ClearanceUpdate::query()
            ->when(!$isSuperAdmin, function($query) use ($user) {
                $query->whereHas('offices', function($q) use ($user) {
                    $q->where('office_id', $user->office_id);
                });
            })
            ->with(['semester', 'type'])
            ->where('status', '!=', 'draft')
            ->latest()
            ->get();

        return Inertia::render('Clearance/Accountabilities/Center', [
            'updates' => ClearanceUpdateResource::collection($updates)->resolve(),
            'userOffice' => $isSuperAdmin ? 'System Wide (Super Admin)' : ($user->office->name ?? 'Unknown Office'),
        ]);
    }

    public function students(Request $request): array
    {
        $search = $request->search;
        if (!$search) return [];

        return User::query()
            ->whereNotNull('student_no')
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_no', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'student_no'])
            ->toArray();
    }

    public function store(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('upload', [ClearanceAccountability::class, $update]);

        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'office_id' => ['required', 'exists:offices,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['clearance_update_id'] = $update->id;

        app(ClearanceAccountabilityService::class)->createAccountability($validated);

        return back()->with('success', 'Accountability added successfully.');
    }

    public function index(Request $request, ClearanceUpdate $update): Response
    {
        $this->authorize('viewAny', [ClearanceAccountability::class, $update]);

        $accountabilities = ClearanceAccountability::query()
            ->where('clearance_update_id', $update->id)
            ->with(['student:id,name,student_no', 'office', 'uploader:id,name', 'resolver:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('student_no', 'like', "%{$search}%");
                });
            })
            ->when($request->office_id, fn($query, $id) => $query->where('office_id', $id))
            ->when($request->status, fn($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clearance/Admin/Accountabilities', [
            'update' => $update->load(['semester', 'type']),
            'accountabilities' => $this->resourcePage($accountabilities, ClearanceAccountabilityResource::class),
            'filters' => $request->only(['search', 'office_id', 'status']),
            'offices' => Office::all(['id', 'name']),
        ]);
    }

    public function resolve(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('resolve', $accountability);

        app(ClearanceAccountabilityService::class)->resolveAccountability($accountability->id, $request->remarks);

        return back()->with('success', 'Accountability resolved.');
    }

    public function waive(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('waive', $accountability);

        app(ClearanceAccountabilityService::class)->waiveAccountability($accountability->id, $request->remarks);

        return back()->with('success', 'Accountability waived.');
    }

    public function uploadPreview(Request $request, ClearanceUpdate $update): Response
    {
        $this->authorize('upload', [ClearanceAccountability::class, $update]);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'office_id' => ['required', 'exists:offices,id'],
        ]);

        $results = app(ClearanceUploadService::class)->processUpload(
            $request->file('file'),
            $update,
            $request->integer('office_id')
        );

        return Inertia::render('Clearance/Admin/UploadPreview', [
            'update' => $update,
            'office' => Office::find($request->office_id),
            'results' => $results,
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function uploadSave(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('upload', [ClearanceAccountability::class, $update]);

        $request->validate([
            'data' => ['required', 'array'],
            'office_id' => ['required', 'exists:offices,id'],
            'filename' => ['required', 'string'],
        ]);

        app(ClearanceUploadService::class)->saveAccountabilities(
            $request->data,
            $update,
            $request->integer('office_id'),
            $request->filename
        );

        return redirect()->route('admin.clearance.accountabilities.index', $update->id)
            ->with('success', 'Accountabilities imported successfully.');
    }

    /**
     * @param  class-string  $resource
     * @return array<string, mixed>
     */
    private function resourcePage(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => $resource::collection(collect($paginator->items()))->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => $paginator->linkCollection(),
        ];
    }
}
