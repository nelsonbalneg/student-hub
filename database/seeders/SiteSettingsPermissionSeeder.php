<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SiteSettingsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'site-settings.view',
            'site-settings.create',
            'site-settings.edit',
            'site-settings.delete',
            'site-settings.manage-terms',
            'site-settings.activate-term',
            'site-settings.student-profile.view',
            'site-settings.student-profile.create',
            'site-settings.student-profile.update',
            'site-settings.student-profile.delete',
            'site_settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }
}
