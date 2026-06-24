<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemFeature;
use App\Services\Features\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function __construct(private readonly FeatureService $features) {}

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('features.view'), 403);

        $filters = $request->only(['module', 'status', 'search']);

        return Inertia::render('Admin/FeatureManagement/Index', [
            'features'        => $this->features->getPaginated($filters),
            'modules'         => $this->features->getDistinctModules(),
            'filters'         => $filters,
            'statusOptions'   => ['active', 'maintenance', 'disabled'],
        ]);
    }

    public function update(Request $request, SystemFeature $feature): RedirectResponse
    {
        abort_unless(auth()->user()?->can('features.edit'), 403);

        $validated = $request->validate([
            'status'              => ['required', 'in:active,maintenance,disabled'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'reason'              => ['nullable', 'string', 'max:500'],
        ]);

        $this->features->updateStatus(
            feature: $feature,
            status: $validated['status'],
            maintenanceMessage: $validated['maintenance_message'] ?? null,
            reason: $validated['reason'] ?? null,
            actor: $request->user(),
        );

        return back()->with('success', "Feature \"{$feature->feature_name}\" status updated to {$validated['status']}.");
    }

    public function history(SystemFeature $feature): JsonResponse
    {
        abort_unless(auth()->user()?->can('features.view'), 403);

        return response()->json([
            'feature' => [
                'id'           => $feature->id,
                'feature_name' => $feature->feature_name,
                'feature_key'  => $feature->feature_key,
                'status'       => $feature->status,
            ],
            'logs' => $this->features->getHistory($feature),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('features.sync'), 403);

        $result = $this->features->syncRoutes($request->user());

        return back()->with(
            'success',
            "Route sync complete. {$result['inserted']} new feature(s) added, {$result['skipped']} skipped."
        );
    }
}
