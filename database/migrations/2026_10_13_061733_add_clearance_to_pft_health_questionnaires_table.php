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
        Schema::table('pft_health_questionnaires', function (Blueprint $table) {
            $table->boolean('clearance_granted')->default(false);
            $table->foreignId('clearance_granted_by')->nullable()->constrained('users')->noActionOnDelete();
            $table->timestamp('clearance_granted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pft_health_questionnaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clearance_granted_by');
            $table->dropColumn(['clearance_granted', 'clearance_granted_at']);
        });
    }
};
