<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CcdCaresSiteEvaluationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ccd-cares.site-evaluation.view',
            'ccd-cares.site-evaluation.create',
            'ccd-cares.site-evaluation.update',
            'ccd-cares.site-evaluation.delete',
            'ccd-cares.site-evaluation.submissions.view',
            'ccd-cares.site-evaluation.analytics.view',
            'ccd-cares.site-evaluation.submit',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'module' => 'CCD Cares Site Evaluation',
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
            ?->givePermissionTo('ccd-cares.site-evaluation.submit');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
