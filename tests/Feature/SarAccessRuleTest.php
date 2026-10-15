<?php

use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\SiteSettings\SarSettingController;
use App\Http\Requests\StoreSarAccessRuleRequest;
use App\Models\SarAccessRule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use App\Services\AcademicApiService;
use App\Services\SarAccessService;
use App\Services\SiteSettingService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '41', 'status' => 'Active']);
    $this->term = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2026-2027', 'semester' => '1st Semester', 'term_id' => '501', 'status' => 'Active']);
    $this->student = User::factory()->create(['campus_id' => '41']);
    $this->academic = Mockery::mock(AcademicApiService::class);
    $this->academic->shouldReceive('getActiveSemesterForUser')->andReturn(['termId' => '501', 'error' => null]);
    $this->settings = Mockery::mock(SiteSettingService::class);
    $this->settings->shouldReceive('all')->andReturn(['sar_enabled' => '1']);
    $this->access = new SarAccessService($this->settings, $this->academic);
});

test('SAR is closed without a matching enabled campus and term rule', function () {
    expect($this->access->allows($this->student))->toBeFalse();
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    expect($this->access->allows($this->student))->toBeFalse();
    $rule->update(['is_enabled' => true]);
    expect($this->access->allows($this->student))->toBeTrue();
});

test('SAR does not use another campus or term access rule', function () {
    $otherCampus = SiteCampus::create(['campus_name' => 'Other Campus', 'real_campus_id' => '42']);
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $otherCampus->id, 'school_year' => '2026-2027', 'semester' => '1st Semester', 'term_id' => '501']);
    SarAccessRule::create(['site_academic_term_id' => $otherTerm->id, 'is_enabled' => true]);
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2025-2026', 'semester' => '1st Semester', 'term_id' => '500']);
    SarAccessRule::create(['site_academic_term_id' => $previous->id, 'is_enabled' => true]);
    expect($this->access->allows($this->student))->toBeFalse();
});

test('the SAR master switch closes enabled access rules', function () {
    SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $settings = Mockery::mock(SiteSettingService::class);
    $settings->shouldReceive('all')->andReturn(['sar_enabled' => '0']);
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldNotReceive('getActiveSemesterForUser');
    expect((new SarAccessService($settings, $api))->allows($this->student))->toBeFalse();
});

test('SAR closes when academic term context cannot be resolved', function () {
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('getActiveSemesterForUser')->andReturn(['termId' => null, 'error' => 'Unavailable']);
    expect((new SarAccessService($this->settings, $api))->allows($this->student))->toBeFalse();
});

test('SAR rule validation rejects a term from a different campus', function () {
    $other = SiteCampus::create(['campus_name' => 'Other Campus', 'real_campus_id' => '42']);
    $request = StoreSarAccessRuleRequest::create('/rules', 'POST', ['site_campus_id' => $other->id]);
    $validator = Validator::make(['site_campus_id' => $other->id, 'site_academic_term_id' => $this->term->id, 'is_enabled' => true], $request->rules());
    expect($validator->fails())->toBeTrue()->and($validator->errors()->has('site_academic_term_id'))->toBeTrue();
});

test('saving SAR access twice updates the existing term rule', function () {
    $controller = new SarSettingController($this->settings);
    foreach ([true, false] as $enabled) {
        $request = Mockery::mock(StoreSarAccessRuleRequest::class);
        $request->shouldReceive('validated')->andReturn(['site_campus_id' => $this->campus->id, 'site_academic_term_id' => $this->term->id, 'is_enabled' => $enabled]);
        $request->shouldReceive('user')->andReturn($this->student);
        $controller->storeRule($request);
    }
    expect(SarAccessRule::count())->toBe(1)->and(SarAccessRule::first()->is_enabled)->toBeFalse();
});

