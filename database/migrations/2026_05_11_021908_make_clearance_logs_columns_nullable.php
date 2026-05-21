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
        Schema::table('clearance_logs', function (Blueprint $table) {
            $table->foreignId('student_semester_clearance_id')->nullable()->change();
            $table->foreignId('student_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep existing nullable data intact during rollback. A refresh will drop
        // the clearance_logs table in the previous clearance module migration.
    }
};
