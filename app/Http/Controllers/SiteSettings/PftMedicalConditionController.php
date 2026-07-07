<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\PftMedicalCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PftMedicalConditionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SiteSettings/MedicalConditions/Index', [
            'conditions' => PftMedicalCondition::query()
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(PftMedicalCondition::class, 'name')],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        PftMedicalCondition::query()->create($validated);

        return to_route('site-settings.physical-fitness.configuration.index', ['tab' => 'medical-conditions'])
            ->with('success', 'Medical condition created successfully.');
    }

    public function update(Request $request, PftMedicalCondition $medicalCondition): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(PftMedicalCondition::class, 'name')->ignore($medicalCondition->id),
            ],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $medicalCondition->update($validated);

        return to_route('site-settings.physical-fitness.configuration.index', ['tab' => 'medical-conditions'])
            ->with('success', 'Medical condition updated successfully.');
    }

    public function destroy(PftMedicalCondition $medicalCondition): RedirectResponse
    {
        $medicalCondition->delete();

        return to_route('site-settings.physical-fitness.configuration.index', ['tab' => 'medical-conditions'])
            ->with('success', 'Medical condition deleted successfully.');
    }
}
