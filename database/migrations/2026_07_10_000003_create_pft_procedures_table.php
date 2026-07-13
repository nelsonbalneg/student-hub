<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pft_test_types', 'procedure')) {
            Schema::table('pft_test_types', function (Blueprint $table): void {
                $table->dropColumn('procedure');
            });
        }

        Schema::create('pft_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pft_test_type_id')->constrained('pft_test_types')->cascadeOnDelete();
            $table->unsignedInteger('step_no')->default(1);
            $table->text('description');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pft_test_type_id', 'step_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pft_procedures');

        Schema::table('pft_test_types', function (Blueprint $table): void {
            $table->json('procedure')->nullable()->after('unit');
        });
    }
};
