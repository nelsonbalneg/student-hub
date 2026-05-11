<?php

namespace App\Policies;

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
        return $user->can('clearance-application.apply') && $update->status === ClearanceUpdate::STATUS_PUBLISHED;
    }

    public function print(User $user, StudentSemesterClearance $clearance): bool
    {
        return ($user->id === $clearance->student_id || $user->can('clearance-application.print')) 
            && ($clearance->status === StudentSemesterClearance::STATUS_CLEARED || $clearance->status === StudentSemesterClearance::STATUS_COMPLETED);
    }
}
