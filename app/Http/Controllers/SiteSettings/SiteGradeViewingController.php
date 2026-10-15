<?php

namespace App\Http\Controllers\SiteSettings;

use App\Enums\GradeAccessScope;
use App\Enums\GradeEvaluationMode;
use App\Http\Controllers\Controller;
use App\Models\GradeViewingLog;
use App\Models\GradeViewingRule;
use App\Models\SiteCampus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteGradeViewingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SiteSettings/GradeViewing/Index', [
            'rules' => GradeViewingRule::with(['campus', 'creator', 'updater'])
                ->latest()
                ->get(),
            'campuses' => SiteCampus::where('status', 'Active')->get(),
            'logs' => GradeViewingLog::with(['user', 'rule.campus'])
                ->latest()
                ->take(50)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_campus_id' => 'required|exists:site_campuses,id',
            'rule_name' => 'required|string|max:255',
            'grade_evaluation_mode' => ['required', Rule::enum(GradeEvaluationMode::class)],
            'access_scope' => ['required', Rule::enum(GradeAccessScope::class)],
            'bypass_evaluation' => 'required|boolean',
            'show_gwa_gpa' => 'required|boolean',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $rule = GradeViewingRule::create($validated);

        GradeViewingLog::create([
            'rule_id' => $rule->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'changes' => $validated,
            'ip_address' => $request->ip(),
        ]);

        return to_route('site-settings.grade-viewing.index')
            ->with('success', 'Grade viewing rule created successfully.');
    }

    public function update(Request $request, GradeViewingRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'site_campus_id' => 'required|exists:site_campuses,id',
            'rule_name' => 'required|string|max:255',
            'grade_evaluation_mode' => ['required', Rule::enum(GradeEvaluationMode::class)],
            'access_scope' => ['required', Rule::enum(GradeAccessScope::class)],
            'bypass_evaluation' => 'required|boolean',
            'show_gwa_gpa' => 'required|boolean',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $oldValues = $rule->only(array_keys($validated));
        $validated['updated_by'] = auth()->id();

        $rule->update($validated);

        GradeViewingLog::create([
            'rule_id' => $rule->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'changes' => [
                'old' => $oldValues,
                'new' => $validated,
            ],
            'ip_address' => $request->ip(),
        ]);

        return to_route('site-settings.grade-viewing.index')
            ->with('success', 'Grade viewing rule updated successfully.');
    }

    public function destroy(Request $request, GradeViewingRule $rule): RedirectResponse
    {
        abort_if($rule->is_active, 422, 'Deactivate this rule before deleting it.');

        $oldValues = $rule->toArray();

        $rule->delete();

        GradeViewingLog::create([
            'rule_id' => null,
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'changes' => $oldValues,
            'ip_address' => $request->ip(),
        ]);

        return to_route('site-settings.grade-viewing.index')
            ->with('success', 'Grade viewing rule deleted successfully.');
    }

    public function toggle(Request $request, GradeViewingRule $rule): RedirectResponse
    {
        $rule->is_active = ! $rule->is_active;
        $rule->updated_by = auth()->id();
        $rule->save();

        GradeViewingLog::create([
            'rule_id' => $rule->id,
            'user_id' => auth()->id(),
            'action' => 'toggled',
            'changes' => ['is_active' => $rule->is_active],
            'ip_address' => $request->ip(),
        ]);

        return to_route('site-settings.grade-viewing.index')
            ->with('success', 'Rule status updated.');
    }
}
