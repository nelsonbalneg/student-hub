<?php

namespace App\Http\Controllers\FileVault;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileVault\StoreDossierDocumentRequest;
use App\Http\Requests\FileVault\VerifyDossierDocumentRequest;
use App\Jobs\ScanDossierDocumentForMalware;
use App\Models\DossierAccessLog;
use App\Models\DossierDocument;
use App\Models\StudentDossier;
use App\Services\FileVault\DossierChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DossierDocumentController extends Controller
{
    public function __construct(private readonly DossierChecklistService $dossierChecklistService) {}

    public function store(StoreDossierDocumentRequest $request, StudentDossier $studentDossier): JsonResponse|RedirectResponse
    {
        $uploadedFile = $request->file('file');
        $user = $request->user();

        $version = (int) DossierDocument::query()
            ->where('student_dossier_id', $studentDossier->id)
            ->where('document_type', $request->validated('document_type'))
            ->max('version') + 1;

        $extension = $uploadedFile->getClientOriginalExtension();
        $filename = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $uploadedFile->storeAs("dossiers/{$studentDossier->id}", $filename, 'local');

        $requiredTypes = $this->dossierChecklistService->requiredDocumentTypes($studentDossier);
        $documentType = $request->validated('document_type');

        $document = DossierDocument::query()->create([
            'student_dossier_id' => $studentDossier->id,
            'document_type' => $documentType,
            'document_code' => $request->validated('document_code'),
            'version' => $version,
            'is_required' => in_array($documentType, $requiredTypes, true) || (bool) $request->validated('is_required', false),
            'is_verified' => false,
            'file_path' => $path,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'mime_type' => $uploadedFile->getMimeType() ?? 'application/octet-stream',
            'file_size' => $uploadedFile->getSize() ?: 0,
            'checksum' => hash_file('sha256', $uploadedFile->getRealPath()),
            'scan_status' => 'pending',
            'uploaded_by' => $user->id,
        ]);

        ScanDossierDocumentForMalware::dispatch($document->id);

        $this->logAccess($studentDossier, 'upload', $user->id, [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'version' => $document->version,
        ]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Document uploaded successfully.');
        }

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document,
        ], 201);
    }

    public function verify(VerifyDossierDocumentRequest $request, DossierDocument $document): JsonResponse|RedirectResponse
    {
        $document->update([
            'is_verified' => $request->validated('is_verified'),
            'verified_by' => $request->validated('is_verified') ? $request->user()->id : null,
            'verified_at' => $request->validated('is_verified') ? now() : null,
        ]);

        $this->logAccess($document->dossier, 'verify', $request->user()->id, [
            'document_id' => $document->id,
            'is_verified' => (bool) $request->validated('is_verified'),
        ]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Document verification status updated.');
        }

        return response()->json([
            'message' => 'Document verification status updated.',
            'document' => $document->fresh(),
        ]);
    }

    public function retryScan(Request $request, DossierDocument $document): JsonResponse|RedirectResponse
    {
        $this->authorize('verify', $document);

        abort_if($document->scan_status === 'clean', 422, 'Document is already clean and does not need rescanning.');

        $document->update([
            'scan_status' => 'pending',
            'scan_message' => 'Scan queued for retry.',
            'scanned_at' => null,
        ]);

        ScanDossierDocumentForMalware::dispatch($document->id);

        $this->logAccess($document->dossier, 'verify', $request->user()->id, [
            'document_id' => $document->id,
            'retry_scan' => true,
        ]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Malware scan queued.');
        }

        return response()->json([
            'message' => 'Malware scan queued.',
            'document' => $document->fresh(),
        ]);
    }

    public function destroy(Request $request, DossierDocument $document): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        $this->logAccess($document->dossier, 'delete_document', $request->user()->id, [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
        ]);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Document removed successfully.');
        }

        return response()->json([
            'message' => 'Document removed successfully.',
        ]);
    }

    public function download(Request $request, DossierDocument $document): JsonResponse
    {
        $this->authorize('download', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'File not found.');

        $expiresAt = now()->addMinutes(5);
        $temporaryUrl = Storage::disk('local')->temporaryUrl($document->file_path, $expiresAt);

        $this->logAccess($document->dossier, 'download', $request->user()->id, [
            'document_id' => $document->id,
        ]);

        return response()->json([
            'url' => $temporaryUrl,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function logAccess(StudentDossier $dossier, string $action, int $actorId, ?array $metadata = null): void
    {
        DossierAccessLog::query()->create([
            'student_dossier_id' => $dossier->id,
            'action' => $action,
            'actor_id' => $actorId,
            'metadata_json' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
