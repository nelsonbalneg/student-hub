<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FaqPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => 'web',
                ],
                [
                    'module' => Str::startsWith($permission, 'faq-category.') ? 'FAQ Categories' : 'FAQ',
                    'label' => Str::headline($permission),
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        $studentRole = Role::query()->where('name', 'Student')->where('guard_name', 'web')->first();
        if ($studentRole) {
            $studentRole->givePermissionTo('faq.view');
        }

        $registrarRole = Role::query()->where('name', 'Registrar')->where('guard_name', 'web')->first();
        if ($registrarRole) {
            $registrarRole->givePermissionTo('faq.view');
        }
    }
}
