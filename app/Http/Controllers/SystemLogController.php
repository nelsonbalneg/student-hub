<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\SystemLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemLogController extends Controller
{
    public function __construct(private readonly SystemLogService $logs) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeView($request);

        $filters = $this->filters($request);
        $files = $this->logs->files($request->string('file_search')->value(), $request->string('file_sort', 'modified_desc')->value());
        $selectedFile = $request->string('file')->value() ?: ($files[0]['name'] ?? null);

        $this->audit($request, 'viewed logs', [
            'file' => $selectedFile,
            'filters' => Arr::except($filters, ['page']),
        ]);

        return Inertia::render('SystemLogs/Index', [
            'summary' => $this->logs->summary(),
            'files' => $this->paginateArray($files, max((int) $request->query('files_page', 1), 1), 10),
            'entries' => $selectedFile ? $this->logs->entries($selectedFile, $filters) : [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 25,
                'from' => null,
                'to' => null,
                'has_more' => false,
            ],
            'selectedFile' => $selectedFile,
            'filters' => [
                ...$filters,
                'file_search' => $request->string('file_search')->value(),
                'file_sort' => $request->string('file_sort', 'modified_desc')->value(),
            ],
            'can' => [
                'download' => $this->canDownload($request),
                'clear' => $this->canClear($request),
            ],
            'levels' => ['all', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'environments' => ['all', 'production', 'staging', 'development', 'local'],
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeDownload($request);

        $file = $request->string('file')->value();
        $path = $this->logs->resolveFile($file);
        abort_unless($path, 404);

        $this->audit($request, 'downloaded logs', ['file' => $file]);

        return response()->download($path, basename($path));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeExport($request);

        $file = $request->string('file')->value();
        abort_unless($this->logs->resolveFile($file), 404);

        $format = $request->string('format', 'csv')->lower()->value() === 'txt' ? 'txt' : 'csv';
        $entries = $this->logs->exportEntries($file, $this->filters($request));
        $filename = pathinfo($file, PATHINFO_FILENAME).'-filtered-logs.'.$format;

        $this->audit($request, 'exported logs', [
            'file' => $file,
            'format' => $format,
            'count' => count($entries),
        ]);

        return Response::streamDownload(function () use ($entries, $format): void {
            $handle = fopen('php://output', 'w');

            if ($format === 'csv') {
                fputcsv($handle, ['Timestamp', 'Environment', 'Level', 'Message', 'Exception', 'File', 'Line', 'URL']);
                foreach ($entries as $entry) {
                    fputcsv($handle, [
                        $entry['timestamp'] ?? '',
                        $entry['environment'] ?? '',
                        $entry['level'] ?? '',
                        $entry['message'] ?? '',
                        $entry['exception_type'] ?? '',
                        $entry['file'] ?? '',
                        $entry['line'] ?? '',
                        $entry['url'] ?? '',
                    ]);
                }
                fclose($handle);

                return;
            }

            foreach ($entries as $entry) {
                fwrite($handle, sprintf(
                    "[%s] %s.%s: %s\n%s\n\n",
                    $entry['timestamp'] ?? '',
                    $entry['environment'] ?? '',
                    strtoupper((string) ($entry['level'] ?? '')),
                    $entry['message'] ?? '',
                    $entry['raw'] ?? '',
                ));
            }
            fclose($handle);
        }, $filename);
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->authorizeClear($request);

        $validated = $request->validate([
            'file' => ['required', 'string'],
        ]);

        abort_unless($this->logs->clear($validated['file']), 404);

        $this->audit($request, 'cleared logs', ['file' => $validated['file']]);

        return to_route('system.logs.index', ['file' => $validated['file']])
            ->with('success', 'Log file cleared successfully.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Super Admin', 'System Administrator'])
                || $request->user()?->can('system.logs.view'),
            HttpResponse::HTTP_FORBIDDEN
        );
    }

    private function authorizeDownload(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Super Admin', 'System Administrator'])
                || $request->user()?->can('system.logs.download'),
            HttpResponse::HTTP_FORBIDDEN
        );
    }

    private function authorizeExport(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Super Admin', 'System Administrator'])
                || $request->user()?->can('system.logs.export')
                || $request->user()?->can('system.logs.download'),
            HttpResponse::HTTP_FORBIDDEN
        );
    }

    private function authorizeClear(Request $request): void
    {
        abort_unless(
            $request->user()?->hasRole('Super Admin')
                || $request->user()?->can('system.logs.clear'),
            HttpResponse::HTTP_FORBIDDEN
        );
    }

    private function canDownload(Request $request): bool
    {
        return (bool) (
            $request->user()?->hasAnyRole(['Super Admin', 'System Administrator'])
            || $request->user()?->can('system.logs.download')
        );
    }

    private function canClear(Request $request): bool
    {
        return (bool) (
            $request->user()?->hasRole('Super Admin')
            || $request->user()?->can('system.logs.clear')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'level' => $request->string('level', 'all')->value(),
            'range' => $request->string('range', 'today')->value(),
            'from' => $request->string('from')->value(),
            'to' => $request->string('to')->value(),
            'search' => $request->string('search')->value(),
            'environment' => $request->string('environment', 'all')->value(),
            'page' => max((int) $request->query('page', 1), 1),
            'per_page' => min(max((int) $request->query('per_page', 25), 10), 100),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{data: array<int, array<string, mixed>>, links: array<int, array{url: string|null, label: string, active: bool}>, current_page: int, from: int|null, to: int|null, total: int}
     */
    private function paginateArray(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($items, $offset, $perPage);
        $lastPage = max((int) ceil($total / $perPage), 1);

        return [
            'data' => array_values($data),
            'links' => collect(range(1, $lastPage))->map(fn (int $number): array => [
                'url' => request()->fullUrlWithQuery(['files_page' => $number]),
                'label' => (string) $number,
                'active' => $number === $page,
            ])->all(),
            'current_page' => $page,
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($offset + count($data), $total) : null,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function audit(Request $request, string $action, array $values = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'actor_name' => $request->user()?->name,
            'actor_email' => $request->user()?->email,
            'module' => 'System Logs',
            'action' => $action,
            'description' => $request->user()?->name.' '.$action.'.',
            'new_values' => $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route_name' => $request->route()?->getName(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);
    }
}
