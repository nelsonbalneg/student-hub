<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettings\ImportExaminationScheduleRequest;
use App\Imports\ExaminationScheduleDataImport;
use App\Imports\ExaminationSchedulePreviewImport;
use App\Models\ExaminationSchedule;
use App\Support\SpreadsheetAutoload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExaminationScheduleImportController extends Controller
{
    public function returnFromPreview(ExaminationSchedule $examinationSchedule): RedirectResponse
    {
        return redirect()->route(
            'site-settings.examination-schedules.show',
            $examinationSchedule,
        );
    }

    public function preview(
        ImportExaminationScheduleRequest $request,
        ExaminationSchedule $examinationSchedule,
    ): JsonResponse {
        SpreadsheetAutoload::register();

        $file = $request->file('file');

        if (! $file || ! $file->isValid()) {
            return response()->json([
                'message' => 'The uploaded spreadsheet is missing or invalid.',
            ], 422);
        }

        try {
            $contents = $file->get();
            $extension = $file->getClientOriginalExtension()
                ?: $file->extension()
                ?: 'xlsx';
            $path = sprintf(
                'imports/examination-schedules/previews/%s.%s',
                Str::uuid(),
                Str::lower($extension),
            );
            $stored = Storage::disk('local')->put($path, $contents);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'The spreadsheet could not be stored for preview.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        if (! $stored) {
            return response()->json([
                'message' => 'The spreadsheet could not be stored for preview.',
            ], 422);
        }

        try {
            // Find the heading row dynamically
            $array = Excel::toArray(new class {}, $path, 'local')[0] ?? [];
            $headingRow = 1;
            foreach ($array as $index => $row) {
                if (isset($row[0]) && strtolower(trim((string) $row[0])) === 'subject code') {
                    $headingRow = $index + 1;
                    break;
                }
            }

            $previewImport = new ExaminationSchedulePreviewImport($headingRow);
            Excel::import($previewImport, $path, 'local');
            $rows = $this->previewRows($previewImport->rows(), $examinationSchedule, $headingRow);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            return response()->json([
                'message' => 'The spreadsheet could not be read.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $token = Str::uuid()->toString();
        $request->session()->put("examination_schedule_import_preview.{$token}", [
            'schedule_id' => $examinationSchedule->id,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'heading_row' => $headingRow,
            'invalid_rows' => collect($rows)->where('valid', false)->count(),
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        return response()->json([
            'token' => $token,
            'filename' => $file->getClientOriginalName(),
            'total_rows' => count($rows),
            'valid_rows' => collect($rows)->where('valid', true)->count(),
            'invalid_rows' => collect($rows)->where('valid', false)->count(),
            'rows' => array_slice($rows, 0, 100),
            'truncated' => count($rows) > 100,
        ]);
    }

    public function sync(Request $request, ExaminationSchedule $examinationSchedule): JsonResponse
    {
        SpreadsheetAutoload::register();

        Gate::authorize('examination-schedule.import');

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
        ]);
        $sessionKey = "examination_schedule_import_preview.{$validated['token']}";
        $preview = $request->session()->pull($sessionKey);

        if (
            ! is_array($preview)
            || (int) ($preview['schedule_id'] ?? 0) !== $examinationSchedule->id
            || (int) ($preview['invalid_rows'] ?? 0) > 0
            || (int) ($preview['expires_at'] ?? 0) < now()->timestamp
            || ! Storage::disk('local')->exists($preview['path'] ?? '')
        ) {
            return response()->json([
                'message' => 'The import preview has expired. Please upload the spreadsheet again.',
            ], 422);
        }

        $importLog = $examinationSchedule->imports()->create([
            'uploaded_filename' => $preview['filename'],
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        $headingRow = $preview['heading_row'] ?? 1;
        $import = new ExaminationScheduleDataImport($examinationSchedule, $importLog, $headingRow);
        $path = $preview['path'];

        dispatch(function () use ($import, $path) {
            Excel::import($import, $path, 'local');
        });

        return response()->json([
            'message' => 'Import started. You can track its progress in Import Logs.',
            'import_id' => $importLog->id,
        ]);
    }

    public function records(Request $request, ExaminationSchedule $examinationSchedule)
    {
        Gate::authorize('examination-schedule.view');

        $query = $examinationSchedule->subjects();

        if ($request->has('search') && $request->search !== null && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject_code', 'like', "%{$search}%")
                    ->orWhere('room', 'like', "%{$search}%")
                    ->orWhere('section', 'like', "%{$search}%");
            });
        }

        if ($request->has('section') && $request->section !== null && $request->section !== '') {
            $query->where('section', $request->section);
        }

        if ($request->has('room') && $request->room !== null && $request->room !== '') {
            $query->where('room', $request->room);
        }

        if ($request->has('day') && $request->day !== null && $request->day !== '') {
            $query->where('day', $request->day);
        }

        $paginated = $query->latest()->paginate($request->get('per_page', 15));

        $response = $paginated->toArray();
        $response['sections'] = $examinationSchedule->subjects()->distinct()->whereNotNull('section')->pluck('section')->sort()->values();
        $response['rooms'] = $examinationSchedule->subjects()->distinct()->whereNotNull('room')->pluck('room')->sort()->values();
        $response['days'] = $examinationSchedule->subjects()->distinct()->whereNotNull('day')->pluck('day')->sort()->values();

        return response()->json($response);
    }

    public function logs(Request $request, ExaminationSchedule $examinationSchedule)
    {
        Gate::authorize('examination-schedule.view');

        return response()->json(
            $examinationSchedule->imports()->with('uploader:id,name')->latest()->paginate($request->get('per_page', 10))
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function previewRows($rows, ExaminationSchedule $examinationSchedule, int $headingRow): array
    {
        $lastSubjectCode = '';

        return $rows->map(function (array $row, int $index) use (&$lastSubjectCode, $headingRow): array {
            $errors = [];
            $subjectCode = trim((string) ($row['subject_code'] ?? ''));

            if ($subjectCode !== '') {
                $lastSubjectCode = $subjectCode;
            } else {
                $subjectCode = $lastSubjectCode;
            }
            $day = trim((string) ($row['day'] ?? ''));
            $time = trim((string) ($row['time'] ?? ''));

            $parsedTime = $this->parseTimeRange($time);

            $startTime = $parsedTime ? $parsedTime['start'] : null;
            $endTime = $parsedTime ? $parsedTime['end'] : null;

            if ($subjectCode === '') {
                $errors[] = 'Subject code is required.';
            }

            if ($day === '') {
                $errors[] = 'Day is required.';
            }

            if (! $startTime) {
                $errors[] = 'Start time is invalid.';
            }

            if ($endTime && $startTime && $endTime <= $startTime) {
                $errors[] = 'End time must be after start time.';
            }

            return [
                'row' => $headingRow + 1 + $index,
                'subject_code' => $subjectCode,
                'section' => $row['section_name'] ?? null,
                'room' => $row['room'] ?? null,
                'day' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'instructor' => trim((string) ($row['instructor'] ?? '')),
                'proctor_name' => trim((string) ($row['proctor_name'] ?? '')),
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        })->all();
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
