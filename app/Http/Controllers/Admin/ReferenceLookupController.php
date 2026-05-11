<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClearanceType;
use App\Models\Office;
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
            'offices' => Office::all(),
            'clearanceTypes' => ClearanceType::all(),
            'semesters' => Semester::orderByDesc('academic_year')->orderByDesc('term')->get(),
        ]);
    }

    // Office CRUD
    public function storeOffice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        Office::create($validated);

        return back()->with('success', 'Office created successfully.');
    }

    public function updateOffice(Request $request, Office $office): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $office->update($validated);

        return back()->with('success', 'Office updated successfully.');
    }

    public function destroyOffice(Office $office): RedirectResponse
    {
        $office->delete();
        return back()->with('success', 'Office deleted.');
    }

    // Clearance Type CRUD
    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        ClearanceType::create($validated);

        return back()->with('success', 'Clearance type created successfully.');
    }

    public function updateType(Request $request, ClearanceType $type): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $type->update($validated);

        return back()->with('success', 'Clearance type updated successfully.');
    }

    public function destroyType(ClearanceType $type): RedirectResponse
    {
        $type->delete();
        return back()->with('success', 'Clearance type deleted.');
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

        $campusName = match ((int)$validated['campus_id']) {
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

        return back()->with('success', 'Semester created successfully.');
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

        $campusName = match ((int)$validated['campus_id']) {
            1 => 'Main Campus',
            3 => 'USM KCC',
            4 => 'Advance Education',
            default => 'Other Campus',
        };

        if ($validated['is_active'] && (!$semester->is_active || $semester->campus_id != $validated['campus_id'])) {
            Semester::where('is_active', true)
                ->where('campus_id', $validated['campus_id'])
                ->update(['is_active' => false]);
        }

        $semester->update([
            ...$validated,
            'campus_name' => $campusName,
        ]);

        return back()->with('success', 'Semester updated successfully.');
    }

    public function destroySemester(Semester $semester): RedirectResponse
    {
        $semester->delete();
        return back()->with('success', 'Semester deleted.');
    }
}