test('closed SAR access rejects all direct registration endpoints', function (string $method) {
    $request = Request::create('/student-academic-registration');
    $request->setUserResolver(fn () => $this->student);
    $controller = new EnrollmentController($this->academic, $this->access);
    try {
        $controller->{$method}($request);
        $this->fail('Closed SAR access must be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
})->with(['confirm', 'status', 'submitConfirmation']);

test('SAR settings request requires administrative permission', function () {
    $request = StoreSarAccessRuleRequest::create('/rules', 'POST');
    $request->setUserResolver(fn () => $this->student);
    expect($request->authorize())->toBeFalse();
});

test('active SAR rules must be deactivated before deletion', function () {
    Gate::define('site_settings.manage', fn () => true);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $request = Request::create('/rules/'.$rule->id, 'DELETE');
    $request->setUserResolver(fn () => $this->student);
    $controller = new SarSettingController($this->settings);
    try {
        $controller->destroyRule($request, $rule);
        $this->fail('Active SAR rules must not be deleted.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }
    $this->assertModelExists($rule);
    $rule->update(['is_enabled' => false]);
    $controller->destroyRule($request, $rule);
    $this->assertModelMissing($rule);
    expect($this->access->allows($this->student))->toBeFalse();
});

test('deleting SAR rules requires settings permission', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/rules/'.$rule->id, 'DELETE');
    $request->setUserResolver(fn () => $this->student);
    try {
        (new SarSettingController($this->settings))->destroyRule($request, $rule);
        $this->fail('An unauthorized user must not delete rules.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    $this->assertModelExists($rule);
});

test('editing SAR campus and term updates the same rule', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $otherCampus = SiteCampus::create(['campus_name' => 'Other Campus', 'real_campus_id' => '42']);
    $target = SiteAcademicTerm::create(['site_campus_id' => $otherCampus->id, 'school_year' => '2026-2027', 'semester' => '1st Semester', 'term_id' => '502']);
    $request = Mockery::mock(StoreSarAccessRuleRequest::class);
    $request->shouldReceive('validated')->andReturn(['site_campus_id' => $otherCampus->id, 'site_academic_term_id' => $target->id, 'is_enabled' => true]);
    $request->shouldReceive('user')->andReturn($this->student);
    (new SarSettingController($this->settings))->updateRule($request, $rule);
    expect(SarAccessRule::count())->toBe(1)
        ->and($rule->fresh()->site_academic_term_id)->toBe($target->id)
        ->and($this->access->allows($this->student))->toBeFalse();
});

test('SAR edit validation accepts its own term and rejects another rule term', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2026-2027', 'semester' => '2nd Semester', 'term_id' => '502']);
    SarAccessRule::create(['site_academic_term_id' => $otherTerm->id, 'is_enabled' => false]);
    $request = StoreSarAccessRuleRequest::create('/rules/'.$rule->id, 'PUT', ['site_campus_id' => $this->campus->id]);
    $route = new Route('PUT', 'rules/{rule}', []);
    $route->bind($request);
    $route->setParameter('rule', $rule);
    $request->setRouteResolver(fn () => $route);
    $data = ['site_campus_id' => $this->campus->id, 'site_academic_term_id' => $this->term->id, 'is_enabled' => true];
    expect(Validator::make($data, $request->rules())->passes())->toBeTrue();
    $data['site_academic_term_id'] = $otherTerm->id;
    expect(Validator::make($data, $request->rules())->errors()->has('site_academic_term_id'))->toBeTrue();
});

test('SAR status supplies table context before submission', function () {
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('studentNumberFor')->andReturn('S100');
    $api->shouldReceive('getActiveSemesterForUser')->andReturn(['termId' => '501', 'semester' => '2026-2027, 1st Semester']);
    $api->shouldReceive('sarTrialProgramByStudentTerm')->with('S100', '501', '41')->andReturn(['ok' => true, 'data' => null]);
    $access = Mockery::mock(SarAccessService::class);
    $access->shouldReceive('allows')->andReturn(true);
    $this->student->campus_name = 'Test Campus';
    $request = Request::create('/student-academic-registration/status');
    $request->setUserResolver(fn () => $this->student);
    $response = (new EnrollmentController($api, $access))->status($request);
    $data = $response->getData(true)['data'];
    expect($data['campusName'])->toBe('Test Campus')
        ->and($data['term'])->toBe('2026-2027, 1st Semester')
        ->and($data['studentNo'])->toBe('S100')
        ->and($data['submitted'])->toBeFalse()
        ->and($data['updatedAt'])->toBeNull();
});

test('SAR date updated comes from the submitted record', function () {
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('studentNumberFor')->andReturn('S100');
    $api->shouldReceive('getActiveSemesterForUser')->andReturn(['termId' => '501']);
    $api->shouldReceive('sarTrialProgramByStudentTerm')->andReturn(['ok' => true, 'data' => ['id' => 99, 'submitted' => true, 'status' => 'Submitted', 'transactionType' => 'Regular', 'dateUpdated' => '2026-10-01 10:30:00']]);
    $access = Mockery::mock(SarAccessService::class);
    $access->shouldReceive('allows')->andReturn(true);
    $request = Request::create('/student-academic-registration/status');
    $request->setUserResolver(fn () => $this->student);
    $data = (new EnrollmentController($api, $access))->status($request)->getData(true)['data'];
    expect($data['updatedAt'])->toBe('2026-10-01 10:30:00')
        ->and($data['submitted'])->toBeTrue()
        ->and($data['sarId'])->toBe(99);
});

test('SAR lookup failure is not treated as an unsubmitted record', function () {
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('studentNumberFor')->andReturn('S100');
    $api->shouldReceive('getActiveSemesterForUser')->andReturn(['termId' => '501']);
    $api->shouldReceive('sarTrialProgramByStudentTerm')->andReturn(['ok' => false, 'data' => null]);
    $access = Mockery::mock(SarAccessService::class);
    $access->shouldReceive('allows')->andReturn(true);
    $request = Request::create('/student-academic-registration/status');
    $request->setUserResolver(fn () => $this->student);
    expect((new EnrollmentController($api, $access))->status($request)->getStatusCode())->toBe(503);
});

test('each campus term has an independent SAR row and closed submitted terms remain viewable', function () {
    SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2025-2026', 'semester' => '1st Semester', 'term_id' => '500']);
    SarAccessRule::create(['site_academic_term_id' => $previous->id, 'is_enabled' => false]);
    $otherCampus = SiteCampus::create(['campus_name' => 'Other Campus', 'real_campus_id' => '42']);
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $otherCampus->id, 'school_year' => '2026-2027', 'semester' => '1st Semester', 'term_id' => '501']);
    SarAccessRule::create(['site_academic_term_id' => $otherTerm->id, 'is_enabled' => true]);
    $this->academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $this->academic->shouldReceive('sarTrialProgramByStudentTerm')->with('S100', '501', '41')->andReturn(['ok' => true, 'data' => null]);
    $this->academic->shouldReceive('sarTrialProgramByStudentTerm')->with('S100', '500', '41')->andReturn(['ok' => true, 'data' => ['submitted' => true, 'status' => 'Submitted', 'id' => 55]]);
    $request = Request::create('/student-academic-registration/records');
    $request->setUserResolver(fn () => $this->student);
    $rows = collect((new EnrollmentController($this->academic, $this->access))->records($request)->getData(true)['data'])->keyBy('termId');
    expect($rows)->toHaveCount(2)
        ->and($rows['501']['can_apply'])->toBeTrue()
        ->and($rows['500']['can_apply'])->toBeFalse()
        ->and($rows['500']['submitted'])->toBeTrue()
        ->and($rows['500']['sarId'])->toBe(55);
});

test('failed SAR verification affects only the corresponding term row', function () {
    SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => true]);
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2026-2027', 'semester' => '2nd Semester', 'term_id' => '502']);
    SarAccessRule::create(['site_academic_term_id' => $otherTerm->id, 'is_enabled' => true]);
    $this->academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $this->academic->shouldReceive('sarTrialProgramByStudentTerm')->with('S100', '501', '41')->andReturn(['ok' => false, 'data' => null]);
    $this->academic->shouldReceive('sarTrialProgramByStudentTerm')->with('S100', '502', '41')->andReturn(['ok' => true, 'data' => null]);
    $request = Request::create('/records');
    $request->setUserResolver(fn () => $this->student);
    $rows = collect((new EnrollmentController($this->academic, $this->access))->records($request)->getData(true)['data'])->keyBy('termId');
    expect($rows['501']['can_apply'])->toBeFalse()
        ->and($rows['501']['verification_error'])->not->toBeNull()
        ->and($rows['502']['can_apply'])->toBeTrue();
});

