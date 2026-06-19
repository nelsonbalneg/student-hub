<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scoring_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('evaluation_statement_categories')->noActionOnDelete();
            $table->foreignId('statement_id')->constrained('evaluation_statements')->noActionOnDelete();
            $table->string('formula_type')->default('sum');
            $table->decimal('multiplier', 8, 2)->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(
                ['template_id', 'category_id', 'statement_id'],
                'evaluation_scoring_rules_unique',
            );
        });

        Schema::create('evaluation_interpretation_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('evaluation_statement_categories')->noActionOnDelete();
            $table->decimal('min_value', 8, 2);
            $table->decimal('max_value', 8, 2);
            $table->string('interpretation');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(
                ['template_id', 'category_id', 'min_value', 'max_value'],
                'evaluation_interpretation_ranges_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_interpretation_ranges');
        Schema::dropIfExists('evaluation_scoring_rules');
    }
};
