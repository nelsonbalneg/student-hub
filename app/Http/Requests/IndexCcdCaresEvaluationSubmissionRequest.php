<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCcdCaresEvaluationSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('evaluation.templates.view')
            || $this->user()->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'campus' => ['nullable', 'string', 'max:255'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
