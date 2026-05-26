<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMikroTikAccount;
use App\Models\InternetAccountRequest;
use App\Services\AcademicApiService;
use App\Services\MikroTikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class InternetAccountController extends Controller
{
    public function __construct(
        protected MikroTikService $mikrotik,
        protected AcademicApiService $academicApiService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $this->canManageInternetAccounts($user);
        $canViewTermId = $user->can('internet-accounts.view-term-id');

        abort_unless($user->can('internet-accounts.view') || $canManage, 403);

        $activeSemester = $this->academicApiService->getActiveSemesterForUser($user);

        $filters = [
            'request_search' => $request->query('request_search'),
        ];

        if ($canManage) {
            $filters['status'] = $request->query('status');
        }

        if ($canManage && $canViewTermId) {
            $filters['term_id'] = $request->query('term_id');
        }

        return Inertia::render('InternetAccounts/Index', [
            'activeSemester' => $activeSemester,
            'student' => [
                'name' => $user->name,
                'student_no' => $this->academicApiService->studentNumberFor($user),
                'campus_name' => $user->campus_name,
                'campus_id' => $user->campus_id,
            ],
            'currentTermRequest' => $this->currentTermRequest($user, $activeSemester),
            'requests' => $this->paginatedRequests($request, $canManage),
            'filters' => $filters,
            'filterOptions' => [
                'statuses' => [
                    InternetAccountRequest::STATUS_PENDING,
                    InternetAccountRequest::STATUS_CANCELLED,
                    InternetAccountRequest::STATUS_PROCESSING,
                    InternetAccountRequest::STATUS_ACTIVE,
                    InternetAccountRequest::STATUS_FAILED,
                ],
            ],
            'can' => [
                'manage' => $canManage,
                'viewTermId' => $canViewTermId,
                'approve' => $user->can('internet-accounts.approve'),
                'cancel' => $user->can('internet-accounts.cancel'),
                'delete' => $user->can('internet-accounts.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $studentId = $this->academicApiService->studentNumberFor($user);

        if (blank($studentId)) {
            return redirect()->route('internet-accounts.index')->with('error', 'No student number is linked to your SSO account.');
        }

        $activeSemester = $this->academicApiService->getActiveSemesterForUser($user);

        if ($activeSemester['error']) {
            return redirect()->route('internet-accounts.index')->with('error', $activeSemester['error']);
        }

        $semester = $activeSemester['semester'];
        $termId = $activeSemester['termId'];
        $campusId = $activeSemester['campusId'];

        if (blank($semester) || blank($termId)) {
            return redirect()->route('internet-accounts.index')->with('error', 'No active semester found.');
        }

        $alreadyRequested = InternetAccountRequest::query()
            ->where('student_no', $studentId)
            ->where('semester', $semester)
            ->where('term_id', $termId)
            ->exists();

        if ($alreadyRequested) {
            return redirect()->route('internet-accounts.index')->with('error', 'You already have an internet account request for the active term.');
        }

        $username = $studentId.'-'.$termId;
        $password = Str::random(12);

        try {
            $account = InternetAccountRequest::query()->create([
                'user_id' => $user->id,
                'student_no' => $studentId,
                'semester' => $semester,
                'term_id' => $termId,
                'campus_id' => $campusId,
                'username' => $username,
                'password' => $password,
                'status' => InternetAccountRequest::STATUS_PENDING,
            ]);

            CreateMikroTikAccount::dispatch(
                internetAccountRequest: $account,
                semester: $account->semester,
                termId: $account->term_id,
                username: $account->username,
                password: $account->password,
            )->afterCommit();

            Log::info('Internet account request submitted', [
                'internet_account_request_id' => $account->id,
                'user_id' => $user->id,
                'student_no' => $studentId,
                'semester' => $semester,
                'term_id' => $termId,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to create internet account request', [
                'user_id' => $user->id,
                'student_no' => $studentId,
                'semester' => $semester,
                'term_id' => $termId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('internet-accounts.index')->with('error', 'Unable to submit your internet account request right now.');
        }

        return redirect()->route('internet-accounts.index')->with('success', 'Internet account request submitted and queued for provisioning.');
    }

    public function approve(InternetAccountRequest $internetAccount): RedirectResponse
    {
        if ($internetAccount->status === InternetAccountRequest::STATUS_ACTIVE || $internetAccount->status === InternetAccountRequest::STATUS_PROCESSING) {
            return redirect()->route('internet-accounts.index')->with('error', 'This internet account request has already been provisioned.');
        }

        if (blank($internetAccount->password)) {
            return redirect()->route('internet-accounts.index')->with('error', 'This internet account request has no password to provision.');
        }

        try {
            CreateMikroTikAccount::dispatch(
                internetAccountRequest: $internetAccount,
                semester: $internetAccount->semester,
                termId: $internetAccount->term_id,
                username: $internetAccount->username,
                password: $internetAccount->password,
            )->afterCommit();

            Log::info('Internet account request approved', [
                'internet_account_request_id' => $internetAccount->id,
                'approved_by' => auth()->id(),
                'student_no' => $internetAccount->student_no,
                'semester' => $internetAccount->semester,
                'term_id' => $internetAccount->term_id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to approve internet account request', [
                'internet_account_request_id' => $internetAccount->id,
                'approved_by' => auth()->id(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('internet-accounts.index')->with('error', 'Unable to approve this internet account request right now.');
        }

        return redirect()->route('internet-accounts.index')->with('success', 'Internet account request approved. MikroTik provisioning has been queued.');
    }

    public function cancel(InternetAccountRequest $internetAccount): RedirectResponse
    {
        if ($internetAccount->status === InternetAccountRequest::STATUS_ACTIVE) {
            return redirect()->route('internet-accounts.index')->with('error', 'Active internet accounts cannot be cancelled from this screen.');
        }

        $internetAccount->update([
            'status' => InternetAccountRequest::STATUS_CANCELLED,
            'failure_reason' => null,
        ]);

        Log::info('Internet account request cancelled', [
            'internet_account_request_id' => $internetAccount->id,
            'cancelled_by' => auth()->id(),
        ]);

        return redirect()->route('internet-accounts.index')->with('success', 'Internet account request cancelled.');
    }

    public function update(Request $request, InternetAccountRequest $internetAccount): RedirectResponse
    {
        if ($internetAccount->status === InternetAccountRequest::STATUS_PROCESSING) {
            return redirect()->route('internet-accounts.index')->with('error', 'Processing internet account requests cannot be edited.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'status' => [
                'required',
                'string',
                'in:'.implode(',', [
                    InternetAccountRequest::STATUS_PENDING,
                    InternetAccountRequest::STATUS_CANCELLED,
                    InternetAccountRequest::STATUS_ACTIVE,
                    InternetAccountRequest::STATUS_FAILED,
                ]),
            ],
            'failure_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] !== InternetAccountRequest::STATUS_FAILED) {
            $validated['failure_reason'] = null;
        }

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        $internetAccount->update($validated);

        Log::info('Internet account request updated', [
            'internet_account_request_id' => $internetAccount->id,
            'updated_by' => $request->user()->id,
            'status' => $internetAccount->status,
        ]);

        return redirect()->route('internet-accounts.index')->with('success', 'Internet account request updated.');
    }

    public function destroy(InternetAccountRequest $internetAccount): RedirectResponse
    {
        if ($internetAccount->status === InternetAccountRequest::STATUS_PROCESSING) {
            return redirect()->route('internet-accounts.index')->with('error', 'Processing internet account requests cannot be deleted.');
        }

        $internetAccount->delete();

        Log::info('Internet account request deleted', [
            'internet_account_request_id' => $internetAccount->id,
            'deleted_by' => auth()->id(),
        ]);

        return redirect()->route('internet-accounts.index')->with('success', 'Internet account request deleted.');
    }

    private function canManageInternetAccounts($user): bool
    {
        return collect([
            'internet-accounts.manage',
            'internet-accounts.approve',
            'internet-accounts.cancel',
            'internet-accounts.delete',
        ])->contains(fn (string $permission): bool => $user->can($permission));
    }

    private function paginatedRequests(Request $request, bool $canManage): array
    {
        $requests = InternetAccountRequest::query()
            ->when(
                ! $canManage,
                fn ($query) => $query->where('user_id', $request->user()->id),
            )
            ->with('user:id,name,email')
            ->when($request->query('request_search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('student_no', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('semester', 'like', "%{$search}%")
                        ->orWhere('term_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($canManage && $request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($canManage && $request->user()->can('internet-accounts.view-term-id') && $request->query('term_id'), fn ($query, string $termId) => $query->where('term_id', $termId))
            ->latest();

        return $this->requestPage($requests->paginate(10)->withQueryString());
    }

    private function currentTermRequest($user, array $activeSemester): ?array
    {
        if (blank($activeSemester['semester'] ?? null) || blank($activeSemester['termId'] ?? null)) {
            return null;
        }

        $studentNo = $this->academicApiService->studentNumberFor($user);

        if (blank($studentNo)) {
            return null;
        }

        $request = InternetAccountRequest::query()
            ->where('student_no', $studentNo)
            ->where('semester', $activeSemester['semester'])
            ->where('term_id', $activeSemester['termId'])
            ->first();

        return $request ? $this->requestPayload($request) : null;
    }

    private function requestPage(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())
                ->map(fn (InternetAccountRequest $account): array => $this->requestPayload($account))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => $paginator->linkCollection(),
        ];
    }

    private function requestPayload(InternetAccountRequest $account): array
    {
        return [
            'id' => $account->id,
            'student_no' => $account->student_no,
            'semester' => $account->semester,
            'term_id' => $account->term_id,
            'campus_id' => $account->campus_id,
            'username' => $account->username,
            'password' => $account->password,
            'status' => $account->status,
            'failure_reason' => $account->failure_reason,
            'created_at' => $account->created_at?->toAtomString(),
            'student_name' => $account->user?->name,
            'student_email' => $account->user?->email,
        ];
    }
}
