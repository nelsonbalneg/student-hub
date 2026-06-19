<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('evaluation_templates')
            ->orderBy('id')
            ->each(function (object $template): void {
                $defaultScaleIds = DB::table('evaluation_rating_scales')
                    ->where('template_id', $template->id)
                    ->whereNull('statement_id')
                    ->pluck('id');

                if ($defaultScaleIds->isNotEmpty()) {
                    $defaultSetId = DB::table('evaluation_scale_sets')->insertGetId([
                        'template_id' => $template->id,
                        'name' => 'Template Default',
                        'description' => 'Migrated template-level rating scale.',
                        'is_default' => true,
                        'status' => 'active',
                        'sort_order' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('evaluation_rating_scales')
                        ->whereIn('id', $defaultScaleIds)
                        ->update(['scale_set_id' => $defaultSetId]);
                }

                DB::table('evaluation_statements')
                    ->where('template_id', $template->id)
                    ->orderBy('id')
                    ->each(function (object $statement): void {
                        $scaleIds = DB::table('evaluation_rating_scales')
                            ->where('statement_id', $statement->id)
                            ->pluck('id');

                        if ($scaleIds->isEmpty()) {
                            return;
                        }

                        $scaleSetId = DB::table('evaluation_scale_sets')->insertGetId([
                            'template_id' => $statement->template_id,
                            'name' => 'Question '.$statement->id.' Scale',
                            'description' => 'Migrated question-specific rating scale.',
                            'is_default' => false,
                            'status' => 'active',
                            'sort_order' => $statement->id + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('evaluation_rating_scales')
                            ->whereIn('id', $scaleIds)
                            ->update(['scale_set_id' => $scaleSetId]);

                        DB::table('evaluation_statements')
                            ->where('id', $statement->id)
                            ->update(['scale_set_id' => $scaleSetId]);
                    });
            });
    }

    public function down(): void
    {
        DB::table('evaluation_statement_categories')->update(['scale_set_id' => null]);
        DB::table('evaluation_statements')->update(['scale_set_id' => null]);
        DB::table('evaluation_rating_scales')->update(['scale_set_id' => null]);
        DB::table('evaluation_scale_sets')->delete();
    }
};
