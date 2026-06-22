<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExaminationSchedulePreviewImport implements ToCollection, WithHeadingRow
{
    private int $headingRow;
    
    public function headingRow(): int
    {
        return $this->headingRow;
    }
    
    /** @var Collection<int, array<string, mixed>> */
    private Collection $rows;

    public function __construct(int $headingRow = 1)
    {
        $this->headingRow = $headingRow;
        $this->rows = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows
            ->map(fn ($row) => $row->toArray())
            ->filter(fn (array $row) => collect($row)->contains(fn ($value) => filled($value)))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return $this->rows;
    }
}
