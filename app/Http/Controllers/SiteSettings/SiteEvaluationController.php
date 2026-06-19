<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSiteEvaluationResultRequest;
use App\Http\Requests\StoreSiteEvaluationPeriodRequest;
use App\Models\EvaluationTemplate;
use App\Models\SiteEvaluationPeriod;
use App\Services\SiteEvaluationAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiteEvaluationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('evaluation.templates.view') || $request->user()->hasRole('Super Admin'),
            403,
        );

        return Inertia::render('SiteSettings/SiteEvaluation/Index', [
            'periods' => SiteEvaluationPeriod::query()
                ->with('template:id,name')
                ->withCount(['submissions', 'dismissals'])
                ->withSum('dismissals as skips_count', 'skip_count')
                ->latest()
                ->get()
                ->map(fn (SiteEvaluationPeriod $period): array => [
                    'id' => $period->id,
                    'evaluation_template_id' => $period->evaluation_template_id,
                    'title' => $period->title,
                    'description' => $period->description,
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'max_skips' => $period->max_skips,
                    'status' => $period->status,
                    'template' => $period->template,
                    'submissions_count' => $period->submissions_count,
                    'dismissals_count' => $period->dismissals_count,
                    'skips_count' => (int) ($period->skips_count ?? 0),
                ]),
            'templates' => EvaluationTemplate::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'can' => [
                'create' => $request->user()->can('evaluation.templates.create') || $request->user()->hasRole('Super Admin'),
                'update' => $request->user()->can('evaluation.templates.update') || $request->user()->hasRole('Super Admin'),
                'delete' => $request->user()->can('evaluation.templates.delete') || $request->user()->hasRole('Super Admin'),
            ],
        ]);
    }

    public function store(StoreSiteEvaluationPeriodRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $validated = $request->validated();
            $this->closeOtherActivePeriods($validated['status']);
            SiteEvaluationPeriod::query()->create([
                ...$validated,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('site-settings.site-evaluation.index')
            ->with('success', 'Site evaluation period created.');
    }

    public function results(
        IndexSiteEvaluationResultRequest $request,
        SiteEvaluationAnalysisService $analysis,
    ): Response {
        $filters = $request->validated();
        $periods = SiteEvaluationPeriod::query()
            ->with('template:id,name')
            ->withCount(['submissions', 'dismissals'])
            ->withSum('dismissals as skips_count', 'skip_count')
            ->latest('start_date')
            ->get();
        $period = $periods->firstWhere('id', (int) ($filters['period_id'] ?? 0))
            ?? $periods->first();

        if (! $period) {
            return Inertia::render('SiteSettings/SiteEvaluation/Results', [
                'periods' => [],
                'selectedPeriod' => null,
                'submissions' => null,
                'analytics' => null,
                'comments' => null,
                'campuses' => [],
                'filters' => $filters,
            ]);
        }

        $template = $analysis->prepareTemplate($period->template()->firstOrFail());
        $query = $period->submissions()->with('user:id,name,email,student_no,campus_name,user_type');

        $query
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('student_no', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    }));
            })
            ->when($filters['campus'] ?? null, fn ($query, string $campus) => $query
                ->whereHas('user', fn ($userQuery) => $userQuery->where('campus_name', $campus)))
            ->when($filters['submitted_from'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['submitted_to'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '<=', $date));

        $submissions = $query
            ->latest('submitted_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
        $submissions->through(fn ($submission): array => $analysis->submission($submission, $template));

        return Inertia::render('SiteSettings/SiteEvaluation/Results', [
            'periods' => $periods->map(fn (SiteEvaluationPeriod $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'start_date' => $item->start_date->toDateString(),
                'end_date' => $item->end_date->toDateString(),
                'status' => $item->status,
                'max_skips' => $item->max_skips,
                'template_name' => $item->template->name,
                'submissions_count' => $item->submissions_count,
                'dismissals_count' => $item->dismissals_count,
                'skips_count' => (int) ($item->skips_count ?? 0),
            ]),
            'selectedPeriod' => [
                'id' => $period->id,
                'title' => $period->title,
                'description' => $period->description,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status,
                'submissions_count' => $period->submissions_count,
                'dismissals_count' => $period->dismissals_count,
                'skips_count' => (int) ($period->skips_count ?? 0),
                'template' => $analysis->templatePayload($template),
            ],
            'submissions' => $submissions,
            'comments' => $analysis->comments(
                $period,
                $template,
                $filters,
                max(1, $request->integer('comments_page', 1)),
            )->withQueryString(),
            'campuses' => $period->submissions()
                ->join('users', 'users.id', '=', 'site_evaluation_submissions.user_id')
                ->whereNotNull('users.campus_name')
                ->where('users.campus_name', '<>', '')
                ->distinct()
                ->orderBy('users.campus_name')
                ->pluck('users.campus_name'),
            'filters' => [
                'period_id' => $period->id,
                'search' => $filters['search'] ?? '',
                'campus' => $filters['campus'] ?? '',
                'submitted_from' => $filters['submitted_from'] ?? '',
                'submitted_to' => $filters['submitted_to'] ?? '',
                'per_page' => $filters['per_page'] ?? 15,
            ],
        ]);
    }

    public function analytics(
        Request $request,
        SiteEvaluationAnalysisService $analysis,
    ): Response {
        abort_unless(
            $request->user()->can('evaluation.templates.view') || $request->user()->hasRole('Super Admin'),
            403,
        );
        $periods = SiteEvaluationPeriod::query()
            ->with('template:id,name')
            ->latest('start_date')
            ->get();
        $period = $periods->firstWhere('id', $request->integer('period_id'))
            ?? $periods->first();

        if (! $period) {
            return Inertia::render('SiteSettings/SiteEvaluation/Analytics', [
                'periods' => [],
                'selectedPeriod' => null,
                'analytics' => null,
            ]);
        }

        $template = $analysis->prepareTemplate($period->template()->firstOrFail());
        $submissions = $period->submissions()
            ->with('user:id,campus_name')
            ->get();

        return Inertia::render('SiteSettings/SiteEvaluation/Analytics', [
            'periods' => $periods->map(fn (SiteEvaluationPeriod $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'start_date' => $item->start_date->toDateString(),
                'end_date' => $item->end_date->toDateString(),
            ]),
            'selectedPeriod' => [
                'id' => $period->id,
                'title' => $period->title,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'template_name' => $template->name,
            ],
            'analytics' => $analysis->analytics($period, $submissions, $template),
        ]);
    }

    public function update(
        StoreSiteEvaluationPeriodRequest $request,
        SiteEvaluationPeriod $period,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $period): void {
            $validated = $request->validated();
            abort_if(
                $period->submissions()->exists()
                    && $period->evaluation_template_id !== (int) $validated['evaluation_template_id'],
                422,
                'The template cannot be changed after responses have been submitted.',
            );
            $this->closeOtherActivePeriods($validated['status'], $period);
            $period->update([...$validated, 'updated_by' => $request->user()->id]);
        });

        return to_route('site-settings.site-evaluation.index')
            ->with('success', 'Site evaluation period updated.');
    }

    public function destroy(Request $request, SiteEvaluationPeriod $period): RedirectResponse
    {
        abort_unless(
            $request->user()->can('evaluation.templates.delete') || $request->user()->hasRole('Super Admin'),
            403,
        );
        abort_if($period->submissions()->exists(), 422, 'Periods with responses cannot be deleted.');
        $period->delete();

        return to_route('site-settings.site-evaluation.index')
            ->with('success', 'Site evaluation period deleted.');
    }

    private function closeOtherActivePeriods(
        string $status,
        ?SiteEvaluationPeriod $except = null,
    ): void {
        if ($status !== SiteEvaluationPeriod::STATUS_ACTIVE) {
            return;
        }

        SiteEvaluationPeriod::query()
            ->where('status', SiteEvaluationPeriod::STATUS_ACTIVE)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['status' => SiteEvaluationPeriod::STATUS_CLOSED]);
    }
}
