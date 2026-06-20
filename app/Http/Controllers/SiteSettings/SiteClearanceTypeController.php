<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettings\StoreClearanceTypeRequest;
use App\Http\Requests\SiteSettings\UpdateClearanceTypeRequest;
use App\Models\ClearanceType;
use App\Models\SiteCampus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteClearanceTypeController extends Controller
{
    public function store(StoreClearanceTypeRequest $request, SiteCampus $campus): RedirectResponse
    {
        $campus->clearanceTypes()->create($request->validated());

        return back()->with('success', 'Clearance type created successfully.');
    }

    public function update(UpdateClearanceTypeRequest $request, SiteCampus $campus, ClearanceType $clearanceType): RedirectResponse
    {
        $clearanceType->update($request->validated());

        return back()->with('success', 'Clearance type updated successfully.');
    }

    public function destroy(SiteCampus $campus, ClearanceType $clearanceType): RedirectResponse
    {
        $this->authorize('site-settings.delete');
        $clearanceType->delete();

        return back()->with('success', 'Clearance type deleted successfully.');
    }

    public function syncOffices(Request $request, SiteCampus $campus, ClearanceType $clearanceType): RedirectResponse
    {
        $this->authorize('site-settings.edit');

        $validated = $request->validate([
            'office_ids' => ['nullable', 'array'],
            'office_ids.*' => ['integer', 'exists:offices,id'],
        ]);

        $clearanceType->offices()->sync($validated['office_ids'] ?? []);

        return back()->with('success', 'Clearance type configurations updated successfully.');
    }
}
