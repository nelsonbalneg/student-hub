<?php

namespace App\Services;

use App\Models\ClearanceAccountability;
use App\Models\ClearanceLog;
use App\Models\StudentSemesterClearance;
use Illuminate\Support\Facades\DB;

class ClearanceAccountabilityService
{
    /**
     * Create an individual accountability.
     */
    public function createAccountability(array $data): ClearanceAccountability
    {
        return DB::transaction(function () use ($data) {
            $accountability = ClearanceAccountability::create([
                'clearance_update_id' => $data['clearance_update_id'],
                'student_id' => $data['student_id'],
                'office_id' => $data['office_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'] ?? 0,
                'status' => ClearanceAccountability::STATUS_PENDING,
                'uploaded_by' => auth()->id(),
            ]);

            $this->syncStudentClearanceStatus($data['student_id'], $data['clearance_update_id'], 'accountability_added');
            
            return $accountability;
        });
    }

    /**
     * Resolve an accountability.
     */
    public function resolveAccountability(int $id, ?string $remarks = null): void
    {
        DB::transaction(function () use ($id, $remarks) {
            $accountability = ClearanceAccountability::findOrFail($id);
            $accountability->update([
                'status' => ClearanceAccountability::STATUS_RESOLVED,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'remarks' => $remarks ?? $accountability->remarks,
            ]);

            $this->syncStudentClearanceStatus($accountability->student_id, $accountability->clearance_update_id, 'accountability_resolved');
        });
    }

    /**
     * Waive an accountability.
     */
    public function waiveAccountability(int $id, ?string $remarks = null): void
    {
        DB::transaction(function () use ($id, $remarks) {
            $accountability = ClearanceAccountability::findOrFail($id);
            $accountability->update([
                'status' => ClearanceAccountability::STATUS_WAIVED,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'remarks' => $remarks ?? $accountability->remarks,
            ]);

            $this->syncStudentClearanceStatus($accountability->student_id, $accountability->clearance_update_id, 'accountability_waived');
        });
    }

    /**
     * Sync student clearance status based on current accountabilities.
     */
    public function syncStudentClearanceStatus(int $studentId, int $updateId, string $action): void
    {
        $clearance = StudentSemesterClearance::where('student_id', $studentId)
            ->where('clearance_update_id', $updateId)
            ->first();

        if ($clearance) {
            app(ClearanceApplicationService::class)->refreshStudentClearanceStatus($clearance->id);
        }
    }
}
