<?php

use App\Services\StudentEvaluationApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.evaluation_api.base_url', 'https://evaluation.example/api/app');
    config()->set('services.evaluation_api.timeout', 15);
    config()->set('services.evaluation_api.connect_timeout', 5);
    config()->set('services.evaluation_api.verify_ssl', false);
});

test('it resolves the student evaluation id from the campus and tenant scoped portal token', function () {
    $token = base64_encode(json_encode([
        'studentId' => '43f63268-11e7-8895-e2a3-3a0fcdb7789e',
        'exp' => '2026-05-26T23:59:59',
    ]));

    Http::fake([
        'https://evaluation.example/api/app/student/student-portal-token*' => Http::response($token),
    ]);

    $studentId = app(StudentEvaluationApiService::class)
        ->findStudentByStudentNo('23-68057', 1, 1);

    expect($studentId)->toBe('43f63268-11e7-8895-e2a3-3a0fcdb7789e');

    Http::assertSent(fn ($request) => $request->url() === 'https://evaluation.example/api/app/student/student-portal-token?campusId=1&studentNo=23-68057&tenantId=1');
});

test('it does not call the evaluation API when campus or tenant context is missing', function () {
    Http::fake();

    $studentId = app(StudentEvaluationApiService::class)
        ->findStudentByStudentNo('23-68057', null, 1);

    expect($studentId)->toBeNull();

    Http::assertNothingSent();
});

test('it returns null when the portal token is not valid base64', function () {
    Http::fake([
        'https://evaluation.example/api/app/student/student-portal-token*' => Http::response('not base64!'),
    ]);

    $studentId = app(StudentEvaluationApiService::class)
        ->findStudentByStudentNo('23-68057', 1, 1);

    expect($studentId)->toBeNull();
});

test('it returns null when the portal token JSON does not contain a student id', function () {
    Http::fake([
        'https://evaluation.example/api/app/student/student-portal-token*' => Http::response(base64_encode(json_encode([
            'exp' => '2026-05-26T23:59:59',
        ]))),
    ]);

    $studentId = app(StudentEvaluationApiService::class)
        ->findStudentByStudentNo('23-68057', 1, 1);

    expect($studentId)->toBeNull();
});
