<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\SiteCampus;
use App\Models\SiteAcademicTerm;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class SiteAcademicTermController extends Controller
{
    public function store(Request $request, SiteCampus $campus): RedirectResponse
    {
        $this->authorize('site-settings.manage-terms');

        $validated = $request->validate([
            'school_year' => [
                'required',
                'string',
                Rule::unique('site_academic_terms')->where(fn ($query) => $query->where('site_campus_id', $campus->id)->where('semester', $request->semester)),
            ],
            'semester' => 'required|string',
            'term_id' => [
                'nullable',
                'string',
                Rule::unique('site_academic_terms')->where(fn ($query) => $query->where('site_campus_id', $campus->id)),
            ],
            'status' => 'required|in:Active,Inactive,Archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $validated['site_campus_id'] = $campus->id;
        $validated['created_by'] = $request->user()->id;

        if ($validated['status'] === 'Active') {
            SiteAcademicTerm::where('site_campus_id', $campus->id)->update(['status' => 'Inactive']);
        }

        SiteAcademicTerm::create($validated);

        return back()->with('success', 'Academic term created successfully.');
    }

    public function update(Request $request, SiteCampus $campus, SiteAcademicTerm $term): RedirectResponse
    {
        $this->authorize('site-settings.manage-terms');

        $validated = $request->validate([
            'school_year' => [
                'required',
                'string',
                Rule::unique('site_academic_terms')->where(fn ($query) => $query->where('site_campus_id', $campus->id)->where('semester', $request->semester))->ignore($term->id),
            ],
            'semester' => 'required|string',
            'term_id' => [
                'nullable',
                'string',
                Rule::unique('site_academic_terms')->where(fn ($query) => $query->where('site_campus_id', $campus->id))->ignore($term->id),
            ],
            'status' => 'required|in:Active,Inactive,Archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validated['status'] === 'Active' && $term->status !== 'Active') {
            SiteAcademicTerm::where('site_campus_id', $campus->id)->update(['status' => 'Inactive']);
        }

        $validated['updated_by'] = $request->user()->id;

        $term->update($validated);

        return back()->with('success', 'Academic term updated successfully.');
    }

    public function activate(Request $request, SiteCampus $campus, SiteAcademicTerm $term): RedirectResponse
    {
        $this->authorize('site-settings.activate-term');

        SiteAcademicTerm::where('site_campus_id', $campus->id)->update(['status' => 'Inactive']);
        
        $term->update([
            'status' => 'Active',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Academic term activated successfully.');
    }

    public function destroy(SiteCampus $campus, SiteAcademicTerm $term): RedirectResponse
    {
        $this->authorize('site-settings.delete');

        $term->delete();

        return back()->with('success', 'Academic term deleted successfully.');
    }
}
