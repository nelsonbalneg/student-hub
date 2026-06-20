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
        Schema::table('clearance_types', function (Blueprint $table) {
            $table->foreignId('campus_id')
                ->nullable()
                ->after('id');
        });

        $defaultCampusId = DB::table('site_campuses')
            ->where('id', 2)
            ->value('id')
            ?? DB::table('site_campuses')->orderBy('id')->value('id');

        if ($defaultCampusId) {
            DB::table('clearance_types')
                ->whereNull('campus_id')
                ->update(['campus_id' => $defaultCampusId]);
        }

        if (! $defaultCampusId && DB::table('clearance_types')->exists()) {
            throw new RuntimeException('A site campus is required before existing clearance types can be assigned.');
        }

        Schema::table('clearance_types', function (Blueprint $table) {
            $table->unsignedBigInteger('campus_id')->nullable(false)->change();
            $table->foreign('campus_id')
                ->references('id')
                ->on('site_campuses')
                ->noActionOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clearance_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_id');
        });
    }
};
