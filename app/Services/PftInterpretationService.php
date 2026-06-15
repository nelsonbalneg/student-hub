<?php

namespace App\Services;

use App\Models\PftInterpretationRule;
use App\Models\PftTestType;
use Illuminate\Support\Collection;

class PftInterpretationService
{
    /**
     * @param  array<string, mixed>  $results
     * @return array{label: string, color: string|null, field_name: string, rule_id: int}|null
     */
    public function interpret(PftTestType $testType, array $results): ?array
    {
        $rules = $testType->relationLoaded('interpretationRules')
            ? $testType->interpretationRules
            : $testType->interpretationRules()->active()->orderBy('sort_order')->orderBy('id')->get();

        return $this->matchRule($rules, $results);
    }

    /**
     * @param  Collection<int, PftInterpretationRule>  $rules
     * @param  array<string, mixed>  $results
     * @return array{label: string, color: string|null, field_name: string, rule_id: int}|null
     */
    private function matchRule(Collection $rules, array $results): ?array
    {
        foreach ($rules->where('is_active', true) as $rule) {
            $value = $results[$rule->field_name] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $numeric = (float) $value;
            $min = $rule->min_value;
            $max = $rule->max_value;

            if ($min !== null && $numeric < (float) $min) {
                continue;
            }

            if ($max !== null && $numeric > (float) $max) {
                continue;
            }

            return [
                'label' => $rule->label,
                'color' => $rule->color,
                'field_name' => $rule->field_name,
                'rule_id' => $rule->id,
            ];
        }

        return null;
    }
}
