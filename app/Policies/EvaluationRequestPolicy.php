<?php

namespace App\Policies;

use App\Models\EvaluationRequest;
use App\Models\User;

class EvaluationRequestPolicy
{
    public function view(User $user, EvaluationRequest $request): bool
    {
        return $user->can('evaluation.manage-requests') || (int) $request->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('evaluation.submit-intent');
    }

    public function cancel(User $user, EvaluationRequest $request): bool
    {
        return (int) $request->user_id === (int) $user->id && $request->status === EvaluationRequest::STATUS_SUBMITTED;
    }

    public function manage(User $user): bool
    {
        return $user->can('evaluation.manage-requests');
    }

    public function evaluate(User $user, EvaluationRequest $request): bool
    {
        return $user->can('evaluation.evaluate');
    }

    public function feedback(User $user, EvaluationRequest $request): bool
    {
        return $user->can('evaluation.feedback');
    }

    public function markDone(User $user, EvaluationRequest $request): bool
    {
        return $user->can('evaluation.mark-done');
    }
}