test('selected SAR term is checked separately from the current academic term', function () {
    SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2025-2026', 'semester' => '1st Semester', 'term_id' => '500']);
    SarAccessRule::create(['site_academic_term_id' => $previous->id, 'is_enabled' => true]);
    expect($this->access->allows($this->student, '500'))->toBeTrue()
        ->and($this->access->allows($this->student, '501'))->toBeFalse()
        ->and($this->access->allows($this->student, '999'))->toBeFalse();
    $request = Request::create('/confirm', 'POST', ['term_id' => '501']);
    $request->setUserResolver(fn () => $this->student);
    try {
        (new EnrollmentController($this->academic, $this->access))->submitConfirmation($request);
        $this->fail('Closed selected term should be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

test('SAR submission sends the selected term instead of replacing it with the active term', function (bool $override) {
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'school_year' => '2025-2026', 'semester' => '1st Semester', 'term_id' => '500']);
    $rule = SarAccessRule::create(['site_academic_term_id' => $previous->id, 'is_enabled' => ! $override]);
    if ($override) {
        $rule->overrideUsers()->attach($this->student->id);
    }
    $this->student->tenant_id = '1';
    config()->set('services.academic_jwt.secret', 'test-secret-for-sar-selected-term');
    config()->set('services.academic_jwt.algorithm', 'HS256');
    $jwt = JWT::encode(['studentNo' => 'S100', 'campusId' => '41', 'termId' => '501', 'exp' => time() + 300], 'test-secret-for-sar-selected-term', 'HS256');
    $this->academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $this->academic->shouldReceive('generateSarToken')->andReturn(['ok' => true, 'token' => base64_encode($jwt)]);
    $this->academic->shouldReceive('submitSarConfirmation')->once()->with(Mockery::on(fn (array $payload) => $payload['termId'] === '500' && $payload['term'] === '2025-2026, 1st Semester'), 'S100', '41')->andReturn(['ok' => true, 'message' => 'added']);
    $request = Request::create('/confirm', 'POST', ['term_id' => '500', 'transactionType' => 'Regular']);
    $request->setUserResolver(fn () => $this->student);
    expect((new EnrollmentController($this->academic, $this->access))->submitConfirmation($request)->getStatusCode())->toBe(200);
})->with([false, true]);

test('student override opens only the selected closed SAR term', function () {
    $this->student->student_no = 'S100';
    $this->student->save();
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    expect($this->access->allows($this->student, '501'))->toBeFalse();
    $rule->overrideUsers()->attach($this->student->id, ['granted_by' => $this->student->id]);
    expect($this->access->allows($this->student, '501'))->toBeTrue()
        ->and($this->access->allows($this->student, '500'))->toBeFalse()
        ->and($this->access->rulesFor($this->student)->first()->has_user_override)->toBeTrue();
    $rule->overrideUsers()->detach($this->student->id);
    expect($this->access->allows($this->student, '501'))->toBeFalse();
});

test('master SAR switch still closes student overrides', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $rule->overrideUsers()->attach($this->student->id);
    $settings = Mockery::mock(SiteSettingService::class);
    $settings->shouldReceive('all')->andReturn(['sar_enabled' => '0']);
    expect((new SarAccessService($settings, $this->academic))->allows($this->student, '501'))->toBeFalse();
});

test('overridden student sees Apply on the closed SAR row', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $rule->overrideUsers()->attach($this->student->id);
    $this->academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $this->academic->shouldReceive('sarTrialProgramByStudentTerm')->andReturn(['ok' => true, 'data' => null]);
    $request = Request::create('/records');
    $request->setUserResolver(fn () => $this->student);
    $row = (new EnrollmentController($this->academic, $this->access))->records($request)->getData(true)['data'][0];
    expect($row['can_apply'])->toBeTrue()->and($row['registration_open'])->toBeTrue();
});

