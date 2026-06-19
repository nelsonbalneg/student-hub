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
        Schema::table('site_evaluation_periods', function (Blueprint $table): void {
            $table->unsignedInteger('max_skips')->default(1)->after('end_date');
        });

        Schema::table('site_evaluation_dismissals', function (Blueprint $table): void {
            $table->unsignedInteger('skip_count')->default(1)->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_evaluation_dismissals', function (Blueprint $table): void {
            $table->dropColumn('skip_count');
        });

        Schema::table('site_evaluation_periods', function (Blueprint $table): void {
            $table->dropColumn('max_skips');
        });
    }
};
