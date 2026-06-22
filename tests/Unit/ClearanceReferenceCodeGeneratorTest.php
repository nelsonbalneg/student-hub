<?php

use App\Services\ClearanceReferenceCodeGenerator;
use Illuminate\Support\Carbon;

test('it generates the expected clearance reference code format', function () {
    $generator = new class extends ClearanceReferenceCodeGenerator
    {
        protected function exists(string $referenceCode): bool
        {
            return false;
        }
    };

    $referenceCode = $generator->generate(
        Carbon::parse('2026-06-21'),
    );

    expect($referenceCode)->toMatch('/^CLR260621[A-Z]{6}\d$/');
});
