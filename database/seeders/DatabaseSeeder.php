<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $testUser = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ],
        );

        $this->call([
            RolesAndPermissionsSeeder::class,
            SiteSettingsPermissionSeeder::class,
            SiteEvaluationPermissionSeeder::class,
            CcdCaresSiteEvaluationPermissionSeeder::class,
            FaqPermissionsSeeder::class,
            CarbonFootprintPermissionSeeder::class,
            LegalPermissionsSeeder::class,
            LegalDocumentsSeeder::class,
            ClearancePermissionsSeeder::class,
            ClearanceInitialDataSeeder::class,
            SocietyPermissionSeeder::class,
            SocietyOfficerPositionSeeder::class,
            SocietyAccreditationRequirementSeeder::class,
            PhysicalFitnessTestPermissionSeeder::class,
            PhysicalFitnessTestSeeder::class,
            Dass21EvaluationTemplateSeeder::class,
            StudentPortalSystemEvaluationSeeder::class,
            ExaminationSchedulePermissionSeeder::class,
        ]);

        $testUser->assignRole('Super Admin');
    }
}
