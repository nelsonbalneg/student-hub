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

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.evaluation_api.base_url'), '/');
        $this->timeout = (int) config('services.evaluation_api.timeout', 15);
        $this->connectTimeout = (int) config('services.evaluation_api.connect_timeout', 5);
    }

    /**
     * Step 1: Find student evaluation system ID using student number.
     * GET /api/app/student?StudentNo={studentNo}
     */
    public function findStudentByStudentNo(string $studentNo): ?string
    {
        try {
            $response = $this->client()
                ->get('student', [
                    'StudentNo' => $studentNo,
                ])
                ->throw();

            $data = $response->json();
            
            return $data['items'][0]['id'] ?? null;
        } catch (Throwable $exception) {
            Log::warning('Unable to find student evaluation ID', [
                'student_no' => $studentNo,
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
            $response = $this->client()
                ->get("student/{$studentId}")
                ->throw();

            return $response->json();
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
    public function buildEvaluationLookup(array $evaluationPeriods): array
    {
        $lookup = [];

        foreach ($evaluationPeriods as $period) {
            $termId = $period['termId'] ?? null;
            
            // API Spelling: subjectsForEvalution
            $subjects = $period['subjectsForEvalution'] ?? [];

            foreach ($subjects as $subject) {
                $subjectId = $subject['subjectId'] ?? null;
                
                if ($termId && $subjectId) {
                    $key = "{$termId}-{$subjectId}";
                    
                    // A subject requires evaluation if its overall status is 'Not Evaluated'
                    // or if any of its evaluations (lecture/lab) are 'Not Evaluated'.
                    $isNotEvaluated = ($subject['status'] ?? '') === 'Not Evaluated';
                    
                    $evaluations = $subject['evaluations'] ?? [];
                    $hasPendingEvaluations = false;
                    $pendingItems = [];

                    foreach ($evaluations as $eval) {
                        if (($eval['status'] ?? '') === 'Not Evaluated') {
                            $hasPendingEvaluations = true;
                            $pendingItems[] = [
                                'faculty' => $eval['faculty'] ?? 'Unknown Faculty',
                                'facultyEmployeeId' => $eval['facultyEmployeeId'] ?? '',
                                'type' => ($eval['lecture'] ?? false) ? 'lecture' : (($eval['lab'] ?? false) ? 'lab' : 'unknown'),
                                'id' => $eval['id'] ?? '',
                                'surveyTemplateId' => $eval['surveyTemplateId'] ?? '',
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
                        'faculty_names' => collect($evaluations)->pluck('faculty')->unique()->filter()->values()->all(),
                        'evaluation_payload' => [
                            'evaluationPeriodId' => $period['id'] ?? '',
                            'subjectForEvaluationId' => $subject['id'] ?? '',
                            'subjectId' => $subjectId,
                            'termId' => $termId,
                        ]
                    ];
                }
            }
        }

        return $lookup;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'accept' => 'text/plain',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);
    }
}
