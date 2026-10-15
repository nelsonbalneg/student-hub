<?php

namespace App\Services;

use App\Enums\GradeAccessScope;
use App\Models\GradeViewingRule;
use Illuminate\Support\Collection;

class GradeViewingRuleResolver
{
    public function resolve(Collection $rules, int|string|null $termId, int|string|null $activeTermId): ?GradeViewingRule
    {
        $priority = fn (GradeViewingRule $rule): int => match ($rule->access_scope) {
            'specific_term' => 3,
            'current_term' => 2,
            default => 1,
        };

        return $rules->filter(function (GradeViewingRule $rule) use ($termId, $activeTermId): bool {
            $scope = GradeAccessScope::tryFrom($rule->access_scope ?? '') ?? GradeAccessScope::CurrentTerm;

            return $rule->is_active && $scope->includes($termId, $activeTermId, $rule->selectedAcademicTerm?->term_id);
        })->sort(fn (GradeViewingRule $a, GradeViewingRule $b): int => ($priority($b) <=> $priority($a))
            ?: (($b->created_at?->getTimestamp() ?? 0) <=> ($a->created_at?->getTimestamp() ?? 0))
            ?: ($b->id <=> $a->id)
        )->first();
    }
}
