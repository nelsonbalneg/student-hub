<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('feedback', $this->route('evaluationRequest'));
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'visibility' => ['required', Rule::in(['internal', 'student_visible'])],
            'status' => ['nullable', Rule::in(['needs_action', 'resolved'])],
        ];
    }
}
