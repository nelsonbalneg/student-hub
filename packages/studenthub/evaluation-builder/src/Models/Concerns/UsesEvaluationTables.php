<?php

namespace StudentHub\EvaluationBuilder\Models\Concerns;

trait UsesEvaluationTables
{
    protected function evaluationTable(string $name): string
    {
        return config('evaluation-builder.table_prefix', 'evaluation_').$name;
    }

    protected function modelClass(string $key): string
    {
        return config("evaluation-builder.models.{$key}");
    }
}
