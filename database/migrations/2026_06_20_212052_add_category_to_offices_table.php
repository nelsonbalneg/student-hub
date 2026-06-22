<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('category', 32)->default('support')->after('code')->index();
        });

        DB::table('offices')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(function (object $office): void {
                $name = Str::lower($office->name);

                $category = Str::contains($name, ['college', 'institute', 'graduate'])
                    ? 'academic'
                    : (Str::contains($name, ['vice president', 'president', 'administration', 'administrative'])
                        ? 'administration'
                        : 'support');

                DB::table('offices')
                    ->where('id', $office->id)
                    ->update(['category' => $category]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
