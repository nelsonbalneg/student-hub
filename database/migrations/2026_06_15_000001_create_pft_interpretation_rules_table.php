<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pft_interpretation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pft_test_type_id')->constrained('pft_test_types')->cascadeOnDelete();
            $table->string('field_name');
            $table->string('label');
            $table->decimal('min_value', 12, 4)->nullable();
            $table->decimal('max_value', 12, 4)->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pft_test_type_id', 'field_name'], 'pft_interp_test_field_idx');
        });

        $bmiTestTypeId = DB::table('pft_test_types')->where('slug', 'bmi-test')->value('id');
        if ($bmiTestTypeId) {
            collect([
                ['pft_test_type_id' => $bmiTestTypeId, 'field_name' => 'bmi', 'label' => 'Underweight', 'min_value' => null, 'max_value' => 18.4999, 'color' => 'amber', 'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['pft_test_type_id' => $bmiTestTypeId, 'field_name' => 'bmi', 'label' => 'Normal', 'min_value' => 18.5, 'max_value' => 24.9999, 'color' => 'emerald', 'sort_order' => 20, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['pft_test_type_id' => $bmiTestTypeId, 'field_name' => 'bmi', 'label' => 'Overweight', 'min_value' => 25, 'max_value' => 29.9999, 'color' => 'orange', 'sort_order' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['pft_test_type_id' => $bmiTestTypeId, 'field_name' => 'bmi', 'label' => 'Obese', 'min_value' => 30, 'max_value' => null, 'color' => 'red', 'sort_order' => 40, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ])->each(fn (array $rule) => DB::table('pft_interpretation_rules')->insert($rule));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pft_interpretation_rules');
    }
};
