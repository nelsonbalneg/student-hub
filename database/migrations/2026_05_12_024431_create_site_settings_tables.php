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
        Schema::create('site_campuses', function (Blueprint $table) {
            $table->id();
            $table->string('campus_name');
            $table->string('campus_address')->nullable();
            $table->string('campus_logo_path')->nullable();
            $table->string('real_campus_id')->nullable()->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('site_academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_campus_id')->constrained('site_campuses')->cascadeOnDelete();
            $table->string('school_year'); // e.g. 2025-2026
            $table->string('semester');   // e.g. 1st Semester
            $table->string('term_id')->nullable(); // From Academic API
            $table->enum('status', ['Active', 'Inactive', 'Archived'])->default('Inactive');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // Business Rules: Unique constraints
            $table->unique(['site_campus_id', 'term_id'], 'unique_term_id_per_campus');
            $table->unique(['site_campus_id', 'school_year', 'semester'], 'unique_period_per_campus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_academic_terms');
        Schema::dropIfExists('site_campuses');
    }
};
