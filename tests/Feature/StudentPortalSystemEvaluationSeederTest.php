<?php

use Database\Seeders\StudentPortalSystemEvaluationSeeder;

function studentPortalSeederData(string $method): array
{
    $reflection = new ReflectionMethod(StudentPortalSystemEvaluationSeeder::class, $method);

    return $reflection->invoke(new StudentPortalSystemEvaluationSeeder);
}

test('student portal system evaluation defines complete sections and questions', function () {
    $sections = studentPortalSeederData('sections');

    expect($sections)
        ->toHaveCount(4)
        ->and(collect($sections)->sum(fn (array $section): int => count($section['questions'])))
        ->toBe(16)
        ->and(array_column($sections, 'name'))
        ->toBe([
            'Usability and Accessibility',
            'Performance and Reliability',
            'Information and Features',
            'Security and Overall Satisfaction',
        ]);
});

test('student portal system evaluation defines its scale ranges and feedback', function () {
    $ratingScale = studentPortalSeederData('ratingScale');
    $ranges = studentPortalSeederData('interpretationRanges');
    $feedback = studentPortalSeederData('feedbackQuestions');

    expect($ratingScale)
        ->toHaveCount(5)
        ->and(array_column($ratingScale, 'value'))->toBe([1, 2, 3, 4, 5])
        ->and($ranges)->toHaveCount(5)
        ->and($ranges[0]['min'])->toBe(4)
        ->and($ranges[4]['max'])->toBe(20)
        ->and($feedback)->toHaveCount(2);
});
