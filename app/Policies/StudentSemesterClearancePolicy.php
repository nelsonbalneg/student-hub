<?php

namespace App\Policies;

use App\Models\ClearanceType;
use App\Models\ClearanceUpdate;
use App\Models\StudentSemesterClearance;
use App\Models\User;

class StudentSemesterClearancePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Students can view their own, admins can view all
    }

    public function view(User $user, StudentSemesterClearance $clearance): bool
    {
        return $user->id === $clearance->student_id || $user->can('clearance-application.view');
    }

    public function apply(User $user, ClearanceUpdate $update): bool
    {
        if (! $user->can('clearance-application.apply') || $update->status !== ClearanceUpdate::STATUS_PUBLISHED) {
            return false;
        }

        $update->loadMissing('type');

        return $update->type->audience === ClearanceType::AUDIENCE_ALL
            || $update->targetedStudents()->whereKey($user->id)->exists();
    }

    public function print(User $user, StudentSemesterClearance $clearance): bool
    {
        return ($user->id === $clearance->student_id || $user->can('clearance-application.print'))
            && ($clearance->status === StudentSemesterClearance::STATUS_CLEARED || $clearance->status === StudentSemesterClearance::STATUS_COMPLETED);
    }
}
