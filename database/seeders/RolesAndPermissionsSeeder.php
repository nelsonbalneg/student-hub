<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles and permissions for the current Student Hub modules.
     */
    public function run(): void
    {
        $this->repairPartialPermissionSchema();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            ['module' => 'Dashboard', 'name' => 'dashboard.view'],
            ['module' => 'Grades', 'name' => 'grades.view'],
            ['module' => 'Curriculum', 'name' => 'curriculum.view'],
            ['module' => 'Academic', 'name' => 'view class schedule'],
            ['module' => 'Academic', 'name' => 'download cor'],
            ['module' => 'Student Profile', 'name' => 'student-profile.view'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.view'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.create'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.manage'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.view-term-id'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.approve'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.cancel'],
            ['module' => 'Internet Accounts', 'name' => 'internet-accounts.delete'],
            ['module' => 'Achievements', 'name' => 'achievements.create'],
            ['module' => 'Achievements', 'name' => 'achievements.update'],
            ['module' => 'Achievements', 'name' => 'achievements.delete'],
            ['module' => 'Trainings', 'name' => 'trainings.create'],
            ['module' => 'Trainings', 'name' => 'trainings.update'],
            ['module' => 'Trainings', 'name' => 'trainings.delete'],
            ['module' => 'Users', 'name' => 'users.view'],
            ['module' => 'Users', 'name' => 'users.create'],
            ['module' => 'Users', 'name' => 'users.update'],
            ['module' => 'Users', 'name' => 'users.assign-role'],
            ['module' => 'Users', 'name' => 'users.delete'],
            ['module' => 'Roles', 'name' => 'roles.view'],
            ['module' => 'Roles', 'name' => 'roles.create'],
            ['module' => 'Roles', 'name' => 'roles.update'],
            ['module' => 'Roles', 'name' => 'roles.delete'],
            ['module' => 'Permissions', 'name' => 'permissions.view'],
            ['module' => 'Permissions', 'name' => 'permissions.create'],
            ['module' => 'Permissions', 'name' => 'permissions.update'],
            ['module' => 'Permissions', 'name' => 'permissions.delete'],
            ['module' => 'Announcements', 'name' => 'announcement.view'],
            ['module' => 'Announcements', 'name' => 'announcement.create'],
            ['module' => 'Announcements', 'name' => 'announcement.edit'],
            ['module' => 'Announcements', 'name' => 'announcement.delete'],
            ['module' => 'Announcements', 'name' => 'announcement.publish'],
            ['module' => 'Announcements', 'name' => 'announcement.archive'],
            ['module' => 'Announcements', 'name' => 'announcement.manage-attachments'],
            ['module' => 'Announcements', 'name' => 'announcement.manage-categories'],
            ['module' => 'Announcements', 'name' => 'announcement.settings'],
            ['module' => 'Announcements', 'name' => 'announcements.view'],
            ['module' => 'Announcements', 'name' => 'announcements.create'],
            ['module' => 'Announcements', 'name' => 'announcements.edit'],
            ['module' => 'Announcements', 'name' => 'announcements.delete'],
            ['module' => 'Announcements', 'name' => 'announcements.publish'],
            ['module' => 'Announcements', 'name' => 'announcements.archive'],
            ['module' => 'Announcements', 'name' => 'announcements.manage-attachments'],
            ['module' => 'Announcements', 'name' => 'announcements.manage-categories'],
            ['module' => 'Announcements', 'name' => 'announcements.settings'],
            ['module' => 'Evaluation', 'name' => 'evaluation.view'],
            ['module' => 'Evaluation', 'name' => 'evaluation.create-period'],
            ['module' => 'Evaluation', 'name' => 'evaluation.edit-period'],
            ['module' => 'Evaluation', 'name' => 'evaluation.delete-period'],
            ['module' => 'Evaluation', 'name' => 'evaluation.submit-intent'],
            ['module' => 'Evaluation', 'name' => 'evaluation.manage-requests'],
            ['module' => 'Evaluation', 'name' => 'evaluation.evaluate'],
            ['module' => 'Evaluation', 'name' => 'evaluation.feedback'],
            ['module' => 'Evaluation', 'name' => 'evaluation.mark-done'],
            ['module' => 'Reports', 'name' => 'reports.view'],
            ['module' => 'Reporting', 'name' => 'reporting.view'],
            ['module' => 'Reporting', 'name' => 'reporting.overview.view'],
            ['module' => 'Reporting', 'name' => 'reporting.audit_logs.view'],
            ['module' => 'Reporting', 'name' => 'reporting.carbon_footprint.view'],
            ['module' => 'Reporting', 'name' => 'reporting.carbon_footprint.user_view'],
            ['module' => 'Reporting', 'name' => 'reporting.export'],
            ['module' => 'Settings', 'name' => 'settings.view'],
            ['module' => 'Site Settings', 'name' => 'site_settings.manage'],
            ['module' => 'Legal', 'name' => 'legal.view'],
            ['module' => 'Legal', 'name' => 'legal.create'],
            ['module' => 'Legal', 'name' => 'legal.edit'],
            ['module' => 'Legal', 'name' => 'legal.delete'],
            ['module' => 'Legal', 'name' => 'legal.activate'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                [
                    'module' => $permission['module'],
                    'label' => Str::headline($permission['name']),
                ],
            );
        }

        $adminRole = Role::query()->updateOrCreate(
            [
                'name' => 'Super Admin',
                'guard_name' => 'web',
            ],
            [
                'label' => 'Super Admin',
            ],
        );

        $adminRole->syncPermissions(Permission::query()->pluck('name'));

        $registrarRole = Role::query()->updateOrCreate(
            [
                'name' => 'Registrar',
                'guard_name' => 'web',
            ],
            [
                'label' => 'Registrar',
            ],
        );

        $registrarRole->givePermissionTo([
            'evaluation.view',
            'evaluation.create-period',
            'evaluation.edit-period',
            'evaluation.delete-period',
            'evaluation.manage-requests',
            'evaluation.evaluate',
            'evaluation.feedback',
            'evaluation.mark-done',
            'reporting.carbon_footprint.user_view',
        ]);

        $studentRole = Role::query()->updateOrCreate(
            [
                'name' => 'Student',
                'guard_name' => 'web',
            ],
            [
                'label' => 'Student',
            ],
        );

        $studentRole->givePermissionTo([
            'view class schedule',
            'download cor',
            'evaluation.view',
            'evaluation.submit-intent',
            'reporting.carbon_footprint.user_view',
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@sso.test'],
            [
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_active' => true,
                'user_type' => 'Admin',
            ],
        );

        $admin->assignRole($adminRole);
    }

    private function repairPartialPermissionSchema(): void
    {
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table): void {
                if (! Schema::hasColumn('permissions', 'guard_name')) {
                    $table->string('guard_name')->default('web');
                }

                if (! Schema::hasColumn('permissions', 'module')) {
                    $table->string('module')->nullable();
                }
            });
        }

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->string('guard_name')->default('web');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            if (! Schema::hasTable('model_has_permissions')) {
                Schema::create('model_has_permissions', function (Blueprint $table): void {
                    $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                    $table->string('model_type', 191);
                    $table->unsignedBigInteger('model_id');
                    $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
                    $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
                });
            }

            if (! Schema::hasTable('model_has_roles')) {
                Schema::create('model_has_roles', function (Blueprint $table): void {
                    $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                    $table->string('model_type', 191);
                    $table->unsignedBigInteger('model_id');
                    $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                    $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
                });
            }

            if (! Schema::hasTable('role_has_permissions')) {
                Schema::create('role_has_permissions', function (Blueprint $table): void {
                    $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                    $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                    $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
                });
            }
        }
    }
}
