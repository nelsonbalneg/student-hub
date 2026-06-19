<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->text('help_text')->nullable()->after('statement');
            $table->decimal('weight', 8, 2)->default(0)->after('is_required');
            $table->boolean('is_visible')->default(true)->after('weight');
            $table->boolean('scoring_enabled')->default(true)->after('is_visible');
            $table->boolean('is_read_only')->default(false)->after('scoring_enabled');
            $table->json('settings_json')->nullable()->after('is_read_only');
        });

        Schema::table('evaluation_choices', function (Blueprint $table): void {
            $table->decimal('score_value', 8, 2)->nullable()->after('choice_value');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_choices', function (Blueprint $table): void {
            $table->dropColumn('score_value');
        });

        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->dropColumn([
                'help_text',
                'weight',
                'is_visible',
                'scoring_enabled',
                'is_read_only',
                'settings_json',
            ]);
        });
    }
};
