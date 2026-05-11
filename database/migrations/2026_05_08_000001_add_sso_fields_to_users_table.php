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
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_id')->nullable()->index();
            $table->uuid('sso_uuid')->nullable()->index();
            $table->string('sso_username')->nullable();
            $table->string('sso_account_type')->nullable();
            $table->string('sso_avatar', 2048)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sso_id']);
            $table->dropIndex(['sso_uuid']);
            $table->dropColumn([
                'sso_id',
                'sso_uuid',
                'sso_username',
                'sso_account_type',
                'sso_avatar',
            ]);
        });
    }
};
