<?php

namespace App\Http\Controllers;

use App\Models\CarbonFootprintLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CarbonFootprintController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function index(Request $request): Response
    {
        $request->user()->can('reporting.carbon_footprint.view') || abort(403);

        $query = $this->filteredQuery($request);

        return Inertia::render('Reporting/CarbonFootprint', [
            'logs' => $this->logPage((clone $query)->latest()->paginate($this->pageSize($request))->withQueryString()),
            'filters' => $request->only(['search', 'user_id', 'module', 'date_from', 'date_to', 'per_page']),
            'stats' => $this->stats((clone $query)),
            'chart' => $this->chart((clone $query), $request->query('group', 'day')),
            'topModules' => $this->topModules((clone $query)),
            'topUsers' => $this->topUsers((clone $query)),
            'filterOptions' => [
                'users' => User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'email']),
                'modules' => CarbonFootprintLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            ],
            'pageSizeOptions' => self::PAGE_SIZES,
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        return CarbonFootprintLog::query()
            ->with('user:id,name,email')
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('url', 'like', "%{$search}%")
                        ->orWhere('page_title', 'like', "%{$search}%")
                        ->orWhere('route_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('user_id'), fn ($query, string $userId) => $query->where('user_id', $userId))
            ->when($request->query('module'), fn ($query, string $module) => $query->where('module', $module))
            ->when($request->query('date_from'), fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->query('date_to'), fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function pageSize(Request $request): int
    {
        $pageSize = $request->integer('per_page', 10);

        return in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : 10;
    }

    /**
     * @return array<string, float|int>
     */
    private function stats(Builder $query): array
    {
        return [
            'total_co2e_grams' => round((float) $query->sum('estimated_co2e_grams'), 4),
            'total_page_views' => (clone $query)->count(),
            'total_active_users' => (clone $query)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'total_sessions' => (clone $query)->whereNotNull('session_id')->distinct('session_id')->count('session_id'),
            'estimated_data_kb' => round((float) (clone $query)->sum('estimated_data_kb'), 2),
            'estimated_energy_kwh' => round((float) (clone $query)->sum('estimated_energy_kwh'), 8),
        ];
    }

    /**
     * @return array<int, array{label: string, co2e: float, views: int}>
     */
    private function chart(Builder $query, string $group): array
    {
        $expression = $group === 'month'
            ? "FORMAT(created_at, 'yyyy-MM')"
            : ($group === 'week' ? "FORMAT(created_at, 'yyyy-ww')" : 'CAST(created_at AS date)');

        return $query
            ->withoutEagerLoads()
            ->selectRaw("{$expression} as period, SUM(estimated_co2e_grams) as co2e, COUNT(*) as views")
            ->groupByRaw($expression)
            ->orderByRaw($expression)
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->period,
                'co2e' => round((float) $row->co2e, 4),
                'views' => (int) $row->views,
            ])
            ->values()
            ->all();
    }

    private function topModules(Builder $query): array
    {
        return $query
            ->withoutEagerLoads()
            ->select('module', DB::raw('SUM(estimated_co2e_grams) as co2e'), DB::raw('COUNT(*) as views'))
            ->whereNotNull('module')
            ->groupBy('module')
            ->orderByDesc('co2e')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'module' => $row->module,
                'co2e' => round((float) $row->co2e, 4),
                'views' => (int) $row->views,
            ])
            ->all();
    }

    private function topUsers(Builder $query): array
    {
        return $query
            ->withoutEagerLoads()
            ->join('users', 'users.id', '=', 'carbon_footprint_logs.user_id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('SUM(estimated_co2e_grams) as co2e'), DB::raw('COUNT(*) as views'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('co2e')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'co2e' => round((float) $row->co2e, 4),
                'views' => (int) $row->views,
            ])
            ->all();
    }

    public function logPage(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (CarbonFootprintLog $log): array => [
                'id' => $log->id,
                'user' => $log->user ? ['name' => $log->user->name, 'email' => $log->user->email] : null,
                'module' => $log->module,
                'route_name' => $log->route_name,
                'url' => $log->url,
                'page_title' => $log->page_title,
                'estimated_data_kb' => (float) $log->estimated_data_kb,
                'estimated_energy_kwh' => (float) $log->estimated_energy_kwh,
                'estimated_co2e_grams' => (float) $log->estimated_co2e_grams,
                'duration_seconds' => $log->duration_seconds,
                'device_type' => $log->device_type,
                'ip_address' => $log->ip_address,
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
