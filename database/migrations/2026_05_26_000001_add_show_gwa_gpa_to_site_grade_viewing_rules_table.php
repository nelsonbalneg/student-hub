<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_grade_viewing_rules', function (Blueprint $table) {
            $table->boolean('show_gwa_gpa')->default(true)->after('bypass_evaluation');
        });
    }

    public function down(): void
    {
        Schema::table('site_grade_viewing_rules', function (Blueprint $table) {
            $table->dropColumn('show_gwa_gpa');
        });
    }
};
