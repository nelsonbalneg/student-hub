<?php

namespace Database\Seeders;

use App\Models\PftMedicalCondition;
use Illuminate\Database\Seeder;

class PftMedicalConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            'Hypertension (High Blood Pressure)',
            'Heart Disease',
            'Congenital Heart Condition',
            'Asthma',
            'Chronic Lung Disease',
            'Diabetes Mellitus',
            'Thyroid Disorder',
            'Epilepsy/Seizure Disorder',
            'Migraine',
            'Arthritis',
            'Chronic Back Pain',
            'Previous Bone or Joint Injury',
            'Scoliosis',
            'Visual Impairment',
            'Hearing Impairment',
            'Anxiety Disorder',
            'Depression',
            'Severe Allergy',
        ];

        foreach ($conditions as $index => $condition) {
            PftMedicalCondition::query()->updateOrCreate(
                ['name' => $condition],
                [
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
