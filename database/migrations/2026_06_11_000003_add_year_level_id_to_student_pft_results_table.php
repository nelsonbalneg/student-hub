<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->string('year_level_id')->nullable()->after('campus_id');
            $table->index('year_level_id', 'student_pft_results_year_level_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropIndex('student_pft_results_year_level_idx');
            $table->dropColumn('year_level_id');
        });
    }
};
