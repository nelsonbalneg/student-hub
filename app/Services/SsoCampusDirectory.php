<?php

namespace App\Services;

use App\Models\SsoCampus;

class SsoCampusDirectory
{
    /**
     * @return array<int, array{record_id: int, name: string, tenant_id: int, campus_id: int}>
     */
    public function all(): array
    {
        return SsoCampus::query()
            ->whereNotNull('tenant_id')
            ->whereNotNull('campus_id')
            ->get()
            ->map(fn (SsoCampus $campus): array => $this->normalize($campus))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array{record_id: int, name: string, tenant_id: int, campus_id: int}|null
     */
    public function find(int $recordId): ?array
    {
        $campus = SsoCampus::query()->find($recordId);

        if ($campus === null || $campus->tenantId() === null || $campus->campusId() === null) {
            return null;
        }

        return $this->normalize($campus);
    }

    /**
     * @return array{record_id: int, name: string, tenant_id: int, campus_id: int}
     */
    private function normalize(SsoCampus $campus): array
    {
        return [
            'record_id' => (int) $campus->getKey(),
            'name' => $campus->displayName(),
            'tenant_id' => (int) $campus->tenantId(),
            'campus_id' => (int) $campus->campusId(),
        ];
    }
}
