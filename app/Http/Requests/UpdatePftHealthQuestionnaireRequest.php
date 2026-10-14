<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePftHealthQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reporting.pft_result.view');
    }

    public function rules(): array
    {
        return [
            'civil_status' => ['nullable', 'string', 'max:255'],
            'household_monthly_income' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'smoking_status' => ['nullable', 'string', 'max:255'],
            'alcohol_consumption' => ['nullable', 'string', 'max:255'],
            'has_medical_condition' => ['required', 'boolean'],
            'has_medication' => ['required', 'boolean'],
            'specific_conditions' => ['nullable', 'array'],
            'specific_conditions.*' => ['string', 'max:255'],
            'other_condition' => ['nullable', 'string', 'max:255'],
            'medical_condition_details' => ['nullable', 'string', 'max:1000'],
            'medication_details' => ['nullable', 'string', 'max:1000'],
            'clearance_granted' => ['required', 'boolean'],
        ];
    }
}
