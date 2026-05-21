<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CarbonFootprintPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'reporting.carbon_footprint.view',
            'reporting.carbon_footprint.user_view',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => 'web',
                ],
                [
                    'module' => 'Carbon Footprint',
                    'label' => Str::headline($permission),
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permissions);

        Role::query()
            ->whereIn('name', ['Student', 'Registrar'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo('reporting.carbon_footprint.user_view'));
    }
}
