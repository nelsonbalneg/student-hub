<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SiteBrandingController extends Controller
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function index(Request $request): Response
    {
        $request->user()->can('site_settings.manage') || abort(403);

        return Inertia::render('SiteSettings/Branding/Index', [
            'settings' => $this->settings->branding(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->can('site_settings.manage') || abort(403);

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:180'],
            'site_footer_name' => ['nullable', 'string', 'max:255'],
            'site_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:3072'],
            'site_favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $current = $this->settings->all();
        $userId = $request->user()->id;

        foreach (['site_name', 'site_tagline', 'site_footer_name'] as $key) {
            $this->settings->set($key, $validated[$key] ?? null, 'string', $userId);
        }

        $this->syncUpload($request, 'site_logo', 'branding/logos', (bool) ($validated['remove_logo'] ?? false), $current['site_logo'], $userId);
        $this->syncUpload($request, 'site_favicon', 'branding/favicons', (bool) ($validated['remove_favicon'] ?? false), $current['site_favicon'], $userId);

        return back()->with('success', 'Site settings updated successfully.');
    }

    private function syncUpload(Request $request, string $key, string $directory, bool $remove, ?string $currentPath, int $userId): void
    {
        if ($request->hasFile($key)) {
            $file = $request->file($key);

            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $file->storeAs(
                    $directory,
                    Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'png'),
                    'public',
                );

                $this->settings->set($key, $path, 'file', $userId);

                if ($currentPath) {
                    Storage::disk('public')->delete($currentPath);
                }
            }

            return;
        }

        if ($remove && $currentPath) {
            Storage::disk('public')->delete($currentPath);
            $this->settings->set($key, null, 'file', $userId);
        }
    }
}
