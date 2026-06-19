<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitCcdCaresEvaluationRequest;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\CcdCaresEvaluationSubmission;
use App\Services\EvaluationTemplatePayloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CcdCaresEvaluationSubmissionController extends Controller
{
    public function __invoke(
        SubmitCcdCaresEvaluationRequest $request,
        EvaluationTemplatePayloadService $templates,
    ): RedirectResponse {
        $period = CcdCaresEvaluationPeriod::query()
            ->with('template')
            ->whereKey($request->validated('period_id'))
            ->firstOrFail();

        $isOpen = $period->status === CcdCaresEvaluationPeriod::STATUS_ACTIVE
            && $period->start_date->lte(today())
            && $period->end_date->gte(today());

        if (! $isOpen) {
            return back()->with('error', 'This evaluation period is closed and no longer accepts submissions.');
        }

        $template = $templates->build($period->template);
        $answers = $request->validated('answers');

        $this->validateAnswers($template, $answers);

        DB::transaction(fn () => CcdCaresEvaluationSubmission::query()->create([
            'ccd_cares_evaluation_period_id' => $period->id,
            'evaluation_template_id' => $period->evaluation_template_id,
            'student_id' => $request->user()->id,
            'answers_json' => $answers,
            'submitted_at' => now(),
        ]));

        return to_route('student-profile.index', ['ccd' => 1])
            ->with('success', 'CCD Cares evaluation submitted.');
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
}
