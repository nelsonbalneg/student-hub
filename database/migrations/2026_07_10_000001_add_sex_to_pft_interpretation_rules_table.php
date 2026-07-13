<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pft_interpretation_rules', function (Blueprint $table): void {
            $table->string('sex', 10)->nullable()->after('field_name')->index();
            $table->index(['pft_test_type_id', 'field_name', 'sex'], 'pft_interp_test_field_sex_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pft_interpretation_rules', function (Blueprint $table): void {
            $table->dropIndex('pft_interp_test_field_sex_idx');
            $table->dropIndex(['sex']);
            $table->dropColumn('sex');
        });
    }
};
