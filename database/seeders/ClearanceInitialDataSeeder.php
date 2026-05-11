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
        $types = [
            ['name' => 'Semestral Clearance', 'description' => 'Standard clearance for every semester.'],
            ['name' => 'Graduation Clearance', 'description' => 'Final clearance for graduating students.'],
            ['name' => 'Transfer/Withdrawal Clearance', 'description' => 'Clearance for students leaving the university.'],
        ];

        foreach ($types as $type) {
            ClearanceType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
