<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserManagement\AssignRoleRequest;
use App\Http\Requests\UserManagement\StorePermissionRequest;
use App\Http\Requests\UserManagement\StoreRoleRequest;
use App\Http\Requests\UserManagement\StoreUserRequest;
use App\Http\Requests\UserManagement\UpdatePermissionRequest;
use App\Http\Requests\UserManagement\UpdateRoleRequest;
use App\Http\Requests\UserManagement\UpdateUserRequest;
use App\Http\Resources\UserManagement\PermissionResource;
use App\Http\Resources\UserManagement\RoleResource;
use App\Http\Resources\UserManagement\UserResource;
use App\Models\ClearanceAccountability;
use App\Models\ClearanceAccountabilityUpload;
use App\Models\ClearanceCertificate;
use App\Models\ClearanceLog;
use App\Models\ClearanceUpdate;
use App\Models\ClearanceUpdateOffice;
use App\Models\Office;
use App\Models\StudentSemesterClearance;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserManagementController extends Controller
{
    private const USER_PAGE_SIZES = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $hasRoles = Schema::hasTable('roles');

        return Inertia::render('UserManagement/Users', [
            'filters' => $request->only(['user_search', 'status', 'role', 'office', 'department', 'user_type', 'per_page']),
            'filterOptions' => [
                'roles' => $hasRoles ? Role::query()->orderBy('name')->pluck('name') : [],
                'offices' => $this->userColumnOptions('office'),
                'departments' => $this->userColumnOptions('department'),
                'userTypes' => $this->userColumnOptions('user_type'),
            ],
            'pageSizeOptions' => self::USER_PAGE_SIZES,
            'allRoles' => $hasRoles ? Role::query()->orderBy('name')->get(['id', 'name']) : [],
            'can' => [
                'create' => Gate::allows('create', User::class),
                'update' => $request->user()->can('users.update'),
                'delete' => $request->user()->can('users.delete'),
                'assignRole' => $request->user()->can('users.assign-role'),
                'impersonate' => $request->user()->can('users.impersonate'),
            ],
            'lookupOffices' => Office::query()->orderBy('name')->get(['id', 'name', 'code']),
            'users' => $this->paginatedUsers($request),
        ]);
    }

    public function rolesPermissions(Request $request): Response
    {
        abort_unless(
            Gate::allows('viewAny', Role::class) || Gate::allows('viewAny', Permission::class),
            403
        );

        $hasRoles = Schema::hasTable('roles');
        $hasPermissions = Schema::hasTable('permissions');
        $roles = $hasRoles
            ? Role::query()
                ->with('permissions:id,name')
                ->when(Schema::hasTable('model_has_roles'), fn ($query) => $query->withCount('users'))
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'selection_token' => Crypt::encryptString((string) $role->id),
                    'name' => $role->name,
                    'users_count' => $role->users_count ?? 0,
                    'permissions' => $role->permissions->map(fn (Permission $permission): array => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ])->values(),
                ])
                ->values()
            : collect();

        $selectedRoleId = $this->decryptRoleId($request->query('roleId'))
            ?? $roles->first()['id']
            ?? null;

        if ($selectedRoleId && ! $roles->contains('id', $selectedRoleId)) {
            $selectedRoleId = $roles->first()['id'] ?? null;
        }

        return Inertia::render('UserManagement/RolesPermissions', [
            'roles' => $roles,
            'permissions' => $hasPermissions
                ? PermissionResource::collection(Permission::query()->orderBy('module')->orderBy('name')->get(['id', 'name', 'guard_name', 'module', 'created_at']))->resolve()
                : [],
            'selectedRoleId' => $selectedRoleId,
            'can' => [
                'createRole' => Gate::allows('create', Role::class),
                'updateRole' => $request->user()->can('roles.update'),
                'deleteRole' => $request->user()->can('roles.delete'),
                'createPermission' => Gate::allows('create', Permission::class),
                'updatePermission' => $request->user()->can('permissions.update'),
                'deletePermission' => $request->user()->can('permissions.delete'),
            ],
        ]);
    }

    private function decryptRoleId(mixed $encryptedRoleId): ?int
    {
        if (! is_string($encryptedRoleId) || blank($encryptedRoleId)) {
            return null;
        }

        try {
            $roleId = Crypt::decryptString($encryptedRoleId);

            return ctype_digit($roleId) ? (int) $roleId : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function storeUser(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            ...$this->onlyExistingUserColumns($validated, $this->editableUserColumns()),
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        return to_route('user-management.index')->with('success', 'User created.');
    }

    public function updateUser(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        if ($password) {
            $validated['password'] = Hash::make($password);
        }

        $user->update($this->onlyExistingUserColumns($validated, [...$this->editableUserColumns(), 'password']));

        if ($request->user()->can('users.assign-role') && array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles'] ?? []);
        }

        return to_route('user-management.index')->with('success', 'User updated.');
    }

    public function assignRoles(AssignRoleRequest $request, User $user): RedirectResponse
    {
        $user->syncRoles($request->validated('roles') ?? []);

        return to_route('user-management.index')->with('success', 'User roles updated.');
    }

    public function assignOffice(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'office_id' => ['nullable', 'exists:offices,id'],
        ]);

        $user->update($validated);

        return to_route('user-management.index')->with('success', 'User office updated.');
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if (Schema::hasColumn('users', 'is_active')) {
            $user->update(['is_active' => ! $user->is_active]);
        }

        return to_route('user-management.index')->with('success', 'User status updated.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('users.impersonate'), 403);

        if ($request->user()->is($user)) {
            return to_route('user-management.index')->with('error', 'You cannot impersonate your own account.');
        }

        if ($request->session()->has('impersonator_id')) {
            return to_route('user-management.index')->with('error', 'You are already impersonating a user.');
        }

        if (Schema::hasColumn('users', 'is_active') && ! $user->is_active) {
            return to_route('user-management.index')->with('error', 'You cannot impersonate an inactive account.');
        }

        $impersonator = $request->user();

        if ($user->hasRole('Super Admin') && ! $impersonator->hasRole('Super Admin')) {
            return to_route('user-management.index')->with('error', 'Only a Super Admin can impersonate another Super Admin.');
        }

        Log::notice('User impersonation started', [
            'impersonator_id' => $impersonator->id,
            'target_user_id' => $user->id,
        ]);

        $request->session()->put('impersonator_id', $impersonator->id);
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        if (! $impersonatorId) {
            return to_route('dashboard')->with('error', 'No active impersonation session found.');
        }

        $impersonator = User::query()->find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('auth.sso.redirect')->with('error', 'The original account could not be restored. Please sign in again.');
        }

        Log::notice('User impersonation stopped', [
            'impersonator_id' => $impersonator->id,
            'impersonated_user_id' => $request->user()?->id,
        ]);

        Auth::login($impersonator);
        $request->session()->regenerate();

        return to_route('user-management.index')->with('success', 'Impersonation ended.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $force = $request->boolean('force');

        Log::info('User delete requested', [
            'actor_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'target_user_type' => $user->user_type,
            'target_is_active' => $user->is_active,
            'force' => $force,
        ]);

        if ($request->user()->is($user)) {
            Log::warning('User delete blocked because actor attempted to delete own account', [
                'actor_id' => $request->user()->id,
                'target_user_id' => $user->id,
            ]);

            return back()->with('error', 'You cannot delete your own account from user management.');
        }

        $protectedHistoryCounts = $this->protectedUserHistoryCounts($user);

        if (! $force && $this->hasProtectedUserHistory($protectedHistoryCounts)) {
            Log::warning('User delete blocked because protected history exists', [
                'actor_id' => $request->user()->id,
                'target_user_id' => $user->id,
                'protected_history_counts' => $protectedHistoryCounts,
            ]);

            return back()->with('error', 'This user has clearance records and cannot be deleted. Deactivate the account instead to preserve clearance history.');
        }

        try {
            $deletedHistoryCounts = DB::transaction(function () use ($force, $protectedHistoryCounts, $request, $user): array {
                $deletedHistoryCounts = [];

                if ($force) {
                    $deletedHistoryCounts = $this->forceDeleteProtectedUserHistory($user, $protectedHistoryCounts);
                    $deletedHistoryCounts['user_references'] = $this->forceDeleteUserReferences($user, $request->user()->id);
                }

                $user->delete();

                return $deletedHistoryCounts;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                Log::error('User delete failed with unexpected database error', [
                    'actor_id' => $request->user()->id,
                    'target_user_id' => $user->id,
                    'sql_state' => $exception->getCode(),
                    'database_error' => $exception->getPrevious()?->getMessage(),
                ]);

                throw $exception;
            }

            Log::warning('User delete blocked by database foreign key constraint', [
                'actor_id' => $request->user()->id,
                'target_user_id' => $user->id,
                'sql_state' => $exception->getCode(),
                'database_error' => $exception->getPrevious()?->getMessage(),
            ]);

            return back()->with('error', 'This user is linked to existing records and cannot be deleted. Deactivate the account instead.');
        }

        Log::info('User deleted', [
            'actor_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'force' => $force,
            'deleted_history_counts' => $deletedHistoryCounts,
        ]);

        return to_route('user-management.index')->with('success', $force ? 'User and linked records deleted.' : 'User deleted.');
    }

    public function storeRole(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
            ...$this->legacyLabelColumn('roles', $request->validated('name')),
        ]);

        $role->syncPermissions($request->validated('permissions') ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Role created.');
    }

    public function updateRole(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin' && $request->validated('name') !== 'Super Admin') {
            throw ValidationException::withMessages([
                'name' => 'The Super Admin role name cannot be changed.',
            ]);
        }

        $role->update([
            'name' => $request->validated('name'),
            ...$this->legacyLabelColumn('roles', $request->validated('name')),
        ]);
        $role->syncPermissions($request->validated('permissions') ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Role updated.');
    }

    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionNames = Permission::query()
            ->whereIn('id', $validated['permissions'] ?? [])
            ->pluck('name')
            ->all();

        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Role permissions updated.');
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Role deleted.');
    }

    public function storePermission(StorePermissionRequest $request): RedirectResponse
    {
        Permission::query()->create([
            ...$request->validated(),
            'guard_name' => 'web',
            ...$this->legacyLabelColumn('permissions', $request->validated('name')),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Permission created.');
    }

    public function updatePermission(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update([
            ...$request->validated(),
            ...$this->legacyLabelColumn('permissions', $request->validated('name')),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Permission updated.');
    }

    public function destroyPermission(Request $request, Permission $permission): RedirectResponse
    {
        Gate::authorize('delete', $permission);

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return to_route('user-management.roles.index')->with('success', 'Permission deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedUsers(Request $request): array
    {
        $users = User::query()
            ->when($request->query('user_search'), function ($query, string $search): void {
                $search = $this->escapedLike($search);

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status') && Schema::hasColumn('users', 'is_active'), fn ($query) => $query->where('is_active', $request->query('status') === 'active'))
            ->when($request->query('role'), fn ($query, string $role) => $query->role($role))
            ->when($request->query('office') && Schema::hasColumn('users', 'office'), fn ($query) => $query->where('office', $request->query('office')))
            ->when($request->query('department') && Schema::hasColumn('users', 'department'), fn ($query) => $query->where('department', $request->query('department')))
            ->when($request->query('user_type') && Schema::hasColumn('users', 'user_type'), fn ($query) => $query->where('user_type', $request->query('user_type')))
            ->latest();

        if (Schema::hasTable('model_has_roles')) {
            $users->with('roles:id,name')->withCount('roles');
        }

        $users->with('office:id,name,code');

        return $this->resourcePage($users->paginate($this->userPageSize($request))->withQueryString(), UserResource::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedRoles(Request $request): array
    {
        if (! Schema::hasTable('roles')) {
            return $this->emptyPage();
        }

        $roles = Role::query()
            ->when($request->query('role_search'), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name');

        if (Schema::hasTable('role_has_permissions')) {
            $roles->with('permissions:id,name')->withCount('permissions');
        }

        if (Schema::hasTable('model_has_roles')) {
            $roles->withCount('users');
        }

        return $this->resourcePage($roles->paginate(10, ['*'], 'roles_page')->withQueryString(), RoleResource::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPermissions(Request $request): array
    {
        if (! Schema::hasTable('permissions')) {
            return $this->emptyPage();
        }

        $permissions = Permission::query()
            ->when($request->query('permission_search'), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->query('module'), fn ($query, string $module) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('name');

        if (Schema::hasTable('role_has_permissions')) {
            $permissions->withCount('roles');
        }

        return $this->resourcePage($permissions->paginate(10, ['*'], 'permissions_page')->withQueryString(), PermissionResource::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPage(): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'from' => null,
                'last_page' => 1,
                'per_page' => 10,
                'to' => null,
                'total' => 0,
            ],
            'links' => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function legacyLabelColumn(string $table, string $name): array
    {
        return Schema::hasColumn($table, 'label')
            ? ['label' => Str::headline($name)]
            : [];
    }

    private function userColumnOptions(string $column): array
    {
        if (! Schema::hasColumn('users', $column)) {
            return [];
        }

        return User::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    private function userPageSize(Request $request): int
    {
        $pageSize = $request->integer('per_page', 10);

        return in_array($pageSize, self::USER_PAGE_SIZES, true) ? $pageSize : 10;
    }

    private function escapedLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function onlyExistingUserColumns(array $values, array $columns): array
    {
        return collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
            ->filter(fn (string $column): bool => array_key_exists($column, $values))
            ->mapWithKeys(fn (string $column): array => [$column => $values[$column]])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function editableUserColumns(): array
    {
        return [
            'name',
            'email',
            'email_verified_at',
            'is_active',
            'user_type',
            'office',
            'office_id',
            'department',
            'sso_id',
            'sso_uuid',
            'sso_username',
            'sso_account_type',
            'sso_avatar',
            'tenant_id',
            'campus_id',
            'campus_name',
            'student_no',
            'employee_no',
            'two_factor_confirmed_at',
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function hasProtectedUserHistory(array $counts): bool
    {
        return collect($counts)->contains(fn (int $count): bool => $count > 0);
    }

    /**
     * @return array<string, int>
     */
    private function protectedUserHistoryCounts(User $user): array
    {
        return [
            'student_clearances' => $user->studentClearances()->withTrashed()->count(),
            'clearance_accountabilities' => $user->clearanceAccountabilities()->withTrashed()->count(),
            'uploaded_clearance_accountabilities' => $user->uploadedClearanceAccountabilities()->withTrashed()->count(),
            'created_clearance_updates' => $user->createdClearanceUpdates()->withTrashed()->count(),
            'clearance_accountability_uploads' => $user->clearanceAccountabilityUploads()->count(),
            'student_clearance_logs' => $user->studentClearanceLogs()->count(),
            'performed_clearance_logs' => $user->performedClearanceLogs()->count(),
        ];
    }

    /**
     * @param  array<string, int>  $existingCounts
     * @return array<string, int>
     */
    private function forceDeleteProtectedUserHistory(User $user, array $existingCounts): array
    {
        Log::warning('Force deleting user protected history', [
            'target_user_id' => $user->id,
            'protected_history_counts' => $existingCounts,
        ]);

        $clearanceUpdateIds = $user->createdClearanceUpdates()
            ->withTrashed()
            ->pluck('id')
            ->all();

        $studentClearanceIds = StudentSemesterClearance::withTrashed()
            ->where(function ($query) use ($clearanceUpdateIds, $user): void {
                $query->where('student_id', $user->id)
                    ->when($clearanceUpdateIds !== [], fn ($query): mixed => $query->orWhereIn('clearance_update_id', $clearanceUpdateIds));
            })
            ->pluck('id')
            ->all();

        $accountabilityIds = ClearanceAccountability::withTrashed()
            ->where(function ($query) use ($clearanceUpdateIds, $user): void {
                $query->where('student_id', $user->id)
                    ->orWhere('uploaded_by', $user->id)
                    ->when($clearanceUpdateIds !== [], fn ($query): mixed => $query->orWhereIn('clearance_update_id', $clearanceUpdateIds));
            })
            ->pluck('id')
            ->all();

        $accountabilityIds = $this->accountabilityIdsWithDescendants($accountabilityIds);

        $deleted = [
            'clearance_logs' => ClearanceLog::query()
                ->where(function ($query) use ($clearanceUpdateIds, $studentClearanceIds, $user): void {
                    $query->where('student_id', $user->id)
                        ->orWhere('performed_by', $user->id)
                        ->when($clearanceUpdateIds !== [], fn ($query): mixed => $query->orWhereIn('clearance_update_id', $clearanceUpdateIds))
                        ->when($studentClearanceIds !== [], fn ($query): mixed => $query->orWhereIn('student_semester_clearance_id', $studentClearanceIds));
                })
                ->delete(),
            'clearance_certificates' => ClearanceCertificate::query()
                ->when($studentClearanceIds === [], fn ($query): mixed => $query->whereRaw('1 = 0'))
                ->whereIn('student_semester_clearance_id', $studentClearanceIds)
                ->delete(),
            'clearance_accountabilities' => $this->forceDeleteAccountabilities($accountabilityIds),
            'clearance_accountability_uploads' => ClearanceAccountabilityUpload::query()
                ->where(function ($query) use ($clearanceUpdateIds, $user): void {
                    $query->where('uploaded_by', $user->id)
                        ->when($clearanceUpdateIds !== [], fn ($query): mixed => $query->orWhereIn('clearance_update_id', $clearanceUpdateIds));
                })
                ->delete(),
            'student_clearances' => $this->forceDeleteStudentClearances($studentClearanceIds),
            'clearance_update_offices' => ClearanceUpdateOffice::query()
                ->when($clearanceUpdateIds === [], fn ($query): mixed => $query->whereRaw('1 = 0'))
                ->whereIn('clearance_update_id', $clearanceUpdateIds)
                ->delete(),
            'clearance_updates' => $this->forceDeleteClearanceUpdates($clearanceUpdateIds),
        ];

        Log::warning('Force deleted user protected history', [
            'target_user_id' => $user->id,
            'deleted_history_counts' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function accountabilityIdsWithDescendants(array $ids): array
    {
        $ids = collect($ids)->unique()->values()->all();

        do {
            $childIds = ClearanceAccountability::withTrashed()
                ->whereIn('parent_id', $ids)
                ->pluck('id')
                ->all();
            $newIds = array_values(array_diff($childIds, $ids));
            $ids = array_values(array_unique([...$ids, ...$newIds]));
        } while ($newIds !== []);

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     */
    private function forceDeleteAccountabilities(array $ids): int
    {
        $deleted = 0;

        ClearanceAccountability::withTrashed()
            ->whereIn('id', $ids)
            ->orderByDesc('id')
            ->get()
            ->each(function (ClearanceAccountability $accountability) use (&$deleted): void {
                $accountability->forceDelete();
                $deleted++;
            });

        return $deleted;
    }

    /**
     * @param  list<int>  $ids
     */
    private function forceDeleteStudentClearances(array $ids): int
    {
        $deleted = 0;

        StudentSemesterClearance::withTrashed()
            ->whereIn('id', $ids)
            ->get()
            ->each(function (StudentSemesterClearance $clearance) use (&$deleted): void {
                $clearance->forceDelete();
                $deleted++;
            });

        return $deleted;
    }

    /**
     * @param  list<int>  $ids
     */
    private function forceDeleteClearanceUpdates(array $ids): int
    {
        $deleted = 0;

        ClearanceUpdate::withTrashed()
            ->whereIn('id', $ids)
            ->get()
            ->each(function (ClearanceUpdate $update) use (&$deleted): void {
                $update->forceDelete();
                $deleted++;
            });

        return $deleted;
    }

    /**
     * @return array<string, int>
     */
    private function forceDeleteUserReferences(User $user, int $actorId): array
    {
        Log::warning('Force deleting user references', [
            'actor_id' => $actorId,
            'target_user_id' => $user->id,
        ]);

        $deleted = [
            'society_memberships' => $this->deleteUserReferenceRows('society_memberships', 'student_id', $user),
            'society_event_registrations' => $this->deleteUserReferenceRows('society_event_registrations', 'student_id', $user),
            'society_event_attendances' => $this->deleteUserReferenceRows('society_event_attendances', 'student_id', $user),
        ];

        $unlinked = [
            'faq_feedback' => $this->nullUserReference('faq_feedback', 'user_id', $user),
            'faq_search_logs' => $this->nullUserReference('faq_search_logs', 'user_id', $user),
            'faqs_updated_by' => $this->nullUserReference('faqs', 'updated_by', $user),
            'society_officers' => $this->nullUserReference('society_officers', 'student_id', $user),
            'society_members' => $this->nullUserReference('society_members', 'user_id', $user),
            'society_accreditation_logs' => $this->nullUserReference('society_accreditation_logs', 'user_id', $user),
            'society_accreditation_requests_received' => $this->nullUserReference('society_accreditation_requests', 'received_checked_by', $user),
            'society_accreditation_requests_approved' => $this->nullUserReference('society_accreditation_requests', 'approved_by', $user),
            'society_requirement_submissions_submitted' => $this->nullUserReference('society_requirement_submissions', 'submitted_by', $user),
            'society_requirement_submissions_checked' => $this->nullUserReference('society_requirement_submissions', 'checked_by', $user),
            'societies' => $this->nullUserReference('societies', 'created_by', $user),
        ];

        $reassigned = [
            'faq_categories' => $this->reassignUserReference('faq_categories', 'created_by', $user, $actorId),
            'faqs_created_by' => $this->reassignUserReference('faqs', 'created_by', $user, $actorId),
        ];

        $counts = [
            ...collect($deleted)
                ->mapWithKeys(fn (int $count, string $table): array => ["{$table}_deleted" => $count])
                ->all(),
            ...collect($unlinked)
                ->mapWithKeys(fn (int $count, string $table): array => ["{$table}_unlinked" => $count])
                ->all(),
            ...collect($reassigned)
                ->mapWithKeys(fn (int $count, string $table): array => ["{$table}_reassigned" => $count])
                ->all(),
        ];

        Log::warning('Force deleted user references', [
            'actor_id' => $actorId,
            'target_user_id' => $user->id,
            'reference_counts' => $counts,
        ]);

        return $counts;
    }

    private function deleteUserReferenceRows(string $table, string $column, User $user): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $user->id)
            ->delete();
    }

    private function nullUserReference(string $table, string $column, User $user): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $user->id)
            ->update([$column => null]);
    }

    private function reassignUserReference(string $table, string $column, User $user, int $actorId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $user->id)
            ->update([$column => $actorId]);
    }

    /**
     * @param  class-string  $resource
     * @return array<string, mixed>
     */
    private function resourcePage(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => $resource::collection(collect($paginator->items()))->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => $paginator->linkCollection(),
        ];
    }
}
