<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AssignCampusRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\SsoCampusDirectory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, SsoCampusDirectory $campusDirectory): Response
    {
        $requiresCampusSelection = blank($request->user()->tenant_id);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'requiresCampusSelection' => $requiresCampusSelection,
            'campuses' => $requiresCampusSelection
                ? $this->campuses($campusDirectory)
                : [],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill([
            'name' => $request->validated('name'),
        ]);

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function assignCampus(
        AssignCampusRequest $request,
        SsoCampusDirectory $campusDirectory,
    ): RedirectResponse {
        $campus = $campusDirectory->find(
            $request->integer('campus_record_id'),
        );

        if ($campus === null) {
            throw ValidationException::withMessages([
                'campus_record_id' => __('The selected campus is unavailable or has incomplete SSO configuration.'),
            ]);
        }

        $request->user()->update([
            'tenant_id' => $campus['tenant_id'],
            'campus_id' => $campus['campus_id'],
            'campus_name' => $campus['name'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campus information updated.'),
        ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * @return array<int, array{record_id: int, name: string, tenant_id: int, campus_id: int}>
     */
    private function campuses(SsoCampusDirectory $campusDirectory): array
    {
        try {
            return $campusDirectory->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to load SSO campuses for profile assignment.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
