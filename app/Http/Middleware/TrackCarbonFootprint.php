<?php

namespace App\Http\Middleware;

use App\Models\CarbonFootprintLog;
use App\Support\ReportingContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackCarbonFootprint
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        if ($request->user() && $request->hasSession() && Schema::hasTable('carbon_footprint_logs') && $this->shouldTrack($request, $response)) {
            $context = ReportingContext::fromRequest($request);
            $estimatedKb = $this->estimatedKilobytes($request, $response);
            $dataGb = $estimatedKb / 1024 / 1024;
            $energyKwh = $dataGb * (float) config('carbon.kwh_per_gb');
            $co2eGrams = $energyKwh * (float) config('carbon.grams_co2e_per_kwh');

            CarbonFootprintLog::query()->create([
                'user_id' => $request->user()->id,
                'session_id' => $request->session()->getId(),
                'module' => $context['module'],
                'route_name' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'page_title' => $context['page'],
                'estimated_data_kb' => $estimatedKb,
                'estimated_energy_kwh' => $energyKwh,
                'estimated_co2e_grams' => $co2eGrams,
                'duration_seconds' => max(1, (int) ceil(microtime(true) - $startedAt)),
                'device_type' => ReportingContext::deviceType($request->userAgent()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->expectsJson()
            && $response->getStatusCode() < 400;
    }

    private function estimatedKilobytes(Request $request, Response $response): float
    {
        $contentLength = (int) $response->headers->get('Content-Length', 0);
        $responseKb = $contentLength > 0 ? $contentLength / 1024 : (float) config('carbon.default_page_kb');
        $requestKb = strlen((string) $request->getContent()) / 1024;

        return round(max((float) config('carbon.default_page_kb'), $responseKb + $requestKb), 2);
    }
}
