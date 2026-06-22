<?php

use App\Models\Office;

test('office categories are stable', function () {
    expect([
        Office::CATEGORY_ACADEMIC,
        Office::CATEGORY_SUPPORT,
        Office::CATEGORY_ADMINISTRATION,
    ])->toBe([
        'academic',
        'support',
        'administration',
    ]);
});
