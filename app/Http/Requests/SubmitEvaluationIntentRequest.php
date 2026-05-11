<?php

namespace App\Http\Requests;

use App\Models\EvaluationRequest;
use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EvaluationRequest::class);
    }

    public function rules(): array
    {
        return [
            'evaluation_period_id' => ['required', 'exists:evaluation_periods,id'],
            'intent' => ['required', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
