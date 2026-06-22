<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clearance_updates', function (Blueprint $table) {
            $table->string('reference_code', 16)->nullable()->after('id');
        });

        DB::table('clearance_updates')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->each(function (object $update): void {
                do {
                    $letters = '';

                    for ($index = 0; $index < 6; $index++) {
                        $letters .= chr(random_int(65, 90));
                    }

                    $date = Carbon::parse($update->created_at)->format('ymd');
                    $referenceCode = "CLR{$date}{$letters}".random_int(0, 9);
                } while (
                    DB::table('clearance_updates')
                        ->where('reference_code', $referenceCode)
                        ->exists()
                );

                DB::table('clearance_updates')
                    ->where('id', $update->id)
                    ->update(['reference_code' => $referenceCode]);
            });

        Schema::table('clearance_updates', function (Blueprint $table) {
            $table->string('reference_code', 16)->nullable(false)->change();
            $table->unique('reference_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clearance_updates', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
