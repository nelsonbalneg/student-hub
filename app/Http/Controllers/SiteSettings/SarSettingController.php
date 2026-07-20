<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SystemFeature;

class SarSettingController extends Controller
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function index(Request $request): Response
    {
        $request->user()->can('site_settings.manage') || abort(403);

        $settings = $this->settings->all();

        return Inertia::render('SiteSettings/Sar/Index', [
            'settings' => [
                'sar_enabled' => isset($settings['sar_enabled']) ? (bool) $settings['sar_enabled'] : true,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->can('site_settings.manage') || abort(403);

        $request->validate([
            'sar_enabled' => ['required', 'boolean'],
        ]);

        $isEnabled = $request->boolean('sar_enabled');

        $this->settings->set('sar_enabled', $isEnabled ? '1' : '0', 'boolean', $request->user()->id);

        $status = $isEnabled ? 'active' : 'maintenance';
        SystemFeature::where('route_name', 'enrollment.student-academic-registration')
            ->update([
                'status' => $status,
                'maintenance_message' => 'Student Academic Registration is currently closed by the administrator.',
            ]);

        SystemFeature::clearCache();

        return redirect()->route('site-settings.sar')->with('success', 'SAR settings updated successfully.');
    }
}
