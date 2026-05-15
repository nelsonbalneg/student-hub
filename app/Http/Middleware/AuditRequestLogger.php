<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\ReportingContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'otp',
        'secret',
        'two_factor_code',
        'recovery_code',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->route() && Schema::hasTable('audit_logs') && $this->shouldAudit($request)) {
            $context = ReportingContext::fromRequest($request);
            $action = ReportingContext::auditAction($request);
            $user = $request->user();

            AuditLog::query()->create([
                'user_id' => $user->id,
                'actor_name' => $user->name,
                'actor_email' => $user->email,
                'module' => $context['module'],
                'action' => $action,
                'description' => "{$user->name} performed {$action} on {$context['module']}.",
                'new_values' => $this->sanitizedPayload($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route_name' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        if ($request->is('storage/*') || $request->is('_debugbar/*')) {
            return false;
        }

        return $request->isMethod('GET')
            || in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizedPayload(Request $request): ?array
    {
        if ($request->isMethod('GET')) {
            return null;
        }

        $payload = Arr::except($request->all(), self::SENSITIVE_KEYS);

        return $payload === [] ? null : $payload;
    }
}
