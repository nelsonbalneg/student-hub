<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSarAccessRuleRequest;
use App\Models\SarAccessRule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\SystemFeature;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SarSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function index(Request $request): Response
    {
        $request->user()->can('site_settings.manage') || abort(403);

        $settings = $this->settings->all();

        return Inertia::render('SiteSettings/Sar/Index', [
            'campuses' => SiteCampus::orderBy('campus_name')->get(['id', 'campus_name']),
            'terms' => SiteAcademicTerm::orderByDesc('school_year')->orderBy('semester')->get(['id', 'site_campus_id', 'school_year', 'semester', 'term_id', 'status']),
            'rules' => SarAccessRule::with('academicTerm.campus')->withCount('overrideUsers')->latest()->get(),
            'settings' => [
                'sar_enabled' => isset($settings['sar_enabled']) ? (bool) $settings['sar_enabled'] : true,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->can('site_settings.manage') || abort(403);

        $request->validate([
            'sar_enabled' => ['required', 'boolean'],
        ]);

        $isEnabled = $request->boolean('sar_enabled');

        $this->settings->set('sar_enabled', $isEnabled ? '1' : '0', 'boolean', $request->user()->id);

        $status = $isEnabled ? 'active' : 'maintenance';
        SystemFeature::where('route_name', 'enrollment.student-academic-registration')
            ->update([
                'status' => $status,
                'maintenance_message' => 'Student Academic Registration is currently closed by the administrator.',
            ]);

        SystemFeature::clearCache();

        return redirect()->route('site-settings.sar')->with('success', 'SAR settings updated successfully.');
    }

    public function storeRule(StoreSarAccessRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        SarAccessRule::updateOrCreate(
            ['site_academic_term_id' => $data['site_academic_term_id']],
            ['is_enabled' => $data['is_enabled'], 'updated_by' => $request->user()->id],
        );

        return to_route('site-settings.sar')->with('success', 'SAR access rule saved successfully.');
    }

    public function updateRule(StoreSarAccessRuleRequest $request, SarAccessRule $rule): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($rule, $data, $request): void {
            if ((int) $rule->site_academic_term_id !== (int) $data['site_academic_term_id']) {
                $rule->overrideUsers()->detach();
            }
            $rule->update([
                'site_academic_term_id' => $data['site_academic_term_id'],
                'is_enabled' => $data['is_enabled'],
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('site-settings.sar')->with('success', 'SAR access rule updated successfully.');
    }

    public function setRuleStatus(Request $request, SarAccessRule $rule): RedirectResponse
    {
        abort_unless($request->user()->can('site_settings.manage'), 403);
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $rule->update(['is_enabled' => $data['is_enabled'], 'updated_by' => $request->user()->id]);

        return to_route('site-settings.sar')->with('success', 'SAR access rule updated successfully.');
    }

    public function destroyRule(Request $request, SarAccessRule $rule): RedirectResponse
    {
        abort_unless($request->user()->can('site_settings.manage'), 403);
        abort_if($rule->is_enabled, 422, 'Deactivate this SAR rule before deleting it.');
        $rule->delete();

        return to_route('site-settings.sar')->with('success', 'SAR access rule deleted successfully.');
    }

    public function searchOverrideStudents(Request $request, SarAccessRule $rule): JsonResponse
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
            ->withCount(['sarOverrideRules as override_count' => fn (Builder $rules) => $rules->whereKey($rule->id)]);
        if ($selectedOnly) {
            $query->whereHas('sarOverrideRules', fn (Builder $rules) => $rules->whereKey($rule->id));
        }
        if ($search !== '') {
            $search = str_replace(['%', '_', '['], '', $search);
            $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('student_no', 'like', '%'.$search.'%'));
        }
        $results = $query->orderBy('name')->orderBy('id')->simplePaginate(20);

        return response()->json(['data' => collect($results->items())->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'student_no' => $user->student_no, 'overridden' => $user->override_count > 0])->all(), 'next_page' => $results->hasMorePages() ? $results->currentPage() + 1 : null]);
    }

    public function saveOverrides(Request $request, SarAccessRule $rule): RedirectResponse
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

        return to_route('site-settings.sar')->with('success', 'Student overrides updated successfully.');
    }
}
