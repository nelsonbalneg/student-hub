<?php

namespace App\Services\FileVault;

use App\Models\StudentDossier;

class DossierChecklistService
{
    /**
     * @return list<string>
     */
    public function requiredDocumentTypes(StudentDossier $dossier): array
    {
        return config('file_vault.checklist_templates.'.$dossier->transaction_type, []);
    }

    public function isComplete(StudentDossier $dossier): bool
    {
        $requiredTypes = $this->requiredDocumentTypes($dossier);

        if ($requiredTypes === []) {
            return false;
        }

        $documents = $dossier->documents()
            ->whereIn('document_type', $requiredTypes)
            ->get(['document_type', 'is_verified'])
            ->groupBy('document_type');

        foreach ($requiredTypes as $documentType) {
            $typeDocuments = $documents->get($documentType);

            if ($typeDocuments === null || $typeDocuments->isEmpty()) {
                return false;
            }

            if (! $typeDocuments->contains(fn ($document): bool => (bool) $document->is_verified)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{document_type: string, is_uploaded: bool, is_verified: bool}>
     */
    public function checklistProgress(StudentDossier $dossier): array
    {
        $requiredTypes = $this->requiredDocumentTypes($dossier);

        if ($requiredTypes === []) {
            return [];
        }

        $documents = $dossier->documents()
            ->whereIn('document_type', $requiredTypes)
            ->get(['document_type', 'is_verified'])
            ->groupBy('document_type');

        return collect($requiredTypes)->map(function (string $documentType) use ($documents): array {
            $typeDocuments = $documents->get($documentType);
            $isUploaded = $typeDocuments !== null && $typeDocuments->isNotEmpty();
            $isVerified = $isUploaded && $typeDocuments->contains(fn ($document): bool => (bool) $document->is_verified);

            return [
                'document_type' => $documentType,
                'is_uploaded' => $isUploaded,
                'is_verified' => $isVerified,
            ];
        })->values()->all();
    }
}
