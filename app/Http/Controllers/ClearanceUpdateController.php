<?php

namespace App\Http\Controllers;

use App\Http\Resources\Clearance\ClearanceUpdateResource;
use App\Models\ClearanceType;
use App\Models\ClearanceLog;
use App\Models\ClearanceUpdate;
use App\Models\ClearanceUpdateOffice;
use App\Models\Office;
use App\Models\Semester;
use App\Services\GetActiveSem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceUpdateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ClearanceUpdate::class);

        $updates = ClearanceUpdate::query()
            ->with(['semester', 'type', 'creator'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->semester_id, fn($query, $id) => $query->where('semester_id', $id))
            ->when($request->status, fn($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Clearance/Updates/Index', [
            'updates' => $this->resourcePage($updates, ClearanceUpdateResource::class),
            'filters' => $request->only(['search', 'semester_id', 'status']),
            'semesters' => Semester::orderByDesc('academic_year')->orderByDesc('term')->get(['id', 'academic_year', 'term', 'campus_name']),
            'types' => ClearanceType::all(['id', 'name']),
            'activeSemester' => GetActiveSem::current(),
            'can' => [
                'create' => $request->user()->can('clearance-update.create'),
                'edit' => $request->user()->can('clearance-update.edit'),
                'publish' => $request->user()->can('clearance-update.publish'),
                'delete' => $request->user()->can('clearance-update.delete'),
            ]
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ClearanceUpdate::class);

        $validated = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'clearance_type_id' => ['required', 'exists:clearance_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        ClearanceUpdate::create([
            ...$validated,
            'status' => ClearanceUpdate::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Clearance update created as draft.');
    }

    public function show(ClearanceUpdate $update): Response
    {
        $this->authorize('view', $update);

        $update->load(['semester', 'type', 'creator', 'offices.office']);

        $logs = ClearanceLog::where('clearance_update_id', $update->id)
            ->with(['performer', 'office'])
            ->latest()
            ->get();

        return Inertia::render('Clearance/Updates/Show', [
            'update' => (new ClearanceUpdateResource($update))->resolve(),
            'logs' => $logs,
            'can' => [
                'publish' => auth()->user()->can('clearance-update.publish'),
                'close' => auth()->user()->can('clearance-update.close'),
                'edit' => auth()->user()->can('clearance-update.edit'),
                'delete' => auth()->user()->can('clearance-update.delete'),
                'extend' => auth()->user()->can('extend', $update),
            ]
        ]);
    }

    public function publish(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('publish', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return back()->with('error', 'Only draft updates can be published.');
        }

        $update->update([
            'status' => ClearanceUpdate::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Audit Trail
        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => 'PUBLISHED',
            'remarks' => 'Clearance update published to students.',
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Clearance update published successfully.');
    }

    public function close(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('close', $update);

        $update->update([
            'status' => ClearanceUpdate::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        // Audit Trail
        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => 'CLOSED',
            'remarks' => 'Clearance update closed.',
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Clearance update closed.');
    }

    public function syncOffices(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('update', $update);

        $offices = Office::all();
        foreach ($offices as $idx => $office) {
            ClearanceUpdateOffice::updateOrCreate(
                ['clearance_update_id' => $update->id, 'office_id' => $office->id],
                ['sequence' => $idx + 1, 'is_required' => true, 'can_upload_accountability' => true, 'can_resolve_accountability' => true]
            );
        }

        return back()->with('success', 'All offices assigned to this clearance.');
    }

    public function destroy(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('delete', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return back()->with('error', 'Only draft clearance updates can be deleted.');
        }

        $update->delete();

        return redirect()->route('clearance.updates.index')
            ->with('success', 'Clearance update deleted successfully.');
    }

    public function extend(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('extend', $update);

        $validated = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:' . now()->toDateString()],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $oldEndDate = $update->end_date->toDateString();
        $newEndDate = $validated['end_date'];

        $update->update([
            'end_date' => $newEndDate,
        ]);

        // Audit Trail
        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => 'EXTEND_PERIOD',
            'remarks' => "Extended application period from {$oldEndDate} to {$newEndDate}. " . ($validated['remarks'] ?? ''),
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Application period extended successfully.');
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
