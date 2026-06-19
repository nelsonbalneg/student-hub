<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteEvaluationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('evaluation.templates.create')
            || $this->user()->can('evaluation.templates.update')
            || $this->user()->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'evaluation_template_id' => ['required', 'integer', 'exists:evaluation_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'max_skips' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['draft', 'active', 'closed'])],
        ];
    }
}
