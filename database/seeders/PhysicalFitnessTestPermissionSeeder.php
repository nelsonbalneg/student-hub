<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PhysicalFitnessTestPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'pft.view',
            'pft.submit',
            'pft.configuration.view',
            'pft.configuration.create',
            'pft.configuration.update',
            'pft.configuration.delete',
            'pft.permission.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['module' => 'Physical Fitness Test', 'label' => Str::headline($permission)],
            );
        }

        Role::query()->where('name', 'Super Admin')->first()?->givePermissionTo($permissions);
        Role::query()->where('name', 'Student')->first()?->givePermissionTo(['pft.view', 'pft.submit']);
    }
}
