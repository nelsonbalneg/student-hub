<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SiteEvaluationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'site-evaluation.view',
            'site-evaluation.create',
            'site-evaluation.update',
            'site-evaluation.delete',
            'site-evaluation.results.view',
            'site-evaluation.analytics.view',
            'site-evaluation.submit',
            'site-evaluation.dismiss',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'module' => 'Site Evaluation',
                    'label' => Str::headline($permission),
                ],
            );
        }

        Role::query()
            ->where('name', 'Super Admin')
            ->first()
            ?->givePermissionTo($permissions);

        Role::query()
            ->where('name', 'Student')
            ->first()
            ?->givePermissionTo([
                'site-evaluation.submit',
                'site-evaluation.dismiss',
            ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
