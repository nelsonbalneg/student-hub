<?php

namespace App\Http\Requests\Society;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccreditationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('society.apply_accreditation') || $this->user()?->can('society.create');
    }

    public function rules(): array
    {
        return [
            'semester' => ['required', 'string', 'max:80'],
            'school_year' => ['required', 'string', 'max:20'],
            'mode_of_submission' => ['required', Rule::in(['online', 'onsite'])],
            'submitted_by_name' => ['required', 'string', 'max:255'],
            'submitted_by_position' => ['required', 'string', 'max:120'],
            'submitted_by_signature' => ['nullable', 'string', 'max:255'],
        ];
    }
}
