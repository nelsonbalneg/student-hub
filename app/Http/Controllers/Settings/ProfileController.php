<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AssignCampusRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Office;
use App\Models\SiteCampus;
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
        $user = $request->user();
        $requiresCampusSelection = blank($user->tenant_id)
            || blank($user->campus_id)
            || blank($user->office_id);

        $ssoCampuses = [];
        $officesByCampus = [];

        if ($requiresCampusSelection) {
            $ssoCampuses = $this->campuses($campusDirectory);

            $ssoCampusIds = collect($ssoCampuses)->pluck('campus_id')->filter()->all();

            $siteCampuses = SiteCampus::query()
                ->whereIn('campus_id', $ssoCampusIds)
                ->get();

            $siteCampusesGrouped = $siteCampuses->groupBy('campus_id');

            $allOffices = Office::query()
                ->whereIn('campus_id', $siteCampuses->pluck('id'))
                ->get()
                ->groupBy('campus_id');

            foreach ($ssoCampuses as $ssoCampus) {
                $recordId = $ssoCampus['record_id'];
                $campusId = $ssoCampus['campus_id'];

                $matchingSiteCampuses = $siteCampusesGrouped->get($campusId) ?? collect();

                $offices = collect();
                foreach ($matchingSiteCampuses as $sc) {
                    $scOffices = $allOffices->get($sc->id) ?? collect();
                    $offices = $offices->concat($scOffices);
                }

                $sortedOffices = $offices->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

                $officesByCampus[$recordId] = $sortedOffices->map(fn ($office) => [
                    'id' => $office->id,
                    'name' => $office->name,
                ])->values()->all();
            }
        }

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'requiresCampusSelection' => $requiresCampusSelection,
            'campuses' => $ssoCampuses,
            'officesByCampus' => (object) $officesByCampus,
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

        $office = Office::query()->find($request->integer('office_id'));

        if ($office === null) {
            throw ValidationException::withMessages([
                'office_id' => __('The selected office is invalid.'),
            ]);
        }

        // Verify that the office belongs to the selected campus
        $siteCampus = SiteCampus::query()->find($office->campus_id);
        if ($siteCampus === null || (int) $siteCampus->campus_id !== (int) $campus['campus_id']) {
            throw ValidationException::withMessages([
                'office_id' => __('The selected office does not belong to the selected campus.'),
            ]);
        }

        $request->user()->update([
            'tenant_id' => $campus['tenant_id'],
            'campus_id' => $campus['campus_id'],
            'campus_name' => $campus['name'],
            'office_id' => $office->id,
            'office' => $office->name,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Campus and office information updated.'),
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
