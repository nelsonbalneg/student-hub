<?php

namespace App\Services;

use App\Models\EvaluationStatement;
use App\Models\EvaluationTemplate;
use App\Models\SiteEvaluationPeriod;
use App\Models\SiteEvaluationSubmission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SiteEvaluationAnalysisService
{
    public function __construct(
        private readonly CcdCaresEvaluationAnalysisService $evaluation,
        private readonly EvaluationTemplatePayloadService $templates,
    ) {}

    public function prepareTemplate(EvaluationTemplate $template): EvaluationTemplate
    {
        return $this->evaluation->prepareTemplate($template);
    }

    public function submission(SiteEvaluationSubmission $submission, EvaluationTemplate $template): array
    {
        return [
            'id' => $submission->id,
            'answers' => $submission->answers_json ?? [],
            'submitted_at' => $submission->submitted_at?->toDateTimeString(),
            'user' => [
                'id' => $submission->user->id,
                'name' => $submission->user->name,
                'email' => $submission->user->email,
                'student_no' => $submission->user->student_no,
                'campus_name' => $submission->user->campus_name,
                'user_type' => $submission->user->user_type,
            ],
            'interpretations' => $this->evaluation->interpretations(
                $submission->answers_json ?? [],
                $template,
            ),
        ];
    }

    public function analytics(
        SiteEvaluationPeriod $period,
        Collection $submissions,
        EvaluationTemplate $template,
    ): array {
        $interpretations = collect();

        foreach ($submissions as $submission) {
            foreach ($this->evaluation->interpretations($submission->answers_json ?? [], $template) as $result) {
                $interpretations->push($result);
            }
        }

        $categories = $template->categories->map(function ($category) use ($interpretations, $submissions, $template): array {
            $results = $interpretations->where('category_id', $category->id);
            $questions = $template->statements
                ->where('category_id', $category->id)
                ->filter(fn (EvaluationStatement $statement): bool => $statement->scoring_enabled)
                ->map(function (EvaluationStatement $statement) use ($submissions): array {
                    $values = $submissions
                        ->map(fn (SiteEvaluationSubmission $submission) => $submission->answers_json[$statement->id] ?? null)
                        ->filter(fn ($value): bool => is_numeric($value))
                        ->map(fn ($value): float => (float) $value);

                    return [
                        'id' => $statement->id,
                        'statement' => $statement->statement,
                        'average' => round((float) $values->avg(), 2),
                        'responses' => $values->count(),
                    ];
                })
                ->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'average_score' => round((float) $results->avg('score'), 2),
                'average_rating' => round((float) $questions->avg('average'), 2),
                'distribution' => $results
                    ->countBy('interpretation')
                    ->sortDesc()
                    ->map(fn (int $count, string $interpretation): array => [
                        'interpretation' => $interpretation,
                        'count' => $count,
                    ])
                    ->values(),
                'questions' => $questions,
            ];
        })->values();
        $dismissedUsers = $period->dismissals()->count();
        $skips = (int) $period->dismissals()->sum('skip_count');
        $responded = $submissions->count();
        $engaged = $responded + $dismissedUsers;

        return [
            'total_submissions' => $responded,
            'total_dismissals' => $skips,
            'dismissed_users' => $dismissedUsers,
            'response_rate' => $engaged === 0 ? 0 : round(($responded / $engaged) * 100, 1),
            'overall_rating' => round((float) $categories->avg('average_rating'), 2),
            'campuses' => $submissions
                ->countBy(fn (SiteEvaluationSubmission $submission): string => $submission->user->campus_name ?: 'Not specified')
                ->sortDesc()
                ->map(fn (int $count, string $campus): array => compact('campus', 'count'))
                ->values(),
            'categories' => $categories,
            'insights' => $this->insights($categories, $responded, $skips),
        ];
    }

    public function templatePayload(EvaluationTemplate $template): array
    {
        return $this->templates->build($template);
    }

    public function comments(
        SiteEvaluationPeriod $period,
        EvaluationTemplate $template,
        array $filters,
        int $page,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $commentStatements = $template->statements
            ->filter(fn (EvaluationStatement $statement): bool => in_array(
                $statement->statement_type,
                ['long_answer', 'text_answer'],
                true,
            ))
            ->keyBy('id');

        if ($commentStatements->isEmpty()) {
            return new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => request()->url(),
                'pageName' => 'comments_page',
            ]);
        }

        $query = DB::query()
            ->fromRaw('site_evaluation_submissions AS submissions CROSS APPLY OPENJSON(submissions.answers_json) AS answer')
            ->join('users as users', 'users.id', '=', 'submissions.user_id')
            ->where('submissions.site_evaluation_period_id', $period->id)
            ->whereIn(
                DB::raw('[answer].[key]'),
                $commentStatements->keys()->map(fn ($id): string => (string) $id)->all(),
            )
            ->whereRaw("NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(max), [answer].[value]))), '') IS NOT NULL")
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.student_no', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($filters['campus'] ?? null, fn ($query, string $campus) => $query->where('users.campus_name', $campus))
            ->when($filters['submitted_from'] ?? null, fn ($query, string $date) => $query->whereDate('submissions.submitted_at', '>=', $date))
            ->when($filters['submitted_to'] ?? null, fn ($query, string $date) => $query->whereDate('submissions.submitted_at', '<=', $date))
            ->select([
                'submissions.id as submission_id',
                'submissions.submitted_at',
                'users.name',
                'users.campus_name',
                DB::raw('[answer].[key] as statement_id'),
                DB::raw('CONVERT(nvarchar(max), [answer].[value]) as comment'),
            ])
            ->orderByDesc('submissions.submitted_at')
            ->orderByDesc('submissions.id');

        return $query
            ->paginate($perPage, ['*'], 'comments_page', $page)
            ->withQueryString()
            ->through(fn ($row): array => [
                'id' => $row->submission_id.'-'.$row->statement_id,
                'question' => $commentStatements->get((int) $row->statement_id)?->statement ?? 'Comment',
                'comment' => $row->comment,
                'submitted_at' => $row->submitted_at,
                'user' => [
                    'name' => $row->name,
                    'campus_name' => $row->campus_name,
                ],
            ]);
    }

    private function insights(Collection $categories, int $responded, int $dismissed): array
    {
        if ($responded === 0) {
            return [[
                'tone' => 'neutral',
                'title' => 'No submitted evaluations yet',
                'message' => 'Keep the period active and monitor participation before drawing conclusions.',
            ]];
        }

        $highest = $categories->sortByDesc('average_rating')->first();
        $lowest = $categories->sortBy('average_rating')->first();
        $lowestQuestion = $categories
            ->flatMap(fn (array $category): Collection => collect($category['questions'])->map(fn (array $question): array => [
                ...$question,
                'category' => $category['name'],
            ]))
            ->filter(fn (array $question): bool => $question['responses'] > 0)
            ->sortBy('average')
            ->first();
        $insights = [];

        if ($highest) {
            $insights[] = [
                'tone' => 'positive',
                'title' => 'Strongest experience area',
                'message' => "{$highest['name']} has the highest average rating at {$highest['average_rating']} out of 5.",
            ];
        }

        if ($lowest) {
            $insights[] = [
                'tone' => $lowest['average_rating'] < 3 ? 'warning' : 'neutral',
                'title' => 'Priority improvement area',
                'message' => "{$lowest['name']} has the lowest average rating at {$lowest['average_rating']} out of 5.",
            ];
        }

        if ($lowestQuestion) {
            $insights[] = [
                'tone' => $lowestQuestion['average'] < 3 ? 'warning' : 'neutral',
                'title' => 'Lowest-rated item',
                'message' => "\"{$lowestQuestion['statement']}\" averaged {$lowestQuestion['average']} out of 5 under {$lowestQuestion['category']}.",
            ];
        }

        if ($dismissed > $responded) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Participation opportunity',
                'message' => "{$dismissed} users skipped the evaluation compared with {$responded} completed responses. Consider a shorter message or a later reminder.",
            ];
        }

        return $insights;
    }
}
