<?php

namespace App\Http\Controllers;

use App\Http\Resources\Clearance\ClearanceUpdateResource;
use App\Http\Resources\Clearance\StudentClearanceResource;
use App\Models\ClearanceLog;
use App\Models\ClearanceType;
use App\Models\ClearanceUpdate;
use App\Models\ClearanceUpdateOffice;
use App\Models\Office;
use App\Models\Semester;
use App\Models\SiteCampus;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use App\Services\GetActiveSem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceUpdateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ClearanceUpdate::class);

        $updates = ClearanceUpdate::query()
            ->with(['semester', 'type', 'creator', 'targetedStudents:id'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->semester_id, fn ($query, $id) => $query->where('semester_id', $id))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $campuses = SiteCampus::all(['id', 'real_campus_id']);
        $semesters = Semester::orderByDesc('academic_year')
            ->orderByDesc('term')
            ->get(['id', 'academic_year', 'term', 'campus_id', 'campus_name'])
            ->map(function ($semester) use ($campuses) {
                $siteCampus = $campuses->firstWhere('real_campus_id', (string) $semester->campus_id)
                    ?? $campuses->firstWhere('id', $semester->campus_id);
                $semester->site_campus_id = $siteCampus?->id;
                return $semester;
            });

        return Inertia::render('Clearance/Updates/Index', [
            'updates' => $this->resourcePage($updates, ClearanceUpdateResource::class),
            'filters' => $request->only(['search', 'semester_id', 'status']),
            'semesters' => $semesters,
            'types' => ClearanceType::all(['id', 'name', 'audience', 'campus_id']),
            'students' => $this->studentOptions(),
            'activeSemester' => GetActiveSem::current(),
            'can' => [
                'create' => $request->user()->can('clearance-update.create'),
                'edit' => $request->user()->can('clearance-update.edit'),
                'publish' => $request->user()->can('clearance-update.publish'),
                'delete' => $request->user()->can('clearance-update.delete'),
            ],
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
            'selected_student_ids' => ['nullable', 'array'],
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        $siteCampusId = SiteCampus::query()
            ->where('real_campus_id', (string) $semester->campus_id)
            ->value('id')
            ?? SiteCampus::query()->whereKey($semester->campus_id)->value('id');

        $type = ClearanceType::findOrFail($validated['clearance_type_id']);
        if ($type->campus_id !== $siteCampusId) {
            throw ValidationException::withMessages([
                'clearance_type_id' => 'The selected clearance type is not available for this campus.',
            ]);
        }

        $studentIds = $this->validatedStudentIds($request, $validated);
        unset($validated['selected_student_ids']);

        DB::transaction(function () use ($validated, $studentIds, $request) {
            $update = ClearanceUpdate::create([
                ...$validated,
                'status' => ClearanceUpdate::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $update->targetedStudents()->sync($studentIds);
        });

        return back()->with('success', 'Clearance update created as draft.');
    }

    public function update(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('update', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return back()->with('error', 'Only draft clearance updates can be edited.');
        }

        $validated = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'clearance_type_id' => ['required', 'exists:clearance_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'selected_student_ids' => ['nullable', 'array'],
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        $siteCampusId = SiteCampus::query()
            ->where('real_campus_id', (string) $semester->campus_id)
            ->value('id')
            ?? SiteCampus::query()->whereKey($semester->campus_id)->value('id');

        $type = ClearanceType::findOrFail($validated['clearance_type_id']);
        if ($type->campus_id !== $siteCampusId) {
            throw ValidationException::withMessages([
                'clearance_type_id' => 'The selected clearance type is not available for this campus.',
            ]);
        }

        $studentIds = $this->validatedStudentIds($request, $validated);
        unset($validated['selected_student_ids']);

        DB::transaction(function () use ($update, $validated, $studentIds) {
            $update->update($validated);
            $update->targetedStudents()->sync($studentIds);
        });

        return back()->with('success', 'Clearance update updated successfully.');
    }

    public function show(ClearanceUpdate $update): Response
    {
        $this->authorize('view', $update);

        $update->load(['semester', 'type', 'creator', 'offices.office', 'targetedStudents:id,name,student_no']);

        $logs = ClearanceLog::where('clearance_update_id', $update->id)
            ->with(['performer', 'office'])
            ->latest()
            ->get();

        $campuses = SiteCampus::all(['id', 'real_campus_id']);
        $semesters = Semester::orderByDesc('academic_year')
            ->orderByDesc('term')
            ->get(['id', 'academic_year', 'term', 'campus_id', 'campus_name'])
            ->map(function ($semester) use ($campuses) {
                $siteCampus = $campuses->firstWhere('real_campus_id', (string) $semester->campus_id)
                    ?? $campuses->firstWhere('id', $semester->campus_id);
                $semester->site_campus_id = $siteCampus?->id;
                return $semester;
            });

        return Inertia::render('Clearance/Updates/Show', [
            'update' => (new ClearanceUpdateResource($update))->resolve(),
            'logs' => $logs,
            'semesters' => $semesters,
            'types' => ClearanceType::all(['id', 'name', 'audience', 'campus_id']),
            'allOffices' => $this->officesForSemester($update->semester)->get(['id', 'name']),
            'students' => $this->studentOptions(),
            'applications' => StudentClearanceResource::collection(
                $update->applications()->with('student')->latest()->get()
            )->resolve(),
            'can' => [
                'publish' => auth()->user()->can('clearance-update.publish'),
                'close' => auth()->user()->can('clearance-update.close'),
                'edit' => auth()->user()->can('clearance-update.edit'),
                'delete' => auth()->user()->can('clearance-update.delete'),
                'extend' => auth()->user()->can('extend', $update),
            ],
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

        $configuredOffices = $update->type->offices;

        if ($configuredOffices->isNotEmpty()) {
            $offices = $configuredOffices;
            $msg = 'Configured offices assigned to this clearance.';
        } else {
            $offices = $this->officesForSemester($update->semester)->get();
            $msg = 'All offices assigned to this clearance.';
        }

        foreach ($offices as $idx => $office) {
            ClearanceUpdateOffice::updateOrCreate(
                ['clearance_update_id' => $update->id, 'office_id' => $office->id],
                ['sequence' => $idx + 1, 'is_required' => true, 'can_upload_accountability' => true, 'can_resolve_accountability' => true]
            );
        }

        return back()->with('success', $msg);
    }

    public function toggleOffice(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('update', $update);

        $validated = $request->validate([
            'office_id' => ['required', 'exists:offices,id'],
        ]);

        $officeId = $validated['office_id'];
        $office = $this->officesForSemester($update->semester)
            ->whereKey($officeId)
            ->firstOrFail();

        $exists = ClearanceUpdateOffice::where('clearance_update_id', $update->id)
            ->where('office_id', $officeId)
            ->first();

        if ($exists) {
            $officeName = $exists->office->name;
            $exists->delete();
            $msg = 'Office removed from clearance.';
            $action = 'OFFICE_REMOVED';
            $remarks = "Removed office: {$officeName}.";
        } else {
            ClearanceUpdateOffice::create([
                'clearance_update_id' => $update->id,
                'office_id' => $officeId,
                'sequence' => ($update->offices()->max('sequence') ?? 0) + 1,
                'is_required' => true,
                'can_upload_accountability' => true,
                'can_resolve_accountability' => true,
            ]);
            $msg = 'Office added to clearance.';
            $action = 'OFFICE_ADDED';
            $remarks = "Added office: {$office->name}.";
        }

        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => $action,
            'remarks' => $remarks,
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', $msg);
    }

    public function removeOffice(ClearanceUpdate $update, Office $office): RedirectResponse
    {
        $this->authorize('update', $update);

        ClearanceUpdateOffice::where('clearance_update_id', $update->id)
            ->where('office_id', $office->id)
            ->delete();

        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => 'OFFICE_REMOVED',
            'remarks' => "Removed office: {$office->name}.",
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Office removed.');
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
            'end_date' => ['required', 'date', 'after_or_equal:'.now()->toDateString()],
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
            'remarks' => "Extended application period from {$oldEndDate} to {$newEndDate}. ".($validated['remarks'] ?? ''),
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

    /**
     * @return array<int, array{id: int, name: string, student_no: string|null, campus_id: int|null}>
     */
    private function studentOptions(): array
    {
        return User::query()
            ->whereNotNull('student_no')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'student_no', 'campus_id'])
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function validatedStudentIds(Request $request, array $validated): array
    {
        $type = ClearanceType::findOrFail($validated['clearance_type_id']);

        if ($type->audience === ClearanceType::AUDIENCE_ALL) {
            return [];
        }

        $semester = Semester::findOrFail($validated['semester_id']);
        $data = $request->validate([
            'selected_student_ids' => ['required', 'array', 'min:1'],
            'selected_student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->whereNotNull('student_no')
                        ->where('campus_id', $semester->campus_id),
                ),
            ],
        ]);

        $studentIds = array_values(array_unique($data['selected_student_ids']));

        if ($studentIds === []) {
            throw ValidationException::withMessages([
                'selected_student_ids' => 'Select at least one student for an individual clearance.',
            ]);
        }

        return $studentIds;
    }

    private function officesForSemester(Semester $semester)
    {
        $siteCampusId = SiteCampus::query()
            ->where('real_campus_id', (string) $semester->campus_id)
            ->value('id')
            ?? SiteCampus::query()->whereKey($semester->campus_id)->value('id');

        return Office::query()
            ->when(
                $siteCampusId,
                fn ($query) => $query->where('campus_id', $siteCampusId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('name');
    }

    public function deleteApplication(Request $request, ClearanceUpdate $update, StudentSemesterClearance $application): RedirectResponse
    {
        $this->authorize('update', $update);

        $studentName = $application->student->name;
        $application->delete();

        ClearanceLog::create([
            'clearance_update_id' => $update->id,
            'action' => 'APPLICATION_REMOVED',
            'remarks' => "Removed student application: {$studentName}.",
            'performed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Student application removed.');
    }
}
