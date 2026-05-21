<?php

namespace App\Services;

use App\Models\SiteAcademicTerm;
use Illuminate\Database\Eloquent\Builder;

class GetActiveAcademicTermService
{
    /**
     * Get the active academic term for a specific campus.
     */
    public function execute(int|string|null $campusId = null, ?string $realCampusId = null): ?SiteAcademicTerm
    {
        $query = fn (): Builder => SiteAcademicTerm::query()
            ->where('status', 'Active')
            ->with('campus');

        if ($realCampusId) {
            return $query()
                ->whereHas('campus', function (Builder $query) use ($realCampusId) {
                    $query->where('real_campus_id', $realCampusId);
                })
                ->first();
        }

        if ($campusId) {
            $activeTerm = $query()
                ->whereHas('campus', function (Builder $query) use ($campusId) {
                    $query->where('real_campus_id', (string) $campusId);
                })
                ->first();

            if ($activeTerm) {
                return $activeTerm;
            }

            return $query()
                ->where('site_campus_id', $campusId)
                ->first();
        }

        return $query()->first();
    }
}
