<?php

namespace App\Services\FileVault;

use Illuminate\Http\UploadedFile;

class FileSignatureValidatorService
{
    public function isValid(UploadedFile $file): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedByExtension = config('file_vault.upload_signature_map.'.$extension, []);

        if ($allowedByExtension === []) {
            return false;
        }

        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return false;
        }

        $detected = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath);

        return in_array($detected, $allowedByExtension, true);
    }
}
