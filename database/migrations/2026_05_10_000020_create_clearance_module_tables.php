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
        // 1. Semesters
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->integer('external_id')->nullable(); // Removed unique() for SQL Server NULL compatibility
            $table->integer('campus_id')->nullable();
            $table->string('campus_name')->nullable();
            $table->string('academic_year');
            $table->string('term');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Offices
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Clearance Types
        Schema::create('clearance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Clearance Updates
        Schema::create('clearance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clearance_type_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('purpose')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft'); // draft, published, closed
            $table->foreignId('created_by')->constrained('users')->noActionOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Clearance Update Offices
        Schema::create('clearance_update_offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('can_upload_accountability')->default(true);
            $table->boolean('can_resolve_accountability')->default(true);
            $table->timestamps();
        });

        // 6. Student Semester Clearances
        Schema::create('student_semester_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->noActionOnDelete();
            $table->foreignId('student_id')->constrained('users')->noActionOnDelete();
            $table->string('reference_no')->unique();
            $table->string('status')->default('pending_review'); // cleared, not_cleared, with_accountability, pending_review, completed
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clearance_update_id', 'student_id']);
        });

        // 7. Clearance Accountabilities
        Schema::create('clearance_accountabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->noActionOnDelete();
            $table->foreignId('student_id')->constrained('users')->noActionOnDelete();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->noActionOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->default('pending'); // pending, resolved, waived
            $table->string('proof_attachment')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Clearance Accountability Uploads
        Schema::create('clearance_accountability_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->noActionOnDelete();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->noActionOnDelete();
            $table->string('filename');
            $table->integer('total_rows')->default(0);
            $table->integer('matched_students')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 9. Clearance Logs
        Schema::create('clearance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_semester_clearance_id')->constrained('student_semester_clearances')->cascadeOnDelete();
            $table->foreignId('clearance_update_id')->constrained()->noActionOnDelete();
            $table->foreignId('student_id')->constrained('users')->noActionOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->constrained('users')->noActionOnDelete();
            $table->timestamps();
        });

        // 10. Clearance Certificates
        Schema::create('clearance_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_semester_clearance_id')->constrained('student_semester_clearances')->cascadeOnDelete();
            $table->string('certificate_no')->unique();
            $table->string('verification_hash')->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clearance_certificates');
        Schema::dropIfExists('clearance_logs');
        Schema::dropIfExists('clearance_accountability_uploads');
        Schema::dropIfExists('clearance_accountabilities');
        Schema::dropIfExists('student_semester_clearances');
        Schema::dropIfExists('clearance_update_offices');
        Schema::dropIfExists('clearance_updates');
        Schema::dropIfExists('clearance_types');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('semesters');
    }
};
