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
        Schema::create('user_legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('version')->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'legal_document_id', 'type', 'version'],
                'user_legal_acceptances_version_unique',
            );
            $table->index(['user_id', 'type', 'version'], 'user_legal_acceptances_user_type_version_idx');
            $table->index(['legal_document_id', 'accepted_at'], 'user_legal_acceptances_document_accepted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_legal_acceptances');
    }
};
