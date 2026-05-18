<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('societies', function (Blueprint $table) {
            if (! Schema::hasColumn('societies', 'full_name')) {
                $table->string('full_name')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('societies', 'abbreviation')) {
                $table->string('abbreviation')->nullable()->after('full_name')->index();
            }
            if (! Schema::hasColumn('societies', 'category')) {
                $table->string('category')->nullable()->after('abbreviation');
            }
            if (! Schema::hasColumn('societies', 'college_unit')) {
                $table->string('college_unit')->nullable()->after('category');
            }
            if (! Schema::hasColumn('societies', 'description')) {
                $table->text('description')->nullable()->after('college_unit');
            }
            if (! Schema::hasColumn('societies', 'facebook_page_url')) {
                $table->string('facebook_page_url')->nullable()->after('description');
            }
            if (! Schema::hasColumn('societies', 'status')) {
                $table->string('status')->default('draft')->after('facebook_page_url')->index();
            }
            if (! Schema::hasColumn('societies', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users');
            }
        });

        Schema::create('society_accreditation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('accreditation_request_no')->nullable()->index();
            $table->string('semester')->index();
            $table->string('school_year')->index();
            $table->string('mode_of_submission')->default('online');
            $table->date('date_received')->nullable();
            $table->foreignId('received_checked_by')->nullable()->constrained('users');
            $table->string('submitted_by_name')->nullable();
            $table->string('submitted_by_position')->nullable();
            $table->string('submitted_by_signature')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['society_id', 'semester', 'school_year'], 'society_accreditation_unique_term');
        });

        Schema::table('society_officers', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();

            if (! Schema::hasColumn('society_officers', 'accreditation_request_id')) {
                $table->foreignId('accreditation_request_id')->nullable()->after('society_id')->constrained('society_accreditation_requests');
            }
            if (! Schema::hasColumn('society_officers', 'student_identifier')) {
                $table->string('student_identifier')->nullable()->after('student_id');
            }
            if (! Schema::hasColumn('society_officers', 'full_name')) {
                $table->string('full_name')->nullable()->after('student_identifier');
            }
            if (! Schema::hasColumn('society_officers', 'year_course_section')) {
                $table->string('year_course_section')->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('society_officers', 'permanent_address')) {
                $table->text('permanent_address')->nullable()->after('year_course_section');
            }
            if (! Schema::hasColumn('society_officers', 'usm_email')) {
                $table->string('usm_email')->nullable()->after('permanent_address');
            }
            if (! Schema::hasColumn('society_officers', 'contact_no')) {
                $table->string('contact_no')->nullable()->after('usm_email');
            }
            if (! Schema::hasColumn('society_officers', 'school_year')) {
                $table->string('school_year')->nullable()->after('contact_no')->index();
            }
            if (! Schema::hasColumn('society_officers', 'semester')) {
                $table->string('semester')->nullable()->after('school_year')->index();
            }
        });

        Schema::create('society_advisers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_request_id')->nullable()->constrained('society_accreditation_requests');
            $table->string('full_name');
            $table->string('college_unit')->nullable();
            $table->string('usm_email')->nullable();
            $table->string('signature')->nullable();
            $table->boolean('commitment_form_accepted')->default(false);
            $table->date('commitment_date')->nullable();
            $table->json('commitment_acknowledgements')->nullable();
            $table->string('school_year')->nullable()->index();
            $table->string('semester')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('society_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_request_id')->nullable()->constrained('society_accreditation_requests');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('student_id')->nullable()->index();
            $table->string('full_name');
            $table->string('year_course_section')->nullable();
            $table->string('usm_email')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('membership_type')->default('member');
            $table->string('status')->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->string('school_year')->index();
            $table->string('semester')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('society_accreditation_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('general')->index();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->string('applies_to')->nullable();
            $table->timestamps();
        });

        Schema::create('society_requirement_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_request_id')->constrained('society_accreditation_requests')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('society_accreditation_requirements')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('original_file_name')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users');
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('resubmission_count')->default(0);
            $table->json('resubmission_history')->nullable();
            $table->timestamps();

            $table->unique(['accreditation_request_id', 'requirement_id']);
        });

        Schema::create('society_accreditation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->nullable()->constrained();
            $table->foreignId('accreditation_request_id')->nullable()->constrained('society_accreditation_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action')->index();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_accreditation_logs');
        Schema::dropIfExists('society_requirement_submissions');
        Schema::dropIfExists('society_accreditation_requirements');
        Schema::dropIfExists('society_members');
        Schema::dropIfExists('society_advisers');
        Schema::dropIfExists('society_accreditation_requests');
    }
};
