<?php

namespace App\Http\Requests;

use App\Models\CcdCaresEvaluationPeriod;
use App\Models\CcdCaresEvaluationSubmission;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCcdCaresEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $period = CcdCaresEvaluationPeriod::query()
            ->whereKey($this->integer('period_id'))
            ->first();

        return $period !== null
            && ! CcdCaresEvaluationSubmission::query()
                ->where('ccd_cares_evaluation_period_id', $period->id)
                ->where('student_id', $this->user()->id)
                ->exists();
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:ccd_cares_evaluation_periods,id'],
            'answers' => ['required', 'array'],
        ];
    }
}
