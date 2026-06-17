<?php

use Illuminate\Support\Facades\Route;

test('physical fitness analytics requires view permission', function () {
    expect(Route::getRoutes()
        ->getByName('student-profile.physical-fitness.analytics')
        ?->gatherMiddleware())
        ->toContain('can:pft.view');
});
