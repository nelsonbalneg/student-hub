<?php

namespace App\Http\Controllers;

use App\Models\CarbonFootprintLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MyCarbonFootprintController extends Controller
{
    public function index(Request $request): Response
    {
        $request->user()->can('reporting.carbon_footprint.user_view') || abort(403);

        $query = CarbonFootprintLog::query()
            ->where('user_id', $request->user()->id)
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('page_title', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%");
                });
            })
            ->when($request->query('module'), fn ($query, string $module) => $query->where('module', $module))
            ->when($request->query('date_from'), fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->query('date_to'), fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));

        return Inertia::render('Reporting/MyCarbonFootprint', [
            'logs' => app(CarbonFootprintController::class)->logPage((clone $query)->latest()->paginate(10)->withQueryString()),
            'filters' => $request->only(['search', 'module', 'date_from', 'date_to']),
            'stats' => [
                'total_co2e_grams' => round((float) (clone $query)->sum('estimated_co2e_grams'), 4),
                'page_views' => (clone $query)->count(),
                'sessions' => (clone $query)->whereNotNull('session_id')->distinct('session_id')->count('session_id'),
                'estimated_energy_kwh' => round((float) (clone $query)->sum('estimated_energy_kwh'), 8),
            ],
            'chart' => $this->chart((clone $query)),
            'modules' => $this->modules((clone $query)),
            'filterOptions' => [
                'modules' => CarbonFootprintLog::query()
                    ->where('user_id', $request->user()->id)
                    ->whereNotNull('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module'),
            ],
        ]);
    }

    private function chart(Builder $query): array
    {
        return $query
            ->withoutEagerLoads()
            ->selectRaw('CAST(created_at AS date) as period, SUM(estimated_co2e_grams) as co2e, COUNT(*) as views')
            ->groupByRaw('CAST(created_at AS date)')
            ->orderByRaw('CAST(created_at AS date)')
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

    private function modules(Builder $query): array
    {
        return $query
            ->withoutEagerLoads()
            ->select('module', DB::raw('SUM(estimated_co2e_grams) as co2e'), DB::raw('COUNT(*) as views'))
            ->whereNotNull('module')
            ->groupBy('module')
            ->orderByDesc('views')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'module' => $row->module,
                'co2e' => round((float) $row->co2e, 4),
                'views' => (int) $row->views,
            ])
            ->all();
    }
}
