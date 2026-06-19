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
        Schema::create('evaluation_scale_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('evaluation_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::table('evaluation_statement_categories', function (Blueprint $table): void {
            $table->foreignId('scale_set_id')
                ->nullable()
                ->after('template_id')
                ->constrained('evaluation_scale_sets')
                ->noActionOnDelete();
        });

        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->foreignId('scale_set_id')
                ->nullable()
                ->after('category_id')
                ->constrained('evaluation_scale_sets')
                ->noActionOnDelete();
        });

        Schema::table('evaluation_rating_scales', function (Blueprint $table): void {
            $table->foreignId('scale_set_id')
                ->nullable()
                ->after('template_id')
                ->constrained('evaluation_scale_sets')
                ->noActionOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_rating_scales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scale_set_id');
        });

        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scale_set_id');
        });

        Schema::table('evaluation_statement_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scale_set_id');
        });

        Schema::dropIfExists('evaluation_scale_sets');
    }
};
