<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportingContext
{
    /**
     * @return array{module: string, page: string}
     */
    public static function fromRequest(Request $request): array
    {
        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');
        $firstSegment = Str::of($path)->explode('/')->first() ?: 'dashboard';

        $module = match (true) {
            Str::startsWith($routeName, 'reporting.') => 'Reporting',
            Str::startsWith($routeName, 'user-management.') => 'User Management',
            Str::startsWith($routeName, 'clearance.') => 'Clearance',
            Str::startsWith($routeName, 'announcements.') => 'Announcements',
            Str::startsWith($routeName, 'faqs.') => 'FAQ',
            Str::startsWith($routeName, 'admin.evaluations.'), Str::startsWith($routeName, 'student.evaluation.') => 'Evaluation',
            Str::startsWith($routeName, 'site-settings.') => 'Site Settings',
            Str::startsWith($routeName, 'legal.') => 'Legal',
            Str::startsWith($routeName, 'grades.') => 'Grades',
            Str::startsWith($routeName, 'academic.') => 'Academic',
            Str::startsWith($routeName, 'internet-accounts.') => 'Internet Accounts',
            Str::startsWith($routeName, 'student-profile.') => 'Student Profile',
            default => Str::headline((string) $firstSegment),
        };

        return [
            'module' => $module,
            'page' => Str::headline($routeName ? Str::afterLast($routeName, '.') : (string) $firstSegment),
        ];
    }

    public static function browser(?string $userAgent): string
    {
        $userAgent = (string) $userAgent;

        return match (true) {
            Str::contains($userAgent, 'Edg/') => 'Microsoft Edge',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown',
        };
    }

    public static function deviceType(?string $userAgent): string
    {
        $userAgent = Str::lower((string) $userAgent);

        return match (true) {
            Str::contains($userAgent, ['ipad', 'tablet']) => 'tablet',
            Str::contains($userAgent, ['mobile', 'iphone', 'android']) => 'mobile',
            default => 'desktop',
        };
    }

    public static function auditAction(Request $request): string
    {
        if ($request->routeIs('logout')) {
            return 'logout';
        }

        return match ($request->method()) {
            'GET' => 'view',
            'POST' => self::actionFromRoute($request, 'create'),
            'PUT', 'PATCH' => self::actionFromRoute($request, 'update'),
            'DELETE' => 'delete',
            default => Str::lower($request->method()),
        };
    }

    private static function actionFromRoute(Request $request, string $fallback): string
    {
        $routeName = $request->route()?->getName() ?? '';

        foreach (['approve', 'reject', 'upload', 'download', 'export', 'publish', 'archive', 'login', 'logout'] as $action) {
            if (Str::contains($routeName, $action)) {
                return $action;
            }
        }

        return $fallback;
    }
}