test('override search is paginated across all user accounts', function () {
    Gate::define('site_settings.manage', fn () => true);
    User::factory()->count(25)->create(['campus_id' => '41', 'name' => 'Search Student', 'student_no' => 'S123']);
    User::factory()->create(['campus_id' => '42', 'name' => 'Search Other', 'student_no' => null]);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/students', 'GET', ['q' => 'Search']);
    $request->setUserResolver(fn () => $this->student);
    $data = (new SarSettingController($this->settings))->searchOverrideStudents($request, $rule)->getData(true);
    expect($data['data'])->toHaveCount(20)->and($data['next_page'])->toBe(2);
    expect(collect($data['data'])->contains(fn ($user) => $user['name'] === 'Search Other'))->toBeTrue();
});

test('administrators can grant multiple student overrides and revoke them', function () {
    Gate::define('site_settings.manage', fn () => true);
    $users = User::factory()->count(2)->create(['campus_id' => '41', 'student_no' => 'S123']);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $controller = new SarSettingController($this->settings);
    $request = Request::create('/overrides', 'POST', ['add_user_ids' => $users->pluck('id')->all(), 'remove_user_ids' => []]);
    $request->setUserResolver(fn () => $this->student);
    $controller->saveOverrides($request, $rule);
    expect($rule->overrideUsers()->count())->toBe(2);
    $remove = Request::create('/overrides', 'POST', ['add_user_ids' => [], 'remove_user_ids' => [$users[0]->id]]);
    $remove->setUserResolver(fn () => $this->student);
    $controller->saveOverrides($remove, $rule);
    expect($rule->overrideUsers()->count())->toBe(1);
});

