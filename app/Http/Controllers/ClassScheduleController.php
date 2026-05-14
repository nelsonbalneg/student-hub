<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ClassScheduleController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly GetActiveAcademicTermService $activeTermService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user->can('view class schedule') || $user->hasAnyRole(['Student', 'Super Admin']),
            403,
        );

        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? 1 : (string) $user->tenant_id;
        $activeTerm = $this->activeTermService->execute($user->campus_id);
        $termId = $activeTerm?->term_id;
        $termName = $activeTerm
            ? trim(collect([$activeTerm->school_year, $activeTerm->semester])->filter()->implode(' '))
            : null;

        if (blank($termId)) {
            $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
            $termId = $activeSemester['termId'];
            $termName = $activeSemester['semester'];
        }

        return Inertia::render('ClassSchedule/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => (string) $tenantId,
            ],
            'activeTerm' => [
                'term_id' => blank($termId) ? null : (string) $termId,
                'name' => $termName ?: 'Active Term',
            ],
            'classSchedule' => $this->academicApi->classScheduleForStudent($studentNo, $termId, $tenantId),
        ]);
    }

    public function downloadCOR(Request $request): SymfonyResponse
    {
        $user = $request->user();

        abort_unless(
            $user->can('download cor') || $user->hasAnyRole(['Student', 'Super Admin']),
            403,
        );

        $studentNo = $this->academicApi->studentNumberFor($user);
        $termId = $this->resolveActiveTermId($user);
        $tenantId = 1;

        try {
            $registrationResult = $this->academicApi->registrationForStudentTerm($studentNo, $termId, $tenantId);
            $registration = $registrationResult['data'];

            if ($registrationResult['error']) {
                Log::warning('COR download registration lookup failed', [
                    'user_id' => $user->id,
                    'student_no' => $studentNo,
                    'term_id' => $termId,
                    'error' => $registrationResult['error'],
                ]);

                return $this->downloadError('Unable to load your registration record right now.');
            }

            if (! $registration) {
                return $this->downloadError('No registration record found for the active semester.');
            }

            $regId = $this->valueFrom($registration, ['regId', 'regID', 'RegID', 'registrationId', 'registration_id', 'id']);
            $campusId = $this->valueFrom($registration, ['campusId', 'campus_id']);
            $registrationStudentNo = $this->valueFrom($registration, ['studentNo', 'student_no']) ?? $studentNo;
            $registrationTermId = $this->valueFrom($registration, ['termId', 'term_id']) ?? $termId;

            if (blank($regId)) {
                Log::warning('COR download missing registration ID', [
                    'user_id' => $user->id,
                    'student_no' => $studentNo,
                    'term_id' => $termId,
                    'registration' => $registration,
                ]);

                return $this->downloadError('Registration ID not found.');
            }

            $reportName = (string) $campusId === '3' ? 'COR_KCC' : 'COR';
            $pdf = $this->requestCorPdf((string) $regId, $reportName, [
                'user_id' => $user->id,
                'student_no' => $registrationStudentNo,
                'term_id' => $registrationTermId,
                'campus_id' => $campusId,
            ]);

            if ($pdf === null) {
                return $this->downloadError('Unable to generate your COR right now. Please try again later.');
            }

            $safeStudentNo = preg_replace('/[^A-Za-z0-9-]/', '', (string) $registrationStudentNo) ?: 'student';
            $safeTermId = preg_replace('/[^A-Za-z0-9-]/', '', (string) $registrationTermId) ?: 'term';
            $filename = "COR-{$safeStudentNo}-{$safeTermId}.pdf";

            Log::info('COR PDF generated successfully', [
                'user_id' => $user->id,
                'student_no' => $registrationStudentNo,
                'term_id' => $registrationTermId,
                'campus_id' => $campusId,
                'report_name' => $reportName,
                'filename' => $filename,
                'bytes' => strlen($pdf),
            ]);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (Throwable $exception) {
            Log::error('Unexpected COR download failure', [
                'user_id' => $user->id,
                'student_no' => $studentNo,
                'term_id' => $termId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->downloadError('Unable to download your COR right now. Please try again later.');
        }
    }

    private function resolveActiveTermId($user): ?string
    {
        $activeTerm = $this->activeTermService->execute($user->campus_id);

        if (filled($activeTerm?->term_id)) {
            return (string) $activeTerm->term_id;
        }

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);

        return blank($activeSemester['termId']) ? null : (string) $activeSemester['termId'];
    }

    private function requestCorPdf(string $regId, string $reportName, array $context): ?string
    {
        $baseUrl = rtrim((string) config('services.report_server.base_url'), '/');
        $timeout = (int) config('services.report_server.timeout', 30);
        $connectTimeout = (int) config('services.report_server.connect_timeout', 5);
        $apiUrl = $baseUrl.'/reports/get-pdf-report';
        $queryParams = [
            'folder' => 'enrollment',
            'reportName' => $reportName,
        ];

        Log::info('Requesting COR PDF from report server', [
            ...$context,
            'report_name' => $reportName,
            'reg_id' => $regId,
            'url' => $apiUrl,
        ]);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry(2, 250)
            ->post($apiUrl.'?'.http_build_query($queryParams), [
                'RegID' => $regId,
            ]);

        if (! $response->successful()) {
            Log::warning('COR report server request failed', [
                ...$context,
                'report_name' => $reportName,
                'reg_id' => $regId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $base64Pdf = $this->extractBase64Pdf($response->json() ?? $response->body());

        if (blank($base64Pdf)) {
            Log::warning('COR report server returned no PDF payload', [
                ...$context,
                'report_name' => $reportName,
                'reg_id' => $regId,
                'body' => $response->body(),
            ]);

            return null;
        }

        $base64Pdf = preg_replace('/^data:application\/pdf;base64,/i', '', trim((string) $base64Pdf));
        $base64Pdf = preg_replace('/\s+/', '', (string) $base64Pdf);
        $pdf = base64_decode($base64Pdf, true);

        if (! is_string($pdf) || $pdf === '' || ! str_starts_with(ltrim($pdf), '%PDF')) {
            Log::warning('COR report server returned invalid PDF content', [
                ...$context,
                'report_name' => $reportName,
                'reg_id' => $regId,
                'decoded_bytes' => is_string($pdf) ? strlen($pdf) : 0,
            ]);

            return null;
        }

        return $pdf;
    }

    private function extractBase64Pdf(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $trimmed = trim($payload, " \t\n\r\0\x0B\"");

            return $trimmed === '' ? null : $trimmed;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['data', 'result', 'file', 'pdf', 'base64', 'content', 'report'] as $key) {
            $value = Arr::get($payload, $key);

            if (is_string($value) && filled($value)) {
                return $value;
            }

            if (is_array($value)) {
                $nested = $this->extractBase64Pdf($value);

                if ($nested) {
                    return $nested;
                }
            }
        }

        foreach ($payload as $value) {
            $nested = $this->extractBase64Pdf($value);

            if ($nested) {
                return $nested;
            }
        }

        return null;
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

    private function downloadError(string $message): SymfonyResponse
    {
        return response()->json([
            'message' => $message,
        ], 422);
    }
}
