<?php

namespace App\Console\Commands;

use App\Models\Semester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncSemesters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:semesters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync semesters from external academic API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting semester sync...');

        try {
            $response = Http::get('http://172.16.0.60/academic/api/v2/ActiveSemesters/active-only');

            if ($response->failed()) {
                $this->error('Failed to fetch data from API. Status: ' . $response->status());
                return 1;
            }

            $semesters = $response->json();
            $count = 0;

            foreach ($semesters as $data) {
                // Parse term into academic year and term name
                // Example: "2026-2027 1st Semester"
                $termParts = explode(' ', $data['term'], 2);
                $academicYear = $termParts[0] ?? 'Unknown';
                $termName = $termParts[1] ?? 'Unknown';

                $campusName = match ($data['campusId']) {
                    1 => 'Main Campus',
                    3 => 'USM KCC',
                    4 => 'Advance Education',
                    default => 'Other Campus',
                };

                Semester::updateOrCreate(
                    ['external_id' => $data['id']],
                    [
                        'campus_id' => $data['campusId'],
                        'campus_name' => $campusName,
                        'academic_year' => $academicYear,
                        'term' => $termName,
                        'start_date' => $data['start'],
                        'end_date' => $data['end'],
                        'is_active' => $data['isActive'],
                    ]
                );

                $count++;
            }

            $this->info("Successfully synced {$count} semesters.");
            return 0;

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            Log::error('Semester Sync Error: ' . $e->getMessage());
            return 1;
        }
    }
}
