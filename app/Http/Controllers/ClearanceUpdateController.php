<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReopenClearanceUpdateRequest;
use App\Http\Requests\SyncClearanceUpdateOfficesRequest;
use App\Http\Resources\Clearance\ClearanceLogResource;
use App\Http\Resources\Clearance\ClearanceUpdateResource;
use App\Http\Resources\Clearance\StudentClearanceResource;
use App\Models\ClearanceAccountability;
use App\Models\ClearanceLog;
use App\Models\ClearanceType;
use App\Models\ClearanceUpdate;
use App\Models\ClearanceUpdateOffice;
use App\Models\Office;
use App\Models\Semester;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use App\Services\ClearanceReferenceCodeGenerator;
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

        $semesterId = null;
        if ($request->semester_id) {
            $siteTerm = SiteAcademicTerm::find($request->semester_id);
            if ($siteTerm) {
                $semesterId = Semester::where('external_id', $siteTerm->term_id)
                    ->orWhere(fn ($q) => $q->where('academic_year', $siteTerm->school_year)->where('term', $siteTerm->semester))
                    ->value('id');
            }
        }

        $updates = ClearanceUpdate::query()
            ->with(['semester', 'type', 'creator', 'targetedStudents:id'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('reference_code', 'like', "%{$search}%");
                });
            })
            ->when($semesterId, fn ($query, $id) => $query->where('semester_id', $id))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $campuses = SiteCampus::all(['id', 'campus_name', 'real_campus_id']);
        $semesters = SiteAcademicTerm::query()
            ->with('campus')
            ->orderByDesc('school_year')
            ->orderByDesc('semester')
            ->get()
            ->map(function ($term) {
                return [
                    'id' => $term->id,
                    'academic_year' => $term->school_year,
                    'term' => $term->semester,
                    'campus_name' => $term->campus?->campus_name,
                    'campus_id' => $term->campus?->campus_id,
                    'site_campus_id' => $term->site_campus_id,
                    'term_id' => $term->term_id,
                ];
            });

        return Inertia::render('Clearance/Updates/Index', [
            'updates' => $this->resourcePage($updates, ClearanceUpdateResource::class),
            'filters' => $request->only(['search', 'semester_id', 'status']),
            'semesters' => $semesters,
            'types' => ClearanceType::all(['id', 'name', 'audience', 'campus_id']),
            'campuses' => $campuses,
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

    public function store(
        Request $request,
        ClearanceReferenceCodeGenerator $referenceCodeGenerator,
    ): RedirectResponse {
        $this->authorize('create', ClearanceUpdate::class);

        $validated = $request->validate([
            'semester_id' => ['required', 'exists:site_academic_terms,id'],
            'clearance_type_id' => ['required', 'exists:clearance_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'selected_student_ids' => ['nullable', 'array'],
        ]);

        $siteTerm = SiteAcademicTerm::findOrFail($validated['semester_id']);

        $semester = Semester::firstOrCreate(
            ['external_id' => $siteTerm->term_id],
            [
                'campus_id' => $siteTerm->campus?->campus_id ?? $siteTerm->campus_id,
                'campus_name' => $siteTerm->campus?->campus_name,
                'academic_year' => $siteTerm->school_year,
                'term' => $siteTerm->semester,
                'is_active' => $siteTerm->status === 'Active',
            ]
        );

        $siteCampusId = $siteTerm->site_campus_id;

        $type = ClearanceType::findOrFail($validated['clearance_type_id']);
        if ((int) $type->campus_id !== (int) $siteCampusId) {
            throw ValidationException::withMessages([
                'clearance_type_id' => 'The selected clearance type is not available for this campus.',
            ]);
        }

        $studentIds = $this->validatedStudentIds($request, $validated);
        unset($validated['selected_student_ids']);

        $validated['semester_id'] = $semester->id;

        $update = DB::transaction(function () use ($validated, $studentIds, $request, $referenceCodeGenerator, $type) {
            $update = ClearanceUpdate::create([
                ...$validated,
                'reference_code' => $referenceCodeGenerator->generate(),
                'status' => ClearanceUpdate::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $update->targetedStudents()->sync($studentIds);

            // Automatically apply if tagged for an individual clearance update
            if ($type->audience === ClearanceType::AUDIENCE_INDIVIDUAL) {
                $students = User::whereIn('id', $studentIds)->get();
                $applicationService = app(\App\Services\ClearanceApplicationService::class);
                foreach ($students as $student) {
                    $applicationService->applyForClearance($student, $update);
                }
            }

            return $update;
        });

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Clearance update created as draft.');
    }

    public function update(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('update', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return redirect()->route('clearance.updates.show', $update)
                ->with('error', 'Only draft clearance updates can be edited.');
        }

        $validated = $request->validate([
            'semester_id' => ['required'],
            'clearance_type_id' => ['required', 'exists:clearance_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'selected_student_ids' => ['nullable', 'array'],
        ]);

        // Try SiteAcademicTerm first (submitted by the Index page),
        // then fall back to local Semester (submitted by the Show page).
        // This avoids accidentally matching a local Semester record whose
        // campus_id is NULL when the ID numerically collides with a SiteAcademicTerm ID.
        $siteTerm = SiteAcademicTerm::find($validated['semester_id']);
        if ($siteTerm) {
            $semester = Semester::firstOrCreate(
                ['external_id' => $siteTerm->term_id],
                [
                    'campus_id'     => $siteTerm->campus?->campus_id ?? $siteTerm->campus_id,
                    'campus_name'   => $siteTerm->campus?->campus_name,
                    'academic_year' => $siteTerm->school_year,
                    'term'          => $siteTerm->semester,
                    'is_active'     => $siteTerm->status === 'Active',
                ]
            );
            $siteCampusId = $siteTerm->site_campus_id;
        } else {
            $semester = Semester::find($validated['semester_id']);
            if ($semester) {
                $campuses = SiteCampus::all(['id', 'real_campus_id']);
                $siteCampus = $campuses->firstWhere('real_campus_id', (string) $semester->campus_id)
                    ?? $campuses->firstWhere('id', $semester->campus_id);
                $siteCampusId = $siteCampus?->id;
            } else {
                throw ValidationException::withMessages([
                    'semester_id' => 'The selected semester is invalid.',
                ]);
            }
        }

        $type = ClearanceType::findOrFail($validated['clearance_type_id']);
        if ((int) $type->campus_id !== (int) $siteCampusId) {
            throw ValidationException::withMessages([
                'clearance_type_id' => 'The selected clearance type is not available for this campus.',
            ]);
        }

        // Resolve to local Semester ID before calling validatedStudentIds,
        // which does Semester::findOrFail($validated['semester_id']).
        $validated['semester_id'] = $semester->id;
        unset($validated['selected_student_ids']);

        $studentIds = $this->validatedStudentIds($request, $validated);

        DB::transaction(function () use ($update, $validated, $studentIds, $type) {
            $update->update($validated);
            $update->targetedStudents()->sync($studentIds);

            // Automatically apply if tagged for an individual clearance update
            if ($type->audience === ClearanceType::AUDIENCE_INDIVIDUAL) {
                // Delete applications for students who are no longer tagged
                StudentSemesterClearance::where('clearance_update_id', $update->id)
                    ->whereNotIn('student_id', $studentIds)
                    ->delete();

                // Create applications for newly tagged students
                $existingStudentIds = StudentSemesterClearance::where('clearance_update_id', $update->id)
                    ->pluck('student_id')
                    ->toArray();

                $newStudentIds = array_diff($studentIds, $existingStudentIds);
                if (!empty($newStudentIds)) {
                    $students = User::whereIn('id', $newStudentIds)->get();
                    $applicationService = app(\App\Services\ClearanceApplicationService::class);
                    foreach ($students as $student) {
                        $applicationService->applyForClearance($student, $update);
                    }
                }
            } else {
                // If the type is changed to all, delete all existing individual applications
                StudentSemesterClearance::where('clearance_update_id', $update->id)->delete();
            }
        });

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Clearance update updated successfully.');
    }

    public function show(Request $request, ClearanceUpdate $update): Response
    {
        $this->authorize('view', $update);

        $update->load([
            'semester',
            'type.offices:id',
            'creator',
            'offices.office',
            'targetedStudents:id,name,student_no',
        ]);

        $logs = ClearanceLog::where('clearance_update_id', $update->id)
            ->with(['performer', 'office'])
            ->latest()
            ->paginate(10, ['*'], 'logs_page')
            ->withQueryString();

        $applications = $update->applications()
            ->with('student')
            ->when($request->string('applications_search')->trim()->value(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('student_no', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'applications_page')
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

        return Inertia::render('Clearance/Updates/Show', [
            'update' => (new ClearanceUpdateResource($update))->resolve(),
            'logs' => $this->resourcePage($logs, ClearanceLogResource::class),
            'semesters' => $semesters,
            'types' => ClearanceType::all(['id', 'name', 'audience', 'campus_id']),
            'allOffices' => $this->officesForSemester($update->semester)->get(['id', 'name', 'code', 'category']),
            'configuredOfficeIds' => $update->type->offices->modelKeys(),
            'students' => $this->studentOptions(),
            'applications' => $this->resourcePage($applications, StudentClearanceResource::class),
            'applicationFilters' => $request->only('applications_search'),
            'accountabilitySummary' => $this->accountabilitySummary($update),
            'can' => [
                'publish' => auth()->user()->can('clearance-update.publish'),
                'close' => auth()->user()->can('clearance-update.close'),
                'reopen' => auth()->user()->can('reopen', $update),
                'edit' => auth()->user()->can('clearance-update.edit'),
                'delete' => auth()->user()->can('clearance-update.delete'),
                'extend' => auth()->user()->can('extend', $update),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountabilitySummary(ClearanceUpdate $update): array
    {
        $accountabilities = ClearanceAccountability::query()
            ->whereBelongsTo($update)
            ->whereNull('parent_id');

        $totals = (clone $accountabilities)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved")
            ->selectRaw("SUM(CASE WHEN status = 'waived' THEN 1 ELSE 0 END) as waived")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN COALESCE(amount, 0) ELSE 0 END) as outstanding_amount")
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'pending' => (int) ($totals->pending ?? 0),
            'resolved' => (int) ($totals->resolved ?? 0),
            'waived' => (int) ($totals->waived ?? 0),
            'affected_students' => (clone $accountabilities)
                ->distinct('student_id')
                ->count('student_id'),
            'outstanding_amount' => (float) ($totals->outstanding_amount ?? 0),
            'posted_offices' => $update->offices->whereNotNull('finalized_at')->count(),
            'total_offices' => $update->offices->count(),
            'offices' => $update->offices->map(fn (ClearanceUpdateOffice $updateOffice) => [
                'id' => $updateOffice->id,
                'name' => $updateOffice->office->name,
                'posted' => $updateOffice->finalized_at !== null,
                'finalized_at' => $updateOffice->finalized_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ];
    }

    public function publish(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('publish', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return redirect()->route('clearance.updates.show', $update)
                ->with('error', 'Only draft updates can be published.');
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Clearance update published successfully.');
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Clearance update closed.');
    }

    public function reopen(
        ReopenClearanceUpdateRequest $request,
        ClearanceUpdate $update,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $update, $validated): void {
            $update->update([
                'status' => $validated['status'],
                'closed_at' => null,
                'published_at' => $validated['status'] === ClearanceUpdate::STATUS_PUBLISHED
                    ? now()
                    : null,
            ]);

            ClearanceLog::create([
                'clearance_update_id' => $update->id,
                'action' => 'REOPENED',
                'remarks' => sprintf(
                    'Reopened as %s. Reason: %s',
                    ucfirst($validated['status']),
                    $validated['reason'],
                ),
                'performed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', sprintf(
                'Clearance update reopened as %s.',
                ucfirst($validated['status']),
            ));
    }

    public function syncOffices(
        SyncClearanceUpdateOfficesRequest $request,
        ClearanceUpdate $update,
    ): RedirectResponse {
        $this->authorize('update', $update);

        DB::transaction(function () use ($request, $update): void {
            $update->offices()->delete();

            foreach ($request->validated('office_ids') as $index => $officeId) {
                ClearanceUpdateOffice::create([
                    'clearance_update_id' => $update->id,
                    'office_id' => $officeId,
                    'sequence' => $index + 1,
                    'is_required' => true,
                    'can_upload_accountability' => true,
                    'can_resolve_accountability' => true,
                ]);
            }

            ClearanceLog::create([
                'clearance_update_id' => $update->id,
                'action' => 'OFFICES_UPDATED',
                'remarks' => 'Updated participating offices for this clearance.',
                'performed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Participating offices saved successfully.');
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', $msg);
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Office removed.');
    }

    public function destroy(ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('delete', $update);

        if ($update->status !== ClearanceUpdate::STATUS_DRAFT) {
            return redirect()->route('clearance.updates.show', $update)
                ->with('error', 'Only draft clearance updates can be deleted.');
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Application period extended successfully.');
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

        return redirect()->route('clearance.updates.show', $update)
            ->with('success', 'Student application removed.');
    }
}
