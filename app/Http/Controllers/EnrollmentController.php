<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    public function __construct(private readonly AcademicApiService $academicApi) {}

    public function submitConfirmation(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $campusId = $user->campus_id;
        $tenantId = $user->tenant_id;
        $transactionType = (string) $request->input('transactionType', 'Enrollment');

        Log::info('Enrollment confirmation request received', [
            'user_id' => $user?->id,
            'student_no' => $studentNo,
            'campus_id' => $campusId,
            'tenant_id' => $tenantId,
            'transaction_type' => $transactionType,
        ]);

        if (! $studentNo || ! $campusId || ! $tenantId) {
            Log::warning('Enrollment confirmation missing student context', [
                'user_id' => $user?->id,
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'response' => 'error',
                'message' => 'Missing student context (studentNo/campusId/tenantId).',
            ], 422);
        }

        $tokenResult = $this->academicApi->generateSarToken($studentNo, $campusId, $tenantId);
        if (! ($tokenResult['ok'] ?? false)) {
            Log::warning('Enrollment confirmation token generation failed', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
                'token_result' => $tokenResult,
            ]);

            return response()->json([
                'response' => 'error',
                'message' => $tokenResult['error'] ?? 'Unable to generate token.',
            ], 422);
        }

        $rawToken = (string) ($tokenResult['token'] ?? '');
        $normalizedToken = trim($rawToken);

        // Some APIs return a JSON-encoded string token ("..."), unwrap it.
        $jsonToken = json_decode($normalizedToken, true);
        if (is_string($jsonToken) && $jsonToken !== '') {
            $normalizedToken = $jsonToken;
        }

        // Remove wrapping quotes if present.
        $normalizedToken = trim($normalizedToken, "\"' \t\n\r\0\x0B");

        $jwt = base64_decode($normalizedToken, true);
        if (! $jwt) {
            Log::warning('Enrollment confirmation invalid base64 token', [
                'student_no' => $studentNo,
                'campus_id' => $campusId,
                'tenant_id' => $tenantId,
                'raw_token_sample' => substr($rawToken, 0, 120),
            ]);

            return response()->json([
                'response' => 'error',
                'message' => 'Invalid base64 token response.',
            ], 422);
        }

        $secretKey = (string) config('services.academic_jwt.secret');
        $algorithm = (string) config('services.academic_jwt.algorithm', 'HS256');

        if ($secretKey === '') {
            Log::warning('Enrollment confirmation missing ACADEMIC_JWT_SECRET');
            return response()->json([
                'response' => 'error',
                'message' => 'Missing JWT secret configuration.',
            ], 500);
        }

        try {
            // Allow small clock skew between this server and token issuer.
            JWT::$leeway = 60;
            $decodedObj = JWT::decode($jwt, new Key($secretKey, $algorithm));
            $decoded = json_decode(json_encode($decodedObj, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Enrollment confirmation JWT verify/decode failed', [
                'student_no' => $studentNo,
                'message' => $e->getMessage(),
                'algorithm' => $algorithm,
                'secret_length' => strlen($secretKey),
                'jwt_sample' => substr($jwt, 0, 120),
            ]);

            return response()->json([
                'response' => 'error',
                'message' => 'Unable to verify/decode JWT token.',
            ], 422);
        }

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $termId = $activeSemester['termId'] ?? ($decoded['termId'] ?? null);
        $termLabel = $activeSemester['semester'] ?? '';

        $payload = [
            'studentNo' => $decoded['studentNo'] ?? $studentNo,
            'studentName' => $decoded['studentName'] ?? $user->name,
            'campusId' => $decoded['campusId'] ?? (string) $campusId,
            'termId' => $termId,
            'term' => $termLabel,
            'programId' => $decoded['programId'] ?? '',
            'majorId' => $decoded['majorDiscId'] ?? 0,
            'status' => 'Submitted',
            'Submitted' => true,
            'transactionType' => $transactionType,
        ];

        Log::info('Enrollment confirmation submitting to SAR API', [
            'student_no' => $decoded['studentNo'] ?? $studentNo,
            'payload' => $payload,
        ]);

        // Legacy behavior uses campusId in tenantId query parameter.
        $submit = $this->academicApi->submitSarConfirmation(
            $payload,
            (string) ($decoded['studentNo'] ?? $studentNo),
            (string) ($decoded['campusId'] ?? $campusId),
        );

        if (! ($submit['ok'] ?? false)) {
            Log::warning('Enrollment confirmation SAR submit failed', [
                'student_no' => $decoded['studentNo'] ?? $studentNo,
                'submit_result' => $submit,
                'payload' => $payload,
            ]);
        } else {
            Log::info('Enrollment confirmation SAR submit success', [
                'student_no' => $decoded['studentNo'] ?? $studentNo,
                'submit_result' => $submit,
            ]);
        }

        $rawMessage = (string) ($submit['message'] ?? '');
        $parsed = json_decode($rawMessage, true);
        $message = is_array($parsed) && isset($parsed['message'])
            ? (string) $parsed['message']
            : ($rawMessage !== '' ? $rawMessage : (($submit['ok'] ?? false) ? 'added' : 'error'));

        return response()->json([
            'response' => ($submit['ok'] ?? false) ? 'success' : 'error',
            'message' => $message,
        ], ($submit['ok'] ?? false) ? 200 : 422);
    }
}
