<?php

namespace Database\Seeders;

use App\Models\SocietyAccreditationRequirement;
use Illuminate\Database\Seeder;

class SocietyAccreditationRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            [
                'name' => 'OSA Registration Form 06',
                'description' => 'Long size. Use official OSA Template.',
                'category' => 'registration',
            ],
            [
                'name' => 'List of Officers and Adviser/s Contact Information',
                'description' => 'Long size.',
                'category' => 'governance',
            ],
            [
                'name' => 'Summary of Members',
                'description' => 'Long size.',
                'category' => 'membership',
            ],
            [
                'name' => 'Membership Form',
                'description' => 'Long size. First semester only. New members for the next semester will only be required.',
                'category' => 'membership',
            ],
            [
                'name' => 'Softcopy & Hardcopy of MEMBERS’ INFORMATION SHEET',
                'description' => 'Long size. To be uploaded at Google Drive.',
                'category' => 'membership',
            ],
            [
                'name' => 'Resolution for the endorsement of adviser/s',
                'description' => 'Long size. First semester only if the adviser is the same from the previous semester.',
                'category' => 'governance',
            ],
            [
                'name' => 'Budget Appropriations Act/Ordinance (Student Government) LSG/USG',
                'description' => 'Long size.',
                'category' => 'finance',
            ],
            [
                'name' => 'Budget Appropriations Resolution (Campus Organization) Society',
                'description' => 'Long size.',
                'category' => 'finance',
            ],
            [
                'name' => 'Certified True Copy of Approved Constitution and By-Laws (CBL)',
                'description' => 'With USM President signature.',
                'category' => 'governance',
            ],
            [
                'name' => 'Screenshot of FB page',
                'description' => 'Long size.',
                'category' => 'digital_presence',
            ],
            [
                'name' => 'Semestral Summary Action Plan (Campus Organization/Student Government)',
                'description' => 'Long size.',
                'category' => 'plans',
            ],
            [
                'name' => 'Narrative Action Plan',
                'description' => 'Long size.',
                'category' => 'plans',
            ],
            [
                'name' => 'Others as required',
                'description' => null,
                'category' => 'other',
            ],
        ];

        foreach ($requirements as $index => $req) {
            SocietyAccreditationRequirement::query()->updateOrCreate(
                ['name' => $req['name']],
                [
                    'description' => $req['description'],
                    'category' => $req['category'],
                    'is_required' => $req['name'] !== 'Others as required',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'applies_to' => 'USM-OSA-F06-Rev.02.2025.05.05',
                ],
            );
        }
    }
}
