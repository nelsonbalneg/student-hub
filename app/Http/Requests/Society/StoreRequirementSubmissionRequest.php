<?php

namespace App\Http\Requests\Society;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequirementSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('society.submit_requirements');
    }

    public function rules(): array
    {
        return [
            'requirement_id' => ['required', 'exists:society_accreditation_requirements,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
