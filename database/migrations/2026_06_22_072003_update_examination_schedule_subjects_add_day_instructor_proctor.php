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
        Schema::table('examination_schedule_subjects', function (Blueprint $table) {
            $table->dropUnique('exam_sched_subj_unique');
            $table->dropColumn('schedule_date');
            
            $table->string('day')->nullable()->after('room');
            $table->string('instructor')->nullable()->after('end_time');
            $table->string('proctor_name')->nullable()->after('instructor');
            
            $table->unique(['examination_schedule_id', 'subject_code', 'section', 'room', 'day', 'start_time'], 'exam_sched_subj_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examination_schedule_subjects', function (Blueprint $table) {
            $table->dropUnique('exam_sched_subj_day_unique');
            $table->dropColumn(['day', 'instructor', 'proctor_name']);
            $table->date('schedule_date')->nullable()->after('room');
            
            $table->unique(['examination_schedule_id', 'subject_code', 'section', 'room', 'schedule_date', 'start_time'], 'exam_sched_subj_unique');
        });
    }
};
