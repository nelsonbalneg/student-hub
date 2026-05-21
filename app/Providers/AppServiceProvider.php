<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRequest;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\EvaluationPeriodPolicy;
use App\Policies\EvaluationRequestPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        $this->configureAuditEvents();
    }

    protected function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($ability === 'dashboard.view') {
                return true;
            }

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

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(EvaluationPeriod::class, EvaluationPeriodPolicy::class);
        Gate::policy(EvaluationRequest::class, EvaluationRequestPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
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

        if (config('app.force_https')) {
            $secureUrl = Str::replaceStart('http://', 'https://', (string) config('app.url'));
            $assetUrl = config('app.asset_url');

            Config::set('app.url', $secureUrl);

            if (is_string($assetUrl) && Str::startsWith($assetUrl, 'http://')) {
                Config::set('app.asset_url', Str::replaceStart('http://', 'https://', $assetUrl));
            }

            URL::forceRootUrl($secureUrl);
            URL::forceScheme('https');
        }

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

    private function configureAuditEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $this->writeAuthAuditLog($event->user, 'login');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                $this->writeAuthAuditLog($event->user, 'logout');
            }
        });
    }

    private function writeAuthAuditLog(User $user, string $action): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            AuditLog::query()->create([
                'user_id' => $user->id,
                'actor_name' => $user->name,
                'actor_email' => $user->email,
                'module' => 'Authentication',
                'action' => $action,
                'description' => "{$user->name} performed {$action}.",
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'route_name' => request()?->route()?->getName(),
                'url' => request()?->fullUrl(),
                'method' => request()?->method(),
            ]);
        } catch (Throwable) {
            //
        }
    }
}
