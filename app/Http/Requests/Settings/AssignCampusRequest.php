<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (blank($user->tenant_id) || blank($user->campus_id) || blank($user->office_id));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campus_record_id' => ['required', 'integer', 'min:1'],
            'office_id' => ['required', 'integer', 'exists:offices,id'],
        ];
    }
}
