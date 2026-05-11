<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_account_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('student_no')->index();
            $table->string('semester');
            $table->string('term_id');
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('username')->unique();
            $table->text('password');
            $table->string('status')->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->json('mikrotik_response')->nullable();
            $table->timestamps();

            $table->unique(['student_no', 'semester', 'term_id'], 'internet_accounts_student_semester_term_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_account_requests');
    }
};
