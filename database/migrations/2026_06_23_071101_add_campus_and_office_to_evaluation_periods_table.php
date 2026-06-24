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
        Schema::table('evaluation_periods', function (Blueprint $table): void {
            $table->foreignId('campus_id')
                ->nullable()
                ->after('description')
                ->constrained('site_campuses');
            $table->foreignId('office_id')
                ->nullable()
                ->after('campus_id')
                ->constrained('offices');

            $table->index(['campus_id', 'academic_year', 'semester']);
            $table->index(['office_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_periods', function (Blueprint $table): void {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['campus_id']);
            $table->dropIndex(['office_id', 'status']);
            $table->dropIndex(['campus_id', 'academic_year', 'semester']);
            $table->dropColumn(['campus_id', 'office_id']);
        });
    }
};
