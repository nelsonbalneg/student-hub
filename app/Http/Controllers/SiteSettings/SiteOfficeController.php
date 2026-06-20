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
}
