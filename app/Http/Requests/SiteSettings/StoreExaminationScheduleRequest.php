<?php

namespace App\Http\Requests\SiteSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExaminationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('examination-schedule.create');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'academic_term_id' => [
                'required',
                Rule::exists('site_academic_terms', 'id')
                    ->where('site_campus_id', $this->integer('campus_id')),
            ],
            'campus_id' => ['required', 'exists:site_campuses,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:Draft,Published'],
        ];
    }
}
