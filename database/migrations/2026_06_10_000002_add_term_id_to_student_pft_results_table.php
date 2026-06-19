<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->string('term_id')->nullable()->after('pft_test_type_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                update spr
                set spr.term_id = sat.term_id
                from student_pft_results spr
                inner join users u on u.id = spr.user_id
                inner join site_campuses sc on sc.real_campus_id = cast(u.campus_id as nvarchar(255))
                inner join site_academic_terms sat on sat.site_campus_id = sc.id
                where spr.term_id is null
                    and sat.status = 'Active'
                    and sat.term_id is not null
            SQL);
        }

        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropUnique('student_pft_results_user_id_pft_test_type_id_unique');
            $table->unique(['user_id', 'pft_test_type_id', 'term_id'], 'student_pft_results_user_test_term_unique');
            $table->index(['user_id', 'term_id'], 'student_pft_results_user_term_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropUnique('student_pft_results_user_test_term_unique');
            $table->dropIndex('student_pft_results_user_term_idx');
            $table->unique(['user_id', 'pft_test_type_id']);
        });

        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropColumn('term_id');
        });
    }
};
