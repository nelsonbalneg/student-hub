<?php

namespace App\Services;

use App\Models\User;
use App\Models\SiteAcademicTerm;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AcademicApiService
{
    private string $baseUrl;

    private int $timeout;

    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.academic_api.base_url'), '/');
        $this->timeout = (int) config('services.academic_api.timeout', 15);
        $this->connectTimeout = (int) config('services.academic_api.connect_timeout', 5);
    }

    public function studentNumberFor(User $user): ?string
    {
        $candidates = [
            $user->student_no,
            $user->sso_username,
        ];

        foreach ($candidates as $candidate) {
            if (blank($candidate)) {
                continue;
            }

            $normalized = trim((string) $candidate);

            if (preg_match('/^[A-Za-z0-9-]+$/', $normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    public function activeSemestersForCampus(?int $campusId): array
    {
        if (! $campusId) {
            return [
                'data' => [],
                'error' => 'No campus is linked to your SSO account.',
            ];
        }

        try {
            $response = $this->client()
                ->get("ActiveSemesters/campus/{$campusId}/active-only")
                ->throw();

            $data = $response->json();

            return [
                'data' => is_array($data) ? (array_is_list($data) ? $data : [$data]) : [],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load active semesters', [
                'campus_id' => $campusId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load active semester data right now.',
            ];
        }
    }

    public function getActiveSemesterForUser(User $user): array
    {
        $campusId = $user->campus_id;
        $response = $this->activeSemestersForCampus($campusId);

        if ($response['error']) {
            Log::warning('Unable to resolve active semester for user', [
                'user_id' => $user->id,
                'campus_id' => $campusId,
                'error' => $response['error'],
            ]);

            return [
                'semester' => null,
                'termId' => null,
                'campusId' => $campusId,
                'error' => $response['error'],
                'data' => null,
            ];
        }

        $activeSemester = collect($response['data'])->first();

        if (! $activeSemester) {
            Log::warning('No active semester returned for user', [
                'user_id' => $user->id,
                'campus_id' => $campusId,
            ]);

            return [
                'semester' => null,
                'termId' => null,
                'campusId' => $campusId,
                'error' => 'No active semester found.',
                'data' => null,
            ];
        }

        $semester = Arr::get($activeSemester, 'term')
            ?? Arr::get($activeSemester, 'semester')
            ?? Arr::get($activeSemester, 'semesterName')
            ?? Arr::get($activeSemester, 'semester_name')
            ?? Arr::get($activeSemester, 'name');

        $termId = Arr::get($activeSemester, 'termId')
            ?? Arr::get($activeSemester, 'term_id')
            ?? Arr::get($activeSemester, 'semesterId')
            ?? Arr::get($activeSemester, 'semester_id')
            ?? Arr::get($activeSemester, 'id');

        if (blank($semester) || blank($termId)) {
            Log::warning('Active semester response is missing required fields', [
                'user_id' => $user->id,
                'campus_id' => $campusId,
                'active_semester' => $activeSemester,
            ]);

            return [
                'semester' => $semester,
                'termId' => $termId,
                'campusId' => $campusId,
                'error' => 'The active semester response is missing a semester or term ID.',
                'data' => $activeSemester,
            ];
        }

        return [
            'semester' => (string) $semester,
            'termId' => (string) $termId,
            'campusId' => $campusId,
            'error' => null,
            'data' => $activeSemester,
        ];
    }

    public function curriculumForStudent(?string $studentNo, ?string $tenantId): array
    {
        if (blank($studentNo)) {
            return [
                'data' => [],
                'error' => 'No student number is linked to your SSO account.',
            ];
        }

        if (blank($tenantId)) {
            return [
                'data' => [],
                'error' => 'No tenant ID is linked to your SSO account.',
            ];
        }

        $endpoint = 'TrialProgram/curriculum/student/'.rawurlencode($studentNo);

        try {
            $response = $this->client()->get($endpoint, [
                'tenantId' => $tenantId,
            ]);

            if ($response->status() === 404) {
                Log::info('No curriculum found for student', [
                    'student_no' => $studentNo,
                    'tenant_id' => $tenantId,
                    'response_body' => $response->body(),
                ]);

                return [
                    'data' => [],
                    'error' => "No curriculum was found for student number {$studentNo}.",
                ];
            }

            $response->throw();

            $data = $response->json();

            return [
                'data' => is_array($data) ? $data : [],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load student curriculum', [
                'student_no' => $studentNo,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($endpoint),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load your curriculum right now.',
            ];
        }
    }

    public function profileForStudent(?string $studentNo, ?string $tenantId): array
    {
        if (blank($studentNo)) {
            return [
                'data' => null,
                'error' => 'No student number is linked to your SSO account.',
            ];
        }

        if (blank($tenantId)) {
            return [
                'data' => null,
                'error' => 'No tenant ID is linked to your SSO account.',
            ];
        }

        $endpoint = 'Students/'.rawurlencode($studentNo);

        try {
            $response = $this->client()->get($endpoint, [
                'tenantId' => $tenantId,
            ]);

            if ($response->status() === 404) {
                return [
                    'data' => null,
                    'error' => "No student record was found for number {$studentNo}.",
                ];
            }

            $response->throw();

            return [
                'data' => $response->json(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load student profile', [
                'student_no' => $studentNo,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($endpoint),
            ]);

            return [
                'data' => null,
                'error' => 'Unable to load your profile right now.',
            ];
        }
    }

    public function gradeReportForStudent(?string $studentNo, ?string $tenantId): array
    {
        if (blank($studentNo)) {
            return [
                'data' => [],
                'error' => 'No student number is linked to your SSO account.',
            ];
        }

        if (blank($tenantId)) {
            return [
                'data' => [],
                'error' => 'No tenant ID is linked to your SSO account.',
            ];
        }

        $endpoint = 'Grades/studentreport/'.rawurlencode($studentNo);

        try {
            $response = $this->client()->get($endpoint, [
                'tenantId' => $tenantId,
            ]);

            if ($response->status() === 404) {
                Log::info('No grade report found for student', [
                    'student_no' => $studentNo,
                    'tenant_id' => $tenantId,
                    'response_body' => $response->body(),
                ]);

                return [
                    'data' => [],
                    'error' => "No grade report was found for student number {$studentNo}.",
                ];
            }

            $response->throw();

            $data = $response->json();

            return [
                'data' => is_array($data) ? $data : [],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load student grades', [
                'student_no' => $studentNo,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($endpoint),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load your grades right now.',
            ];
        }
    }

    public function facultyEvaluations(?int $campusId, ?int $termId, ?string $studentNo): array
    {
        $activeTerm = null;

        // Resolve correctly from site settings based on the campus
        if ($campusId) {
            $activeTerm = SiteAcademicTerm::where('status', 'Active')
                ->whereHas('campus', function ($query) use ($campusId) {
                    $query->where('real_campus_id', $campusId);
                })
                ->first();

            if ($activeTerm) {
                $termId = (int) $activeTerm->term_id;
            }
        }

        if (! $campusId || ! $termId || ! $studentNo) {
            return [
                'data' => null,
                'error' => 'Missing required parameters for faculty evaluations.',
            ];
        }

        $endpoint = "FacultyEvaluations/campus/{$campusId}/term/{$termId}/student/".rawurlencode($studentNo);

        try {
            $response = $this->client()->get($endpoint);

            if ($response->status() === 404) {
                return [
                    'data' => null,
                    'error' => 'No faculty evaluations found.',
                ];
            }

            $response->throw();

            return [
                'data' => $response->json(),
                'term_id' => $termId,
                'term_name' => $activeTerm ? "{$activeTerm->school_year} {$activeTerm->semester}" : null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load faculty evaluations', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'term_id' => $termId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($endpoint),
            ]);

            return [
                'data' => null,
                'error' => 'Unable to load faculty evaluations right now.',
            ];
        }
    }

    public function generateSarToken(string $studentNo, int|string $campusId, int|string $tenantId): array
    {
        try {
            $tenantQuery = (string) ($tenantId ?: $campusId);
            $endpoint = 'Auth/token/'.rawurlencode($studentNo).'/'.rawurlencode((string) $campusId).'?tenantId='.rawurlencode($tenantQuery);

            $client = $this->client()->withHeaders(['accept' => 'text/plain']);

            $getResponse = $client->get($endpoint);
            if ($getResponse->successful()) {
                return ['ok' => true, 'token' => (string) $getResponse->body(), 'error' => null];
            }

            $postResponse = $client->post($endpoint);
            if ($postResponse->successful()) {
                return ['ok' => true, 'token' => (string) $postResponse->body(), 'error' => null];
            }

            Log::warning('SAR token request rejected', [
                'endpoint' => $this->urlFor($endpoint),
                'get_status' => $getResponse->status(),
                'get_body' => $getResponse->body(),
                'post_status' => $postResponse->status(),
                'post_body' => $postResponse->body(),
            ]);

            return [
                'ok' => false,
                'token' => null,
                'error' => 'Token API rejected request: GET '.$getResponse->status().' / POST '.$postResponse->status(),
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to generate SAR token', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'token' => null, 'error' => 'Unable to generate token: '.$exception->getMessage()];
        }
    }

    public function submitSarConfirmation(array $payload, string $username, int|string $tenantId): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders([
                    'Content-type' => 'application/json',
                    'Username' => $username,
                ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post('sar/SarTrialPrograms?tenantId='.rawurlencode((string) $tenantId), $payload);

            if (!in_array($response->status(), [200, 201], true)) {
                return ['ok' => false, 'message' => $response->body(), 'status' => $response->status()];
            }

            return ['ok' => true, 'message' => $response->body(), 'status' => $response->status()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage(), 'status' => 500];
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->accept('*/*')
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);
    }

    private function urlFor(string $endpoint): string
    {
        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }
}