test('overrides can select user accounts from other campuses', function () {
    Gate::define('site_settings.manage', fn () => true);
    $other = User::factory()->create(['campus_id' => '42', 'student_no' => 'S123']);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/overrides', 'POST', ['add_user_ids' => [$other->id], 'remove_user_ids' => []]);
    $request->setUserResolver(fn () => $this->student);
    (new SarSettingController($this->settings))->saveOverrides($request, $rule);
    expect($rule->overrideUsers()->whereKey($other->id)->exists())->toBeTrue();
});

test('override endpoints require settings management permission', function () {
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/students', 'GET', ['q' => 'Student']);
    $request->setUserResolver(fn () => $this->student);
    foreach (['searchOverrideStudents', 'saveOverrides'] as $method) {
        try {
            (new SarSettingController($this->settings))->{$method}($request, $rule);
            $this->fail('Unauthorized override access must be rejected.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }
    }
});

test('override lookup searches name email and student number without a term or campus restriction', function (string $search) {
    Gate::define('site_settings.manage', fn () => true);
    $user = User::factory()->create(['name' => 'Nelson Balneg', 'email' => 'nelson.balneg@example.test', 'student_no' => '23-68057', 'campus_id' => null]);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/students', 'GET', ['q' => $search]);
    $request->setUserResolver(fn () => $this->student);
    $data = (new SarSettingController($this->settings))->searchOverrideStudents($request, $rule)->getData(true);
    expect(collect($data['data'])->pluck('id')->all())->toContain($user->id);
})->with(['Nelson', 'nelson.balneg@example.test', '23-68057']);

test('override search finds a surname inside a formatted full name', function () {
    Gate::define('site_settings.manage', fn () => true);
    $user = User::factory()->create(['name' => 'Henry, Jr. M. Lee', 'email' => 'henry@example.test', 'student_no' => null]);
    $rule = SarAccessRule::create(['site_academic_term_id' => $this->term->id, 'is_enabled' => false]);
    $request = Request::create('/students', 'GET', ['q' => 'lee']);
    $request->setUserResolver(fn () => $this->student);
    $result = (new SarSettingController($this->settings))->searchOverrideStudents($request, $rule)->getData(true);
    expect(collect($result['data'])->pluck('id')->all())->toContain($user->id);
});
