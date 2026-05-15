<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SocietyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'society.view',
            'society.create',
            'society.update',
            'society.delete',
            'society.apply_accreditation',
            'society.submit_requirements',
            'society.review',
            'society.approve',
            'society.reject',
            'society.return',
            'society.manage_requirements',
            'society.manage_officers',
            'society.manage_advisers',
            'society.manage_members',
            'society.manage_bylaws',
            'society.review_bylaws',
            'society.manage_announcements',
            'society.manage_events',
            'society.approve_events',
            'society.manage_attendance',
            'society.view_reports',
            'society.export_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'Super Admin' => $permissions,
            'OSA Admin' => [
                'society.view',
                'society.review',
                'society.approve',
                'society.reject',
                'society.return',
                'society.manage_requirements',
                'society.review_bylaws',
                'society.approve_events',
                'society.view_reports',
                'society.export_reports',
            ],
            'Society Adviser' => [
                'society.view',
                'society.create',
                'society.update',
                'society.delete',
                'society.apply_accreditation',
                'society.submit_requirements',
                'society.manage_officers',
                'society.manage_advisers',
                'society.manage_members',
                'society.manage_bylaws',
                'society.manage_announcements',
                'society.manage_events',
                'society.manage_attendance',
                'society.view_reports',
            ],
            'Society Officer' => [
                'society.view',
                'society.create',
                'society.update',
                'society.delete',
                'society.apply_accreditation',
                'society.submit_requirements',
                'society.manage_officers',
                'society.manage_advisers',
                'society.manage_members',
                'society.manage_bylaws',
                'society.manage_announcements',
                'society.manage_events',
                'society.manage_attendance',
                'society.view_reports',
            ],
            'Student' => [
                'society.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
