<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AcademicApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SsoAuthenticatedSessionController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);
        $request->session()->save();
        $sso = $this->ssoConfig();

        return redirect()->away($sso['base_url'].'/oauth/authorize?'.http_build_query([
            'client_id' => $sso['client_id'],
            'redirect_uri' => $sso['redirect_uri'],
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()
                ->route('login')
                ->withErrors(['sso' => $request->input('error_description', 'SSO sign in was cancelled.')]);
        }

        $expectedState = $request->session()->pull('sso_state');

        if ($expectedState && ! hash_equals((string) $expectedState, (string) $request->input('state'))) {
            return redirect()
                ->route('login')
                ->withErrors(['sso' => 'Invalid SSO session. Please try again.']);
        }

        if (! $request->filled('code')) {
            return redirect()
                ->route('login')
                ->withErrors(['sso' => 'Missing SSO authorization code. Please try again.']);
        }

        try {
            $token = $this->requestToken($request->string('code')->toString());
        } catch (Throwable $exception) {
            $this->logSsoFailure('token_exchange_failed', $exception);

            return redirect()
                ->route('login')
                ->withErrors(['sso' => 'Unable to exchange the SSO authorization code. Check the SSO client secret and redirect URL.']);
        }

        try {
            $profile = $this->requestProfile($token['access_token'] ?? '');
        } catch (Throwable $exception) {
            $this->logSsoFailure('profile_fetch_failed', $exception);

            return redirect()
                ->route('login')
                ->withErrors(['sso' => 'Unable to load your SSO profile. Check the SSO user API URL.']);
        }

        if (empty($profile['email'])) {
            return redirect()
                ->route('login')
                ->withErrors(['sso' => 'The SSO account did not provide an email address.']);
        }

        $existingUser = User::query()->where('email', $profile['email'])->first();
        $ssoUser = $this->ssoUser($profile);
        $orgUnitMapping = $this->ssoOrgUnitMapping($profile, $ssoUser);
        $studentNo = $this->profileValue($profile, ['student_no', 'studentNo', 'StudentNo', 'student_id', 'studentId', 'StudentId'])
            ?? $this->profileValue($ssoUser, ['student_no', 'studentNo', 'StudentNo'])
            ?? $existingUser?->student_no;
        $tenantId = $this->profileValue($profile, ['tenant_id', 'tenantId', 'tenantID', 'TenantId', 'TenantID'])
            ?? $this->profileValue($orgUnitMapping, ['tenant_id', 'tenantId', 'TenantId'])
            ?? $existingUser?->tenant_id;
        $campusId = $this->profileValue($profile, ['campus_id', 'campusId', 'campusID', 'CampusId', 'CampusID'])
            ?? $this->profileValue($orgUnitMapping, ['campus_id', 'campusId', 'CampusId'])
            ?? $existingUser?->campus_id;
        $campusName = $this->profileValue($profile, ['campus_name', 'campusName', 'CampusName', 'campus'])
            ?? $this->ssoCampusName($campusId, $tenantId)
            ?? $existingUser?->campus_name;
        $accountType = $this->profileValue($profile, ['account_type', 'accountType'])
            ?? $this->profileValue($ssoUser, ['account_type', 'accountType', 'AccountType'])
            ?? $existingUser?->sso_account_type;
        $userType = $this->profileValue($profile, ['user_type', 'userType', 'user_Type'])
            ?? $this->profileValue($ssoUser, ['user_type', 'userType', 'UserType'])
            ?? $accountType
            ?? $existingUser?->user_type;
        $gender = $this->genderFromProfile($profile);

        if ($gender === null) {
            $gender = $this->genderFromProfile($ssoUser);
        }

        if ($gender === null) {
            $gender = $this->genderFromAcademicProfile($studentNo, $tenantId);
        }

        $userAttributes = [
            'name' => $profile['name'] ?? $profile['username'] ?? $profile['email'],
            'password' => Hash::make(Str::random(48)),
            'email_verified_at' => now(),
            'sso_id' => $this->profileValue($profile, ['id']) ?? $this->profileValue($ssoUser, ['id']),
            'sso_uuid' => $this->profileValue($profile, ['uuid']) ?? $this->profileValue($ssoUser, ['uuid']),
            'sso_username' => $this->profileValue($profile, ['username']) ?? $this->profileValue($ssoUser, ['username']),
            'sso_account_type' => $accountType,
            'sso_avatar' => $this->profileValue($profile, ['avatar']) ?? $this->profileValue($ssoUser, ['avatar']),
            'tenant_id' => $tenantId,
            'campus_id' => $campusId,
            'campus_name' => $campusName,
            'user_type' => $userType,
            'student_no' => $studentNo,
            'employee_no' => $this->profileValue($profile, ['employee_no', 'employeeNo'])
                ?? $this->profileValue($ssoUser, ['employee_no', 'employeeNo', 'EmployeeNo'])
                ?? $existingUser?->employee_no,
        ];

        $userAttributes = array_filter($userAttributes, fn (mixed $value): bool => $value !== null);

        if ($gender !== null) {
            $userAttributes['gender'] = $gender;
        }

        $user = User::updateOrCreate(
            ['email' => $profile['email']],
            $userAttributes
        );

        // Assign default roles if the user has none.
        if ($user->roles()->count() === 0) {
            $defaultRole = $this->defaultRoleForAccountType($accountType);

            if ($defaultRole !== null) {
                $user->assignRole($defaultRole);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function requestToken(string $code): array
    {
        $sso = $this->ssoConfig();

        $response = Http::asForm()
            ->acceptJson()
            ->post($sso['base_url'].'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $sso['client_id'],
                'client_secret' => $sso['client_secret'],
                'redirect_uri' => $sso['redirect_uri'],
                'code' => $code,
            ])
            ->throw();

        return $response->json();
    }

    private function ssoConfig(): array
    {
        return [
            'base_url' => rtrim(trim((string) config('services.sso.base_url')), '/'),
            'client_id' => trim((string) config('services.sso.client_id')),
            'client_secret' => trim((string) config('services.sso.client_secret')),
            'redirect_uri' => trim((string) config('services.sso.redirect_uri')),
        ];
    }

    private function requestProfile(string $accessToken): array
    {
        $userUrl = config('services.sso.user_url') ?: rtrim(config('services.sso.base_url'), '/').'/api/user';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get($userUrl)
            ->throw();

        return $response->json();
    }

    private function genderFromProfile(array $profile): ?string
    {
        return $this->normalizeGender($this->profileValue($profile, ['gender', 'sex']));
    }

    private function genderFromAcademicProfile(mixed $studentNo, mixed $tenantId): ?string
    {
        if (blank($studentNo) || blank($tenantId)) {
            return null;
        }

        $profile = app(AcademicApiService::class)->profileForStudent((string) $studentNo, (string) $tenantId);

        return $this->normalizeGender(data_get($profile, 'data.gender'));
    }

    private function ssoUser(array $profile): array
    {
        try {
            if (! Schema::connection('sso_sqlsrv')->hasTable('users')) {
                return [];
            }

            $columns = Schema::connection('sso_sqlsrv')->getColumnListing('users');
            $id = $this->profileValue($profile, ['id']);
            $email = $this->profileValue($profile, ['email']);
            $uuid = $this->profileValue($profile, ['uuid']);
            $username = $this->profileValue($profile, ['username']);
            $studentNo = $this->profileValue($profile, ['student_no', 'studentNo', 'StudentNo', 'student_id', 'studentId', 'StudentId']);
            $hasConstraint = false;

            $query = DB::connection('sso_sqlsrv')->table('users');
            $query->where(function ($query) use ($columns, $id, $email, $uuid, $username, $studentNo, &$hasConstraint): void {
                $this->orWhereExistingColumn($query, $columns, 'id', $id, $hasConstraint);
                $this->orWhereExistingColumn($query, $columns, 'email', $email, $hasConstraint);
                $this->orWhereExistingColumn($query, $columns, 'uuid', $uuid, $hasConstraint);
                $this->orWhereExistingColumn($query, $columns, 'username', $username, $hasConstraint);
                $this->orWhereExistingColumn($query, $columns, 'student_no', $studentNo, $hasConstraint);
            });

            if (! $hasConstraint) {
                return [];
            }

            return (array) $query->first();
        } catch (Throwable $exception) {
            Log::warning('Unable to load SSO user record', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'sso_user_id' => $profile['id'] ?? null,
            ]);

            return [];
        }
    }

    private function ssoOrgUnitMapping(array $profile, array $ssoUser): array
    {
        try {
            if (! Schema::connection('sso_sqlsrv')->hasTable('org_unit_type_mappings')) {
                return [];
            }

            $orgUnitPath = $this->profileValue($ssoUser, ['org_unit_path', 'orgUnitPath', 'OrgUnitPath'])
                ?? $this->profileValue($profile, ['org_unit_path', 'orgUnitPath', 'OrgUnitPath']);

            if (blank($orgUnitPath)) {
                return [];
            }

            $accountType = $this->profileValue($profile, ['account_type', 'accountType'])
                ?? $this->profileValue($ssoUser, ['account_type', 'accountType', 'AccountType']);

            $query = DB::connection('sso_sqlsrv')
                ->table('org_unit_type_mappings')
                ->where('org_unit_path', $orgUnitPath)
                ->where('is_active', 1);

            if (filled($accountType)) {
                $query->orderByRaw('CASE WHEN account_type = ? THEN 0 ELSE 1 END', [$accountType]);
            }

            return (array) $query
                ->orderBy('priority')
                ->orderBy('id')
                ->first();
        } catch (Throwable $exception) {
            Log::warning('Unable to load SSO org unit mapping', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'sso_user_id' => $profile['id'] ?? null,
            ]);

            return [];
        }
    }

    private function ssoCampusName(mixed $campusId, mixed $tenantId): ?string
    {
        if (blank($campusId)) {
            return null;
        }

        try {
            if (! Schema::connection('sso_sqlsrv')->hasTable('campuses')) {
                return null;
            }

            $query = DB::connection('sso_sqlsrv')
                ->table('campuses')
                ->where(function ($query) use ($campusId): void {
                    $query->where('id', $campusId)
                        ->orWhere('real_campus_id', $campusId);
                });

            if (filled($tenantId)) {
                $query->where('tenant_id', $tenantId);
            }

            return $this->profileValue((array) $query->first(), ['campus_name', 'campusName', 'CampusName']);
        } catch (Throwable $exception) {
            Log::warning('Unable to load SSO campus name', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }
    }

    private function normalizeGender(mixed $gender): ?string
    {
        $normalized = Str::of((string) $gender)->trim()->lower()->toString();

        return match ($normalized) {
            'm', 'male' => 'M',
            'f', 'female' => 'F',
            default => null,
        };
    }

    private function profileValue(array $profile, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($profile, $key);

            if ($value !== null && $value !== '') {
                return is_string($value) ? trim($value) : $value;
            }
        }

        return null;
    }

    private function orWhereExistingColumn($query, array $columns, string $column, mixed $value, bool &$hasConstraint): void
    {
        if (blank($value) || ! in_array($column, $columns, true)) {
            return;
        }

        $query->orWhere($column, $value);
        $hasConstraint = true;
    }

    private function defaultRoleForAccountType(?string $accountType): ?string
    {
        return match (Str::of($accountType ?? 'student')->lower()->replace(['_', '-'], ' ')->squish()->toString()) {
            'admin', 'super admin' => 'Super Admin',
            'employee', 'student' => 'Student',
            default => null,
        };
    }

    private function logSsoFailure(string $stage, Throwable $exception): void
    {
        $response = $exception instanceof RequestException ? $exception->response : null;

        $sso = $this->ssoConfig();

        Log::error('SSO sign in failed', [
            'stage' => $stage,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'response_status' => $response?->status(),
            'response_body' => $response?->body(),
            'sso_base_url' => $sso['base_url'],
            'sso_client_id' => $sso['client_id'],
            'sso_redirect_uri' => $sso['redirect_uri'],
        ]);
    }
}
