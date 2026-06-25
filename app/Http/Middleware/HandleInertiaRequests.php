<?php

namespace App\Http\Middleware;

use App\Models\SystemFeature;
use App\Models\User;
use App\Services\LegalDocumentService;
use App\Services\SiteEvaluationPromptService;
use App\Services\SiteSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly LegalDocumentService $legalDocuments,
        private readonly SiteSettingService $siteSettings,
        private readonly SiteEvaluationPromptService $siteEvaluation,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => $this->siteSettings->branding()['site_name'] ?? config('app.name'),
            'siteSettings' => fn () => $this->siteSettings->branding(),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $this->permissionsFor($request),
                'roles' => $this->rolesFor($request),
                'impersonation' => fn () => $this->impersonationFor($request),
            ],
            'flash' => [
                'toast' => $this->toastFor($request),
            ],
            'legal' => [
                'active' => fn () => $this->legalDocuments->activeDocumentsForShare(),
                'requiredTerms' => fn () => $request->user()
                    ? $this->legalDocuments->requiredTermsFor($request->user())
                    : null,
            ],
            'siteEvaluation' => fn () => $this->siteEvaluation->forUser(
                $request->user(),
                $request->session()->pull('site_evaluation_suppressed_period_id'),
            ),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'systemFeatures' => fn () => SystemFeature::routeStatusMap(),
        ];
    }

    private function toastFor(Request $request): ?array
    {
        if ($request->session()->has('success')) {
            return [
                'type' => 'success',
                'message' => $request->session()->get('success'),
            ];
        }

        if ($request->session()->has('error')) {
            return [
                'type' => 'error',
                'message' => $request->session()->get('error'),
            ];
        }

        return $request->session()->get('toast');
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(Request $request): array
    {
        if (! $request->user() || ! $this->hasPermissionTables()) {
            return [];
        }

        return $request->user()->permissionNames();
    }

    /**
     * @return list<string>
     */
    private function rolesFor(Request $request): array
    {
        if (! $request->user() || ! $this->hasPermissionTables()) {
            return [];
        }

        return $request->user()
            ->roles()
            ->pluck('name')
            ->values()
            ->all();
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

    private function impersonationFor(Request $request): ?array
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! $impersonatorId || ! $request->user()) {
            return null;
        }

        $impersonator = User::query()->find($impersonatorId);

        return [
            'active' => true,
            'impersonator' => $impersonator ? [
                'id' => $impersonator->id,
                'name' => $impersonator->name,
                'email' => $impersonator->email,
            ] : null,
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];
    }
}
