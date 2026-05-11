<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountabilityPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view',
            'create',
            'update',
            'delete',
            'resolve',
            'waive',
            'reset',
            'upload'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => 'clearance-accountability.' . $permission,
                'guard_name' => 'web'
            ]);
        }

        $admin = Role::where('name', 'Super Admin')->first();
        if ($admin) {
            $admin->givePermissionTo(Permission::where('name', 'like', 'clearance-accountability.%')->get());
        }
    }
}
