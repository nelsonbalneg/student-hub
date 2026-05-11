<?php

namespace App\Policies;

use App\Models\ClearanceAccountability;
use App\Models\ClearanceUpdate;
use App\Models\User;

class ClearanceAccountabilityPolicy
{
    public function viewAny(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-accountability.view');
    }

    public function upload(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-accountability.upload');
    }

    public function resolve(User $user, ClearanceAccountability $accountability): bool
    {
        return $user->can('clearance-accountability.resolve') && $accountability->status === ClearanceAccountability::STATUS_PENDING;
    }

    public function waive(User $user, ClearanceAccountability $accountability): bool
    {
        return $user->can('clearance-accountability.waive') && $accountability->status === ClearanceAccountability::STATUS_PENDING;
    }

    public function reset(User $user, ClearanceAccountability $accountability): bool
    {
        return $user->can('clearance-accountability.reset') && $accountability->status !== ClearanceAccountability::STATUS_PENDING;
    }

    public function create(User $user): bool
    {
        return $user->can('clearance-accountability.create');
    }

    public function update(User $user, ClearanceAccountability $accountability): bool
    {
        return $user->can('clearance-accountability.update');
    }

    public function delete(User $user, ClearanceAccountability $accountability): bool
    {
        return $user->can('clearance-accountability.delete');
    }
}
