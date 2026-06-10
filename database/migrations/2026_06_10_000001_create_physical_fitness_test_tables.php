<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pft_components', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pft_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pft_component_id')->constrained('pft_components')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pft_component_id', 'slug']);
        });

        Schema::create('pft_test_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pft_category_id')->constrained('pft_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pft_category_id', 'slug']);
        });

        Schema::create('pft_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pft_test_type_id')->constrained('pft_test_types')->cascadeOnDelete();
            $table->string('field_name');
            $table->string('field_label');
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['pft_test_type_id', 'field_name']);
        });

        Schema::create('student_pft_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pft_test_type_id')->constrained('pft_test_types')->cascadeOnDelete();
            $table->json('results_json');
            $table->text('remarks')->nullable();
            $table->date('tested_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'pft_test_type_id']);
            $table->index('tested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_pft_results');
        Schema::dropIfExists('pft_configurations');
        Schema::dropIfExists('pft_test_types');
        Schema::dropIfExists('pft_categories');
        Schema::dropIfExists('pft_components');
    }
};
