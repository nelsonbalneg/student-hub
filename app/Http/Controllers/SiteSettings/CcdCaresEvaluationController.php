<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCcdCaresEvaluationSubmissionRequest;
use App\Http\Requests\StoreCcdCaresEvaluationPeriodRequest;
use App\Http\Requests\UpdateCcdCaresEvaluationPeriodRequest;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\EvaluationTemplate;
use App\Services\CcdCaresEvaluationAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CcdCaresEvaluationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('evaluation.templates.view') || $request->user()->hasRole('Super Admin'),
            403,
        );

        return Inertia::render('SiteSettings/CcdCares/Index', [
            'periods' => CcdCaresEvaluationPeriod::query()
                ->with([
                    'template:id,name,status',
                    'creator:id,name',
                    'submissions' => fn ($query) => $query
                        ->with('student:id,name,email,student_no,campus_name')
                        ->latest('submitted_at'),
                ])
                ->withCount('submissions')
                ->latest()
                ->get()
                ->map(fn (CcdCaresEvaluationPeriod $period): array => [
                    ...$period->toArray(),
                    'start_date' => $period->start_date->toDateString(),
                    'end_date' => $period->end_date->toDateString(),
                    'submissions' => $period->submissions->map(fn ($submission): array => [
                        'id' => $submission->id,
                        'submitted_at' => $submission->submitted_at,
                        'student' => [
                            'id' => $submission->student->id,
                            'name' => $submission->student->name,
                            'email' => $submission->student->email,
                            'student_no' => $submission->student->student_no,
                            'campus_name' => $submission->student->campus_name,
                        ],
                    ])->values(),
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

    public function submissions(
        IndexCcdCaresEvaluationSubmissionRequest $request,
        CcdCaresEvaluationPeriod $period,
        CcdCaresEvaluationAnalysisService $analysis,
    ): Response {
        $filters = $request->validated();
        $template = $analysis->prepareTemplate($period->template()->firstOrFail());
        $query = $period->submissions()->with('student:id,name,email,student_no,campus_name');

        $query
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery
                    ->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('student_no', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    }));
            })
            ->when($filters['campus'] ?? null, fn ($query, string $campus) => $query
                ->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_name', $campus)))
            ->when($filters['submitted_from'] ?? null, fn ($query, string $date) => $query
                ->whereDate('submitted_at', '>=', $date))
            ->when($filters['submitted_to'] ?? null, fn ($query, string $date) => $query
                ->whereDate('submitted_at', '<=', $date));

        $submissions = $query
            ->latest('submitted_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
        $submissions->through(fn ($submission): array => $analysis->submission($submission, $template));
        $allSubmissions = $period->submissions()
            ->with('student:id,campus_name')
            ->get();
        $campuses = $period->submissions()
            ->join('users', 'users.id', '=', 'ccd_cares_evaluation_submissions.student_id')
            ->whereNotNull('users.campus_name')
            ->where('users.campus_name', '<>', '')
            ->distinct()
            ->orderBy('users.campus_name')
            ->pluck('users.campus_name');

        return Inertia::render('SiteSettings/CcdCares/Submissions', [
            'period' => [
                'id' => $period->id,
                'title' => $period->title,
                'description' => $period->description,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $period->status,
                'template' => $analysis->templatePayload($template),
            ],
            'submissions' => $submissions,
            'analytics' => $analysis->analytics($allSubmissions, $template),
            'campuses' => $campuses,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'campus' => $filters['campus'] ?? '',
                'submitted_from' => $filters['submitted_from'] ?? '',
                'submitted_to' => $filters['submitted_to'] ?? '',
                'per_page' => $filters['per_page'] ?? 15,
            ],
        ]);
    }

    public function store(StoreCcdCaresEvaluationPeriodRequest $request): RedirectResponse
    {
        $period = DB::transaction(function () use ($request): CcdCaresEvaluationPeriod {
            $validated = $request->validated();
            $this->deactivateOtherPeriodsWhenActive($validated['status']);

            return CcdCaresEvaluationPeriod::query()->create([
                ...$validated,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('site-settings.ccd-cares.index')
            ->with('success', "CCD Cares period {$period->title} created.");
    }

    public function update(
        UpdateCcdCaresEvaluationPeriodRequest $request,
        CcdCaresEvaluationPeriod $period,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $period): void {
            $validated = $request->validated();
            abort_if(
                $period->submissions()->exists()
                    && $period->evaluation_template_id !== (int) $validated['evaluation_template_id'],
                422,
                'The template cannot be changed after students have submitted responses.',
            );
            $this->deactivateOtherPeriodsWhenActive($validated['status'], $period);
            $period->update([
                ...$validated,
                'updated_by' => $request->user()->id,
            ]);
        });

        return to_route('site-settings.ccd-cares.index')
            ->with('success', 'CCD Cares evaluation period updated.');
    }

    public function destroy(Request $request, CcdCaresEvaluationPeriod $period): RedirectResponse
    {
        abort_unless(
            $request->user()->can('evaluation.templates.delete') || $request->user()->hasRole('Super Admin'),
            403,
        );
        abort_if($period->submissions()->exists(), 422, 'Periods with submissions cannot be deleted.');

        $period->delete();

        return to_route('site-settings.ccd-cares.index')
            ->with('success', 'CCD Cares evaluation period deleted.');
    }

    private function deactivateOtherPeriodsWhenActive(
        string $status,
        ?CcdCaresEvaluationPeriod $except = null,
    ): void {
        if ($status !== CcdCaresEvaluationPeriod::STATUS_ACTIVE) {
            return;
        }

        CcdCaresEvaluationPeriod::query()
            ->where('status', CcdCaresEvaluationPeriod::STATUS_ACTIVE)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['status' => CcdCaresEvaluationPeriod::STATUS_CLOSED]);
    }
}
