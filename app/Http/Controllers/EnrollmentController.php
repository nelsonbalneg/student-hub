<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use App\Services\SarAccessService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly SarAccessService $sarAccess
    ) {}

    private function isSarClosed(Request $request): bool
    {
        return ! $this->sarAccess->allows($request->user(), $request->input('term_id'));
    }

    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Enrollment/StudentAcademicRegistration');
    }

    public function records(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        if (blank($studentNo) || blank($user->campus_id)) {
            return response()->json(['message' => 'Missing student or campus context.'], 422);
        }
        $moduleEnabled = $this->sarAccess->moduleEnabled();
        $rows = $this->sarAccess->rulesFor($user)->map(function ($rule) use ($user, $studentNo, $moduleEnabled): array {
            $term = $rule->academicTerm;
            $sar = $this->academicApi->sarTrialProgramByStudentTerm((string) $studentNo, (string) $term->term_id, (string) $user->campus_id);
            $record = $sar['data'] ?? [];
            $verified = (bool) ($sar['ok'] ?? false);

            return [
                'studentNo' => $studentNo,
                'studentName' => $user->name,
                'termId' => $term->term_id,
                'term' => $term->school_year.', '.$term->semester,
                'campusName' => $term->campus->campus_name,
                'status' => $record['status'] ?? null,
                'submitted' => (bool) ($record['submitted'] ?? false),
                'transactionType' => $record['transactionType'] ?? null,
                'programName' => $record['programName'] ?? null,
                'curriculum' => $record['curriculum'] ?? null,
                'dateCreated' => $record['dateCreated'] ?? null,
                'sarId' => $record['id'] ?? null,
                'updatedAt' => $record['dateUpdated'] ?? $record['updatedAt'] ?? $record['updated_at'] ?? $record['dateModified'] ?? $record['dateCreated'] ?? null,
                'can_apply' => $moduleEnabled && ($rule->is_enabled || $rule->has_user_override) && $verified,
                'registration_open' => $moduleEnabled && ($rule->is_enabled || $rule->has_user_override),
                'verification_error' => $verified ? null : 'Unable to verify this term. Refresh to try again.',
            ];
        });

        return response()->json(['response' => 'success', 'data' => $rows->values()]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        if ($this->isSarClosed($request)) {
            abort(403, 'Student Academic Registration is currently closed.');
        }

        return redirect()->route('enrollment.student-academic-registration');
    }

    public function status(Request $request): JsonResponse
    {
        if ($this->isSarClosed($request)) {
            abort(403, 'Student Academic Registration is currently closed.');
        }

        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $campusId = $user->campus_id;
        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $termId = $activeSemester['termId'] ?? null;
        $tenantId = $campusId; // keep old behavior

        if (! $studentNo || ! $termId || ! $tenantId) {
            return response()->json([
                'response' => 'error',
                'message' => 'Missing student/term context.',
                'data' => null,
            ], 422);
        }

        $sar = $this->academicApi->sarTrialProgramByStudentTerm((string) $studentNo, (string) $termId, (string) $tenantId);
        if (! ($sar['ok'] ?? false)) {
            return response()->json(['message' => 'Unable to verify your SAR status. Please try again.'], 503);
        }

        $record = $sar['data'] ?? null;

        return response()->json([
            'response' => 'success',
            'data' => [
                'studentNo' => $studentNo,
                'studentName' => $user->name,
                'termId' => $termId,
                'term' => $record['term'] ?? $activeSemester['semester'] ?? null,
                'status' => $record['status'] ?? null,
                'submitted' => (bool) ($record['submitted'] ?? false),
                'transactionType' => $record['transactionType'] ?? null,
                'programName' => $record['programName'] ?? null,
                'curriculum' => $record['curriculum'] ?? null,
                'dateCreated' => $record['dateCreated'] ?? null,
                'campusName' => $record['campusName'] ?? $user->campus_name,
                'sarId' => $record['id'] ?? null,
                'updatedAt' => $record['dateUpdated'] ?? $record['updatedAt'] ?? $record['updated_at'] ?? $record['dateModified'] ?? $record['dateCreated'] ?? null,
                'checkedAt' => Carbon::now()->toDateTimeString(),
            ],
        ]);
    }

    public function submitConfirmation(Request $request): JsonResponse
    {
        $request->validate(['term_id' => ['sometimes', 'required', 'string', 'max:255']]);
        if ($this->isSarClosed($request)) {
            abort(403, 'Student Academic Registration is currently closed.');
        }

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

        if ($request->filled('term_id')) {
            $termId = $request->string('term_id')->toString();
            $termRule = $this->sarAccess->rulesFor($user)->first(fn ($rule) => (string) $rule->academicTerm->term_id === $termId);
            abort_unless($termRule && ($termRule->is_enabled || $termRule->has_user_override), 403, 'Registration is closed for this term.');
            $termLabel = $termRule->academicTerm->school_year.', '.$termRule->academicTerm->semester;
        } else {
            $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
            $termId = $activeSemester['termId'] ?? ($decoded['termId'] ?? null);
            $termLabel = $activeSemester['semester'] ?? '';
        }

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
