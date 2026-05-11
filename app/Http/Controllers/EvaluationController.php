<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationFeedbackRequest;
use App\Http\Requests\StoreEvaluationPeriodRequest;
use App\Http\Requests\SubmitEvaluationIntentRequest;
use App\Http\Requests\UpdateEvaluationPeriodRequest;
use App\Models\EvaluationActivityLog;
use App\Models\EvaluationFeedback;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    public function studentIndex(Request $request)
    {
        $this->authorize('viewAny', EvaluationPeriod::class);

        $user = $request->user();
        $activePeriod = EvaluationPeriod::query()
            ->visibleToStudents()
            ->latest('start_date')
            ->first();

        $currentRequest = $activePeriod
            ? EvaluationRequest::query()
                ->with(['period', 'feedbacks.author'])
                ->whereBelongsTo($activePeriod, 'period')
                ->where('student_id', $user->id)
                ->first()
            : null;

        $history = EvaluationRequest::query()
            ->with(['period', 'feedbacks' => fn ($query) => $query->where('visibility', 'student_visible')->latest()])
            ->where('student_id', $user->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Evaluation/StudentIndex', [
            'activePeriod' => $activePeriod ? $this->periodPayload($activePeriod) : null,
            'currentRequest' => $currentRequest ? $this->requestPayload($currentRequest, true) : null,
            'requests' => $this->paginated($history, fn (EvaluationRequest $evaluationRequest): array => $this->requestPayload($evaluationRequest, true)),
            'student' => [
                'name' => $user->name,
                'student_no' => $user->student_no,
            ],
            'can' => [
                'submitIntent' => $user->can('create', EvaluationRequest::class),
            ],
        ]);
    }

    public function submitIntent(SubmitEvaluationIntentRequest $request)
    {
        $period = EvaluationPeriod::query()
            ->visibleToStudents()
            ->whereKey($request->validated('evaluation_period_id'))
            ->firstOrFail();

        abort_if(
            EvaluationRequest::query()
                ->whereBelongsTo($period, 'period')
                ->where('student_id', $request->user()->id)
                ->exists(),
            422,
            'You have already submitted an intent for this evaluation period.'
        );

        $evaluationRequest = EvaluationRequest::create([
            'evaluation_period_id' => $period->id,
            'student_id' => $request->user()->id,
            'student_no' => $request->user()->student_no,
            'intent' => $request->validated('intent'),
            'remarks' => $request->validated('remarks'),
            'status' => EvaluationRequest::STATUS_SUBMITTED,
        ]);

        $this->log($request->user(), 'Intent submitted', "Submitted evaluation intent for {$period->title}", $evaluationRequest);

        return back()->with('success', 'Evaluation intent submitted.');
    }

    public function cancelIntent(EvaluationRequest $evaluationRequest)
    {
        $this->authorize('cancel', $evaluationRequest);

        $evaluationRequest->update(['status' => EvaluationRequest::STATUS_CANCELLED]);
        $this->log(request()->user(), 'Intent cancelled', 'Cancelled evaluation intent.', $evaluationRequest);

        return back()->with('success', 'Evaluation request cancelled.');
    }

    public function adminIndex(Request $request)
    {
        $this->authorize('manage', EvaluationRequest::class);

        $periods = $this->periodsQuery($request)
            ->with('creator:id,name')
            ->latest()
            ->paginate(10, ['*'], 'periods_page')
            ->withQueryString();

        $requests = $this->requestsQuery($request)
            ->with(['period', 'student:id,name,email,student_no,campus_name', 'evaluator:id,name'])
            ->latest()
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString();

        return Inertia::render('Evaluation/AdminIndex', [
            'periods' => $this->paginated($periods, fn (EvaluationPeriod $period): array => [
                ...$this->periodPayload($period),
                'creator' => $period->creator ? $this->userPayload($period->creator) : null,
                'requests_count' => $period->requests_count ?? null,
            ]),
            'requests' => $this->paginated($requests, fn (EvaluationRequest $evaluationRequest): array => $this->requestPayload($evaluationRequest)),
            'filters' => $request->only(['tab', 'period_search', 'request_search', 'period_id', 'status', 'semester']),
            'filterOptions' => [
                'periods' => EvaluationPeriod::query()->latest()->get(['id', 'title', 'academic_year', 'semester']),
                'statuses' => ['submitted', 'under_evaluation', 'needs_action', 'resolved', 'done', 'cancelled'],
                'periodStatuses' => ['draft', 'active', 'closed', 'archived'],
                'semesters' => EvaluationPeriod::query()->select('semester')->distinct()->orderBy('semester')->pluck('semester')->values(),
            ],
            'reports' => $this->reports(),
            'can' => [
                'createPeriod' => $request->user()->can('create', EvaluationPeriod::class),
                'editPeriod' => $request->user()->can('evaluation.edit-period'),
                'deletePeriod' => $request->user()->can('evaluation.delete-period'),
                'evaluate' => $request->user()->can('evaluation.evaluate'),
                'feedback' => $request->user()->can('evaluation.feedback'),
                'markDone' => $request->user()->can('evaluation.mark-done'),
            ],
        ]);
    }

    public function showRequest(EvaluationRequest $evaluationRequest)
    {
        $this->authorize('view', $evaluationRequest);

        $evaluationRequest->load(['period', 'student', 'evaluator', 'completer', 'feedbacks.author']);

        $logs = EvaluationActivityLog::query()
            ->where('model_type', EvaluationRequest::class)
            ->where('model_id', $evaluationRequest->id)
            ->with('user:id,name')
            ->latest()
            ->get();

        return Inertia::render('Evaluation/RequestShow', [
            'request' => $this->requestPayload($evaluationRequest, true),
            'activityLogs' => $logs->map(fn (EvaluationActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at,
                'user' => $log->user ? $this->userPayload($log->user) : null,
            ]),
            'can' => [
                'evaluate' => request()->user()->can('evaluate', $evaluationRequest),
                'feedback' => request()->user()->can('feedback', $evaluationRequest),
                'markDone' => request()->user()->can('markDone', $evaluationRequest),
            ],
        ]);
    }

    public function storePeriod(StoreEvaluationPeriodRequest $request)
    {
        $period = EvaluationPeriod::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $this->log($request->user(), 'Period created', "Created evaluation period {$period->title}", $period);

        return back()->with('success', 'Evaluation period created.');
    }

    public function updatePeriod(UpdateEvaluationPeriodRequest $request, EvaluationPeriod $evaluationPeriod)
    {
        $evaluationPeriod->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        $this->log($request->user(), 'Period updated', "Updated evaluation period {$evaluationPeriod->title}", $evaluationPeriod);

        return back()->with('success', 'Evaluation period updated.');
    }

    public function destroyPeriod(EvaluationPeriod $evaluationPeriod)
    {
        $this->authorize('delete', $evaluationPeriod);

        $evaluationPeriod->delete();
        $this->log(request()->user(), 'Period deleted', "Deleted evaluation period {$evaluationPeriod->title}", $evaluationPeriod);

        return back()->with('success', 'Evaluation period deleted.');
    }

    public function updatePeriodStatus(Request $request, EvaluationPeriod $evaluationPeriod)
    {
        $this->authorize('update', $evaluationPeriod);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'closed', 'archived'])],
        ]);

        if ($validated['status'] === EvaluationPeriod::STATUS_ACTIVE) {
            $duplicate = EvaluationPeriod::query()
                ->where('academic_year', $evaluationPeriod->academic_year)
                ->where('semester', $evaluationPeriod->semester)
                ->where('status', EvaluationPeriod::STATUS_ACTIVE)
                ->whereKeyNot($evaluationPeriod->id)
                ->exists();

            abort_if($duplicate, 422, 'Only one active evaluation period is allowed per semester.');
        }

        $evaluationPeriod->update([
            'status' => $validated['status'],
            'updated_by' => $request->user()->id,
        ]);

        $this->log($request->user(), 'Period '.$validated['status'], "Marked {$evaluationPeriod->title} as {$validated['status']}", $evaluationPeriod);

        return back()->with('success', 'Evaluation period status updated.');
    }

    public function updateRequestStatus(Request $request, EvaluationRequest $evaluationRequest)
    {
        $this->authorize(
            $request->input('status') === EvaluationRequest::STATUS_DONE ? 'markDone' : 'evaluate',
            $evaluationRequest
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_evaluation', 'done'])],
        ]);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === EvaluationRequest::STATUS_UNDER_EVALUATION) {
            $updates['evaluated_by'] = $request->user()->id;
            $updates['evaluated_at'] = now();
        }

        if ($validated['status'] === EvaluationRequest::STATUS_DONE) {
            $updates['done_by'] = $request->user()->id;
            $updates['done_at'] = now();
        }

        $evaluationRequest->update($updates);
        $this->log($request->user(), 'Request '.$validated['status'], "Marked evaluation request as {$validated['status']}", $evaluationRequest);

        return back()->with('success', 'Evaluation request updated.');
    }

    public function addFeedback(EvaluationFeedbackRequest $request, EvaluationRequest $evaluationRequest)
    {
        DB::transaction(function () use ($request, $evaluationRequest): void {
            EvaluationFeedback::create([
                'evaluation_request_id' => $evaluationRequest->id,
                'user_id' => $request->user()->id,
                'message' => $request->validated('message'),
                'visibility' => $request->validated('visibility'),
            ]);

            $updates = [
                'registrar_feedback' => $request->validated('message'),
                'evaluated_by' => $request->user()->id,
                'evaluated_at' => now(),
            ];

            if ($request->validated('status')) {
                $updates['status'] = $request->validated('status');
            }

            $evaluationRequest->update($updates);
            $this->log($request->user(), 'Feedback added', 'Added evaluation feedback.', $evaluationRequest);
        });

        return back()->with('success', 'Feedback saved.');
    }

    private function periodsQuery(Request $request)
    {
        return EvaluationPeriod::query()
            ->withCount('requests')
            ->when($request->period_search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('academic_year', 'like', "%{$search}%")
                        ->orWhere('semester', 'like', "%{$search}%");
                });
            });
    }

    private function requestsQuery(Request $request)
    {
        return EvaluationRequest::query()
            ->when($request->request_search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('student_no', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->period_id, fn ($query, string $periodId) => $query->where('evaluation_period_id', $periodId))
            ->when($request->status, fn ($query, string $status) => $query->where('status', $status))
            ->when($request->semester, fn ($query, string $semester) => $query->whereHas('period', fn ($query) => $query->where('semester', $semester)));
    }

    private function reports(): array
    {
        return [
            'periods_total' => EvaluationPeriod::query()->count(),
            'active_periods' => EvaluationPeriod::query()->where('status', EvaluationPeriod::STATUS_ACTIVE)->count(),
            'requests_total' => EvaluationRequest::query()->count(),
            'done_requests' => EvaluationRequest::query()->where('status', EvaluationRequest::STATUS_DONE)->count(),
            'by_status' => EvaluationRequest::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ];
    }

    private function periodPayload(EvaluationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'title' => $period->title,
            'description' => $period->description,
            'academic_year' => $period->academic_year,
            'semester' => $period->semester,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'status' => $period->status,
            'created_at' => $period->created_at,
            'updated_at' => $period->updated_at,
        ];
    }

    private function requestPayload(EvaluationRequest $evaluationRequest, bool $includeFeedbacks = false): array
    {
        return [
            'id' => $evaluationRequest->id,
            'evaluation_period_id' => $evaluationRequest->evaluation_period_id,
            'student_no' => $evaluationRequest->student_no,
            'intent' => $evaluationRequest->intent,
            'remarks' => $evaluationRequest->remarks,
            'status' => $evaluationRequest->status,
            'registrar_feedback' => $evaluationRequest->registrar_feedback,
            'evaluated_at' => $evaluationRequest->evaluated_at,
            'done_at' => $evaluationRequest->done_at,
            'created_at' => $evaluationRequest->created_at,
            'period' => $evaluationRequest->period ? $this->periodPayload($evaluationRequest->period) : null,
            'student' => $evaluationRequest->student ? $this->userPayload($evaluationRequest->student) : null,
            'evaluator' => $evaluationRequest->evaluator ? $this->userPayload($evaluationRequest->evaluator) : null,
            'feedbacks' => $includeFeedbacks
                ? $evaluationRequest->feedbacks->map(fn (EvaluationFeedback $feedback): array => [
                    'id' => $feedback->id,
                    'message' => $feedback->message,
                    'visibility' => $feedback->visibility,
                    'created_at' => $feedback->created_at,
                    'author' => $feedback->author ? $this->userPayload($feedback->author) : null,
                ])
                : [],
        ];
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? null,
            'student_no' => $user->student_no ?? null,
            'campus_name' => $user->campus_name ?? null,
        ];
    }

    private function paginated(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values()->all(),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function log($user, string $action, string $description, $model): void
    {
        EvaluationActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'model_type' => $model::class,
            'model_id' => $model->id,
        ]);
    }
}
