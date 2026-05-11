<?php

namespace Database\Seeders;

use App\Models\AnnouncementCategory;
use Illuminate\Database\Seeder;

class AnnouncementCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'General Announcement',
                'slug' => 'general',
                'color' => '#3b82f6',
                'icon' => 'Megaphone',
                'description' => 'General news and updates.',
            ],
            [
                'name' => 'Academic Advisory',
                'slug' => 'academic',
                'color' => '#10b981',
                'icon' => 'GraduationCap',
                'description' => 'Updates regarding classes, grades, and curriculum.',
            ],
            [
                'name' => 'Emergency / Urgent',
                'slug' => 'emergency',
                'color' => '#ef4444',
                'icon' => 'AlertTriangle',
                'description' => 'Urgent alerts and emergency notices.',
            ],
            [
                'name' => 'Memorandum',
                'slug' => 'memo',
                'color' => '#8b5cf6',
                'icon' => 'FileText',
                'description' => 'Official memorandums and directives.',
            ],
        ];

        foreach ($categories as $category) {
            AnnouncementCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
