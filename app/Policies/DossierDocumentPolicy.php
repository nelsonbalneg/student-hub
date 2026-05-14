<?php

namespace App\Policies;

use App\Models\DossierDocument;
use App\Models\User;

class DossierDocumentPolicy
{
    public function view(User $user, DossierDocument $document): bool
    {
        return $user->can('dossiers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('dossiers.documents.upload');
    }

    public function verify(User $user, DossierDocument $document): bool
    {
        return $user->can('dossiers.documents.verify');
    }

    public function delete(User $user, DossierDocument $document): bool
    {
        return $user->can('dossiers.documents.upload');
    }

    public function download(User $user, DossierDocument $document): bool
    {
        return $user->can('dossiers.download');
    }
}
