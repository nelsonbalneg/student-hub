<?php

namespace App\Policies;

use App\Models\Society;
use App\Models\User;

class SocietyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('society.view');
    }

    public function view(User $user, Society $society): bool
    {
        return $user->can('society.view') || $society->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('society.create') || $user->can('society.apply_accreditation');
    }

    public function update(User $user, Society $society): bool
    {
        return $user->can('society.update') || $society->created_by === $user->id;
    }

    public function delete(User $user): bool
    {
        return $user->can('society.delete');
    }

    public function review(User $user): bool
    {
        return $user->can('society.review');
    }
}
