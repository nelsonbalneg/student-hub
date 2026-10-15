<?php

namespace App\Enums;

enum GradeAccessScope: string
{
    case AllGrades = 'all_grades';
    case CurrentTerm = 'current_term';
    case SpecificTerm = 'specific_term';

    public function includes(int|string|null $termId, int|string|null $activeTermId, int|string|null $selectedTermId = null): bool
    {
        $targetTermId = $this === self::SpecificTerm ? $selectedTermId : $activeTermId;

        return $this === self::AllGrades
            || ($targetTermId !== null && $termId !== null && (string) $termId === (string) $targetTermId);
    }
}
