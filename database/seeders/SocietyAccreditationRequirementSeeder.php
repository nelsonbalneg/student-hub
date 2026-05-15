<?php

namespace Database\Seeders;

use App\Models\SocietyAccreditationRequirement;
use Illuminate\Database\Seeder;

class SocietyAccreditationRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            ['Accomplishment Report', 'accomplishments'],
            ['Semestral Summary Accomplishment Report', 'accomplishments'],
            ['Narrative Accomplishment Report', 'accomplishments'],
            ['Approved Activity Permits', 'accomplishments'],
            ['Completion Forms', 'accomplishments'],
            ['Activity Evaluation Form', 'accomplishments'],
            ['Semestral Audited Financial Report', 'finance'],
            ['Summary of Semestral Financial Report', 'finance'],
            ['Liquidation Report with supporting documents', 'finance'],
            ['Certification from USG Commission on Audit', 'finance'],
            ['Copy of Passbook', 'finance'],
            ['Semestral Property Report', 'property'],
            ['Summary of Semestral Property Report', 'property'],
            ['Pictures of Properties with property tags', 'property'],
            ['Property Acknowledgement Receipt', 'property'],
            ['Semestral Plan of Activities', 'plans'],
            ['Semestral Summary Action Plan', 'plans'],
            ['Narrative Action Plan', 'plans'],
            ['Registration/Accreditation Form', 'registration'],
            ['List of Officers and Adviser/s Contact Information', 'registration'],
            ['Summary of Members', 'membership'],
            ['Membership Form', 'membership'],
            ['Members Information Sheet', 'membership'],
            ['Resolution for endorsement of adviser/s', 'governance'],
            ['Budget Appropriations Act/Ordinance', 'finance'],
            ['Budget Appropriations Resolution', 'finance'],
            ['Certified True Copy of Approved Constitution and By-Laws with USM President signature', 'governance'],
            ['Screenshot of FB page', 'digital_presence'],
            ['Others as required', 'other'],
        ];

        foreach ($requirements as $index => [$name, $category]) {
            SocietyAccreditationRequirement::query()->updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'is_required' => $name !== 'Others as required',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'applies_to' => 'USM-OSA-F06-Rev.02.2025.05.05',
                ],
            );
        }
    }
}
