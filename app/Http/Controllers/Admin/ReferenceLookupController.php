<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceLookupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ReferenceLookups/Index', [
            'semesters' => Semester::orderByDesc('academic_year')->orderByDesc('term')->get(),
        ]);
    }

    // Semester CRUD
    public function storeSemester(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:50'],
            'term' => ['required', 'string', 'max:50'],
            'campus_id' => ['required', 'integer', 'in:1,3,4'],
            'is_active' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $campusName = match ((int) $validated['campus_id']) {
            1 => 'Main Campus',
            3 => 'USM KCC',
            4 => 'Advance Education',
            default => 'Other Campus',
        };

        if ($validated['is_active']) {
            Semester::where('is_active', true)
                ->where('campus_id', $validated['campus_id'])
                ->update(['is_active' => false]);
        }

        Semester::create([
            ...$validated,
            'campus_name' => $campusName,
        ]);

        return to_route('admin.reference-lookups.index')->with('success', 'Semester created successfully.');
    }

    public function updateSemester(Request $request, Semester $semester): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:50'],
            'term' => ['required', 'string', 'max:50'],
            'campus_id' => ['required', 'integer', 'in:1,3,4'],
            'is_active' => ['boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $campusName = match ((int) $validated['campus_id']) {
            1 => 'Main Campus',
            3 => 'USM KCC',
            4 => 'Advance Education',
            default => 'Other Campus',
        };

        if ($validated['is_active'] && (! $semester->is_active || $semester->campus_id != $validated['campus_id'])) {
            Semester::where('is_active', true)
                ->where('campus_id', $validated['campus_id'])
                ->update(['is_active' => false]);
        }

        $semester->update([
            ...$validated,
            'campus_name' => $campusName,
        ]);

        return to_route('admin.reference-lookups.index')->with('success', 'Semester updated successfully.');
    }

    public function destroySemester(Semester $semester): RedirectResponse
    {
        $semester->delete();

        return to_route('admin.reference-lookups.index')->with('success', 'Semester deleted.');
    }
}
