<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_interpretation_ranges', function (Blueprint $table): void {
            $table->text('suggested_intervention')->nullable()->after('interpretation');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_interpretation_ranges', function (Blueprint $table): void {
            $table->dropColumn('suggested_intervention');
        });
    }
};
