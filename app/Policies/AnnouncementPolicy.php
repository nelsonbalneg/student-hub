<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAnnouncementPermission($user, 'view');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->hasAnnouncementPermission($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->hasAnnouncementPermission($user, 'create');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->isSuperAdmin($user) && $this->hasAnnouncementPermission($user, 'edit');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->isSuperAdmin($user) && $this->hasAnnouncementPermission($user, 'delete');
    }

    public function publish(User $user, Announcement $announcement): bool
    {
        return $this->isSuperAdmin($user) && $this->hasAnnouncementPermission($user, 'publish');
    }

    public function archive(User $user, Announcement $announcement): bool
    {
        return $this->isSuperAdmin($user) && $this->hasAnnouncementPermission($user, 'archive');
    }

    private function hasAnnouncementPermission(User $user, string $action): bool
    {
        return $user->can("announcement.{$action}") || $user->can("announcements.{$action}");
    }

    private function isSuperAdmin(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('Super Admin');
    }
}
