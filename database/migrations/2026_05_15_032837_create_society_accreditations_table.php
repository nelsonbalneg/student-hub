<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_accreditations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained();
            $table->foreignId('academic_year_id')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('remarks')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('constitution_bylaws_path')->nullable();
            $table->string('list_of_officers_path')->nullable();
            $table->string('supporting_documents_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_accreditations');
    }
};