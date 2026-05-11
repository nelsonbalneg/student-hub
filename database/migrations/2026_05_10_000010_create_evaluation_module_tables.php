<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('academic_year');
            $table->string('semester');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed', 'archived'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['academic_year', 'semester']);
            $table->index(['status', 'start_date', 'end_date']);
            $table->index('created_by');
        });

        Schema::create('evaluation_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_period_id')->constrained('evaluation_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('student_no')->nullable();
            $table->text('intent');
            $table->text('remarks')->nullable();
            $table->enum('status', ['submitted', 'under_evaluation', 'needs_action', 'resolved', 'done', 'cancelled'])->default('submitted');
            $table->text('registrar_feedback')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users');
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['evaluation_period_id', 'student_id']);
            $table->index('evaluation_period_id');
            $table->index('student_id');
            $table->index('student_no');
            $table->index('status');
        });

        Schema::create('evaluation_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_request_id')->constrained('evaluation_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('message');
            $table->enum('visibility', ['internal', 'student_visible'])->default('student_visible');
            $table->timestamps();

            $table->index('evaluation_request_id');
            $table->index('visibility');
        });

        Schema::create('evaluation_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_activity_logs');
        Schema::dropIfExists('evaluation_feedbacks');
        Schema::dropIfExists('evaluation_requests');
        Schema::dropIfExists('evaluation_periods');
    }
};
