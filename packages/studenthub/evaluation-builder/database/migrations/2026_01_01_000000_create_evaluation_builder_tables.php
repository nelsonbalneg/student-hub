<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('evaluation-builder.table_prefix', 'evaluation_');

        Schema::create($prefix.'templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($prefix.'scale_sets', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->unique(['template_id', 'name']);
        });

        Schema::create($prefix.'statement_categories', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->foreignId('scale_set_id')->nullable()->constrained($prefix.'scale_sets')->noActionOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create($prefix.'statements', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained($prefix.'statement_categories')->noActionOnDelete();
            $table->foreignId('scale_set_id')->nullable()->constrained($prefix.'scale_sets')->noActionOnDelete();
            $table->unsignedSmallInteger('original_item_number')->nullable();
            $table->text('statement');
            $table->text('help_text')->nullable();
            $table->string('statement_type')->index();
            $table->boolean('is_required')->default(false);
            $table->decimal('weight', 8, 2)->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('scoring_enabled')->default(true);
            $table->boolean('is_read_only')->default(false);
            $table->json('settings_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->index(['template_id', 'original_item_number']);
        });

        Schema::create($prefix.'rating_scales', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->foreignId('scale_set_id')->nullable()->constrained($prefix.'scale_sets')->noActionOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained($prefix.'statements')->noActionOnDelete();
            $table->decimal('value', 8, 2);
            $table->string('label');
            $table->text('interpretation')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create($prefix.'choices', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('statement_id')->constrained($prefix.'statements')->cascadeOnDelete();
            $table->string('choice_text');
            $table->string('choice_value');
            $table->decimal('score_value', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create($prefix.'scoring_rules', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained($prefix.'statement_categories')->noActionOnDelete();
            $table->foreignId('statement_id')->constrained($prefix.'statements')->noActionOnDelete();
            $table->string('formula_type')->default('sum');
            $table->decimal('multiplier', 8, 2)->default(1);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['template_id', 'category_id', 'statement_id']);
        });

        Schema::create($prefix.'interpretation_ranges', function (Blueprint $table) use ($prefix): void {
            $table->id();
            $table->foreignId('template_id')->constrained($prefix.'templates')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained($prefix.'statement_categories')->noActionOnDelete();
            $table->decimal('min_value', 8, 2);
            $table->decimal('max_value', 8, 2);
            $table->string('interpretation');
            $table->text('suggested_intervention')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['template_id', 'category_id', 'min_value', 'max_value']);
        });
    }

    public function down(): void
    {
        $prefix = config('evaluation-builder.table_prefix', 'evaluation_');

        foreach (['interpretation_ranges', 'scoring_rules', 'choices', 'rating_scales', 'statements', 'statement_categories', 'scale_sets', 'templates'] as $table) {
            Schema::dropIfExists($prefix.$table);
        }
    }
};
