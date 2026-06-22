<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnfinalizeClearanceOfficeRequest;
use App\Http\Resources\Clearance\ClearanceAccountabilityResource;
use App\Http\Resources\Clearance\ClearanceUpdateResource;
use App\Models\ClearanceAccountability;
use App\Models\ClearanceLog;
use App\Models\ClearanceUpdate;
use App\Models\Office;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use App\Services\ClearanceAccountabilityService;
use App\Services\ClearanceApplicationService;
use App\Services\ClearanceUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceAccountabilityController extends Controller
{
    public function center(Request $request): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');

        // Ensure user has an office tagged if not Super Admin
        if (! $isSuperAdmin && ! $user->office_id) {
            abort(403, 'You are not tagged to any office. Please contact the administrator.');
        }

        $updates = ClearanceUpdate::query()
            ->when(! $isSuperAdmin, function ($query) use ($user) {
                $query->whereHas('offices', function ($q) use ($user) {
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
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'update_id' => ['required', 'integer', 'exists:clearance_updates,id'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
        ]);

        $update = ClearanceUpdate::findOrFail($validated['update_id']);
        $participatingOffice = $update->offices()
            ->where('office_id', $validated['office_id'])
            ->with('office')
            ->firstOrFail();
        $office = $participatingOffice->office;
        $search = $validated['search'];

        return User::query()
            ->whereNotNull('student_no')
            ->when(
                $office->category === Office::CATEGORY_ACADEMIC,
                fn ($query) => $query->where('office_id', $office->id),
            )
            ->where(function ($q) use ($search) {
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
            'group_title' => ['required_if:items_count,>1', 'nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $participatingOffice = $update->offices()
            ->where('office_id', $validated['office_id'])
            ->with('office')
            ->first();

        if (! $participatingOffice) {
            throw ValidationException::withMessages([
                'office_id' => 'Select an office participating in this clearance update.',
            ]);
        }

        if ($participatingOffice->finalized_at) {
            throw ValidationException::withMessages([
                'office_id' => 'This office has already posted its accountability encoding.',
            ]);
        }

        $student = User::query()
            ->whereNotNull('student_no')
            ->findOrFail($validated['student_id']);

        if (
            $participatingOffice->office->category === Office::CATEGORY_ACADEMIC
            && (int) $student->office_id !== (int) $participatingOffice->office_id
        ) {
            throw ValidationException::withMessages([
                'student_id' => 'This student does not belong to the selected academic office.',
            ]);
        }

        $service = app(ClearanceAccountabilityService::class);
        $parentId = null;

        // If multiple items, create a parent accountability
        if (count($request->items) > 1) {
            $parent = $service->createAccountability([
                'student_id' => $request->student_id,
                'office_id' => $request->office_id,
                'clearance_update_id' => $update->id,
                'semester_id' => $update->semester_id,
                'title' => $request->group_title ?? 'Multiple Accountabilities',
                'description' => 'Group parent for '.count($request->items).' items.',
                'amount' => collect($request->items)->sum('amount'),
            ]);
            $parentId = $parent->id;
        }

        foreach ($request->items as $item) {
            $data = $item;
            $data['student_id'] = $request->student_id;
            $data['office_id'] = $request->office_id;
            $data['clearance_update_id'] = $update->id;
            $data['semester_id'] = $update->semester_id;
            $data['parent_id'] = $parentId;
            $service->createAccountability($data);
        }

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $update,
            'office_id' => $validated['office_id'],
        ])->with('success', 'Accountabilities added successfully.');
    }

    public function finalizeOffice(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('upload', [ClearanceAccountability::class, $update]);

        $validated = $request->validate([
            'office_id' => ['required', 'integer', 'exists:offices,id'],
        ]);

        $user = $request->user();
        if (! $user->hasRole('Super Admin') && (int) $user->office_id !== (int) $validated['office_id']) {
            abort(403, 'You may only post accountability encoding for your assigned office.');
        }

        $updateOffice = $update->offices()
            ->where('office_id', $validated['office_id'])
            ->first();

        if (! $updateOffice) {
            throw ValidationException::withMessages([
                'office_id' => 'Select an office participating in this clearance update.',
            ]);
        }

        if (! $updateOffice->finalized_at) {
            $updateOffice->update([
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);
        }

        $service = app(ClearanceApplicationService::class);
        $update->studentClearances()
            ->select('id')
            ->chunkById(100, function ($clearances) use ($service): void {
                $clearances->each(
                    fn (StudentSemesterClearance $clearance) => $service->refreshStudentClearanceStatus($clearance->id),
                );
            });

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $update,
            'office_id' => $validated['office_id'],
        ])->with('success', 'Office encoding posted. Student results will appear after all relevant offices post.');
    }

    public function unfinalizeOffice(UnfinalizeClearanceOfficeRequest $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('upload', [ClearanceAccountability::class, $update]);

        $validated = $request->validated();

        $user = $request->user();
        if (! $user->hasRole('Super Admin') && (int) $user->office_id !== (int) $validated['office_id']) {
            abort(403, 'You may only unpost accountability encoding for your assigned office.');
        }

        $updateOffice = $update->offices()
            ->where('office_id', $validated['office_id'])
            ->with('office:id,name')
            ->first();

        if (! $updateOffice) {
            throw ValidationException::withMessages([
                'office_id' => 'Select an office participating in this clearance update.',
            ]);
        }

        if ($updateOffice->finalized_at) {
            $updateOffice->update([
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            ClearanceLog::create([
                'clearance_update_id' => $update->id,
                'office_id' => $updateOffice->office_id,
                'action' => 'OFFICE_ENCODING_UNPOSTED',
                'remarks' => "{$updateOffice->office->name}: {$validated['remarks']}",
                'performed_by' => $user->id,
            ]);
        }

        $service = app(ClearanceApplicationService::class);
        $update->studentClearances()
            ->select('id')
            ->chunkById(100, function ($clearances) use ($service): void {
                $clearances->each(
                    fn (StudentSemesterClearance $clearance) => $service->refreshStudentClearanceStatus($clearance->id),
                );
            });

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $update,
            'office_id' => $validated['office_id'],
        ])->with('success', 'Office encoding unposted successfully.');
    }

    public function reset(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('reset', $accountability);

        app(ClearanceAccountabilityService::class)->resetAccountability($accountability->id);

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $accountability->clearance_update_id,
            'office_id' => $accountability->office_id,
        ])->with('success', 'Accountability status reset to pending.');
    }

    public function index(Request $request, ClearanceUpdate $update): Response
    {
        $this->authorize('viewAny', [ClearanceAccountability::class, $update]);

        // Draft updates are not visible to offices.
        if ($update->status === ClearanceUpdate::STATUS_DRAFT) {
            abort(403, 'This clearance update is still in draft and is not yet accessible.');
        }

        $accountabilities = ClearanceAccountability::query()
            ->where('clearance_update_id', $update->id)
            ->whereNull('parent_id') // Only show top-level accountabilities
            ->with(['student:id,name,student_no', 'office', 'uploader:id,name', 'resolver:id,name', 'children', 'children.student', 'children.office', 'children.uploader', 'children.resolver'])
            ->when($request->search, function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('student_no', 'like', "%{$search}%");
                });
            })
            ->when($request->office_id, fn ($query, $id) => $query->where('office_id', $id))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clearance/Admin/Accountabilities', [
            'update' => $update->load(['semester', 'type']),
            'accountabilities' => $this->resourcePage($accountabilities, ClearanceAccountabilityResource::class),
            'filters' => $request->only(['search', 'office_id', 'status']),
            'offices' => $update->offices()
                ->with(['office:id,name,code,category', 'finalizer:id,name'])
                ->get()
                ->map(fn ($updateOffice) => [
                    'id' => $updateOffice->office->id,
                    'name' => $updateOffice->office->name,
                    'code' => $updateOffice->office->code,
                    'category' => $updateOffice->office->category,
                    'finalized_at' => $updateOffice->finalized_at?->format('Y-m-d H:i:s'),
                    'finalized_by' => $updateOffice->finalizer?->name,
                ])
                ->values(),
        ]);
    }

    public function resolve(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('resolve', $accountability);

        app(ClearanceAccountabilityService::class)->resolveAccountability($accountability->id, $request->remarks);

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $accountability->clearance_update_id,
            'office_id' => $accountability->office_id,
        ])->with('success', 'Accountability resolved.');
    }

    public function waive(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('waive', $accountability);

        app(ClearanceAccountabilityService::class)->waiveAccountability($accountability->id, $request->remarks);

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $accountability->clearance_update_id,
            'office_id' => $accountability->office_id,
        ])->with('success', 'Accountability waived.');
    }

    public function update(Request $request, ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('update', $accountability);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $accountability->update($validated);

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $accountability->clearance_update_id,
            'office_id' => $accountability->office_id,
        ])->with('success', 'Accountability updated.');
    }

    public function destroy(ClearanceAccountability $accountability): RedirectResponse
    {
        $this->authorize('delete', $accountability);

        $studentId = $accountability->student_id;
        $updateId = $accountability->clearance_update_id;

        $accountability->delete();

        // Sync status after deletion
        app(ClearanceAccountabilityService::class)->syncStudentClearanceStatus($studentId, $updateId, 'accountability_deleted');

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $updateId,
            'office_id' => $accountability->office_id,
        ])->with('success', 'Accountability deleted.');
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

        return redirect()->route('clearance.accountabilities.index', [
            'update' => $update,
            'office_id' => $request->integer('office_id'),
        ])
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
