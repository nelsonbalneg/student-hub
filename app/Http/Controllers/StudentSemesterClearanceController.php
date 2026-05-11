<?php

namespace App\Http\Controllers;

use App\Http\Resources\Clearance\StudentSemesterClearanceResource;
use App\Models\ClearanceUpdate;
use App\Models\StudentSemesterClearance;
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
        
        $activeUpdates = $activeSem ? ClearanceUpdate::query()
            ->where('semester_id', $activeSem->id)
            ->where('status', ClearanceUpdate::STATUS_PUBLISHED)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->with(['type', 'offices.office'])
            ->get() : collect();

        $myClearances = StudentSemesterClearance::query()
            ->where('student_id', $request->user()->id)
            ->with([
                'clearanceUpdate.type', 
                'semester',
                'clearanceUpdate.offices.office',
                'clearanceUpdate.accountabilities' => function($query) use ($request) {
                    $query->where('student_id', $request->user()->id);
                }
            ])
            ->latest()
            ->get();

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

        // Validate application period
        if ($update->status !== ClearanceUpdate::STATUS_PUBLISHED || now()->isBefore($update->start_date) || now()->isAfter($update->end_date)) {
            return back()->with('error', 'The application period for this clearance has ended or not yet started.');
        }

        // Check if already applied
        $exists = StudentSemesterClearance::where('student_id', $request->user()->id)
            ->where('clearance_update_id', $update->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'You have already applied for this clearance.');
        }

        app(ClearanceApplicationService::class)->applyForClearance($request->user(), $update);

        return back()->with('success', 'Clearance application submitted.');
    }

    /**
     * View specific clearance details (for students).
     */
    public function show(StudentSemesterClearance $clearance): Response
    {
        $this->authorize('view', $clearance);

        $clearance->load([
            'clearanceUpdate.offices.office', 
            'clearanceUpdate.accountabilities' => function($query) use ($clearance) {
                $query->where('student_id', $clearance->student_id)
                      ->whereNull('parent_id')
                      ->with(['student', 'office', 'children', 'children.student', 'children.office']);
            },
            'logs.performer'
        ]);

        return Inertia::render('Clearance/Student/Details', [
            'clearance' => (new StudentSemesterClearanceResource($clearance))->resolve(),
        ]);
    }
}
