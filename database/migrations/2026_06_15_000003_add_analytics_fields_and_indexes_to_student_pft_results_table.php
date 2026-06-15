<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\StudentPftResult;
use App\Services\AcademicApiService;
use App\Services\PftInterpretationService;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->string('sex', 10)->nullable()->after('year_level_id');
            $table->string('classification')->nullable()->after('status');
            $table->text('interpretation')->nullable()->after('classification');
            $table->text('suggested_intervention')->nullable()->after('interpretation');
            $table->string('color_class', 50)->nullable()->after('suggested_intervention');

            // Add indexes for columns without indexes
            $table->index('tenant_id');
            $table->index('campus_id');
            $table->index('college_id');
            $table->index('term_id');
            $table->index('sex');
            $table->index('classification');
        });

        // Populate existing rows
        try {
            $academicApi = app(AcademicApiService::class);
            $interpretationService = app(PftInterpretationService::class);

            $results = StudentPftResult::with(['testType.interpretationRules'])->get();

            foreach ($results as $row) {
                $sex = null;
                if ($row->user && $row->user->student_no) {
                    try {
                        $profileResult = $academicApi->profileForStudent($row->user->student_no, $row->tenant_id ?: '1');
                        $sex = $profileResult['data']['gender'] ?? null;
                    } catch (\Throwable $e) {
                        Log::warning("Migration failed to fetch profile for user {$row->user->id}: " . $e->getMessage());
                    }
                }

                $classification = null;
                $interpretation = null;
                $suggestedIntervention = null;
                $colorClass = null;

                if ($row->testType) {
                    $resultsJson = $row->results_json ?? [];
                    $matched = $interpretationService->interpret($row->testType, $resultsJson);
                    if ($matched) {
                        $classification = $matched['classification'] ?? $matched['label'];
                        $interpretation = $matched['interpretation'] ?? null;
                        $suggestedIntervention = $matched['suggested_intervention'] ?? null;
                        $colorClass = $matched['color_class'] ?? $matched['color'] ?? null;
                    }
                }

                $row->update([
                    'sex' => $sex,
                    'classification' => $classification,
                    'interpretation' => $interpretation,
                    'suggested_intervention' => $suggestedIntervention,
                    'color_class' => $colorClass,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Data migration in student_pft_results failed: " . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('student_pft_results', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['campus_id']);
            $table->dropIndex(['college_id']);
            $table->dropIndex(['term_id']);
            $table->dropIndex(['sex']);
            $table->dropIndex(['classification']);

            $table->dropColumn([
                'sex',
                'classification',
                'interpretation',
                'suggested_intervention',
                'color_class',
            ]);
        });
    }
};
