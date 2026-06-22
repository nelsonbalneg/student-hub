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
        Schema::create('examination_schedule_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_schedule_id')->constrained('examination_schedules');
            $table->string('subject_code');
            $table->string('section')->nullable();
            $table->string('room')->nullable();
            $table->date('schedule_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate subjects in the same schedule
            $table->unique(['examination_schedule_id', 'subject_code', 'section', 'room', 'schedule_date', 'start_time'], 'exam_sched_subj_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examination_schedule_subjects');
    }
};
