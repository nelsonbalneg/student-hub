<?php

namespace App\Http\Requests\Society;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAccreditationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('society.review')
            || $this->user()?->can('society.approve')
            || $this->user()?->can('society.reject')
            || $this->user()?->can('society.return');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['under_review', 'returned', 'approved', 'rejected'])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
