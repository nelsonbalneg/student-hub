<?php

namespace App\Http\Requests\FileVault;

use App\Models\StudentDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStudentDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dossier = $this->route('studentDossier');

        return $dossier instanceof StudentDossier && $this->user()->can('assign', $dossier);
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
