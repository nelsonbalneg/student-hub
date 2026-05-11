<?php

namespace App\Http\Requests;

use App\Models\EvaluationPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evaluationPeriod'));
    }

    public function rules(): array
    {
        $period = $this->route('evaluationPeriod');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'academic_year' => ['required', 'string', 'max:50'],
            'semester' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => [
                'required',
                Rule::in(['draft', 'active', 'closed', 'archived']),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('status') !== 'active') {
                return;
            }

            $period = $this->route('evaluationPeriod');

            $hasActivePeriod = EvaluationPeriod::query()
                ->where('academic_year', $this->input('academic_year'))
                ->where('semester', $this->input('semester'))
                ->where('status', EvaluationPeriod::STATUS_ACTIVE)
                ->whereKeyNot($period?->id)
                ->exists();

            if ($hasActivePeriod) {
                $validator->errors()->add('status', 'Only one active evaluation period is allowed per semester.');
            }
        });
    }
}
