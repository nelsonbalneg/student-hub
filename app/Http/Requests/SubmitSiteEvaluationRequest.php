<?php

namespace App\Http\Requests;

use App\Models\SiteEvaluationPeriod;
use App\Models\SiteEvaluationSubmission;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSiteEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SiteEvaluationPeriod::query()
            ->currentlyOpen()
            ->whereKey($this->integer('period_id'))
            ->exists()
            && ! SiteEvaluationSubmission::query()
                ->where('site_evaluation_period_id', $this->integer('period_id'))
                ->where('user_id', $this->user()->id)
                ->exists();
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:site_evaluation_periods,id'],
            'answers' => ['required', 'array'],
        ];
    }
}
