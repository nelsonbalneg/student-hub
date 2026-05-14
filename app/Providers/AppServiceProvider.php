<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Announcement;
use App\Models\DossierDocument;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRequest;
use App\Models\StudentDossier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureDefaults();
    }

    protected function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($this->isUserManagementAbility($ability) && $this->hasBootstrapAccess($user)) {
                return true;
            }

            if (! $this->hasPermissionTables()) {
                return null;
            }

            if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
                return true;
            }

            if (Str::startsWith($ability, 'announcement.') && $user->can('announcements.'.Str::after($ability, 'announcement.'))) {
                return true;
            }

            return null;
        });

        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(Announcement::class, \App\Policies\AnnouncementPolicy::class);
        Gate::policy(EvaluationPeriod::class, \App\Policies\EvaluationPeriodPolicy::class);
        Gate::policy(EvaluationRequest::class, \App\Policies\EvaluationRequestPolicy::class);
        Gate::policy(StudentDossier::class, \App\Policies\StudentDossierPolicy::class);
        Gate::policy(DossierDocument::class, \App\Policies\DossierDocumentPolicy::class);
        Gate::policy(Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(Permission::class, \App\Policies\PermissionPolicy::class);
    }

    private function isUserManagementAbility(string $ability): bool
    {
        return Str::startsWith($ability, [
            'users.',
            'roles.',
            'permissions.',
        ]);
    }

    private function hasBootstrapAccess(User $user): bool
    {
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }

            if ($user->email === 'admin@sso.test') {
                return true;
            }

            if (! Schema::hasTable('model_has_roles') || ! DB::table('model_has_roles')->exists()) {
                $query = User::query()->orderBy('id');

                if (Schema::hasColumn('users', 'is_active')) {
                    $query->where('is_active', true);
                }

                return (int) $query->value('id') === (int) $user->id;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function hasPermissionTables(): bool
    {
        return collect([
            'permissions',
            'roles',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ])->every(fn (string $table): bool => Schema::hasTable($table));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
