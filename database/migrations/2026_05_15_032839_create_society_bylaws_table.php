<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_bylaws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->foreignId('academic_year_id')->nullable();
            $table->string('title');
            $table->string('version')->nullable();
            $table->string('file_path');
            $table->string('status')->default('Pending Review');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_bylaws');
    }
};