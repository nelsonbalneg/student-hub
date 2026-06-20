<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettings\StoreOfficeRequest;
use App\Http\Requests\SiteSettings\UpdateOfficeRequest;
use App\Models\Office;
use App\Models\SiteCampus;
use Illuminate\Http\RedirectResponse;

class SiteOfficeController extends Controller
{
    public function store(StoreOfficeRequest $request, SiteCampus $campus): RedirectResponse
    {
        $campus->offices()->create($request->validated());

        return back()->with('success', 'Office created successfully.');
    }

    public function update(UpdateOfficeRequest $request, SiteCampus $campus, Office $office): RedirectResponse
    {
        abort_unless($office->campus_id === $campus->id, 404);

        $office->update($request->validated());

        return back()->with('success', 'Office updated successfully.');
    }

    public function destroy(SiteCampus $campus, Office $office): RedirectResponse
    {
        $this->authorize('site-settings.delete');
        abort_unless($office->campus_id === $campus->id, 404);

        $office->delete();

        return back()->with('success', 'Office deleted successfully.');
    }

    public function fetchColleges(SiteCampus $campus, \App\Services\AcademicApiService $apiService): \Illuminate\Http\JsonResponse
    {
        $this->authorize('site-settings.edit');

        // get the tenant_id in site_campuses. Default to '1' if not set
        $tenantId = $campus->tenant_id ?: '1';

        $result = $apiService->getColleges($tenantId);

        if (!empty($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'colleges' => $result['data']
        ]);
    }

    public function importColleges(\Illuminate\Http\Request $request, SiteCampus $campus): RedirectResponse
    {
        $this->authorize('site-settings.edit');

        $validated = $request->validate([
            'colleges' => 'required|array',
            'colleges.*.name' => 'required|string|max:255',
            'colleges.*.code' => 'required|string|max:255',
        ]);

        $importedCount = 0;
        foreach ($validated['colleges'] as $college) {
            $exists = $campus->offices()
                ->where(function ($query) use ($college) {
                    $query->where('name', $college['name'])
                          ->orWhere('code', $college['code']);
                })
                ->exists();

            if (!$exists) {
                $campus->offices()->create([
                    'name' => $college['name'],
                    'code' => $college['code'],
                    'description' => 'Imported from Academic API College List',
                ]);
                $importedCount++;
            }
        }

        return back()->with('success', "{$importedCount} colleges imported as offices successfully.");
    }
}
