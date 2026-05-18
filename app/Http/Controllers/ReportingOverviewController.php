<?php

namespace App\Http\Controllers;

use App\Models\UserActivitySession;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ReportingOverviewController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $request->user()->can('reporting.overview.view') || abort(403);

        $onlineCutoff = now()->subMinutes(5);
        $idleCutoff = now()->subMinutes(30);

        $sessions = UserActivitySession::query()
            ->with('user.roles:id,name')
            ->where('last_activity_at', '>=', $idleCutoff)
            ->when($request->query('search'), function ($query, string $search): void {
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_no', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%");
                });
            })
            ->when($request->query('module'), fn ($query, string $module) => $query->where('current_module', $module))
            ->when($request->query('status'), function ($query, string $status) use ($idleCutoff, $onlineCutoff): void {
                if ($status === 'online') {
                    $query->where('last_activity_at', '>=', $onlineCutoff);
                }

                if ($status === 'idle') {
                    $query->whereBetween('last_activity_at', [$idleCutoff, $onlineCutoff]);
                }
            })
            ->latest('last_activity_at');

        $page = $sessions->paginate($this->pageSize($request))->withQueryString();

        return Inertia::render('Reporting/Overview', [
            'sessions' => $this->activityPage($page),
            'filters' => $request->only(['search', 'module', 'status', 'per_page']),
            'stats' => [
                'online' => UserActivitySession::query()->where('last_activity_at', '>=', $onlineCutoff)->count(),
                'idle' => UserActivitySession::query()->whereBetween('last_activity_at', [$idleCutoff, $onlineCutoff])->count(),
                'active_modules' => UserActivitySession::query()->where('last_activity_at', '>=', $idleCutoff)->distinct('current_module')->count('current_module'),
                'active_sessions' => UserActivitySession::query()->where('last_activity_at', '>=', $idleCutoff)->count(),
            ],
            'filterOptions' => [
                'modules' => UserActivitySession::query()->whereNotNull('current_module')->distinct()->orderBy('current_module')->pluck('current_module'),
            ],
            'pageSizeOptions' => self::PAGE_SIZES,
        ]);
    }

    private function pageSize(Request $request): int
    {
        $pageSize = $request->integer('per_page', 10);

        return in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : 10;
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPage(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(function (UserActivitySession $session): array {
                $lastActivity = $session->last_activity_at;
                $status = $lastActivity?->gte(now()->subMinutes(5)) ? 'online' : 'idle';

                return [
                    'id' => $session->id,
                    'name' => $session->user?->name,
                    'student_no' => $session->user?->student_no,
                    'employee_no' => $session->user?->employee_no,
                    'email' => $session->user?->email,
                    'roles' => $session->user?->roles->pluck('name')->values()->all() ?? [],
                    'current_module' => $session->current_module,
                    'current_url' => $session->current_url,
                    'browser' => $session->browser,
                    'ip_address' => $session->ip_address,
                    'device_type' => $session->device_type,
                    'last_activity_at' => $lastActivity?->toDateTimeString(),
                    'session_duration' => $session->logged_in_at ? $session->logged_in_at->diffForHumans(now(), true) : 'Unknown',
                    'status' => $status,
                ];
            })->values(),
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
}
