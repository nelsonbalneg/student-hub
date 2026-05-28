<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CeeStudentRequirementService
{
    private const STORAGE_PUBLIC_PATH = 'usmcee-storage';

    private const REQUIREMENT_FIELDS = [
        'psa' => 'PSA',
        'tor' => 'TOR',
        'shs_card' => 'SHS Card',
        'enrolment_certification' => 'Enrolment Certification',
        'good_moral_char' => 'Good Moral Character',
        'honorable_dismisal' => 'Honorable Dismissal',
        'hepa_b_test' => 'Hepa B Test',
        'chest_x_ray' => 'Chest X-Ray',
        'preg_test' => 'Pregnancy Test',
        'signature' => 'Signature',
        'photo' => 'Photo',
    ];

    private const STORAGE_DIRECTORIES = [
        'psa' => 'psa',
        'tor' => 'tor',
        'shs_card' => 'card',
        'enrolment_certification' => 'certification',
        'good_moral_char' => 'gmc',
        'honorable_dismisal' => 'honorable_dismisal',
        'hepa_b_test' => 'hepa-b',
        'chest_x_ray' => 'chest-xray',
        'preg_test' => 'pregnancy-test',
        'signature' => 'signature',
        'photo' => 'photo',
    ];

    public function forStudent(?string $studentNo, int|string|null $campusId): array
    {
        if (blank($studentNo) || blank($campusId)) {
            Log::info('CEE document lookup skipped because student context is incomplete', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
            ]);

            return [
                'data' => [],
                'error' => 'Missing student number or campus for CEE documents.',
            ];
        }

        if (blank(config('database.connections.cee.host')) || blank(config('database.connections.cee.database'))) {
            Log::warning('CEE document lookup skipped because database connection is not configured', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'host_configured' => filled(config('database.connections.cee.host')),
                'database_configured' => filled(config('database.connections.cee.database')),
            ]);

            return [
                'data' => [],
                'error' => 'CEE database connection is not configured.',
            ];
        }

        try {
            Log::info('Loading CEE student requirement documents', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
            ]);

            $profile = DB::connection('cee')
                ->table('stundent_profiles')
                ->select(['user_id', 'student_no', 'campus_id'])
                ->where('student_no', $studentNo)
                ->where('campus_id', $campusId)
                ->first();

            if (! $profile?->user_id) {
                Log::info('No matching CEE student profile found for document lookup', [
                    'student_no' => $studentNo,
                    'campus_id' => $campusId,
                ]);

                return [
                    'data' => [],
                    'error' => null,
                ];
            }

            Log::info('Matched CEE student profile for document lookup', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'cee_user_id' => $profile->user_id,
            ]);

            $requirements = DB::connection('cee')
                ->table('requirements')
                ->where('user_id', $profile->user_id)
                ->orderByDesc('id')
                ->get();

            if ($requirements->isEmpty()) {
                Log::info('No CEE requirements row found for student profile', [
                    'student_no' => $studentNo,
                    'campus_id' => $campusId,
                    'cee_user_id' => $profile->user_id,
                ]);

                return [
                    'data' => [],
                    'error' => null,
                ];
            }

            $documents = $requirements
                ->flatMap(fn ($requirement) => $this->documentsFromRequirement((array) $requirement))
                ->values()
                ->all();

            Log::info('Loaded CEE requirement documents for student profile', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'cee_user_id' => $profile->user_id,
                'requirements_count' => $requirements->count(),
                'document_count' => count($documents),
                'missing_file_count' => collect($documents)->where('exists', false)->count(),
            ]);

            return [
                'data' => $documents,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load CEE student requirement documents', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load CEE documents right now.',
            ];
        }
    }

    private function documentsFromRequirement(array $requirements): array
    {
        $documents = [];

        foreach (self::REQUIREMENT_FIELDS as $field => $label) {
            foreach ($this->pathsFromValue(Arr::get($requirements, $field)) as $index => $path) {
                $safePath = $this->safeRelativePath($path);

                if (! $safePath) {
                    Log::warning('Skipped unsafe CEE requirement file path', [
                        'field' => $field,
                        'path' => $path,
                    ]);

                    continue;
                }

                $storagePath = $this->storageRelativePath($field, $safePath);
                $absolutePath = public_path(self::STORAGE_PUBLIC_PATH.'/'.$storagePath);
                $exists = is_file($absolutePath);

                if (! $exists) {
                    Log::info('CEE requirement file path does not exist in linked storage', [
                        'field' => $field,
                        'label' => $label,
                        'db_path' => $safePath,
                        'storage_path' => $storagePath,
                        'expected_path' => $absolutePath,
                    ]);
                }

                $documents[] = [
                    'key' => $field.'-'.$index.'-'.md5($safePath),
                    'field' => $field,
                    'label' => $label,
                    'name' => basename($storagePath),
                    'path' => $storagePath,
                    'db_path' => $safePath,
                    'url' => url(self::STORAGE_PUBLIC_PATH.'/'.$storagePath),
                    'exists' => $exists,
                    'extension' => Str::lower(pathinfo($storagePath, PATHINFO_EXTENSION)),
                ];
            }
        }

        return $documents;
    }

    private function pathsFromValue(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($path): bool => filled($path)));
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($path): bool => filled($path)));
        }

        return [$trimmed];
    }

    private function safeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/+#', '', $path) ?? '';

        if ($path === '' || str_contains($path, '../') || str_starts_with($path, '..')) {
            return null;
        }

        return $path;
    }

    private function storageRelativePath(string $field, string $path): string
    {
        if (! Str::startsWith($path, 'doc/')) {
            return $path;
        }

        $directory = self::STORAGE_DIRECTORIES[$field] ?? $field;

        return $directory.'/'.Str::after($path, 'doc/');
    }
}
