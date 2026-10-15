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
        Schema::create('grade_rule_user_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_viewing_rule_id')->constrained('site_grade_viewing_rules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users');
            $table->unique(['grade_viewing_rule_id', 'user_id'], 'grade_override_rule_user_unique');
            $table->index('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_rule_user_overrides');
    }
};
