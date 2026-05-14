<?php

namespace App\Http\Requests\FileVault;

use App\Models\DossierDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyDossierDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof DossierDocument && $this->user()->can('verify', $document);
    }

    public function rules(): array
    {
        return [
            'is_verified' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var DossierDocument|null $document */
            $document = $this->route('document');

            if (! $document instanceof DossierDocument) {
                return;
            }

            if ((bool) $this->boolean('is_verified') && $document->scan_status !== 'clean') {
                $validator->errors()->add('is_verified', 'Document can only be verified after a clean malware scan.');
            }
        });
    }
}
