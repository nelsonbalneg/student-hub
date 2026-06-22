<?php

namespace App\Http\Controllers;

use App\Http\Resources\Clearance\StudentSemesterClearanceResource;
use App\Models\ClearanceType;
use App\Models\ClearanceUpdate;
use App\Models\Office;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use App\Services\ClearanceApplicationService;
use App\Services\GetActiveSem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentSemesterClearanceController extends Controller
{
    /**
     * Student's clearance dashboard.
     */
    public function myClearance(Request $request): Response
    {
        $activeSem = GetActiveSem::current();
        $clearanceDate = now(config('clearance.timezone'))->toDateString();

        $activeUpdates = $activeSem ? ClearanceUpdate::query()
            ->where('semester_id', $activeSem->id)
            ->where('status', ClearanceUpdate::STATUS_PUBLISHED)
            ->whereDate('start_date', '<=', $clearanceDate)
            ->whereDate('end_date', '>=', $clearanceDate)
            ->where(function ($query) use ($request) {
                $query
                    ->whereHas('type', fn ($typeQuery) => $typeQuery->where('audience', ClearanceType::AUDIENCE_ALL))
                    ->orWhereHas('targetedStudents', fn ($studentQuery) => $studentQuery->whereKey($request->user()->id));
            })
            ->with(['type', 'offices.office'])
            ->get()
            ->each(fn (ClearanceUpdate $update) => $this->filterOfficesForStudent($update, $request->user())) : collect();

        $myClearances = StudentSemesterClearance::query()
            ->where('student_id', $request->user()->id)
            // Only show clearances whose parent update has been published (or closed).
            // Draft updates must remain invisible to students even if they were tagged.
            ->whereHas('clearanceUpdate', function ($q) {
                $q->where('status', '!=', ClearanceUpdate::STATUS_DRAFT);
            })
            ->with([
                'clearanceUpdate.type',
                'semester',
                'clearanceUpdate.offices.office',
                'clearanceUpdate.accountabilities' => function ($query) use ($request) {
                    $query->where('student_id', $request->user()->id);
                },
            ])
            ->latest()
            ->get()
            ->each(fn (StudentSemesterClearance $clearance) => $this->filterOfficesForStudent(
                $clearance->clearanceUpdate,
                $request->user(),
            ));

        return Inertia::render('Clearance/Student/MyClearance', [
            'activeSemester' => $activeSem,
            'activeUpdates' => $activeUpdates,
            'myClearances' => StudentSemesterClearanceResource::collection($myClearances)->resolve(),
        ]);
    }

    /**
     * Student applies for a clearance update.
     */
    public function apply(Request $request, ClearanceUpdate $update): RedirectResponse
    {
        $this->authorize('apply', [StudentSemesterClearance::class, $update]);

        $clearanceDate = now(config('clearance.timezone'))->toDateString();

        if (
            $update->status !== ClearanceUpdate::STATUS_PUBLISHED
            || $clearanceDate < $update->start_date->toDateString()
            || $clearanceDate > $update->end_date->toDateString()
        ) {
            return redirect()->route('clearance.my-clearance')
                ->with('error', 'The application period for this clearance has ended or not yet started.');
        }

        $update->loadMissing('type');
        if (
            $update->type->audience === ClearanceType::AUDIENCE_INDIVIDUAL
            && ! $update->targetedStudents()->whereKey($request->user()->id)->exists()
        ) {
            abort(403, 'This clearance is assigned to selected students only.');
        }

        // Check if already applied
        $exists = StudentSemesterClearance::where('student_id', $request->user()->id)
            ->where('clearance_update_id', $update->id)
            ->exists();

        if ($exists) {
            return redirect()->route('clearance.my-clearance')
                ->with('error', 'You have already applied for this clearance.');
        }

        $clearance = app(ClearanceApplicationService::class)->applyForClearance($request->user(), $update);

        return redirect()->route('clearance.show', $clearance)
            ->with('success', 'Clearance application submitted.');
    }

    /**
     * View specific clearance details (for students).
     */
    public function show(StudentSemesterClearance $clearance): Response
    {
        $this->authorize('view', $clearance);

        $clearance->loadMissing('clearanceUpdate');

        // Draft clearance updates are hidden from students, but staff/admins
        // with the clearance-application.view permission can still view them.
        $isDraft = $clearance->clearanceUpdate?->status === ClearanceUpdate::STATUS_DRAFT;
        $isStudent = auth()->id() === $clearance->student_id && ! auth()->user()->can('clearance-application.view');
        if ($isDraft && $isStudent) {
            abort(404);
        }

        $clearance->load([
            'student',
            'clearanceUpdate.offices.office',
            'clearanceUpdate.accountabilities' => function ($query) use ($clearance) {
                $query->where('student_id', $clearance->student_id)
                    ->whereNull('parent_id')
                    ->with(['student', 'office', 'children', 'children.student', 'children.office']);
            },
            'logs.performer',
        ]);

        $this->filterOfficesForStudent($clearance->clearanceUpdate, $clearance->student);

        return Inertia::render('Clearance/Student/Details', [
            'clearance' => (new StudentSemesterClearanceResource($clearance))->resolve(),
        ]);
    }

    private function filterOfficesForStudent(ClearanceUpdate $update, User $student): void
    {
        if (! $update->relationLoaded('offices')) {
            return;
        }

        $update->setRelation(
            'offices',
            $update->offices
                ->filter(function ($updateOffice) use ($student): bool {
                    $office = $updateOffice->office;

                    if (! $office || $office->category !== Office::CATEGORY_ACADEMIC) {
                        return true;
                    }

                    if ($student->office_id) {
                        return (int) $office->id === (int) $student->office_id;
                    }

                    return mb_strtolower(trim($office->name))
                        === mb_strtolower(trim($student->office ?? ''));
                })
                ->values(),
        );
    }
}
