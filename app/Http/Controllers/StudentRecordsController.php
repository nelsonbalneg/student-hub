<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentRecordsController extends Controller
{
    public function storeAchievement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date_received' => 'required|date',
            'awarder' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $request->user()->achievements()->create($validated);

        return redirect()->route('student-profile.index')->with('success', 'Achievement added successfully.');
    }

    public function updateAchievement(Request $request, Achievement $achievement): RedirectResponse
    {
        if ($achievement->user_id != $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date_received' => 'required|date',
            'awarder' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $achievement->update($validated);

        return redirect()->route('student-profile.index')->with('success', 'Achievement updated successfully.');
    }

    public function deleteAchievement(Request $request, Achievement $achievement): RedirectResponse
    {
        if ($achievement->user_id != $request->user()->id) {
            abort(403);
        }

        $achievement->delete();

        return redirect()->route('student-profile.index')->with('success', 'Achievement deleted successfully.');
    }

    public function storeTraining(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'venue' => 'nullable|string|max:255',
            'organizer' => 'nullable|string|max:255',
        ]);

        $request->user()->trainings()->create($validated);

        return redirect()->route('student-profile.index')->with('success', 'Training/Seminar added successfully.');
    }

    public function updateTraining(Request $request, Training $training): RedirectResponse
    {
        if ($training->user_id != $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'venue' => 'nullable|string|max:255',
            'organizer' => 'nullable|string|max:255',
        ]);

        $training->update($validated);

        return redirect()->route('student-profile.index')->with('success', 'Training/Seminar updated successfully.');
    }

    public function deleteTraining(Request $request, Training $training): RedirectResponse
    {
        if ($training->user_id != $request->user()->id) {
            abort(403);
        }

        $training->delete();

        return redirect()->route('student-profile.index')->with('success', 'Training/Seminar deleted successfully.');
    }
}
