<?php

use App\Models\User;
use App\Services\AcademicApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.academic_api.base_url', 'https://academic.example/api/v2');
    config()->set('services.academic_api.timeout', 15);
    config()->set('services.academic_api.connect_timeout', 5);
});

test('it loads active semesters from the configured academic API', function () {
    Http::fake([
        'https://academic.example/api/v2/ActiveSemesters/campus/12/active-only' => Http::response([
            'id' => 7,
            'name' => 'First Semester',
        ]),
    ]);

    $result = app(AcademicApiService::class)->activeSemestersForCampus(12);

    expect($result)->toBe([
        'data' => [
            [
                'id' => 7,
                'name' => 'First Semester',
            ],
        ],
        'error' => null,
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://academic.example/api/v2/ActiveSemesters/campus/12/active-only');
});

test('it resolves the preferred student number for API requests', function () {
    $user = new User([
        'student_no' => ' 2024-001 ',
        'sso_username' => 'fallback',
    ]);

    expect(app(AcademicApiService::class)->studentNumberFor($user))->toBe('2024-001');
});

test('it resolves active semester details for a user', function () {
    Http::fake([
        'https://academic.example/api/v2/ActiveSemesters/campus/9/active-only' => Http::response([
            [
                'termId' => 102,
                'term' => 'First Semester 2026',
            ],
        ]),
    ]);

    $user = new User([
        'campus_id' => 9,
    ]);

    $result = app(AcademicApiService::class)->getActiveSemesterForUser($user);

    expect($result['semester'])->toBe('First Semester 2026')
        ->and($result['termId'])->toBe('102')
        ->and($result['campusId'])->toBe(9)
        ->and($result['error'])->toBeNull();
});
