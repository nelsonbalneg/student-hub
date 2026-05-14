<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->noActionOnDelete();
            $table->string('dossier_number')->unique();
            $table->string('transaction_type');
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->foreignId('current_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('intake_date')->nullable();
            $table->timestamp('completion_due_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transaction_type');
            $table->index('status');
            $table->index('priority');
            $table->index('current_owner_id');
            $table->index(['student_id', 'status']);
        });

        Schema::create('dossier_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_dossier_id')->constrained('student_dossiers')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_code')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users')->noActionOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('document_type');
            $table->index('checksum');
            $table->index(['student_dossier_id', 'document_type']);
        });

        Schema::create('dossier_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_dossier_id')->constrained('student_dossiers')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('remarks')->nullable();
            $table->foreignId('changed_by')->constrained('users')->noActionOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['student_dossier_id', 'changed_at']);
        });

        Schema::create('dossier_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_dossier_id')->constrained('student_dossiers')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->noActionOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->noActionOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['student_dossier_id', 'assigned_at']);
        });

        Schema::create('dossier_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_dossier_id')->constrained('student_dossiers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->noActionOnDelete();
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dossier_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_dossier_id')->constrained('student_dossiers')->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('actor_id')->constrained('users')->noActionOnDelete();
            $table->string('recipient_or_requestor')->nullable();
            $table->string('legal_basis')->nullable();
            $table->string('legitimate_interest')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['student_dossier_id', 'occurred_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_access_logs');
        Schema::dropIfExists('dossier_notes');
        Schema::dropIfExists('dossier_assignments');
        Schema::dropIfExists('dossier_status_histories');
        Schema::dropIfExists('dossier_documents');
        Schema::dropIfExists('student_dossiers');
    }
};
