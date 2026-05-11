<?php

namespace App\Services;

use App\Models\Semester;

class GetActiveSem
{
    /**
     * Get the current active semester.
     * Optionally filtered by campus ID.
     */
    public static function current(?int $campusId = null): ?Semester
    {
        return Semester::where('is_active', true)
            ->when($campusId, fn($query) => $query->where('campus_id', $campusId))
            ->orderByRaw("CASE WHEN campus_id = 1 THEN 0 ELSE 1 END") // Prioritize Main Campus
            ->first();
    }

    /**
     * Get the current active semester ID.
     */
    public static function id(?int $campusId = null): ?int
    {
        return self::current($campusId)?->id;
    }

    /**
     * Get the display name of the current active semester.
     */
    public static function display(?int $campusId = null): string
    {
        $sem = self::current($campusId);
        return $sem ? "{$sem->academic_year} - {$sem->term} ({$sem->campus_name})" : 'No Active Semester';
    }
}
