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
        Schema::create('pft_parqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term_id');
            $table->boolean('q1')->default(false);
            $table->boolean('q2')->default(false);
            $table->boolean('q3')->default(false);
            $table->boolean('q4')->default(false);
            $table->boolean('q5')->default(false);
            $table->boolean('q6')->default(false);
            $table->boolean('q7')->default(false);
            $table->boolean('declaration_agreed')->default(false);
            $table->string('medical_clearance_path')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pft_parqs');
    }
};
