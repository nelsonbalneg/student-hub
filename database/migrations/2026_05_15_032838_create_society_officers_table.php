<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->foreignId('academic_year_id')->nullable();
            $table->foreignId('student_id')->constrained('users');
            $table->string('position');
            $table->integer('rank_order')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_officers');
    }
};