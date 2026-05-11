<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FaqPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'faq-category.view',
            'faq-category.create',
            'faq-category.update',
            'faq-category.delete',
            'faq.view',
            'faq.create',
            'faq.update',
            'faq.delete',
            'faq.publish',
            'faq.feature',
            'faq.analytics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $adminRole = Role::findByName('Super Admin');
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        $studentRole = Role::findByName('Student');
        if ($studentRole) {
            $studentRole->givePermissionTo('faq.view');
        }

        $registrarRole = Role::findByName('Registrar');
        if ($registrarRole) {
            $registrarRole->givePermissionTo('faq.view');
        }
    }
}
