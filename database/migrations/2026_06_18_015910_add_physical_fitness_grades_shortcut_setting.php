<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'pft_grades_shortcut_enabled'],
            [
                'value' => '0',
                'type' => 'boolean',
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'pft_grades_shortcut_enabled')
            ->delete();
    }
};
