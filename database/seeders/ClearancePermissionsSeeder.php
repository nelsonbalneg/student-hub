<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClearancePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            ['module' => 'Clearance', 'name' => 'clearance-update.view'],
            ['module' => 'Clearance', 'name' => 'clearance-update.create'],
            ['module' => 'Clearance', 'name' => 'clearance-update.edit'],
            ['module' => 'Clearance', 'name' => 'clearance-update.publish'],
            ['module' => 'Clearance', 'name' => 'clearance-update.close'],
            ['module' => 'Clearance', 'name' => 'clearance-update.delete'],

            ['module' => 'Clearance', 'name' => 'clearance-office.view'],
            ['module' => 'Clearance', 'name' => 'clearance-office.assign'],

            ['module' => 'Clearance', 'name' => 'clearance-application.view'],
            ['module' => 'Clearance', 'name' => 'clearance-application.apply'],
            ['module' => 'Clearance', 'name' => 'clearance-application.print'],
            ['module' => 'Clearance', 'name' => 'clearance-application.verify'],

            ['module' => 'Clearance', 'name' => 'clearance-accountability.view'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.upload'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.create'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.edit'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.resolve'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.waive'],
            ['module' => 'Clearance', 'name' => 'clearance-accountability.delete'],

            ['module' => 'Clearance', 'name' => 'clearance-report.view'],
            ['module' => 'Clearance', 'name' => 'clearance-report.export'],
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission['name'], 'web');
            
            // Update the module and label if possible
            $p = Permission::where('name', $permission['name'])->first();
            if ($p) {
                $p->module = $permission['module'];
                $p->label = \Illuminate\Support\Str::headline($permission['name']);
                $p->save();
            }
        }

        // Assign to Super Admin
        $role = Role::findByName('Super Admin', 'web');
        if ($role) {
            $role->givePermissionTo(collect($permissions)->pluck('name')->toArray());
        }
    }
}
