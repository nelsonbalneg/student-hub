<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $request->user()->can('reporting.audit_logs.view') || abort(403);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                });
            })
            ->when($request->query('user_id'), fn ($query, string $userId) => $query->where('user_id', $userId))
            ->when($request->query('module'), fn ($query, string $module) => $query->where('module', $module))
            ->when($request->query('action'), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->query('ip_address'), fn ($query, string $ip) => $query->where('ip_address', 'like', "%{$ip}%"))
            ->when($request->query('date_from'), fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->query('date_to'), fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest();

        return Inertia::render('Reporting/AuditLogs', [
            'logs' => $this->logPage($logs->paginate($this->pageSize($request))->withQueryString()),
            'filters' => $request->only(['search', 'user_search', 'user_id', 'module', 'action', 'ip_address', 'date_from', 'date_to', 'per_page']),
            'filterOptions' => [
                'users' => $this->userOptions($request),
                'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
                'actions' => AuditLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
            ],
            'stats' => [
                'total' => AuditLog::query()->count(),
                'today' => AuditLog::query()->whereDate('created_at', today())->count(),
                'actors' => AuditLog::query()->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'modules' => AuditLog::query()->whereNotNull('module')->distinct('module')->count('module'),
            ],
            'pageSizeOptions' => self::PAGE_SIZES,
        ]);
    }

    private function pageSize(Request $request): int
    {
        $pageSize = $request->integer('per_page', 10);

        return in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : 10;
    }

    private function userOptions(Request $request)
    {
        $selectedUserId = $request->query('user_id');
        $search = $request->query('user_search');

        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->when($search, function ($query, string $search): void {
                $search = addcslashes($search, '%_\\');

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        if ($selectedUserId && ! $users->contains('id', (int) $selectedUserId)) {
            $selectedUser = User::query()
                ->select(['id', 'name', 'email'])
                ->find($selectedUserId);

            if ($selectedUser) {
                $users->prepend($selectedUser);
            }
        }

        return $users->take(20)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function logPage(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor_name' => $log->actor_name,
                'actor_email' => $log->actor_email,
                'module' => $log->module,
                'action' => $log->action,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'route_name' => $log->route_name,
                'url' => $log->url,
                'method' => $log->method,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])->values(),
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
