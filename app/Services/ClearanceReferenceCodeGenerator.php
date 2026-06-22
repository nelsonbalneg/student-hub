<?php

namespace App\Services;

use App\Models\ClearanceUpdate;
use Carbon\CarbonInterface;

class ClearanceReferenceCodeGenerator
{
    public function generate(?CarbonInterface $date = null): string
    {
        do {
            $letters = '';

            for ($index = 0; $index < 6; $index++) {
                $letters .= chr(random_int(65, 90));
            }

            $referenceCode = 'CLR'
                .($date ?? now())->format('ymd')
                .$letters
                .random_int(0, 9);
        } while (
            $this->exists($referenceCode)
        );

        return $referenceCode;
    }

    protected function exists(string $referenceCode): bool
    {
        return ClearanceUpdate::withTrashed()
            ->where('reference_code', $referenceCode)
            ->exists();
    }
}
