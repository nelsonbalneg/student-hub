<?php

namespace App\Policies;

use App\Models\StudentDossier;
use App\Models\User;

class StudentDossierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dossiers.view');
    }

    public function view(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('dossiers.create');
    }

    public function update(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.update');
    }

    public function transition(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.transition');
    }

    public function assign(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.assign');
    }

    public function archive(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.archive');
    }

    public function release(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.release');
    }

    public function audit(User $user, StudentDossier $dossier): bool
    {
        return $user->can('dossiers.audit.view');
    }
}
