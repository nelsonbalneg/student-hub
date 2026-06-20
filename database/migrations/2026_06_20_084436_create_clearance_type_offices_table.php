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
        Schema::create('clearance_type_offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_type_id')
                ->constrained('clearance_types')
                ->noActionOnDelete();
            $table->foreignId('office_id')
                ->constrained('offices')
                ->noActionOnDelete();
            $table->timestamps();

            $table->unique(['clearance_type_id', 'office_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clearance_type_offices');
    }
};
