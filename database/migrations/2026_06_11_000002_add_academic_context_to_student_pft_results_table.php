<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->string('college_id')->nullable()->after('term_id');
            $table->string('campus_id')->nullable()->after('college_id');
            $table->string('section_id')->nullable()->after('campus_id');
            $table->string('section_name')->nullable()->after('section_id');
            $table->string('tenant_id')->nullable()->after('section_name');

            $table->index(['campus_id', 'college_id'], 'student_pft_results_campus_college_idx');
            $table->index('section_id', 'student_pft_results_section_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropIndex('student_pft_results_campus_college_idx');
            $table->dropIndex('student_pft_results_section_idx');
            $table->dropColumn([
                'college_id',
                'campus_id',
                'section_id',
                'section_name',
                'tenant_id',
            ]);
        });
    }
};
