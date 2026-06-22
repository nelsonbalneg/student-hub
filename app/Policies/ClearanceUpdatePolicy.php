<?php

namespace App\Policies;

use App\Models\ClearanceUpdate;
use App\Models\User;

class ClearanceUpdatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clearance-update.view');
    }

    public function view(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clearance-update.create');
    }

    public function update(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.edit') && $update->status === ClearanceUpdate::STATUS_DRAFT;
    }

    public function publish(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.publish') && $update->status === ClearanceUpdate::STATUS_DRAFT;
    }

    public function close(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.close') && $update->status === ClearanceUpdate::STATUS_PUBLISHED;
    }

    public function reopen(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.close') && $update->status === ClearanceUpdate::STATUS_CLOSED;
    }

    public function delete(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.delete') && $update->status === ClearanceUpdate::STATUS_DRAFT;
    }

    public function extend(User $user, ClearanceUpdate $update): bool
    {
        return $user->can('clearance-update.edit') && $update->status === ClearanceUpdate::STATUS_PUBLISHED;
    }
}
