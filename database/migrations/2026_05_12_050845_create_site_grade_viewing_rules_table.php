<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_grade_viewing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_campus_id')->constrained('site_campuses')->cascadeOnDelete();
            $table->string('rule_name');
            $table->boolean('bypass_evaluation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('site_grade_viewing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->nullable()->constrained('site_grade_viewing_rules')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // created, updated, deleted, toggled
            $table->json('changes')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_grade_viewing_logs');
        Schema::dropIfExists('site_grade_viewing_rules');
    }
};
