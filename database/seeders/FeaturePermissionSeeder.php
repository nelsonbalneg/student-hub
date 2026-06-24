<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FeaturePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'features.view',
            'features.edit',
            'features.sync',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Grant all feature permissions to Super Admin
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->givePermissionTo($permissions);

        $this->command->info('Feature management permissions seeded successfully.');
    }
}
