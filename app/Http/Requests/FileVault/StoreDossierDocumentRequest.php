<?php

namespace App\Http\Requests\FileVault;

use App\Models\DossierDocument;
use App\Services\FileVault\FileSignatureValidatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDossierDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DossierDocument::class);
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:100'],
            'document_code' => ['nullable', 'string', 'max:100'],
            'is_required' => ['sometimes', 'boolean'],
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
                'mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if ($file === null) {
                return;
            }

            $signatureValidator = app(FileSignatureValidatorService::class);
            if (! $signatureValidator->isValid($file)) {
                $validator->errors()->add('file', 'File signature does not match the declared file type.');
            }
        });
    }
}
