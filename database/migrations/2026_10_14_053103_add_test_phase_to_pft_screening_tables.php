<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pft_parqs', 'pft_health_questionnaires'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('test_phase', 4)->default('pre');
                $table->unique(['user_id', 'term_id', 'test_phase']);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['user_id', 'term_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['pft_parqs', 'pft_health_questionnaires'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique(['user_id', 'term_id']);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['user_id', 'term_id', 'test_phase']);
                $table->dropColumn('test_phase');
            });
        }
    }
};
