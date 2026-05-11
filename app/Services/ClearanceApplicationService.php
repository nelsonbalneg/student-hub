<?php

namespace App\Services;

use App\Models\ClearanceAccountability;
use App\Models\ClearanceLog;
use App\Models\ClearanceUpdate;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClearanceApplicationService
{
    /**
     * Apply for clearance for a student.
     */
    public function applyForClearance(User $student, ClearanceUpdate $update): StudentSemesterClearance
    {
        return DB::transaction(function () use ($student, $update) {
            $status = $this->determineClearanceStatus($student->id, $update->id);

            $clearance = StudentSemesterClearance::create([
                'clearance_update_id' => $update->id,
                'semester_id' => $update->semester_id,
                'student_id' => $student->id,
                'reference_no' => $this->generateReferenceNo(),
                'status' => $status,
                'applied_at' => now(),
                'cleared_at' => $status === StudentSemesterClearance::STATUS_CLEARED ? now() : null,
            ]);

            $this->logAction($clearance, 'applied', null, $status, 'Student applied for clearance.');

            return $clearance;
        });
    }

    /**
     * Determine the clearance status based on accountabilities.
     */
    public function determineClearanceStatus(int $studentId, int $clearanceUpdateId): string
    {
        $hasPending = ClearanceAccountability::where('student_id', $studentId)
            ->where('clearance_update_id', $clearanceUpdateId)
            ->where('status', ClearanceAccountability::STATUS_PENDING)
            ->exists();

        return $hasPending 
            ? StudentSemesterClearance::STATUS_WITH_ACCOUNTABILITY 
            : StudentSemesterClearance::STATUS_CLEARED;
    }

    /**
     * Refresh the clearance status of a student application.
     */
    public function refreshStudentClearanceStatus(int $clearanceId): void
    {
        $clearance = StudentSemesterClearance::findOrFail($clearanceId);
        $oldStatus = $clearance->status;
        $newStatus = $this->determineClearanceStatus($clearance->student_id, $clearance->clearance_update_id);

        if ($oldStatus !== $newStatus) {
            $clearance->status = $newStatus;
            
            if ($newStatus === StudentSemesterClearance::STATUS_CLEARED && ! $clearance->cleared_at) {
                $clearance->cleared_at = now();
            }

            $clearance->save();

            $this->logAction(
                $clearance, 
                'status_refreshed', 
                $oldStatus, 
                $newStatus, 
                'Status automatically refreshed based on accountabilities.'
            );
        }
    }

    /**
     * Generate a unique reference number for the clearance.
     */
    private function generateReferenceNo(): string
    {
        do {
            $ref = 'CLR-' . strtoupper(Str::random(8));
        } while (StudentSemesterClearance::where('reference_no', $ref)->exists());

        return $ref;
    }

    /**
     * Log a clearance action.
     */
    private function logAction(
        StudentSemesterClearance $clearance, 
        string $action, 
        ?string $oldStatus, 
        ?string $newStatus, 
        ?string $remarks = null
    ): void {
        ClearanceLog::create([
            'student_semester_clearance_id' => $clearance->id,
            'clearance_update_id' => $clearance->clearance_update_id,
            'student_id' => $clearance->student_id,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'remarks' => $remarks,
            'performed_by' => auth()->id() ?? $clearance->student_id,
        ]);
    }
}
