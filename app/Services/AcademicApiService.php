<?php

namespace App\Services;

use App\Models\SiteAcademicTerm;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
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

    public function classScheduleForStudent(?string $studentNo, int|string|null $termId, int|string|null $tenantId = 1): array
    {
        if (blank($studentNo)) {
            return [
                'data' => [],
                'error' => 'No student number is linked to your SSO account.',
            ];
        }

        if (blank($termId)) {
            return [
                'data' => [],
                'error' => 'No active academic term is available right now.',
            ];
        }

        $tenantId = blank($tenantId) ? 1 : $tenantId;
        $registrationEndpoint = 'Registrations/schedules/'.rawurlencode($studentNo).'/'.rawurlencode((string) $termId);

        try {
            $response = $this->client()->get($registrationEndpoint, [
                'tenantId' => $tenantId,
            ]);

            if ($response->status() === 404) {
                return [
                    'data' => [],
                    'error' => null,
                ];
            }

            $response->throw();
            $registrations = $this->listFromPayload($response->json());
        } catch (Throwable $exception) {
            Log::warning('Unable to load student class schedule registrations', [
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($registrationEndpoint),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load your class schedule right now.',
            ];
        }

        if ($registrations === []) {
            return [
                'data' => [],
                'error' => null,
            ];
        }

        $registrationsWithSubjects = collect($registrations)
            ->filter(fn (array $registration): bool => filled($this->valueFrom($registration, [
                'subjectId',
                'subject_id',
                'courseId',
                'course_id',
            ])))
            ->values();

        if ($registrationsWithSubjects->isEmpty()) {
            Log::warning('Class schedule registrations did not include subject IDs', [
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'registration_count' => count($registrations),
            ]);

            return [
                'data' => [],
                'error' => 'Your enrolled subjects could not be matched to class schedules right now.',
            ];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($registrationsWithSubjects, $termId, $tenantId) {
                return $registrationsWithSubjects
                    ->map(function (array $registration, int $index) use ($pool, $termId, $tenantId) {
                        $subjectId = $this->valueFrom($registration, [
                            'subjectId',
                            'subject_id',
                            'courseId',
                            'course_id',
                        ]);

                        return $pool
                            ->as((string) $index)
                            ->accept('*/*')
                            ->connectTimeout($this->connectTimeout)
                            ->timeout($this->timeout)
                            ->get($this->urlFor('ClassSchedules/get-schedule-by-subject/term/'.rawurlencode((string) $termId).'/subject/'.rawurlencode((string) $subjectId)), [
                                'tenantId' => $tenantId,
                            ]);
                    })
                    ->all();
            });
        } catch (Throwable $exception) {
            Log::warning('Unable to load class schedule details', [
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to load your class schedule right now.',
            ];
        }

        $records = [];
        $hasScheduleFailure = false;

        foreach ($registrationsWithSubjects as $index => $registration) {
            $subjectId = (string) $this->valueFrom($registration, [
                'subjectId',
                'subject_id',
                'courseId',
                'course_id',
            ]);
            $registrationScheduleId = $this->valueFrom($registration, [
                'scheduleId',
                'schedule_id',
                'schedId',
                'sched_id',
            ]);
            $subjectEndpoint = 'ClassSchedules/get-schedule-by-subject/term/'.rawurlencode((string) $termId).'/subject/'.rawurlencode($subjectId);
            $subjectResponse = $responses[(string) $index] ?? null;

            if (! $subjectResponse?->successful()) {
                $hasScheduleFailure = true;

                Log::warning('Unable to load class schedules for enrolled subject', [
                    'student_no' => $studentNo,
                    'term_id' => $termId,
                    'tenant_id' => $tenantId,
                    'subject_id' => $subjectId,
                    'schedule_id' => $registrationScheduleId,
                    'status' => $subjectResponse?->status(),
                    'body' => $subjectResponse?->body(),
                    'url' => $this->urlFor($subjectEndpoint),
                ]);

                continue;
            }

            $schedules = $this->listFromPayload($subjectResponse->json());
            $matchedSchedule = collect($schedules)->first(function (array $schedule) use ($registrationScheduleId): bool {
                $classScheduleId = $this->valueFrom($schedule, [
                    'scheduleId',
                    'schedule_id',
                    'schedId',
                    'sched_id',
                    'id',
                ]);

                return filled($registrationScheduleId)
                    && filled($classScheduleId)
                    && (string) $registrationScheduleId === (string) $classScheduleId;
            });

            if (! $matchedSchedule) {
                Log::info('No matching class schedule section found for registration', [
                    'student_no' => $studentNo,
                    'term_id' => $termId,
                    'tenant_id' => $tenantId,
                    'subject_id' => $subjectId,
                    'registration_schedule_id' => $registrationScheduleId,
                    'schedule_count' => count($schedules),
                ]);

                continue;
            }

            $records[] = $this->normalizeClassSchedule($registration, $matchedSchedule, $subjectId, $registrationScheduleId);
        }

        return [
            'data' => $records,
            'error' => $hasScheduleFailure
                ? 'Some class schedules could not be loaded. Please refresh the page or try again later.'
                : null,
        ];
    }

    public function registrationForStudentTerm(?string $studentNo, int|string|null $termId, int|string|null $tenantId = 1): array
    {
        if (blank($studentNo)) {
            return [
                'data' => null,
                'error' => 'No student number is linked to your SSO account.',
            ];
        }

        if (blank($termId)) {
            return [
                'data' => null,
                'error' => 'No active academic term is available right now.',
            ];
        }

        $tenantId = blank($tenantId) ? 1 : $tenantId;
        $endpoint = 'Registrations/bystudent/'.rawurlencode($studentNo).'/term/'.rawurlencode((string) $termId);

        try {
            Log::info('Loading student registration for COR download', [
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'url' => $this->urlFor($endpoint),
            ]);

            $response = $this->client()
                ->retry(2, 200)
                ->get($endpoint, [
                    'tenantId' => $tenantId,
                ]);

            if ($response->status() === 404) {
                return [
                    'data' => null,
                    'error' => null,
                ];
            }

            $response->throw();

            $registration = collect($this->listFromPayload($response->json()))->first();

            return [
                'data' => $registration ?: null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Unable to load student registration for COR download', [
                'student_no' => $studentNo,
                'term_id' => $termId,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'url' => $this->urlFor($endpoint),
            ]);

            return [
                'data' => null,
                'error' => 'Unable to load your registration record right now.',
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

            if (! in_array($response->status(), [200, 201], true)) {
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

    private function listFromPayload(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        if (is_array($payload)) {
            foreach (['data', 'items', 'records', 'schedules', 'classSchedules', 'registrations', 'subjects'] as $key) {
                if (isset($payload[$key]) && is_array($payload[$key])) {
                    return $this->listFromPayload($payload[$key]);
                }
            }

            return [$payload];
        }

        return [];
    }

    private function normalizeClassSchedule(array $registration, array $schedule, string $subjectId, mixed $registrationScheduleId): array
    {
        $sessions = [];

        for ($index = 1; $index <= 5; $index++) {
            $scheduleText = $this->valueFrom($schedule, ["sched{$index}", "schedule{$index}"]);
            $roomText = $this->valueFrom($schedule, ["room{$index}", "roomName{$index}", "room_name{$index}"]);

            if (blank($scheduleText) && blank($roomText)) {
                continue;
            }

            $sessions[] = [
                'schedule' => filled($scheduleText) ? (string) $scheduleText : null,
                'room' => filled($roomText) ? (string) $roomText : null,
            ];
        }

        return [
            'subject_id' => $subjectId,
            'schedule_id' => filled($registrationScheduleId) ? (string) $registrationScheduleId : null,
            'subject_code' => (string) ($this->valueFrom($registration, [
                'subjectCode',
                'subject_code',
                'courseCode',
                'course_code',
                'code',
            ]) ?? $this->valueFrom($schedule, [
                'subjectCode',
                'subject_code',
                'courseCode',
                'course_code',
                'code',
            ]) ?? 'Subject'),
            'subject_title' => (string) ($this->valueFrom($registration, [
                'subjectTitle',
                'subject_title',
                'subjectDesc',
                'subject_description',
                'courseTitle',
                'course_title',
                'description',
                'title',
            ]) ?? $this->valueFrom($schedule, [
                'subjectTitle',
                'subject_title',
                'subjectDesc',
                'subject_description',
                'courseTitle',
                'course_title',
                'description',
                'title',
            ]) ?? '-'),
            'section' => (string) ($this->valueFrom($schedule, [
                'section',
                'sectionName',
                'section_name',
                'sectionCode',
                'section_code',
            ]) ?? $this->valueFrom($registration, [
                'section',
                'sectionName',
                'section_name',
                'sectionCode',
                'section_code',
            ]) ?? '-'),
            'faculty_name' => (string) ($this->valueFrom($schedule, [
                'facultyName',
                'faculty_name',
                'instructor',
                'instructorName',
                'teacherName',
            ]) ?? 'TBA'),
            'class_size' => $this->valueFrom($schedule, [
                'classSize',
                'class_size',
                'maxClassSize',
                'capacity',
                'slot',
                'slots',
            ]),
            'sessions' => $sessions,
        ];
    }

    private function valueFrom(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function urlFor(string $endpoint): string
    {
        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }
}
