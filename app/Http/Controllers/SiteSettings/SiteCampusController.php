<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\SiteCampus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteCampusController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('site-settings.view');

        $campuses = SiteCampus::query()
            ->withCount('academicTerms')
            ->when($request->search, function ($query, $search) {
                $query->where('campus_name', 'like', "%{$search}%")
                    ->orWhere('real_campus_id', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('SiteSettings/Campuses/Index', [
            'campuses' => $campuses,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(SiteCampus $campus): Response
    {
        $this->authorize('site-settings.view');

        return Inertia::render('SiteSettings/Campuses/Show', [
            'campus' => $campus->load('academicTerms'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('site-settings.create');

        $validated = $request->validate([
            'campus_name' => 'required|string|max:255',
            'campus_address' => 'nullable|string|max:500',
            'real_campus_id' => 'nullable|string|unique:site_campuses,real_campus_id',
            'status' => 'required|in:Active,Inactive',
            'logo' => 'nullable|image|max:3072',
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $tempPath = $file->getPathname();
                
                if (!empty($tempPath) && file_exists($tempPath)) {
                    $filename = \Illuminate\Support\Str::random(40) . '.' . ($file->getClientOriginalExtension() ?: 'png');
                    // Use move() which is more reliable on some Windows setups
                    $file->move(storage_path('app/public/campus_logos'), $filename);
                    $validated['campus_logo_path'] = 'campus_logos/' . $filename;
                } else {
                    \Log::error('Logo upload failed: getRealPath() returned empty string or false.', [
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    throw ValidationException::withMessages(['logo' => 'The logo could not be processed. Please try again or use a different file.']);
                }
            }
        }

        // Remove logo from validated data as it's not a column
        unset($validated['logo']);

        $validated['created_by'] = $request->user()->id;

        SiteCampus::create($validated);

        return redirect()->route('site-settings.campuses.index')->with('success', 'Campus created successfully.');
    }

    public function update(Request $request, SiteCampus $campus): RedirectResponse
    {
        $this->authorize('site-settings.edit');

        $validated = $request->validate([
            'campus_name' => 'required|string|max:255',
            'campus_address' => 'nullable|string|max:500',
            'real_campus_id' => 'nullable|string|unique:site_campuses,real_campus_id,' . $campus->id,
            'status' => 'required|in:Active,Inactive',
            'logo' => 'nullable|image|max:3072',
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                $tempPath = $file->getPathname();
                
                if (!empty($tempPath) && file_exists($tempPath)) {
                    if (!empty($campus->campus_logo_path)) {
                        Storage::disk('public')->delete($campus->campus_logo_path);
                    }
                    $filename = \Illuminate\Support\Str::random(40) . '.' . ($file->getClientOriginalExtension() ?: 'png');
                    // Use move() which is more reliable on some Windows setups
                    $file->move(storage_path('app/public/campus_logos'), $filename);
                    $validated['campus_logo_path'] = 'campus_logos/' . $filename;
                } else {
                    \Log::error('Logo update failed: getRealPath() returned empty string or false.', [
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    throw ValidationException::withMessages(['logo' => 'The logo could not be processed. Please try again or use a different file.']);
                }
            }
        }

        // Remove logo from validated data as it's not a column
        unset($validated['logo']);

        $validated['updated_by'] = $request->user()->id;

        $campus->update($validated);

        return redirect()->route('site-settings.campuses.show', $campus)->with('success', 'Campus updated successfully.');
    }

    public function destroy(SiteCampus $campus): RedirectResponse
    {
        $this->authorize('site-settings.delete');

        if ($campus->campus_logo_path) {
            Storage::disk('public')->delete($campus->campus_logo_path);
        }

        $campus->delete();

        return redirect()->route('site-settings.campuses.index')->with('success', 'Campus deleted successfully.');
    }
}
