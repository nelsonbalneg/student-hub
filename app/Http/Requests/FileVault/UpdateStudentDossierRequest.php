<?php

namespace App\Http\Requests\FileVault;

use App\Models\StudentDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dossier = $this->route('studentDossier');

        return $dossier instanceof StudentDossier && $this->user()->can('update', $dossier);
    }

    public function rules(): array
    {
        return [
            'transaction_type' => ['sometimes', 'required', 'string', 'max:100'],
            'priority' => ['sometimes', 'required', Rule::in(StudentDossier::priorities())],
            'current_owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'intake_date' => ['nullable', 'date'],
            'completion_due_at' => ['nullable', 'date'],
        ];
    }
}
