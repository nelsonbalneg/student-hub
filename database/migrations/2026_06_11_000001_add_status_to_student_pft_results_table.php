<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->string('status')->default('completed')->index()->after('term_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
