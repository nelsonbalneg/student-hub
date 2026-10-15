<?php

namespace App\Http\Controllers\SiteSettings;

use App\Enums\GradeAccessScope;
use App\Enums\GradeEvaluationMode;
use App\Http\Controllers\Controller;
use App\Models\GradeViewingLog;
use App\Models\GradeViewingRule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SiteGradeViewingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SiteSettings/GradeViewing/Index', [
            'rules' => GradeViewingRule::with(['campus', 'creator', 'updater'])->withCount('overrideUsers')
                ->latest()
                ->get(),
            'terms' => SiteAcademicTerm::whereNotNull('term_id')->orderByDesc('school_year')->orderBy('semester')->get(['id', 'site_campus_id', 'school_year', 'semester', 'term_id']),
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
            'site_academic_term_id' => ['nullable', 'required_if:access_scope,specific_term', 'integer', Rule::exists('site_academic_terms', 'id')->where('site_campus_id', $request->input('site_campus_id'))->whereNotNull('term_id')],
            'bypass_evaluation' => 'required|boolean',
            'show_gwa_gpa' => 'required|boolean',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $validated['site_academic_term_id'] = $validated['access_scope'] === 'specific_term' ? $validated['site_academic_term_id'] : null;

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
            'site_academic_term_id' => ['nullable', 'required_if:access_scope,specific_term', 'integer', Rule::exists('site_academic_terms', 'id')->where('site_campus_id', $request->input('site_campus_id'))->whereNotNull('term_id')],
            'bypass_evaluation' => 'required|boolean',
            'show_gwa_gpa' => 'required|boolean',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $validated['site_academic_term_id'] = $validated['access_scope'] === 'specific_term' ? $validated['site_academic_term_id'] : null;

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

    public function searchOverrideStudents(Request $request, GradeViewingRule $rule): JsonResponse
    {
        abort_unless($request->user()->can('site_settings.manage'), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'selected_only' => ['sometimes', 'boolean']]);
        $search = trim($data['q'] ?? '');
        $selectedOnly = $request->boolean('selected_only');
        if (! $selectedOnly && mb_strlen($search) < 2) {
            return response()->json(['data' => [], 'next_page' => null]);
        }
        $query = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.student_no'])
            ->withCount(['gradeOverrideRules as override_count' => fn (Builder $rules) => $rules->whereKey($rule->id)]);
        if ($selectedOnly) {
            $query->whereHas('gradeOverrideRules', fn (Builder $rules) => $rules->whereKey($rule->id));
        }
        if ($search !== '') {
            $search = str_replace(['%', '_', '['], '', $search);
            $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('student_no', 'like', '%'.$search.'%'));
        }
        $results = $query->orderBy('name')->orderBy('id')->simplePaginate(20);

        return response()->json(['data' => collect($results->items())->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'student_no' => $user->student_no, 'overridden' => $user->override_count > 0])->all(), 'next_page' => $results->hasMorePages() ? $results->currentPage() + 1 : null]);
    }

    public function saveOverrides(Request $request, GradeViewingRule $rule): RedirectResponse
    {
        abort_unless($request->user()->can('site_settings.manage'), 403);
        $data = $request->validate([
            'add_user_ids' => ['present', 'array', 'max:500'], 'add_user_ids.*' => ['integer', 'distinct'],
            'remove_user_ids' => ['present', 'array', 'max:500'], 'remove_user_ids.*' => ['integer', 'distinct'],
        ]);
        $ids = array_unique(array_merge($data['add_user_ids'], $data['remove_user_ids']));
        if (User::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['add_user_ids' => 'One or more selected users no longer exist. Refresh and try again.']);
        }
        DB::transaction(function () use ($rule, $data, $request): void {
            $rule->overrideUsers()->detach($data['remove_user_ids']);
            $grants = array_fill_keys($data['add_user_ids'], ['granted_by' => $request->user()->id]);
            $rule->overrideUsers()->syncWithoutDetaching($grants);
        });

        return to_route('site-settings.grade-viewing.index')->with('success', 'Student overrides updated successfully.');
    }
}
