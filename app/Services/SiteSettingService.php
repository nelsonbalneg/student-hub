<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SiteSettingService
{
    /**
     * @return array<string, string|null>
     */
    public function defaults(): array
    {
        return [
            'site_name' => 'ONE USM',
            'site_tagline' => 'Connecting You to the Digital USM Experience.',
            'site_footer_name' => 'ONE USM SSO Facility • Secure • Unified • Connected',
            'site_logo' => null,
            'site_favicon' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $settings = $this->defaults();

        if (! $this->tableExists()) {
            return $settings;
        }

        SiteSetting::query()
            ->whereIn('key', array_keys($settings))
            ->pluck('value', 'key')
            ->each(function (?string $value, string $key) use (&$settings): void {
                $settings[$key] = $value;
            });

        return $settings;
    }

    /**
     * @return array<string, string|null>
     */
    public function branding(): array
    {
        $settings = $this->all();

        return [
            ...$settings,
            'site_logo_url' => $this->assetUrl($settings['site_logo']),
            'site_favicon_url' => $this->assetUrl($settings['site_favicon']) ?? '/favicon.png',
        ];
    }

    public function set(string $key, ?string $value, string $type = 'string', ?int $userId = null): void
    {
        if (! $this->tableExists()) {
            return;
        }

        SiteSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'updated_by' => $userId,
            ],
        );
    }

    public function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('site_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
