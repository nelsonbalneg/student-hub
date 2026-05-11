<?php

namespace App\Services;

use App\Models\ClearanceAccountability;
use App\Models\ClearanceAccountabilityUpload;
use App\Models\ClearanceUpdate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ClearanceUploadService
{
    /**
     * Process an uploaded file for accountabilities.
     */
    public function processUpload(UploadedFile $file, ClearanceUpdate $update, int $officeId): array
    {
        // For now, we'll assume CSV format.
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($rows);
        
        $results = [
            'total' => count($rows),
            'matched' => 0,
            'failed' => 0,
            'data' => [],
        ];

        foreach ($rows as $row) {
            $studentIdentifier = $row[0] ?? null; // Assume first column is student no or email
            $title = $row[1] ?? 'Accountability';
            $description = $row[2] ?? null;
            $amount = isset($row[3]) ? (float) $row[3] : null;

            $student = User::where('student_no', $studentIdentifier)
                ->orWhere('email', $studentIdentifier)
                ->first();

            if ($student) {
                $results['matched']++;
                $results['data'][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'student_no' => $student->student_no,
                    'title' => $title,
                    'description' => $description,
                    'amount' => $amount,
                    'valid' => true,
                ];
            } else {
                $results['failed']++;
                $results['data'][] = [
                    'identifier' => $studentIdentifier,
                    'title' => $title,
                    'valid' => false,
                    'error' => 'Student not found',
                ];
            }
        }

        return $results;
    }

    /**
     * Save the processed accountabilities.
     */
    public function saveAccountabilities(array $data, ClearanceUpdate $update, int $officeId, string $filename): ClearanceAccountabilityUpload
    {
        return DB::transaction(function () use ($data, $update, $officeId, $filename) {
            $upload = ClearanceAccountabilityUpload::create([
                'clearance_update_id' => $update->id,
                'semester_id' => $update->semester_id,
                'office_id' => $officeId,
                'uploaded_by' => auth()->id(),
                'filename' => $filename,
                'total_rows' => count($data),
                'matched_students' => collect($data)->where('valid', true)->count(),
                'failed_rows' => collect($data)->where('valid', false)->count(),
                'status' => ClearanceAccountabilityUpload::STATUS_COMPLETED,
            ]);

            foreach ($data as $item) {
                if ($item['valid']) {
                    ClearanceAccountability::create([
                        'clearance_update_id' => $update->id,
                        'semester_id' => $update->semester_id,
                        'student_id' => $item['student_id'],
                        'office_id' => $officeId,
                        'uploaded_by' => auth()->id(),
                        'title' => $item['title'],
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'status' => ClearanceAccountability::STATUS_PENDING,
                    ]);

                    // Sync the student's clearance status
                    app(ClearanceAccountabilityService::class)->syncStudentClearanceStatus($item['student_id'], $update->id, 'bulk_upload');
                }
            }

            return $upload;
        });
    }
}
