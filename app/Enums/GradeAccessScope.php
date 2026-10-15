<?php

namespace App\Enums;

enum GradeAccessScope: string
{
    case AllGrades = 'all_grades';
    case CurrentTerm = 'current_term';

    public function includes(int|string|null $termId, int|string|null $activeTermId): bool
    {
        return $this === self::AllGrades
            || ($activeTermId !== null && $termId !== null && (string) $termId === (string) $activeTermId);
    }
}
