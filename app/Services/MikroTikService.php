<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikroTikService
{
    public function createInternetAccount(string $username, string $password, string $semester, string $termId): array
    {
        $baseUrl = rtrim((string) config('services.mikrotik.base_url'), '/');

        if (blank($baseUrl)) {
            Log::warning('MikroTik account creation skipped because no endpoint is configured', [
                'username' => $username,
                'semester' => $semester,
                'term_id' => $termId,
            ]);

            return [
                'configured' => false,
                'message' => 'MikroTik endpoint is not configured.',
            ];
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.mikrotik.timeout', 15))
            ->connectTimeout((int) config('services.mikrotik.connect_timeout', 5))
            ->withToken((string) config('services.mikrotik.token'))
            ->post('/internet-accounts', [
                'username' => $username,
                'password' => $password,
                'semester' => $semester,
                'termId' => $termId,
            ])
            ->throw();

        return $response->json() ?? [];
    }
}
