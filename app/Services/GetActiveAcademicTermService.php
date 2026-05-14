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
        return SiteAcademicTerm::query()
            ->where('status', 'Active')
            ->when($campusId, function (Builder $query) use ($campusId) {
                $query->where('site_campus_id', $campusId);
            })
            ->when($realCampusId, function (Builder $query) use ($realCampusId) {
                $query->whereHas('campus', function (Builder $q) use ($realCampusId) {
                    $q->where('real_campus_id', $realCampusId);
                });
            })
            ->with('campus')
            ->first();
    }
}
