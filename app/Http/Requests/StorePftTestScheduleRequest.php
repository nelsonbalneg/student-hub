<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePftTestScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pft.permission.manage');
    }

    public function rules(): array
    {
        return [
            'site_academic_term_id' => ['required', 'integer', 'exists:site_academic_terms,id'],
            'pre_opens_on' => ['required', 'date_format:Y-m-d'],
            'pre_closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:pre_opens_on'],
            // Strictly after: boundary dates are inclusive, so sharing a day counts as an overlap.
            'post_opens_on' => ['required', 'date_format:Y-m-d', 'after:pre_closes_on'],
            'post_closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:post_opens_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_academic_term_id.required' => 'Select a campus and academic year & term.',
            'site_academic_term_id.exists' => 'The selected academic term no longer exists.',
            'pre_closes_on.after_or_equal' => 'The Pre-test closing date must be on or after its opening date.',
            'post_opens_on.after' => 'The Post-test must open after the Pre-test closes. Pre-test and Post-test periods cannot overlap, including boundary dates.',
            'post_closes_on.after_or_equal' => 'The Post-test closing date must be on or after its opening date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_academic_term_id' => 'academic term',
            'pre_opens_on' => 'Pre-test opening date',
            'pre_closes_on' => 'Pre-test closing date',
            'post_opens_on' => 'Post-test opening date',
            'post_closes_on' => 'Post-test closing date',
        ];
    }
}
