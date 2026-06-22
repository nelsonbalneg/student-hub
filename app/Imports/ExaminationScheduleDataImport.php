<?php

namespace App\Imports;

use App\Models\ExaminationSchedule;
use App\Models\ExaminationScheduleImport;
use App\Models\ExaminationScheduleSubject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExaminationScheduleDataImport implements ToCollection, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow
{
    private int $headingRow;

    public function headingRow(): int
    {
        return $this->headingRow;
    }

    private ExaminationSchedule $schedule;

    private ExaminationScheduleImport $importLog;

    private string $lastSubjectCode = '';

    public function __construct(ExaminationSchedule $schedule, ExaminationScheduleImport $importLog, int $headingRow = 1)
    {
        $this->schedule = $schedule;
        $this->importLog = $importLog;
        $this->headingRow = $headingRow;
    }

    public function collection(Collection $rows)
    {
        $this->importLog->update(['status' => 'processing']);

        $successful = 0;
        $failed = 0;
        $errors = $this->importLog->error_messages ?? [];

        foreach ($rows as $index => $row) {
            try {
                $subjectCode = trim((string) ($row['subject_code'] ?? ''));
                if ($subjectCode !== '') {
                    $this->lastSubjectCode = $subjectCode;
                } else {
                    $subjectCode = $this->lastSubjectCode;
                }
                
                $day = trim((string) ($row['day'] ?? ''));
                $time = trim((string) ($row['time'] ?? ''));

                if ($subjectCode === '') {
                    throw new \Exception('Missing subject code for row '.($index + 5));
                }

                $parsedTime = $this->parseTimeRange($time);

                if ($day === '') {
                    throw new \Exception('Missing day for row '.($index + $this->headingRow + 1));
                }

                if (! $parsedTime) {
                    throw new \Exception('Invalid time format for row '.($index + $this->headingRow + 1));
                }

                ExaminationScheduleSubject::updateOrCreate(
                    [
                        'examination_schedule_id' => $this->schedule->id,
                        'subject_code' => $subjectCode,
                        'section' => $row['section_name'] ?? null,
                        'room' => $row['room'] ?? null,
                        'day' => $day,
                        'start_time' => $parsedTime['start'],
                    ],
                    [
                        'end_time' => $parsedTime['end'],
                        'instructor' => trim((string) ($row['instructor'] ?? '')),
                        'proctor_name' => trim((string) ($row['proctor_name'] ?? '')),
                    ]
                );

                $successful++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        $this->importLog->increment('total_rows', $rows->count());
        $this->importLog->increment('processed_rows', $rows->count());
        $this->importLog->increment('successful_rows', $successful);
        $this->importLog->increment('failed_rows', $failed);

        if (! empty($errors)) {
            $this->importLog->update(['error_messages' => $errors]);
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                $this->importLog->update(['status' => 'completed']);
            },
            ImportFailed::class => function (ImportFailed $event) {
                $this->importLog->update([
                    'status' => 'failed',
                    'error_messages' => array_merge(
                        $this->importLog->error_messages ?? [],
                        [$event->getException()->getMessage()]
                    ),
                ]);
            },
        ];
    }

    private function parseTimeRange(string $timeString): ?array
    {
        $timeString = strtolower(trim($timeString));
        if (preg_match('/^(\d{1,2})-(\d{1,2})(am|pm)$/', $timeString, $matches)) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            $meridian = $matches[3];

            $endHour = $end;
            if ($meridian === 'pm' && $end !== 12) {
                $endHour += 12;
            } elseif ($meridian === 'am' && $end === 12) {
                $endHour = 0;
            }

            $startHour = $start;
            if ($meridian === 'pm') {
                if ($start < $end) {
                    if ($start !== 12) {
                        $startHour += 12;
                    }
                } else {
                    if ($start === 12) {
                        $startHour = 12;
                    }
                }
            } elseif ($meridian === 'am') {
                if ($start === 12) {
                    $startHour = 0;
                }
            }

            return [
                'start' => sprintf('%02d:00:00', $startHour),
                'end' => sprintf('%02d:00:00', $endHour),
            ];
        }

        return null;
    }
}
