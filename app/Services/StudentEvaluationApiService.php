<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StudentEvaluationApiService
{
    private string $baseUrl;

    private int $timeout;

    private int $connectTimeout;

    private bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.evaluation_api.base_url'), '/');
        $this->timeout = (int) config('services.evaluation_api.timeout', 15);
        $this->connectTimeout = (int) config('services.evaluation_api.connect_timeout', 5);
        $this->verifySsl = (bool) config('services.evaluation_api.verify_ssl', true);
    }

    /**
     * Step 1: Find student evaluation system ID using campus and tenant context.
     * GET /api/app/student/student-portal-token?campusId={campusId}&studentNo={studentNo}&tenantId={tenantId}
     */
    public function findStudentByStudentNo(string $studentNo, int|string|null $campusId, int|string|null $tenantId): ?string
    {
        if (blank($studentNo) || blank($campusId) || blank($tenantId)) {
            Log::warning('Unable to find student evaluation ID because required context is missing', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        try {
            Log::info('Requesting evaluation student portal token', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            $response = $this->client()
                ->get('student/student-portal-token', [
                    'campusId' => $campusId,
                    'studentNo' => $studentNo,
                    'tenantId' => $tenantId,
                ])
                ->throw();

            $studentId = $this->studentIdFromPortalToken($response->body(), $studentNo, $campusId, $tenantId);

            if ($studentId) {
                Log::info('Resolved evaluation student ID from portal token', [
                    'student_no' => $studentNo,
                    'campus_id' => $campusId,
                    'tenant_id' => $tenantId,
                    'student_id' => $studentId,
                ]);
            }

            return $studentId;
        } catch (Throwable $exception) {
            Log::warning('Unable to find student evaluation ID', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Step 2: Use the student ID to get evaluation periods and subjects.
     * GET /api/app/student/{id}
     */
    public function getStudentEvaluationDetails(string $studentId): array
    {
        try {
            Log::info('Requesting student evaluation details', [
                'student_id' => $studentId,
            ]);

            $response = $this->client()
                ->get("student/{$studentId}")
                ->throw();

            $details = $response->json();

            Log::info('Loaded student evaluation details', [
                'student_id' => $studentId,
                'evaluation_period_count' => count($details['evaluationPeriods'] ?? []),
            ]);

            return $details;
        } catch (Throwable $exception) {
            Log::warning('Unable to get student evaluation details', [
                'student_id' => $studentId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build lookup key: `${termId}-${subjectId}`
     */
    public function buildEvaluationLookup(array $studentDetails): array
    {
        $evaluationPeriods = $studentDetails['evaluationPeriods'] ?? [];
        $studentId = $studentDetails['id'] ?? '';
        $studentNo = $studentDetails['studentNo'] ?? '';
        $studentName = $studentDetails['name'] ?? '';
        $campusId = $studentDetails['campusId'] ?? '';

        $lookup = [];

        foreach ($evaluationPeriods as $period) {
            $termId = $period['termId'] ?? null;
            $periodStudentId = $period['studentId'] ?? $studentId;

            // API Spelling: subjectsForEvalution
            $subjects = $period['subjectsForEvalution'] ?? [];

            foreach ($subjects as $subject) {
                $subjectId = $subject['subjectId'] ?? null;

                if ($termId && $subjectId) {
                    $key = "{$termId}-{$subjectId}";

                    // A subject requires evaluation if its overall status is 'Not Evaluated'
                    // or if any of its evaluations (lecture/lab) are 'Not Evaluated'.
                    $isNotEvaluated = $this->isNotEvaluatedStatus($subject['status'] ?? null);

                    $evaluations = $subject['evaluations'] ?? [];
                    $hasPendingEvaluations = false;
                    $pendingItems = [];
                    $evaluatedItems = [];

                    foreach ($evaluations as $eval) {
                        $surveyTemplate = $eval['surveyTemplate'] ?? null;
                        $evaluationId = $eval['id'] ?? $eval['subjectForEvaluationId'] ?? '';

                        if ($this->isNotEvaluatedStatus($eval['status'] ?? null)) {
                            $jsonString = [
                                'studentId' => $periodStudentId,
                                'templateSurveyId' => $surveyTemplate['id'] ?? ($eval['surveyTemplateId'] ?? ''),
                                'evaluationId' => $evaluationId,
                                'code' => $surveyTemplate['code'] ?? '',
                                'name' => $surveyTemplate['name'] ?? '',
                                'description' => $surveyTemplate['description'] ?? '',
                                'studentNo' => $studentNo,
                                'studentName' => $studentName,
                                'subjectId' => $subjectId,
                                'schedId' => $subject['schedId'] ?? '',
                                'campusId' => $campusId,
                                'termId' => $termId,
                                'isLaboratory' => (bool) ($eval['lab'] ?? false),
                            ];

                            $hasPendingEvaluations = true;
                            $pendingItems[] = [
                                'faculty' => $eval['facultyName'] ?? $eval['faculty'] ?? 'Unknown Faculty',
                                'type' => ($eval['lecture'] ?? false) ? 'lecture' : (($eval['lab'] ?? false) ? 'lab' : 'unknown'),
                                'id' => $evaluationId,
                                'status' => $eval['status'] ?? 'Not Evaluated',
                                'isEvaluated' => false,
                                'surveyTemplateId' => $eval['surveyTemplateId'] ?? '',
                                'surveyTemplateDescription' => $surveyTemplate['description'] ?? '',
                                'encodedSurveyTemplate' => base64_encode((string) json_encode($surveyTemplate ?? new \stdClass)),
                                'encodedJsonString' => base64_encode((string) json_encode($jsonString)),
                            ];
                        }

                        if (! $this->isNotEvaluatedStatus($eval['status'] ?? null)) {
                            $evaluatedItems[] = [
                                'faculty' => $eval['facultyName'] ?? $eval['faculty'] ?? 'Unknown Faculty',
                                'type' => ($eval['lecture'] ?? false) ? 'lecture' : (($eval['lab'] ?? false) ? 'lab' : 'unknown'),
                                'id' => $evaluationId,
                                'status' => $eval['status'] ?? 'Evaluated',
                                'isEvaluated' => true,
                                'evaluationDate' => ($eval['lab'] ?? false)
                                    ? ($eval['labEvaluationDate'] ?? null)
                                    : ($eval['lectureEvaluationDate'] ?? null),
                                'surveyTemplateId' => $eval['surveyTemplateId'] ?? '',
                                'surveyTemplateName' => $surveyTemplate['name'] ?? '',
                                'surveyTemplateDescription' => $surveyTemplate['description'] ?? '',
                                'encodedSurveyTemplate' => base64_encode((string) json_encode($surveyTemplate ?? new \stdClass)),
                                'encodedSurveyAnswers' => base64_encode((string) json_encode($eval['surveys'] ?? [])),
                            ];
                        }
                    }

                    $lookup[$key] = [
                        'requires_evaluation' => $isNotEvaluated || $hasPendingEvaluations,
                        'status' => $subject['status'] ?? 'Unknown',
                        'evaluation_period_id' => $period['id'] ?? '',
                        'subject_for_evaluation_id' => $subject['id'] ?? '',
                        'subject_id' => $subjectId,
                        'subject_title' => $subject['subjectTitle'] ?? '',
                        'term_id' => $termId,
                        'pending_evaluations' => $pendingItems,
                        'evaluated_evaluations' => $evaluatedItems,
                        'faculty_names' => collect($evaluations)->map(fn ($e) => $e['facultyName'] ?? $e['faculty'] ?? null)->unique()->filter()->values()->all(),
                        'evaluation_payload' => [
                            'evaluationPeriodId' => $period['id'] ?? '',
                            'subjectForEvaluationId' => $subject['id'] ?? '',
                            'subjectId' => $subjectId,
                            'termId' => $termId,
                        ],
                    ];
                }
            }
        }

        return $lookup;
    }

    public function submitSurvey(array $payload): array
    {
        try {
            $response = $this->client()
                ->post('survey/', $payload);

            if ($response->failed()) {
                $body = $response->body();
                Log::warning('Evaluation API rejected survey submission', [
                    'status' => $response->status(),
                    'body' => $body,
                    'payload' => $payload,
                ]);

                return [
                    'ok' => false,
                    'message' => "Evaluation API rejected request (HTTP {$response->status()}): {$body}",
                ];
            }

            return ['ok' => true, 'message' => null];
        } catch (Throwable $exception) {
            Log::warning('Unable to submit evaluation survey', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'accept' => 'text/plain',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->withOptions([
                'verify' => $this->verifySsl,
            ])
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);
    }

    private function isNotEvaluatedStatus(mixed $status): bool
    {
        return strtolower(trim((string) $status)) === 'not evaluated';
    }

    private function studentIdFromPortalToken(string $token, string $studentNo, int|string $campusId, int|string $tenantId): ?string
    {
        $decoded = base64_decode(trim($token), true);

        if ($decoded === false) {
            Log::warning('Evaluation student portal token is not valid base64', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            Log::warning('Evaluation student portal token is not valid JSON', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $studentId = $payload['studentId'] ?? null;

        if (blank($studentId)) {
            Log::warning('Evaluation student portal token is missing studentId', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        return (string) $studentId;
    }
}
