<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_evaluation_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_template_id')->constrained('evaluation_templates')->noActionOnDelete();
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

        Schema::create('site_evaluation_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_evaluation_period_id')->constrained('site_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('evaluation_template_id')->constrained('evaluation_templates')->noActionOnDelete();
            $table->foreignId('user_id')->constrained('users')->noActionOnDelete();
            $table->json('answers_json');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['site_evaluation_period_id', 'user_id'], 'site_evaluation_period_user_unique');
        });

        Schema::create('site_evaluation_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_evaluation_period_id')->constrained('site_evaluation_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->noActionOnDelete();
            $table->timestamp('dismissed_at');
            $table->timestamps();
            $table->unique(['site_evaluation_period_id', 'user_id'], 'site_evaluation_dismissal_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_evaluation_dismissals');
        Schema::dropIfExists('site_evaluation_submissions');
        Schema::dropIfExists('site_evaluation_periods');
    }
};
