<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use Illuminate\Support\Facades\Schema;

class LegalDocumentService
{
    /**
     * @return array<string, array<string, mixed>|null>
     */
    public function activeDocumentsForShare(): array
    {
        if (! $this->legalTablesExist()) {
            return [
                LegalDocument::TYPE_TERMS => null,
                LegalDocument::TYPE_COOKIE_POLICY => null,
                LegalDocument::TYPE_PRIVACY_POLICY => null,
            ];
        }

        $documents = LegalDocument::query()
            ->active()
            ->whereIn('type', LegalDocument::TYPES)
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->unique('type')
            ->mapWithKeys(fn (LegalDocument $document): array => [
                $document->type => $this->serialize($document, includeContent: false),
            ]);

        return collect(LegalDocument::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => $documents->get($type)])
            ->all();
    }

    public function activeDocument(string $type): ?LegalDocument
    {
        if (! $this->legalTablesExist()) {
            return null;
        }

        return LegalDocument::activeFor($type);
    }

    public function needsTermsAcceptance(?User $user): bool
    {
        if (! $user || ! $this->legalTablesExist()) {
            return false;
        }

        $terms = $this->activeDocument(LegalDocument::TYPE_TERMS);

        if (! $terms) {
            return false;
        }

        return ! UserLegalAcceptance::query()
            ->where('user_id', $user->id)
            ->where('legal_document_id', $terms->id)
            ->where('type', LegalDocument::TYPE_TERMS)
            ->where('version', $terms->version)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requiredTermsFor(User $user): ?array
    {
        if (! $this->needsTermsAcceptance($user)) {
            return null;
        }

        $terms = $this->activeDocument(LegalDocument::TYPE_TERMS);

        return $terms ? $this->serialize($terms) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(LegalDocument $document, bool $includeContent = true): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'title' => $document->title,
            'slug' => $document->slug,
            'content' => $includeContent ? $document->content : null,
            'version' => $document->version,
            'is_active' => $document->is_active,
            'published_at' => $document->published_at?->toDateTimeString(),
            'published_at_human' => $document->published_at?->format('M d, Y'),
            'created_at' => $document->created_at?->format('M d, Y'),
            'updated_at' => $document->updated_at?->format('M d, Y'),
        ];
    }

    private function legalTablesExist(): bool
    {
        return Schema::hasTable('legal_documents') && Schema::hasTable('user_legal_acceptances');
    }
}
