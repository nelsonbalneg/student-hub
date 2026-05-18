<?php

namespace App\Http\Requests\Society;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequirementReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('society.review');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['complete', 'incomplete', 'returned', 'not_applicable'])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
