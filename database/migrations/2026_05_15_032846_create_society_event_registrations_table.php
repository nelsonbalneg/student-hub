<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_event_id')->constrained();
            $table->foreignId('student_id')->constrained('users');
            $table->string('status')->default('Registered');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_event_registrations');
    }
};