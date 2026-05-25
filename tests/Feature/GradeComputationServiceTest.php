<?php

use App\Services\GradeComputationService;

it('excludes configured subjects and computes weighted semester and overall averages', function () {
    config()->set('grades.gpa_excluded_keywords', ['NSTP', 'THESIS', 'CAPSTONE', 'OJT', 'PRACTICUM']);

    $result = app(GradeComputationService::class)->computeForTerms([
        [
            'term' => '2025-2026 2nd Semester',
            'grades' => [
                ['courseCode' => 'GE 03', 'courseTitle' => 'The Contemporary World', 'finalGrade' => '1.00', 'units' => '3', 'remarks' => 'PASSED'],
                ['courseCode' => 'CS THS', 'courseTitle' => 'CS Thesis Writing 02', 'finalGrade' => '1.00', 'units' => '3', 'remarks' => 'PASSED'],
                ['courseCode' => 'NSTP 02', 'courseTitle' => 'National Service Training Program', 'finalGrade' => '1.25', 'units' => '3', 'remarks' => 'PASSED'],
                ['courseCode' => 'OJT 01', 'courseTitle' => 'Practicum', 'finalGrade' => '1.25', 'units' => '6', 'remarks' => 'PASSED'],
                ['courseCode' => 'CS 21A', 'courseTitle' => 'Free and Open-Source Software', 'finalGrade' => '1.50', 'units' => '2', 'remarks' => 'PASSED'],
                ['courseCode' => 'CS 22A', 'courseTitle' => 'Numerical Methods', 'finalGrade' => '5.00', 'units' => '3', 'remarks' => 'FAILED'],
                ['courseCode' => 'CS IP', 'courseTitle' => 'In Progress Subject', 'finalGrade' => 'IP', 'units' => '3', 'remarks' => 'IP'],
                ['courseCode' => 'CS INC', 'courseTitle' => 'Incomplete Subject', 'finalGrade' => 'INC', 'units' => '3', 'remarks' => 'INC'],
            ],
        ],
        [
            'term' => '2025-2026 1st Semester',
            'grades' => [
                ['courseCode' => 'CS 20', 'courseTitle' => 'Software Engineering', 'finalGrade' => '2.00', 'units' => '3', 'remarks' => 'PASSED'],
                ['courseCode' => 'CAP 01', 'courseTitle' => 'Capstone Project', 'finalGrade' => '1.00', 'units' => '3', 'remarks' => 'PASSED'],
            ],
        ],
    ]);

    expect($result['terms'][0]['computed_gpa_display'])->toBe('1.2000')
        ->and($result['terms'][0]['computed_counted_units'])->toBe(5.0)
        ->and($result['terms'][0]['computed_included_subject_count'])->toBe(2)
        ->and($result['terms'][0]['computed_excluded_subject_count'])->toBe(6)
        ->and($result['terms'][1]['computed_gpa_display'])->toBe('2.0000')
        ->and($result['overall']['average_gwa_display'])->toBe('1.5000')
        ->and($result['overall']['counted_units'])->toBe(8.0);
});

it('uses mathematical rounding to four decimal places', function () {
    $result = app(GradeComputationService::class)->computeForTerms([
        [
            'term' => 'Rounding',
            'grades' => [
                ['courseCode' => 'MATH 01', 'courseTitle' => 'Calculus', 'finalGrade' => '1.31445', 'units' => '1', 'remarks' => 'PASSED'],
            ],
        ],
    ]);

    expect($result['terms'][0]['computed_gpa_display'])->toBe('1.3145')
        ->and($result['overall']['average_gwa_display'])->toBe('1.3145');
});

it('returns zeroes when no valid subjects are available', function () {
    $result = app(GradeComputationService::class)->computeForTerms([
        [
            'term' => 'No Valid Grades',
            'grades' => [
                ['courseCode' => 'CS 01', 'courseTitle' => 'Draft Grade', 'finalGrade' => null, 'units' => '3', 'remarks' => null],
                ['courseCode' => 'CS 02', 'courseTitle' => 'Dropped Grade', 'finalGrade' => 'DRP', 'units' => '3', 'remarks' => 'DRP'],
                ['courseCode' => 'NSTP 01', 'courseTitle' => 'NSTP', 'finalGrade' => '1.00', 'units' => '3', 'remarks' => 'PASSED'],
            ],
        ],
    ]);

    expect($result['terms'][0]['computed_gpa_display'])->toBe('0.0000')
        ->and($result['terms'][0]['computed_counted_units'])->toEqual(0.0)
        ->and($result['overall']['average_gwa_display'])->toBe('0.0000')
        ->and($result['overall']['counted_units'])->toEqual(0.0);
});
