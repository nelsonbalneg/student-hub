<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pft_test_types', function (Blueprint $table): void {
            $table->json('procedure')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('pft_test_types', function (Blueprint $table): void {
            $table->dropColumn('procedure');
        });
    }
};
