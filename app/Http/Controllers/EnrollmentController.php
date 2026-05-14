<?php

namespace App\Http\Controllers;

use App\Models\SiteAcademicTerm;
use App\Services\AcademicApiService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrollmentController extends Controller
{
    public function __construct(
        protected AcademicApiService $academicApiService
    ) {}

    public function submitConfirmation(Request $request): RedirectResponse
    {
        $user = $request->user();
        $studentNo = $this->academicApiService->studentNumberFor($user);
        $campusId = $user->campus_id;
        $tenantId = $user->tenant_id ?: $campusId;
        $transactionType = (string) $request->input('transactionType', 'Enrollment');
        $active = $this->academicApiService->getActiveSemesterForUser($user);

        if (blank($studentNo) || blank($campusId)) {
            return back()->with('error', 'Missing student number or campus assignment.');
        }
        if (! empty($active['error']) || blank($active['termId']) || blank($active['semester'])) {
            return back()->with('error', $active['error'] ?? 'No active semester found.');
        }

        Log::info('Enrollment confirmation request received', [
            'user_id' => $user->id,
            'student_no' => $studentNo,
            'campus_id' => $campusId,
            'tenant_id' => (string) $tenantId,
            'transaction_type' => $transactionType,
        ]);

        $tokenResult = $this->academicApiService->generateSarToken($studentNo, $campusId, $tenantId);
        if (! ($tokenResult['ok'] ?? false) || blank($tokenResult['token'] ?? null)) {
            Log::warning('Enrollment confirmation token generation failed', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => (string) $tenantId,
                'token_result' => $tokenResult,
            ]);

            return back()->with('error', 'Unable to generate token.');
        }

        $jwt = base64_decode((string) $tokenResult['token'], true);
        if ($jwt === false || blank($jwt)) {
            return back()->with('error', 'Unable to decode base64 token.');
        }
        $jwt = trim($jwt);

        $secret = (string) config('services.academic_jwt.secret');
        if (blank($secret)) {
            $secret = '3a7f92e4b05944c3e2b879ac7d6f8a1f0aef6d94241d70e3cfb8922b1569e8d7';
            Log::warning('Enrollment confirmation JWT secret fallback applied because config value was empty', [
                'student_no' => $studentNo,
            ]);
        }
        $algorithm = (string) config('services.academic_jwt.algorithm', 'HS256');

        try {
            JWT::$leeway = 60;
            $decoded = JWT::decode($jwt, new Key($secret, $algorithm));
        } catch (Throwable $exception) {
            Log::warning('Enrollment confirmation JWT verify/decode failed', [
                'student_no' => $studentNo,
                'message' => $exception->getMessage(),
                'algorithm' => $algorithm,
                'secret_length' => strlen($secret),
                'jwt_sample' => substr($jwt, 0, 120),
            ]);

            return back()->with('error', 'Unable to verify/decode JWT token.');
        }

        $payload = [
            'studentNo' => (string) ($decoded->studentNo ?? $studentNo),
            'studentName' => (string) ($decoded->studentName ?? $user->name),
            'campusId' => (int) ($decoded->campusId ?? $campusId),
            // Match old flow: always submit against active term/semester.
            'termId' => (int) $active['termId'],
            'term' => (string) $active['semester'],
            'programId' => (int) ($decoded->programId ?? 0),
            'majorId' => (int) ($decoded->majorDiscId ?? 0),
            'status' => 'Submitted',
            'submitted' => true,
            'transactionType' => $transactionType,
        ];

        $submit = $this->academicApiService->submitSarConfirmation(
            payload: $payload,
            username: (string) $payload['studentNo'],
            tenantId: $tenantId
        );

        if (! ($submit['ok'] ?? false)) {
            Log::warning('Enrollment confirmation submit failed', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => (string) $tenantId,
                'submit' => $submit,
            ]);

            return back()->with('error', 'Unable to submit confirmation.');
        }

        $body = (string) ($submit['message'] ?? '');
        $decodedResponse = json_decode($body, true);
        $message = is_array($decodedResponse) ? ($decodedResponse['message'] ?? '') : '';

        if ($message === 'added') {
            return back()->with('success', 'Enrollment confirmation submitted successfully.');
        }

        if ($message === 'exists') {
            return back()->with('success', 'Enrollment confirmation already submitted.');
        }

        return back()->with('success', 'Enrollment confirmation processed.');
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentNo = $this->academicApiService->studentNumberFor($user);
        $campusId = $user->campus_id;
        $tenantId = $user->tenant_id ?: $campusId;

        if (blank($studentNo) || blank($campusId)) {
            return response()->json([
                'response' => 'error',
                'message' => 'Missing student number or campus assignment.',
            ], 422);
        }

        $active = $this->academicApiService->getActiveSemesterForUser($user);
        if (! empty($active['error']) || blank($active['termId'])) {
            return response()->json([
                'response' => 'error',
                'message' => $active['error'] ?? 'No active term found.',
            ], 422);
        }

        $statusResult = $this->academicApiService->sarStatusByStudent(
            studentNo: $studentNo,
            termId: (string) $active['termId'],
            tenantId: $tenantId
        );

        // Fallback: if no record for active term, probe other campus terms (latest first) and pick first match.
        if (($statusResult['ok'] ?? false) && empty($statusResult['data'])) {
            $termIds = SiteAcademicTerm::query()
                ->whereHas('campus', fn ($query) => $query->where('real_campus_id', (int) $campusId))
                ->orderByDesc('term_id')
                ->limit(12)
                ->pluck('term_id')
                ->map(fn ($termId) => (string) $termId)
                ->filter()
                ->unique()
                ->values();

            foreach ($termIds as $termId) {
                if ($termId === (string) $active['termId']) {
                    continue;
                }

                $probe = $this->academicApiService->sarStatusByStudent(
                    studentNo: $studentNo,
                    termId: $termId,
                    tenantId: $tenantId
                );

                if (($probe['ok'] ?? false) && ! empty($probe['data'])) {
                    $statusResult = $probe;
                    break;
                }
            }
        }

        if (! ($statusResult['ok'] ?? false)) {
            return response()->json([
                'response' => 'error',
                'message' => 'Unable to load enrollment status.',
            ], 422);
        }

        $record = is_array($statusResult['data']) ? $statusResult['data'] : null;
        $status = $record ? (string) ($record['status'] ?? '') : '';
        $term = $record ? (string) ($record['term'] ?? ($active['semester'] ?? '')) : (string) ($active['semester'] ?? '');

        return response()->json([
            'response' => 'success',
            'status' => $status,
            'term' => $term,
            'submitted' => in_array(strtolower($status), ['submitted', 'pending'], true),
            'record' => $record,
        ]);
    }

}
