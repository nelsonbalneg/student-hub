<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clearance_update_offices', function (Blueprint $table) {
            $table->foreignId('finalized_by')
                ->nullable()
                ->after('can_resolve_accountability')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });

        DB::table('student_semester_clearances')
            ->whereNull('deleted_at')
            ->update([
                'status' => 'pending_review',
                'cleared_at' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clearance_update_offices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });
    }
};
