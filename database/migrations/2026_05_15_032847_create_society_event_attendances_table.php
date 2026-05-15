<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_event_id')->constrained();
            $table->foreignId('student_id')->constrained('users');
            $table->foreignId('society_id')->nullable()->constrained();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('attendance_status')->default('Present');
            $table->text('remarks')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_event_attendances');
    }
};