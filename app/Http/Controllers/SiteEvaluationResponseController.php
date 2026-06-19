<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitSiteEvaluationRequest;
use App\Models\SiteEvaluationDismissal;
use App\Models\SiteEvaluationPeriod;
use App\Models\SiteEvaluationSubmission;
use App\Services\EvaluationTemplatePayloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteEvaluationResponseController extends Controller
{
    public function store(
        SubmitSiteEvaluationRequest $request,
        EvaluationTemplatePayloadService $templates,
    ): RedirectResponse {
        $period = SiteEvaluationPeriod::query()
            ->currentlyOpen()
            ->with('template')
            ->findOrFail($request->validated('period_id'));
        $answers = $request->validated('answers');
        $this->validateAnswers($templates->build($period->template), $answers);

        DB::transaction(fn () => SiteEvaluationSubmission::query()->create([
            'site_evaluation_period_id' => $period->id,
            'evaluation_template_id' => $period->evaluation_template_id,
            'user_id' => $request->user()->id,
            'answers_json' => $answers,
            'submitted_at' => now(),
        ]));

        return $this->redirectToPrevious($request)
            ->with('success', 'Thank you for evaluating the student portal.');
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer', 'exists:site_evaluation_periods,id'],
        ]);
        $period = SiteEvaluationPeriod::query()
            ->currentlyOpen()
            ->findOrFail($validated['period_id']);

        DB::transaction(function () use ($request, $period): void {
            $dismissal = SiteEvaluationDismissal::query()
                ->where('site_evaluation_period_id', $period->id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $dismissal) {
                SiteEvaluationDismissal::query()->create([
                    'site_evaluation_period_id' => $period->id,
                    'user_id' => $request->user()->id,
                    'skip_count' => 1,
                    'dismissed_at' => now(),
                ]);

                return;
            }

            $dismissal->update([
                'skip_count' => min($period->max_skips, $dismissal->skip_count + 1),
                'dismissed_at' => now(),
            ]);
        });

        $request->session()->put('site_evaluation_suppressed_period_id', $period->id);

        return $this->redirectToPrevious($request);
    }

    private function validateAnswers(array $template, array $answers): void
    {
        $rules = [];

        foreach ($template['categories'] as $category) {
            foreach ($category['statements'] as $statement) {
                $key = 'answers.'.$statement['id'];
                $baseRules = $statement['is_required'] ? ['required'] : ['nullable'];
                $options = collect($statement['scale_options'])
                    ->pluck('value')
                    ->merge(collect($statement['choices'])->pluck('value'))
                    ->all();

                if ($statement['statement_type'] === 'checkbox') {
                    $rules[$key] = [...$baseRules, 'array'];
                    $rules[$key.'.*'] = [Rule::in($options)];
                } elseif ($options !== []) {
                    $rules[$key] = [...$baseRules, Rule::in($options)];
                } elseif ($statement['statement_type'] === 'numeric_score') {
                    $rules[$key] = [...$baseRules, 'numeric'];
                } else {
                    $rules[$key] = [...$baseRules, 'string'];
                }
            }
        }

        $validator = Validator::make(['answers' => $answers], $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function redirectToPrevious(Request $request): RedirectResponse
    {
        return redirect()->to($request->headers->get('referer') ?: route('dashboard'));
    }
}
