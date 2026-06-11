<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrarApiService
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

    /**
     * @return array{data: array<int, mixed>, error: string|null}
     */
    public function getStudentGradeReport(string $studentNo, int $tenantId): array
    {
        $endpoint = 'Grades/studentreport/'.rawurlencode($studentNo);

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->get($this->urlFor($endpoint), [
                    'tenantId' => $tenantId,
                ]);

            if (! $response->successful()) {
                Log::warning('Registrar grade report API request failed.', [
                    'student_no' => $studentNo,
                    'tenant_id' => $tenantId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return [
                    'data' => [],
                    'error' => 'Unable to retrieve grade report from Academic API. Please try again later.',
                ];
            }

            $data = $response->json();

            return [
                'data' => is_array($data) ? (array_is_list($data) ? $data : [$data]) : [],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Registrar grade report API exception.', [
                'student_no' => $studentNo,
                'tenant_id' => $tenantId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'data' => [],
                'error' => 'Unable to retrieve grade report from Academic API. Please try again later.',
            ];
        }
    }

    private function urlFor(string $endpoint): string
    {
        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }
}
