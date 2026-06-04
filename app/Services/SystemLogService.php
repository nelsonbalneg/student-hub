<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileObject;

class SystemLogService
{
    private const LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    public function logDirectory(): string
    {
        return storage_path('logs');
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, size_display: string, modified_at: string|null}>
     */
    public function files(?string $search = null, string $sort = 'modified_desc'): array
    {
        $directory = $this->logDirectory();

        if (! is_dir($directory)) {
            return [];
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file): bool => Str::endsWith($file->getFilename(), '.log'))
            ->map(fn ($file): array => [
                'name' => $file->getFilename(),
                'path' => $file->getRealPath(),
                'size' => $file->getSize(),
                'size_display' => $this->bytes($file->getSize()),
                'modified_at' => CarbonImmutable::createFromTimestamp($file->getMTime())->toDateTimeString(),
            ])
            ->when(filled($search), fn (Collection $files): Collection => $files->filter(
                fn (array $file): bool => Str::contains(Str::lower($file['name']), Str::lower((string) $search))
            ));

        return match ($sort) {
            'name_asc' => $files->sortBy('name')->values()->all(),
            'name_desc' => $files->sortByDesc('name')->values()->all(),
            'size_asc' => $files->sortBy('size')->values()->all(),
            'size_desc' => $files->sortByDesc('size')->values()->all(),
            'modified_asc' => $files->sortBy('modified_at')->values()->all(),
            default => $files->sortByDesc('modified_at')->values()->all(),
        };
    }

    /**
     * @return array{total_log_files: int, errors_today: int, critical_errors: int, warning_logs: int, latest_error: string|null}
     */
    public function summary(): array
    {
        return Cache::remember('system_logs.summary', now()->addMinutes(5), function (): array {
            $totalFiles = count($this->files());
            $errorsToday = 0;
            $criticalErrors = 0;
            $warningLogs = 0;
            $latestError = null;
            $today = now()->toDateString();

            foreach ($this->files() as $file) {
                foreach ($this->entries($file['name'], ['per_page' => 250, 'scan_bytes' => 2 * 1024 * 1024])['data'] as $entry) {
                    $level = Str::lower($entry['level']);
                    $date = Str::substr((string) $entry['timestamp'], 0, 10);

                    if ($level === 'error' && $date === $today) {
                        $errorsToday++;
                    }

                    if (in_array($level, ['emergency', 'alert', 'critical'], true)) {
                        $criticalErrors++;
                    }

                    if ($level === 'warning') {
                        $warningLogs++;
                    }

                    if (in_array($level, ['emergency', 'alert', 'critical', 'error'], true)) {
                        $latestError = $latestError
                            ? max($latestError, (string) $entry['timestamp'])
                            : (string) $entry['timestamp'];
                    }
                }
            }

            return [
                'total_log_files' => $totalFiles,
                'errors_today' => $errorsToday,
                'critical_errors' => $criticalErrors,
                'warning_logs' => $warningLogs,
                'latest_error' => $latestError,
            ];
        });
    }

    /**
     * @param  array{level?: string|null, range?: string|null, from?: string|null, to?: string|null, search?: string|null, environment?: string|null, page?: int|null, per_page?: int|null, scan_bytes?: int|null}  $filters
     * @return array{data: array<int, array<string, mixed>>, total: int, current_page: int, per_page: int, from: int|null, to: int|null, has_more: bool}
     */
    public function entries(string $fileName, array $filters = []): array
    {
        $file = $this->resolveFile($fileName);

        if (! $file) {
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 25,
                'from' => null,
                'to' => null,
                'has_more' => false,
            ];
        }

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 10), 100);
        $scanBytes = (int) ($filters['scan_bytes'] ?? 12 * 1024 * 1024);
        $entries = $this->parseTail($file, $scanBytes);
        $entries = $this->filterEntries($entries, $filters);
        $total = count($entries);
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($entries, $offset, $perPage);

        return [
            'data' => array_values($pageItems),
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($offset + count($pageItems), $total) : null,
            'has_more' => $offset + $perPage < $total,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportEntries(string $fileName, array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['per_page'] = 100;
        $filters['scan_bytes'] = 25 * 1024 * 1024;

        return $this->entries($fileName, $filters)['data'];
    }

    public function resolveFile(string $fileName): ?string
    {
        $safeName = basename($fileName);
        $path = $this->logDirectory().DIRECTORY_SEPARATOR.$safeName;

        if (! Str::endsWith($safeName, '.log') || ! is_file($path)) {
            return null;
        }

        $realPath = realpath($path);
        $realDirectory = realpath($this->logDirectory());

        if (! $realPath || ! $realDirectory || ! Str::startsWith($realPath, $realDirectory)) {
            return null;
        }

        return $realPath;
    }

    public function clear(string $fileName): bool
    {
        $path = $this->resolveFile($fileName);

        if (! $path) {
            return false;
        }

        $archive = $path.'.cleared-'.now()->format('YmdHis').'.bak';
        @copy($path, $archive);
        file_put_contents($path, '');
        Cache::forget('system_logs.summary');

        return true;
    }

    private function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, 1)." {$unit}";
            }
            $value /= 1024;
        }

        return number_format($value, 1).' TB';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseTail(string $path, int $scanBytes): array
    {
        $size = filesize($path) ?: 0;
        $start = max(0, $size - $scanBytes);
        $file = new SplFileObject($path, 'r');
        $file->fseek($start);

        if ($start > 0) {
            $file->fgets();
        }

        $entries = [];
        $current = null;
        $index = 0;

        while (! $file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");

            if (preg_match('/^\[(?<timestamp>[^\]]+)\]\s+(?<environment>[^.]+)\.(?<level>[A-Z]+):\s*(?<message>.*)$/', $line, $matches)) {
                if ($current) {
                    $entries[] = $this->finalizeEntry($current);
                }

                $current = [
                    'id' => sha1($path.'|'.$start.'|'.$index++),
                    'timestamp' => $matches['timestamp'],
                    'environment' => $matches['environment'],
                    'level' => Str::lower($matches['level']),
                    'message' => $matches['message'],
                    'raw' => $line,
                ];

                continue;
            }

            if ($current) {
                $current['raw'] .= "\n".$line;
            }
        }

        if ($current) {
            $entries[] = $this->finalizeEntry($current);
        }

        return array_reverse($entries);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function finalizeEntry(array $entry): array
    {
        $raw = (string) $entry['raw'];
        $entry['exception_type'] = $this->match('/(?:local|production|staging|development)\.[A-Z]+:\s+([^:\n]+(?:Exception|Error))[:\s]/i', $raw);
        $entry['file'] = $this->match('/ in (?<file>[A-Z]:\\\\[^:]+|\/[^:]+):(?<line>\d+)/i', $raw, 'file');
        $entry['line'] = $this->match('/ in (?:[A-Z]:\\\\[^:]+|\/[^:]+):(?<line>\d+)/i', $raw, 'line');
        $entry['url'] = $this->match('/https?:\/\/[^\s\]\)]+/i', $raw);
        $entry['method'] = $this->match('/\b(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\b/', $raw);
        $entry['ip_address'] = $this->match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $raw);
        $entry['user_agent'] = $this->match('/User-Agent[:=]\s*(.+)$/mi', $raw);

        return $entry;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterEntries(array $entries, array $filters): array
    {
        $level = Str::lower((string) ($filters['level'] ?? 'all'));
        $environment = Str::lower((string) ($filters['environment'] ?? 'all'));
        $search = Str::lower((string) ($filters['search'] ?? ''));
        [$from, $to] = $this->dateBounds($filters);

        return array_values(array_filter($entries, function (array $entry) use ($level, $environment, $search, $from, $to): bool {
            if ($level !== 'all' && in_array($level, self::LEVELS, true) && $entry['level'] !== $level) {
                return false;
            }

            if ($environment !== 'all' && Str::lower((string) $entry['environment']) !== $environment) {
                return false;
            }

            $date = CarbonImmutable::parse((string) $entry['timestamp']);
            if ($from && $date->lt($from)) {
                return false;
            }

            if ($to && $date->gt($to)) {
                return false;
            }

            if ($search !== '') {
                $haystack = Str::lower(implode(' ', [
                    $entry['message'] ?? '',
                    $entry['exception_type'] ?? '',
                    $entry['file'] ?? '',
                    $entry['url'] ?? '',
                    $entry['raw'] ?? '',
                ]));

                return Str::contains($haystack, $search);
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function dateBounds(array $filters): array
    {
        return match ($filters['range'] ?? 'all') {
            'today' => [now()->startOfDay()->toImmutable(), now()->endOfDay()->toImmutable()],
            '7d' => [now()->subDays(7)->startOfDay()->toImmutable(), now()->endOfDay()->toImmutable()],
            '30d' => [now()->subDays(30)->startOfDay()->toImmutable(), now()->endOfDay()->toImmutable()],
            'custom' => [
                filled($filters['from'] ?? null) ? CarbonImmutable::parse((string) $filters['from'])->startOfDay() : null,
                filled($filters['to'] ?? null) ? CarbonImmutable::parse((string) $filters['to'])->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    private function match(string $pattern, string $value, int|string $group = 0): ?string
    {
        if (! preg_match($pattern, $value, $matches)) {
            return null;
        }

        return isset($matches[$group]) ? trim((string) $matches[$group]) : null;
    }
}
