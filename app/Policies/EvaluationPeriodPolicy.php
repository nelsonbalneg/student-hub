<?php

namespace App\Policies;

use App\Models\EvaluationPeriod;
use App\Models\User;

class EvaluationPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('evaluation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('evaluation.create-period');
    }

    public function update(User $user, EvaluationPeriod $period): bool
    {
        return $user->can('evaluation.edit-period');
    }

    public function delete(User $user, EvaluationPeriod $period): bool
    {
        return $user->can('evaluation.delete-period');
    }
}
