<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('evaluation_statement_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('evaluation_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('evaluation_statement_categories')->noActionOnDelete();
            $table->text('statement');
            $table->string('statement_type')->index();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('evaluation_rating_scales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained('evaluation_statements')->noActionOnDelete();
            $table->decimal('value', 8, 2);
            $table->string('label');
            $table->text('interpretation')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('evaluation_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('evaluation_statements')->cascadeOnDelete();
            $table->string('choice_text');
            $table->string('choice_value');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_choices');
        Schema::dropIfExists('evaluation_rating_scales');
        Schema::dropIfExists('evaluation_statements');
        Schema::dropIfExists('evaluation_statement_categories');
        Schema::dropIfExists('evaluation_templates');
    }
};
