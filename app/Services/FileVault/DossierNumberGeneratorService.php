<?php

namespace App\Services\FileVault;

use App\Models\StudentDossier;

class DossierNumberGeneratorService
{
    public function generate(): string
    {
        $year = now()->format('Y');
        $prefix = "DOS-{$year}-";

        $sequence = StudentDossier::query()
            ->where('dossier_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $sequence++;
        } while (StudentDossier::query()->where('dossier_number', $number)->exists());

        return $number;
    }
}
