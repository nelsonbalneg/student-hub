<?php

namespace App\Http\Requests\FileVault;

use App\Models\StudentDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StudentDossier::class);
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'transaction_type' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in(StudentDossier::priorities())],
            'current_owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'intake_date' => ['nullable', 'date'],
            'completion_due_at' => ['nullable', 'date'],
        ];
    }
}
