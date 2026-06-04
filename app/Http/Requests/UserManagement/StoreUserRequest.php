<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'email_verified_at' => ['nullable', 'date'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'user_type' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'sso_id' => ['nullable', 'string', 'max:255'],
            'sso_uuid' => ['nullable', 'string', 'max:255'],
            'sso_username' => ['nullable', 'string', 'max:255'],
            'sso_account_type' => ['nullable', 'string', 'max:255'],
            'sso_avatar' => ['nullable', 'string', 'max:2048'],
            'tenant_id' => ['nullable', 'integer'],
            'campus_id' => ['nullable', 'integer'],
            'campus_name' => ['nullable', 'string', 'max:255'],
            'student_no' => ['nullable', 'string', 'max:255'],
            'employee_no' => ['nullable', 'string', 'max:255'],
            'two_factor_confirmed_at' => ['nullable', 'date'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
