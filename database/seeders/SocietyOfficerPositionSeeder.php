<?php

namespace Database\Seeders;

use App\Models\SocietyOfficerPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SocietyOfficerPositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['President', true],
            ['Vice President', true],
            ['Secretary', true],
            ['Treasurer', true],
            ['Auditor', true],
            ['P.I.O', true],
            ['Business Manager', true],
            ['Other Officer', false],
        ];

        foreach ($positions as $index => [$name, $required]) {
            SocietyOfficerPosition::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_required' => $required,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
