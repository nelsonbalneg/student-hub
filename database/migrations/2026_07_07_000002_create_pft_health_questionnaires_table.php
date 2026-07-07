<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pft_health_questionnaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('term_id');
            
            $table->string('civil_status')->nullable();
            $table->string('household_monthly_income')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_occupation')->nullable();
            
            $table->boolean('has_medical_condition')->default(false);
            $table->text('medical_condition_details')->nullable();
            $table->boolean('has_medication')->default(false);
            $table->text('medication_details')->nullable();
            
            $table->string('smoking_status')->nullable();
            $table->string('alcohol_consumption')->nullable();
            
            $table->json('specific_conditions')->nullable();
            $table->string('other_condition')->nullable();
            
            $table->timestamps();

            $table->unique(['user_id', 'term_id']);
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pft_health_questionnaires');
    }
};
