<?php

namespace App\Http\Requests\SiteSettings;

use Illuminate\Foundation\Http\FormRequest;

class ImportExaminationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('examination-schedule.import');
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // 10MB max
            ],
        ];
    }
}
