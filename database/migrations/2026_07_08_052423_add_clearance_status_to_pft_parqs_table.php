<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pft_parqs', function (Blueprint $table) {
            // 'verified'           – all No answers (auto) OR admin approved clearance
            // 'pending_evaluation' – has Yes answer + clearance uploaded, waiting admin review
            // 'pending'            – has Yes answer but no clearance uploaded yet
            $table->string('clearance_status')->default('pending')->after('medical_clearance_path');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('no action')->after('clearance_status');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('pft_parqs', function (Blueprint $table) {
            $table->dropColumn(['clearance_status', 'verified_by', 'verified_at']);
        });
    }
};
