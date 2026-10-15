<?php

namespace App\Services;

use App\Models\SarAccessRule;
use App\Models\SiteCampus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SarAccessService
{
    public function __construct(
        private readonly SiteSettingService $settings,
        private readonly AcademicApiService $academicApi,
    ) {}

    public function allows(User $user, int|string|null $termId = null): bool
    {
        if (! (bool) ($this->settings->all()['sar_enabled'] ?? true) || blank($user->campus_id)) {
            return false;
        }

        if ($termId === null) {
            $semester = $this->academicApi->getActiveSemesterForUser($user);
            $termId = $semester['termId'] ?? null;
            if (! empty($semester['error'])) {
                return false;
            }
        }
        if (blank($termId)) {
            return false;
        }

        $campus = SiteCampus::where('real_campus_id', (string) $user->campus_id)->first()
            ?? SiteCampus::find($user->campus_id);
        if ($campus === null) {
            return false;
        }

        return SarAccessRule::where(fn (Builder $query) => $query->where('is_enabled', true)->orWhereHas('overrideUsers', fn (Builder $users) => $users->whereKey($user->id)))
            ->whereHas('academicTerm', function (Builder $query) use ($campus, $termId): void {
                $query->where('site_campus_id', $campus->id)->where('term_id', (string) $termId);
            })
            ->exists();
    }

    public function moduleEnabled(): bool
    {
        return (bool) ($this->settings->all()['sar_enabled'] ?? true);
    }

    public function rulesFor(User $user): Collection
    {
        if (blank($user->campus_id)) {
            return new Collection;
        }
        $campus = SiteCampus::where('real_campus_id', (string) $user->campus_id)->first()
            ?? SiteCampus::find($user->campus_id);
        if ($campus === null) {
            return new Collection;
        }

        return SarAccessRule::with('academicTerm.campus')
            ->withCount(['overrideUsers as has_user_override' => fn (Builder $query) => $query->whereKey($user->id)])
            ->whereHas('academicTerm', fn (Builder $query) => $query->where('site_campus_id', $campus->id)->whereNotNull('term_id'))
            ->latest()->get();
    }
}
