<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('academic_year_id')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_memberships');
    }
};