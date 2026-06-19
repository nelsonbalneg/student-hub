<?php

use Database\Seeders\Dass21EvaluationTemplateSeeder;

function seederData(string $method): array
{
    $reflection = new ReflectionMethod(Dass21EvaluationTemplateSeeder::class, $method);

    return $reflection->invoke(new Dass21EvaluationTemplateSeeder);
}

test('the DASS-21 seeder contains every original item and category mapping', function () {
    $statements = seederData('statements');
    $categoryItems = seederData('categoryItems');

    expect($statements)
        ->toHaveCount(21)
        ->toHaveKeys(range(1, 21))
        ->and($categoryItems['Depression'])->toBe([3, 5, 10, 13, 16, 17, 21])
        ->and($categoryItems['Anxiety'])->toBe([2, 4, 7, 9, 15, 19, 20])
        ->and($categoryItems['Stress'])->toBe([1, 6, 8, 11, 12, 14, 18]);
});

test('the DASS-21 seeder defines the complete scale and interpretation ranges', function () {
    $ratingScale = seederData('ratingScale');
    $ranges = seederData('interpretationRanges');

    expect($ratingScale)
        ->toHaveCount(4)
        ->and(array_column($ratingScale, 'value'))->toBe([0, 1, 2, 3])
        ->and($ranges)->toHaveKeys(['Depression', 'Anxiety', 'Stress'])
        ->and($ranges['Depression'])->toHaveCount(5)
        ->and($ranges['Anxiety'])->toHaveCount(5)
        ->and($ranges['Stress'])->toHaveCount(5);
});
