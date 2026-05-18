<?php

namespace App\Http\Middleware;

use App\Models\UserActivitySession;
use App\Support\ReportingContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->hasSession() && Schema::hasTable('user_activity_sessions')) {
            $context = ReportingContext::fromRequest($request);

            $session = UserActivitySession::query()->firstOrNew([
                'user_id' => $request->user()->id,
                'session_id' => $request->session()->getId(),
            ]);

            $session->fill([
                'current_url' => $request->fullUrl(),
                'current_route' => $request->route()?->getName(),
                'current_module' => $context['module'],
                'page_title' => $context['page'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'browser' => ReportingContext::browser($request->userAgent()),
                'device_type' => ReportingContext::deviceType($request->userAgent()),
                'last_activity_at' => now(),
                'logged_in_at' => $session->logged_in_at ?? now(),
                'logged_out_at' => null,
                'status' => UserActivitySession::STATUS_ONLINE,
            ])->save();
        }

        return $response;
    }
}
