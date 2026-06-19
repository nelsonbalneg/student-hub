<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->unsignedSmallInteger('original_item_number')->nullable()->after('category_id');
            $table->index(
                ['template_id', 'original_item_number'],
                'evaluation_statements_template_item_index',
            );
        });

        Schema::table('evaluation_rating_scales', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('sort_order')->index();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_rating_scales', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('evaluation_statements', function (Blueprint $table): void {
            $table->dropIndex('evaluation_statements_template_item_index');
            $table->dropColumn('original_item_number');
        });
    }
};
