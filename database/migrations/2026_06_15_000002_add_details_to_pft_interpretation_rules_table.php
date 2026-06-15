<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pft_interpretation_rules', function (Blueprint $table): void {
            $table->string('classification')->nullable()->after('label');
            $table->text('interpretation')->nullable()->after('classification');
            $table->text('suggested_intervention')->nullable()->after('interpretation');
            $table->string('color_class')->nullable()->after('color');
        });

        // Populate existing rows
        DB::table('pft_interpretation_rules')->get()->each(function ($rule) {
            $classification = $rule->label;
            $interpretation = "Results fall in the " . strtolower($rule->label) . " range.";
            $suggestedIntervention = match (strtolower($rule->label)) {
                'obese' => 'Structured wellness activities, nutrition counseling, and regular monitoring.',
                'underweight' => 'Nutrition enhancement plans, dietary guidance, and growth tracking.',
                'needs improvement' => 'Specific physical drills, targeted training, and strength monitoring.',
                'poor' => 'Graduated fitness programs, basic drills, and close supervisor tracking.',
                'average', 'fair' => 'Standard physical activities, general fitness classes, and maintenance tracking.',
                'normal', 'good', 'excellent', 'very good' => 'General physical education activities and continued fitness maintenance.',
                default => 'Routine fitness tracking and recommended regular physical activities.'
            };
            $colorClass = $rule->color;

            DB::table('pft_interpretation_rules')->where('id', $rule->id)->update([
                'classification' => $classification,
                'interpretation' => $interpretation,
                'suggested_intervention' => $suggestedIntervention,
                'color_class' => $colorClass,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('pft_interpretation_rules', function (Blueprint $table): void {
            $table->dropColumn(['classification', 'interpretation', 'suggested_intervention', 'color_class']);
        });
    }
};
