<?php

namespace App\Http\Requests\Society;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocietyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('society.create') || $this->user()?->can('society.update') || $this->user()?->can('society.apply_accreditation');
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:120'],
            'college_unit' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'facebook_page_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
