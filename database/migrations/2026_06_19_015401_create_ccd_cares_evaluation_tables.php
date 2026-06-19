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
        Schema::create('ccd_cares_evaluation_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates')
                ->noActionOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });

        Schema::create('ccd_cares_evaluation_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ccd_cares_evaluation_period_id')
                ->constrained('ccd_cares_evaluation_periods')
                ->cascadeOnDelete();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates')
                ->noActionOnDelete();
            $table->foreignId('student_id')->constrained('users')->noActionOnDelete();
            $table->json('answers_json');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(
                ['ccd_cares_evaluation_period_id', 'student_id'],
                'ccd_cares_period_student_unique',
            );
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ccd_cares_evaluation_submissions');
        Schema::dropIfExists('ccd_cares_evaluation_periods');
    }
};
