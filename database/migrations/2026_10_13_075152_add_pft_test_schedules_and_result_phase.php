<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pft_test_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 10);
            $table->date('opens_on');
            $table->date('closes_on');
            $table->timestamps();
            $table->unique(['site_academic_term_id', 'phase']);
        });
        Schema::table('student_pft_results', function (Blueprint $table) {
            $table->string('test_phase', 10)->default('pre');
            $table->dropUnique('student_pft_results_user_test_term_unique');
            $table->unique(['user_id', 'pft_test_type_id', 'term_id', 'test_phase'], 'pft_results_user_test_term_phase_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table) {
            $table->dropUnique('pft_results_user_test_term_phase_unique');
            $table->dropColumn('test_phase');
            $table->unique(['user_id', 'pft_test_type_id', 'term_id'], 'student_pft_results_user_test_term_unique');
        });
        Schema::dropIfExists('pft_test_schedules');
    }
};
