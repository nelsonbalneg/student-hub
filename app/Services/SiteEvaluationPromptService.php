<?php

namespace App\Services;

use App\Models\SiteEvaluationPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class SiteEvaluationPromptService
{
    public function __construct(
        private readonly EvaluationTemplatePayloadService $templates,
    ) {}

    public function forUser(?User $user, ?int $suppressedPeriodId = null): ?array
    {
        if (! $user || ! Schema::hasTable('site_evaluation_periods')) {
            return null;
        }

        $period = SiteEvaluationPeriod::query()
            ->currentlyOpen()
            ->whereDoesntHave('submissions', fn ($query) => $query->where('user_id', $user->id))
            ->with('template')
            ->latest('start_date')
            ->first();

        if (! $period || $period->id === $suppressedPeriodId) {
            return null;
        }

        $skipCount = $period->dismissals()
            ->where('user_id', $user->id)
            ->value('skip_count') ?? 0;

        if ($skipCount >= $period->max_skips) {
            return null;
        }

        return [
            'period' => [
                'id' => $period->id,
                'title' => $period->title,
                'description' => $period->description,
                'end_date' => $period->end_date->toDateString(),
                'max_skips' => $period->max_skips,
                'skips_used' => $skipCount,
            ],
            'template' => $this->templates->build($period->template),
        ];
    }
}
