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
        if (! Schema::hasColumn('examination_schedules', 'start_date')) {
            Schema::table('examination_schedules', function (Blueprint $table) {
                $table->renameColumn('examination_date', 'start_date');
            });
        }

        if (! Schema::hasColumn('examination_schedules', 'end_date')) {
            Schema::table('examination_schedules', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('start_date');
            });
        }

        Schema::table('examination_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('examination_schedules', 'start_time')) {
                $table->dropColumn('start_time');
            }

            if (Schema::hasColumn('examination_schedules', 'end_time')) {
                $table->dropColumn('end_time');
            }

            $table->string('status')->default('Draft')->change();
        });

        DB::table('examination_schedules')
            ->whereIn('status', ['Active', 'Closed'])
            ->update(['status' => 'Published']);

        DB::table('examination_schedules')
            ->whereNull('end_date')
            ->update(['end_date' => DB::raw('start_date')]);

        Schema::table('examination_schedules', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('examination_schedules')
            ->where('status', 'Published')
            ->update(['status' => 'Active']);

        Schema::table('examination_schedules', function (Blueprint $table) {
            $table->renameColumn('start_date', 'examination_date');
            $table->time('start_time')->nullable()->after('examination_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->dropColumn('end_date');
            $table->enum('status', ['Draft', 'Active', 'Closed'])
                ->default('Draft')
                ->change();
        });
    }
};
