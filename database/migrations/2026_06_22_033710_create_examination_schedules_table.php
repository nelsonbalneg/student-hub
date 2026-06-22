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
        Schema::create('examination_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('academic_term_id')->constrained('site_academic_terms');
            $table->foreignId('campus_id')->constrained('site_campuses');
            $table->date('examination_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('description')->nullable();
            $table->enum('status', ['Draft', 'Active', 'Closed'])->default('Draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examination_schedules');
    }
};
