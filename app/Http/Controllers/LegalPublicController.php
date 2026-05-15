<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use App\Services\LegalDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LegalPublicController extends Controller
{
    public function __construct(private readonly LegalDocumentService $legalDocuments) {}

    public function show(string $type): JsonResponse
    {
        abort_unless(in_array($type, LegalDocument::TYPES, true), 404);

        $document = $this->legalDocuments->activeDocument($type);

        abort_unless($document, 404);

        return response()->json([
            'document' => $this->legalDocuments->serialize($document),
        ]);
    }

    public function acceptTerms(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'legal_document_id' => ['required', 'integer', 'exists:legal_documents,id'],
            'accepted' => ['accepted'],
            'type' => ['required', 'string', Rule::in([LegalDocument::TYPE_TERMS])],
        ]);

        $terms = LegalDocument::query()
            ->active()
            ->whereKey($validated['legal_document_id'])
            ->where('type', LegalDocument::TYPE_TERMS)
            ->firstOrFail();

        UserLegalAcceptance::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'legal_document_id' => $terms->id,
                'type' => LegalDocument::TYPE_TERMS,
                'version' => $terms->version,
            ],
            [
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        return back()->with('success', 'Terms and Conditions accepted.');
    }
}
