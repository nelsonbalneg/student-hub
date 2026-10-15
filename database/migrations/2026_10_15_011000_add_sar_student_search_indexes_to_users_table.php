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
        Schema::table('users', function (Blueprint $table) {
            $table->index(['campus_id', 'name'], 'sar_students_campus_name_idx');
            $table->index(['campus_id', 'student_no'], 'sar_students_campus_student_no_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('sar_students_campus_name_idx');
            $table->dropIndex('sar_students_campus_student_no_idx');
        });
    }
};
