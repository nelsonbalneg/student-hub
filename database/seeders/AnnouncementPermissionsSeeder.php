<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AnnouncementPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'announcement.view',
            'announcement.create',
            'announcement.edit',
            'announcement.delete',
            'announcement.publish',
            'announcement.archive',
            'announcement.manage-attachments',
            'announcement.manage-categories',
            'announcement.settings',
            'announcements.view',
            'announcements.create',
            'announcements.edit',
            'announcements.delete',
            'announcements.publish',
            'announcements.archive',
            'announcements.manage-attachments',
            'announcements.manage-categories',
            'announcements.settings',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => 'web',
                ],
                [
                    'module' => 'Announcements',
                    'label' => Str::headline($permission),
                ],
            );
        }

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }
}
