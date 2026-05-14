<?php

namespace App\Http\Requests\FileVault;

use App\Models\StudentDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionStudentDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dossier = $this->route('studentDossier');

        return $dossier instanceof StudentDossier && $this->user()->can('transition', $dossier);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(StudentDossier::statuses())],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'recipient_or_requestor' => ['nullable', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'legitimate_interest' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status');
            $remarks = $this->input('remarks');
            $dossier = $this->route('studentDossier');

            if (! $dossier instanceof StudentDossier || ! is_string($status)) {
                return;
            }

            $remarksRequiredStatuses = [
                StudentDossier::STATUS_INCOMPLETE,
                StudentDossier::STATUS_ON_HOLD,
                StudentDossier::STATUS_FOR_SUPERVISOR_APPROVAL,
                StudentDossier::STATUS_RELEASED,
                StudentDossier::STATUS_ARCHIVED,
            ];

            if (
                in_array($status, $remarksRequiredStatuses, true)
                || ($status === StudentDossier::STATUS_FOR_INTAKE_REVIEW && $dossier->status === StudentDossier::STATUS_INCOMPLETE)
            ) {
                if (blank($remarks)) {
                    $validator->errors()->add('remarks', 'Remarks are required for this transition.');
                }
            }

            if ($status === StudentDossier::STATUS_RELEASED) {
                if (blank($this->input('recipient_or_requestor'))) {
                    $validator->errors()->add('recipient_or_requestor', 'Recipient or requestor is required when releasing a dossier.');
                }

                if (blank($this->input('legal_basis'))) {
                    $validator->errors()->add('legal_basis', 'Legal basis is required when releasing a dossier.');
                }

                if (blank($this->input('legitimate_interest'))) {
                    $validator->errors()->add('legitimate_interest', 'Legitimate interest is required when releasing a dossier.');
                }
            }
        });
    }
}
