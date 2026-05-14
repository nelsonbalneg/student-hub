<?php

namespace App\Http\Controllers\FileVault;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileVault\AssignStudentDossierRequest;
use App\Http\Requests\FileVault\StoreStudentDossierRequest;
use App\Http\Requests\FileVault\TransitionStudentDossierRequest;
use App\Http\Requests\FileVault\UpdateStudentDossierRequest;
use App\Models\DossierAccessLog;
use App\Models\DossierAssignment;
use App\Models\DossierStatusHistory;
use App\Models\StudentDossier;
use App\Models\User;
use App\Services\FileVault\DossierChecklistService;
use App\Services\FileVault\DossierNumberGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentDossierController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_AUDIT_ACTIONS = [
        'create',
        'view',
        'update',
        'status_change',
        'approve',
        'release',
        'archive',
        'assign',
        'upload',
        'verify',
        'download',
        'delete_document',
    ];

    public function __construct(
        private readonly DossierNumberGeneratorService $dossierNumberGeneratorService,
        private readonly DossierChecklistService $dossierChecklistService,
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $this->authorize('viewAny', StudentDossier::class);

        $query = StudentDossier::query()
            ->with(['student:id,name,student_no,email', 'owner:id,name'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('dossier_number', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('student_no', 'like', "%{$search}%");
                    });
            });
        }

        $query
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($builder) => $builder->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('owner_id'), fn ($builder) => $builder->where('current_owner_id', $request->integer('owner_id')));

        $this->applyQueueViewFilter($query, $request);

        $dossiers = $query->paginate(15)->withQueryString();

        if (! $request->expectsJson()) {
            return Inertia::render('FileVault/Dossiers/Index', [
                'dossiers' => $this->paginated($dossiers, fn (StudentDossier $dossier): array => $this->dossierSummaryPayload($dossier)),
                'filters' => $request->only(['search', 'status', 'priority', 'owner_id', 'view']),
                'statuses' => StudentDossier::statuses(),
                'priorities' => StudentDossier::priorities(),
                'owners' => $this->ownerOptions(),
                'students' => $this->studentOptions(),
                'transactionTypes' => $this->transactionTypeOptions(),
                'queueViews' => $this->queueViewOptions(),
                'queueStats' => $this->queueStats($request->user()->id),
                'can' => [
                    'create' => $request->user()->can('create', StudentDossier::class),
                    'update' => $request->user()->can('dossiers.update'),
                    'transition' => $request->user()->can('dossiers.transition'),
                    'archive' => $request->user()->can('dossiers.archive'),
                ],
            ]);
        }

        return response()->json([
            'dossiers' => $dossiers,
            'filters' => $request->only(['search', 'status', 'priority', 'owner_id', 'view']),
        ]);
    }

    public function store(StoreStudentDossierRequest $request): JsonResponse|RedirectResponse
    {
        $payload = $request->validated();
        $user = $request->user();

        $dossier = DB::transaction(function () use ($payload, $user): StudentDossier {
            $dossier = StudentDossier::query()->create([
                ...$payload,
                'dossier_number' => $this->dossierNumberGeneratorService->generate(),
                'status' => StudentDossier::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'current_owner_id' => $payload['current_owner_id'] ?? $user->id,
            ]);

            DossierStatusHistory::query()->create([
                'student_dossier_id' => $dossier->id,
                'from_status' => null,
                'to_status' => StudentDossier::STATUS_DRAFT,
                'remarks' => 'Initial dossier creation.',
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $this->logAccess($dossier, 'create', $user->id, [
                'transaction_type' => $dossier->transaction_type,
            ]);

            return $dossier;
        });

        if (! $request->expectsJson()) {
            return redirect()
                ->route('file-vault.dossiers.show', $dossier)
                ->with('success', 'Dossier created successfully.');
        }

        return response()->json([
            'message' => 'Dossier created successfully.',
            'dossier' => $dossier->load(['student:id,name,student_no,email', 'owner:id,name']),
        ], 201);
    }

    public function show(Request $request, StudentDossier $studentDossier): JsonResponse|Response
    {
        $this->authorize('view', $studentDossier);

        $studentDossier->load([
            'student:id,name,student_no,email',
            'owner:id,name',
            'approver:id,name',
            'documents' => fn ($query) => $query->latest(),
            'statusHistories' => fn ($query) => $query->with('changer:id,name')->latest('changed_at'),
            'assignments' => fn ($query) => $query->with(['assignee:id,name', 'assigner:id,name'])->latest('assigned_at'),
        ]);

        $this->logAccess($studentDossier, 'view', $request->user()->id);

        if (! $request->expectsJson()) {
            return Inertia::render('FileVault/Dossiers/Show', [
                'dossier' => $this->dossierDetailPayload($studentDossier),
                'statuses' => StudentDossier::statuses(),
                'priorities' => StudentDossier::priorities(),
                'transitionOptions' => StudentDossier::allowedTransitions()[$studentDossier->status] ?? [],
                'owners' => $this->ownerOptions(),
                'requiredDocumentTypes' => $this->dossierChecklistService->requiredDocumentTypes($studentDossier),
                'can' => [
                    'update' => $request->user()->can('update', $studentDossier),
                    'assign' => $request->user()->can('assign', $studentDossier),
                    'transition' => $request->user()->can('transition', $studentDossier),
                    'archive' => $request->user()->can('archive', $studentDossier),
                    'release' => $request->user()->can('release', $studentDossier),
                    'verifyDocument' => $request->user()->can('dossiers.documents.verify'),
                    'uploadDocument' => $request->user()->can('dossiers.documents.upload'),
                    'downloadDocument' => $request->user()->can('dossiers.download'),
                    'approve' => $request->user()->can('dossiers.approve'),
                ],
            ]);
        }

        return response()->json([
            'dossier' => $studentDossier,
        ]);
    }

    public function update(UpdateStudentDossierRequest $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        $updates = $request->validated();
        $updates['updated_by'] = $request->user()->id;

        $studentDossier->update($updates);
        $this->logAccess($studentDossier, 'update', $request->user()->id, $updates);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Dossier updated successfully.');
        }

        return response()->json([
            'message' => 'Dossier updated successfully.',
            'dossier' => $studentDossier->fresh(['student:id,name,student_no,email', 'owner:id,name']),
        ]);
    }

    public function updateStatus(TransitionStudentDossierRequest $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        $targetStatus = $request->validated('status');
        $remarks = $request->validated('remarks');

        $this->ensureAllowedTransition($studentDossier, $targetStatus);
        $this->ensureTransitionConstraints($request, $studentDossier, $targetStatus, $remarks);

        DB::transaction(function () use ($studentDossier, $targetStatus, $remarks, $request): void {
            $fromStatus = $studentDossier->status;

            $updates = [
                'status' => $targetStatus,
                'updated_by' => $request->user()->id,
            ];

            if ($targetStatus === StudentDossier::STATUS_RELEASED) {
                $updates['released_at'] = now();
            }

            if ($targetStatus === StudentDossier::STATUS_ARCHIVED) {
                $updates['archived_at'] = now();
            }

            $studentDossier->update($updates);

            DossierStatusHistory::query()->create([
                'student_dossier_id' => $studentDossier->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'remarks' => $remarks,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            $this->logAccess($studentDossier, $targetStatus === StudentDossier::STATUS_RELEASED ? 'release' : 'status_change', $request->user()->id, [
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'remarks' => $remarks,
                'recipient_or_requestor' => $request->validated('recipient_or_requestor'),
                'legal_basis' => $request->validated('legal_basis'),
                'legitimate_interest' => $request->validated('legitimate_interest'),
            ]);
        });

        if (! $request->expectsJson()) {
            return back()->with('success', 'Dossier status updated successfully.');
        }

        return response()->json([
            'message' => 'Dossier status updated successfully.',
            'status' => $studentDossier->fresh()->status,
        ]);
    }

    public function assign(AssignStudentDossierRequest $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $studentDossier): void {
            $studentDossier->update([
                'current_owner_id' => $request->validated('assigned_to'),
                'updated_by' => $request->user()->id,
            ]);

            DossierAssignment::query()->create([
                'student_dossier_id' => $studentDossier->id,
                'assigned_to' => $request->validated('assigned_to'),
                'assigned_by' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'assigned_at' => now(),
            ]);

            $this->logAccess($studentDossier, 'assign', $request->user()->id, [
                'assigned_to' => $request->validated('assigned_to'),
                'reason' => $request->validated('reason'),
            ]);
        });

        if (! $request->expectsJson()) {
            return back()->with('success', 'Dossier assigned successfully.');
        }

        return response()->json([
            'message' => 'Dossier assigned successfully.',
        ]);
    }

    public function archive(Request $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        $this->authorize('archive', $studentDossier);

        abort_unless($studentDossier->status === StudentDossier::STATUS_RELEASED, 422, 'Only released dossiers can be archived.');
        abort_if($studentDossier->released_at === null, 422, 'Dossier must be released before archiving.');

        $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $studentDossier): void {
            $fromStatus = $studentDossier->status;

            $studentDossier->update([
                'status' => StudentDossier::STATUS_ARCHIVED,
                'archived_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            DossierStatusHistory::query()->create([
                'student_dossier_id' => $studentDossier->id,
                'from_status' => $fromStatus,
                'to_status' => StudentDossier::STATUS_ARCHIVED,
                'remarks' => $request->input('remarks'),
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            $this->logAccess($studentDossier, 'archive', $request->user()->id, [
                'from_status' => $fromStatus,
                'to_status' => StudentDossier::STATUS_ARCHIVED,
                'remarks' => $request->input('remarks'),
            ]);
        });

        if (! $request->expectsJson()) {
            return back()->with('success', 'Dossier archived successfully.');
        }

        return response()->json([
            'message' => 'Dossier archived successfully.',
        ]);
    }

    public function approve(Request $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        $this->authorize('release', $studentDossier);
        abort_unless($request->user()->can('dossiers.approve'), 403);
        abort_unless($studentDossier->status === StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL, 422, 'Only dossiers for supervisor approval can be approved.');
        abort_unless($this->dossierChecklistService->isComplete($studentDossier), 422, 'All required documents must be uploaded and verified before approval.');

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $studentDossier, $validated): void {
            $studentDossier->update([
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_remarks' => $validated['remarks'],
                'updated_by' => $request->user()->id,
            ]);

            $this->logAccess($studentDossier, 'approve', $request->user()->id, [
                'remarks' => $validated['remarks'],
            ]);
        });

        if (! $request->expectsJson()) {
            return back()->with('success', 'Dossier approved successfully.');
        }

        return response()->json([
            'message' => 'Dossier approved successfully.',
        ]);
    }

    public function auditLogs(StudentDossier $studentDossier): JsonResponse|Response
    {
        $this->authorize('audit', $studentDossier);
        $studentDossier->loadMissing('student:id,name,student_no');

        $query = $studentDossier->accessLogs()
            ->with('actor:id,name,email')
            ->latest('occurred_at');

        request()->whenFilled('action', fn () => $query->where('action', request()->string('action')->toString()));
        request()->whenFilled('actor_id', fn () => $query->where('actor_id', request()->integer('actor_id')));
        request()->whenFilled('date_from', fn () => $query->whereDate('occurred_at', '>=', request()->string('date_from')->toString()));
        request()->whenFilled('date_to', fn () => $query->whereDate('occurred_at', '<=', request()->string('date_to')->toString()));

        $logs = $query->paginate(20)->withQueryString();

        if (! request()->expectsJson()) {
            $actors = $studentDossier->accessLogs()
                ->with('actor:id,name')
                ->get()
                ->pluck('actor')
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->all();

            return Inertia::render('FileVault/Dossiers/AuditLogs', [
                'dossier' => [
                    'id' => $studentDossier->id,
                    'dossier_number' => $studentDossier->dossier_number,
                    'student_name' => $studentDossier->student?->name,
                    'student_no' => $studentDossier->student?->student_no,
                ],
                'logs' => $this->paginated($logs, fn (DossierAccessLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                    'recipient_or_requestor' => $log->recipient_or_requestor,
                    'legal_basis' => $log->legal_basis,
                    'legitimate_interest' => $log->legitimate_interest,
                    'metadata_json' => $log->metadata_json,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                ]),
                'filters' => request()->only(['action', 'actor_id', 'date_from', 'date_to']),
                'actions' => self::ALLOWED_AUDIT_ACTIONS,
                'actors' => $actors,
            ]);
        }

        return response()->json([
            'logs' => $logs,
        ]);
    }

    private function ensureAllowedTransition(StudentDossier $dossier, string $targetStatus): void
    {
        $allowedTransitions = StudentDossier::allowedTransitions()[$dossier->status] ?? [];

        if (! in_array($targetStatus, $allowedTransitions, true)) {
            abort(422, "Transition from {$dossier->status} to {$targetStatus} is not allowed.");
        }
    }

    private function ensureTransitionConstraints(Request $request, StudentDossier $dossier, string $targetStatus, ?string $remarks): void
    {
        if (in_array($targetStatus, [StudentDossier::STATUS_INCOMPLETE, StudentDossier::STATUS_ON_HOLD], true) && blank($remarks)) {
            abort(422, 'Remarks are required when setting dossier to incomplete or on hold.');
        }

        if ($targetStatus === StudentDossier::STATUS_FOR_INTAKE_REVIEW && $dossier->status === StudentDossier::STATUS_INCOMPLETE && blank($remarks)) {
            abort(422, 'Remarks are required when resubmitting an incomplete dossier.');
        }

        if ($targetStatus === StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL && ! $this->dossierChecklistService->isComplete($dossier)) {
            abort(422, 'All required documents must be uploaded and verified before supervisor approval.');
        }

        if ($targetStatus === StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL) {
            abort_unless($request->user()->can('dossiers.submit-review'), 403);
        }

        if ($targetStatus === StudentDossier::STATUS_RELEASED) {
            abort_unless($request->user()->can('dossiers.approve') && $request->user()->can('release', $dossier), 403);
            abort_unless($dossier->approved_at !== null && $dossier->approved_by !== null, 422, 'Dossier must be approved before release.');
            abort_unless($this->dossierChecklistService->isComplete($dossier), 422, 'All required documents must be uploaded and verified before release.');
            abort_if(blank($remarks), 422, 'Release remarks are required.');
            abort_if(blank($request->input('recipient_or_requestor')), 422, 'Recipient or requestor is required when releasing a dossier.');
            abort_if(blank($request->input('legal_basis')), 422, 'Legal basis is required when releasing a dossier.');
            abort_if(blank($request->input('legitimate_interest')), 422, 'Legitimate interest is required when releasing a dossier.');
            abort_if($dossier->current_owner_id === null, 422, 'Dossier must have an assigned owner before release.');
        }

        if ($targetStatus === StudentDossier::STATUS_ARCHIVED && $dossier->released_at === null) {
            abort(422, 'Dossier must be released before archiving.');
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, TModel>  $page
     * @param  callable(TModel): array<string, mixed>  $transform
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     links: array<int, array{url: ?string, label: string, active: bool}>,
     *     meta: array{current_page: int, last_page: int, from: ?int, to: ?int, total: int}
     * }
     */
    private function paginated($page, callable $transform): array
    {
        return [
            'data' => $page->getCollection()->map($transform)->values()->all(),
            'links' => collect($page->linkCollection())->map(fn ($link): array => [
                'url' => $link['url'],
                'label' => $link['label'],
                'active' => $link['active'],
            ])->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, student_no: string|null, status: string, priority: string, transaction_type: string, current_owner_id: int|null, intake_date: string|null, completion_due_at: string|null, student: array{id: int, name: string, student_no: string|null, email: string|null}|null, owner: array{id: int, name: string}|null, updated_at: string|null}>
     */
    private function dossierSummaryPayload(StudentDossier $dossier): array
    {
        return [
            'id' => $dossier->id,
            'name' => $dossier->dossier_number,
            'student_no' => $dossier->student?->student_no,
            'status' => $dossier->status,
            'priority' => $dossier->priority,
            'transaction_type' => $dossier->transaction_type,
            'current_owner_id' => $dossier->current_owner_id,
            'intake_date' => $dossier->intake_date?->toDateString(),
            'completion_due_at' => $dossier->completion_due_at?->toIso8601String(),
            'is_overdue' => $dossier->completion_due_at !== null
                && $dossier->completion_due_at->isPast()
                && ! in_array($dossier->status, [StudentDossier::STATUS_RELEASED, StudentDossier::STATUS_ARCHIVED], true),
            'student' => $dossier->student ? [
                'id' => $dossier->student->id,
                'name' => $dossier->student->name,
                'student_no' => $dossier->student->student_no,
                'email' => $dossier->student->email,
            ] : null,
            'owner' => $dossier->owner ? [
                'id' => $dossier->owner->id,
                'name' => $dossier->owner->name,
            ] : null,
            'updated_at' => $dossier->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dossierDetailPayload(StudentDossier $dossier): array
    {
        return [
            'id' => $dossier->id,
            'dossier_number' => $dossier->dossier_number,
            'transaction_type' => $dossier->transaction_type,
            'status' => $dossier->status,
            'priority' => $dossier->priority,
            'current_owner_id' => $dossier->current_owner_id,
            'intake_date' => $dossier->intake_date?->toDateString(),
            'completion_due_at' => $dossier->completion_due_at?->toIso8601String(),
            'released_at' => $dossier->released_at?->toIso8601String(),
            'archived_at' => $dossier->archived_at?->toIso8601String(),
            'approved_at' => $dossier->approved_at?->toIso8601String(),
            'approval_remarks' => $dossier->approval_remarks,
            'approver' => $dossier->approver ? [
                'id' => $dossier->approver->id,
                'name' => $dossier->approver->name,
            ] : null,
            'student' => $dossier->student ? [
                'id' => $dossier->student->id,
                'name' => $dossier->student->name,
                'student_no' => $dossier->student->student_no,
                'email' => $dossier->student->email,
            ] : null,
            'owner' => $dossier->owner ? [
                'id' => $dossier->owner->id,
                'name' => $dossier->owner->name,
            ] : null,
            'documents' => $dossier->documents->map(fn ($document): array => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_code' => $document->document_code,
                'version' => $document->version,
                'is_required' => $document->is_required,
                'is_verified' => $document->is_verified,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'scan_status' => $document->scan_status,
                'scanned_at' => $document->scanned_at?->toIso8601String(),
                'scan_message' => $document->scan_message,
                'uploaded_by' => $document->uploaded_by,
                'verified_by' => $document->verified_by,
                'verified_at' => $document->verified_at?->toIso8601String(),
                'created_at' => $document->created_at?->toIso8601String(),
            ])->values()->all(),
            'status_histories' => $dossier->statusHistories->map(fn ($history): array => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'remarks' => $history->remarks,
                'changed_at' => $history->changed_at?->toIso8601String(),
                'changer' => $history->changer ? [
                    'id' => $history->changer->id,
                    'name' => $history->changer->name,
                ] : null,
            ])->values()->all(),
            'assignments' => $dossier->assignments->map(fn ($assignment): array => [
                'id' => $assignment->id,
                'reason' => $assignment->reason,
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                'assignee' => $assignment->assignee ? [
                    'id' => $assignment->assignee->id,
                    'name' => $assignment->assignee->name,
                ] : null,
                'assigner' => $assignment->assigner ? [
                    'id' => $assignment->assigner->id,
                    'name' => $assignment->assigner->name,
                ] : null,
            ])->values()->all(),
            'checklist' => [
                'total_required' => count($this->dossierChecklistService->requiredDocumentTypes($dossier)),
                'verified_required' => collect($this->dossierChecklistService->checklistProgress($dossier))->where('is_verified', true)->count(),
                'is_complete' => $this->dossierChecklistService->isComplete($dossier),
                'items' => $this->dossierChecklistService->checklistProgress($dossier),
            ],
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ownerOptions(): array
    {
        $query = User::query()
            ->select(['id', 'name'])
            ->orderBy('name');

        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->limit(200)->get()->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
        ])->values()->all();
    }

    /**
     * @return list<array{id: int, name: string, student_no: string|null}>
     */
    private function studentOptions(): array
    {
        return User::query()
            ->select(['id', 'name', 'student_no'])
            ->whereNotNull('student_no')
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'student_no' => $user->student_no,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function transactionTypeOptions(): array
    {
        return array_keys(config('file_vault.checklist_templates', []));
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function queueViewOptions(): array
    {
        return [
            ['key' => 'all', 'label' => 'All'],
            ['key' => 'my_queue', 'label' => 'My Queue'],
            ['key' => 'unassigned', 'label' => 'Unassigned'],
            ['key' => 'overdue', 'label' => 'Overdue'],
        ];
    }

    private function applyQueueViewFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $view = $request->string('view')->toString();

        if ($view === 'my_queue') {
            $query->where('current_owner_id', $request->user()->id);
            return;
        }

        if ($view === 'unassigned') {
            $query->whereNull('current_owner_id');
            return;
        }

        if ($view === 'overdue') {
            $query
                ->whereNotNull('completion_due_at')
                ->where('completion_due_at', '<', now())
                ->whereNotIn('status', [StudentDossier::STATUS_RELEASED, StudentDossier::STATUS_ARCHIVED]);
        }
    }

    /**
     * @return array{all: int, my_queue: int, unassigned: int, overdue: int}
     */
    private function queueStats(int $userId): array
    {
        $base = StudentDossier::query();
        $openStatuses = [StudentDossier::STATUS_RELEASED, StudentDossier::STATUS_ARCHIVED];

        return [
            'all' => (clone $base)->count(),
            'my_queue' => (clone $base)->where('current_owner_id', $userId)->count(),
            'unassigned' => (clone $base)->whereNull('current_owner_id')->count(),
            'overdue' => (clone $base)
                ->whereNotNull('completion_due_at')
                ->where('completion_due_at', '<', now())
                ->whereNotIn('status', $openStatuses)
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function logAccess(StudentDossier $dossier, string $action, int $actorId, ?array $metadata = null): void
    {
        if (! in_array($action, self::ALLOWED_AUDIT_ACTIONS, true)) {
            throw new \InvalidArgumentException("Unsupported dossier audit action [{$action}].");
        }

        DossierAccessLog::query()->create([
            'student_dossier_id' => $dossier->id,
            'action' => $action,
            'actor_id' => $actorId,
            'metadata_json' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
