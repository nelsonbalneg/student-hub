<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SsoAuthenticatedSessionController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);
        $request->session()->save();

        return redirect()->away(config('services.sso.base_url').'/oauth/authorize?'.http_build_query([
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => config('services.sso.redirect_uri'),
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

        $user = User::updateOrCreate(
            ['email' => $profile['email']],
            [
                'name' => $profile['name'] ?? $profile['username'] ?? $profile['email'],
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
                'sso_id' => $profile['id'] ?? null,
                'sso_uuid' => $profile['uuid'] ?? null,
                'sso_username' => $profile['username'] ?? null,
                'sso_account_type' => $profile['account_type'] ?? null,
                'sso_avatar' => $profile['avatar'] ?? null,
                'tenant_id' => $profile['tenant_id'] ?? null,
                'campus_id' => $profile['campus_id'] ?? null,
                'campus_name' => $profile['campus_name'] ?? null,
                'student_no' => $profile['student_no'] ?? $profile['student_id'] ?? null,
                'employee_no' => $profile['employee_no'] ?? null,
            ]
        );

        // Assign default roles if the user has none.
        if ($user->roles()->count() === 0) {
            $defaultRole = $this->defaultRoleForAccountType($profile['account_type'] ?? null);

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
        $response = Http::asForm()
            ->acceptJson()
            ->post(config('services.sso.base_url').'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'redirect_uri' => config('services.sso.redirect_uri'),
                'code' => $code,
            ])
            ->throw();

        return $response->json();
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

        Log::warning('SSO sign in failed', [
            'stage' => $stage,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'response_status' => $response?->status(),
            'response_body' => $response?->body(),
        ]);
    }
}
