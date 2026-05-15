<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('event_type')->nullable();
            $table->string('target_audience')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('registration_required')->default(false);
            $table->boolean('attendance_required')->default(false);
            $table->string('status')->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_events');
    }
};