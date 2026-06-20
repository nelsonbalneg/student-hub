<?php

namespace Database\Seeders;

use App\Models\ClearanceType;
use App\Models\Office;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class ClearanceInitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Semester
        Semester::updateOrCreate(
            ['academic_year' => '2023-2024', 'term' => '2nd Semester'],
            ['is_active' => true]
        );

        // 2. Offices
        $offices = [
            ['name' => 'University Registrar', 'code' => 'OUR'],
            ['name' => 'Accounting Office', 'code' => 'ACC'],
            ['name' => 'University Library', 'code' => 'LIB'],
            ['name' => 'Student Affairs Office', 'code' => 'OSA'],
            ['name' => 'Department Office', 'code' => 'DEPT'],
        ];

        foreach ($offices as $office) {
            Office::updateOrCreate(['code' => $office['code']], $office);
        }

        // 3. Clearance Types
        $defaultCampusId = \DB::table('site_campuses')->where('id', 2)->value('id')
            ?? \DB::table('site_campuses')->orderBy('id')->value('id');

        if (! $defaultCampusId) {
            $campus = \App\Models\SiteCampus::create([
                'campus_name' => 'Main Campus',
                'campus_address' => 'Main Address',
                'status' => 'Active',
            ]);
            $defaultCampusId = $campus->id;
        }

        $types = [
            ['name' => 'Semestral Clearance', 'description' => 'Standard clearance for every semester.', 'campus_id' => $defaultCampusId],
            ['name' => 'Graduation Clearance', 'description' => 'Final clearance for graduating students.', 'campus_id' => $defaultCampusId],
            ['name' => 'Transfer/Withdrawal Clearance', 'description' => 'Clearance for students leaving the university.', 'campus_id' => $defaultCampusId],
        ];

        foreach ($types as $type) {
            ClearanceType::updateOrCreate(
                ['name' => $type['name'], 'campus_id' => $defaultCampusId],
                $type
            );
        }
    }
}
