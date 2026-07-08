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
        Schema::table('pft_health_questionnaires', function (Blueprint $table) {
            $table->string('medical_clearance_path')->nullable()->after('other_condition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pft_health_questionnaires', function (Blueprint $table) {
            $table->dropColumn('medical_clearance_path');
        });
    }
};
