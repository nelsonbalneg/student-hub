<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftDataPrivacyConsent;
use App\Models\PftHealthQuestionnaire;
use App\Models\PftParq;
use App\Models\PftTestSchedule;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\StudentPftResult;
use App\Models\User;
use App\Services\AcademicApiService;
use App\Services\PftInterpretationService;
use App\Services\PhysicalFitnessPermissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('schedule payload normalizes SQL Server term IDs and calendar dates', function () {
    $schedule = new PftTestSchedule;
    $schedule->setRawAttributes(['site_academic_term_id' => '4', 'phase' => 'pre', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    expect($schedule->toArray())->toMatchArray([
        'site_academic_term_id' => 4,
        'opens_on' => '2026-10-13',
        'closes_on' => '2026-10-14',
    ]);
});

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-13 12:00:00', 'Asia/Singapore'));
    $campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '1', 'campus_id' => '1', 'tenant_id' => '1', 'status' => 'Active']);
    $this->term = SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'test', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    $this->student = User::factory()->create(['campus_id' => '1']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($this->student);
});

test('schedule manager saves both phases and rejects overlap', function () {
    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);
    $payload = ['site_academic_term_id' => $this->term->id, 'pre_opens_on' => '2026-10-13', 'pre_closes_on' => '2026-10-14', 'post_opens_on' => '2026-10-15', 'post_closes_on' => '2026-10-16'];
    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('pft_test_schedules', 2);
    $payload['post_opens_on'] = '2026-10-14';
    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), $payload)->assertSessionHasErrors('post_opens_on');
});

test('schedule changes require management permission', function () {
    Gate::define('pft.configuration.view', fn () => true);
    Gate::define('pft.permission.manage', fn () => false);
    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), [])->assertForbidden();
});

function prepareScheduledPftSubmission($test): PftTestType
{
    Gate::define('pft.submit', fn () => true);
    \Pest\Laravel\mock(PhysicalFitnessPermissionService::class, fn ($mock) => $mock->shouldReceive('canFillUp')->andReturnTrue());
    \Pest\Laravel\mock(AcademicApiService::class, function ($mock) {
        $mock->shouldReceive('studentNumberFor')->andReturn('');
        $mock->shouldReceive('registrationFromAllForStudentTerm')->andReturn(['data' => [], 'error' => null]);
    });
    \Pest\Laravel\mock(PftInterpretationService::class, fn ($mock) => $mock->shouldReceive('interpret')->andReturnNull());
    PftDataPrivacyConsent::create(['user_id' => $test->student->id, 'term_id' => 'test', 'accepted_at' => now()]);
    PftHealthQuestionnaire::create(['user_id' => $test->student->id, 'term_id' => 'test']);
    PftParq::create(['user_id' => $test->student->id, 'term_id' => 'test', 'clearance_status' => 'verified']);
    $component = PftComponent::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
    $category = PftCategory::create(['pft_component_id' => $component->id, 'name' => 'Test', 'slug' => 'test', 'is_active' => true]);

    return PftTestType::create(['pft_category_id' => $category->id, 'name' => 'Test', 'slug' => 'test', 'is_active' => true]);
}

test('submissions respect opening and closing dates', function (string $opens, string $closes, bool $allowed) {
    $type = prepareScheduledPftSubmission($this);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'pre', 'opens_on' => $opens, 'closes_on' => $closes]);
    $response = $this->post(route('student-profile.physical-fitness.store', $type), ['term_id' => 'test', 'test_phase' => 'pre', 'results' => ['score' => 1]]);
    if ($allowed) {
        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('student_pft_results', 1);
    } else {
        $response->assertSessionHasErrors('test_phase');
        $this->assertDatabaseCount('student_pft_results', 0);
    }
})->with([
    ['2026-10-13', '2026-10-14', true],
    ['2026-10-12', '2026-10-13', true],
    ['2026-10-14', '2026-10-15', false],
    ['2026-10-11', '2026-10-12', false],
]);

test('pre and post results are stored separately for the same term and test', function () {
    $type = prepareScheduledPftSubmission($this);
    PftHealthQuestionnaire::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post']);
    PftParq::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post', 'clearance_status' => 'verified']);
    foreach (['pre' => '2026-10-13', 'post' => '2026-10-14'] as $phase => $date) {
        PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => $phase, 'opens_on' => $date, 'closes_on' => $date]);
        $this->travelTo(Carbon::parse($date.' 12:00:00', 'Asia/Singapore'));
        $this->post(route('student-profile.physical-fitness.store', $type), ['term_id' => 'test', 'test_phase' => $phase, 'results' => ['score' => 1]])->assertSessionHasNoErrors();
    }
    expect(StudentPftResult::pluck('test_phase')->sort()->values()->all())->toBe(['post', 'pre']);
});

function schedulePayload(SiteAcademicTerm $term, array $overrides = []): array
{
    return [
        'site_academic_term_id' => $term->id,
        'pre_opens_on' => '2026-10-13',
        'pre_closes_on' => '2026-10-14',
        'post_opens_on' => '2026-10-15',
        'post_closes_on' => '2026-10-16',
        ...$overrides,
    ];
}

test('schedule status is resolved from the campus-local date with inclusive boundaries', function (?array $window, string $today, string $expected) {
    $schedule = $window ? new PftTestSchedule(['opens_on' => $window[0], 'closes_on' => $window[1]]) : null;

    expect(PftTestSchedule::statusFor($schedule, $today))->toBe($expected);
})->with([
    'not scheduled' => [null, '2026-10-13', PftTestSchedule::STATUS_NOT_SCHEDULED],
    'upcoming' => [['2026-10-14', '2026-10-20'], '2026-10-13', PftTestSchedule::STATUS_UPCOMING],
    'open on opening day' => [['2026-10-13', '2026-10-20'], '2026-10-13', PftTestSchedule::STATUS_OPEN],
    'open on closing day' => [['2026-10-01', '2026-10-13'], '2026-10-13', PftTestSchedule::STATUS_OPEN],
    'closed' => [['2026-10-01', '2026-10-12'], '2026-10-13', PftTestSchedule::STATUS_CLOSED],
]);

test('campus-local today uses the campus timezone rather than UTC', function () {
    // 23:30 UTC on Oct 13 is already Oct 14 in Asia/Singapore.
    $this->travelTo(Carbon::parse('2026-10-13 23:30:00', 'UTC'));

    expect(PftTestSchedule::localToday())->toBe('2026-10-14');
});

test('overlapping or touching pre-test and post-test periods are rejected', function (array $overrides) {
    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);

    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), schedulePayload($this->term, $overrides))
        ->assertSessionHasErrors('post_opens_on');
    $this->assertDatabaseCount('pft_test_schedules', 0);
})->with([
    'post opens on pre closing day' => [['post_opens_on' => '2026-10-14']],
    'post opens inside pre window' => [['post_opens_on' => '2026-10-13']],
    'post entirely before pre' => [['post_opens_on' => '2026-10-01', 'post_closes_on' => '2026-10-05']],
]);

test('closing dates must not precede opening dates', function () {
    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);

    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), schedulePayload($this->term, [
        'pre_closes_on' => '2026-10-12',
        'post_closes_on' => '2026-10-14',
    ]))->assertSessionHasErrors(['pre_closes_on', 'post_closes_on']);
});

test('editing a schedule updates in place without duplicates and keeps submitted results', function () {
    $type = prepareScheduledPftSubmission($this);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'pre', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    $this->post(route('student-profile.physical-fitness.store', $type), ['term_id' => 'test', 'test_phase' => 'pre', 'results' => ['score' => 1]])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseCount('student_pft_results', 1);

    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);
    $route = route('site-settings.physical-fitness.configuration.test-schedules.store');

    $this->post($route, schedulePayload($this->term))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Pre-test and Post-test schedules updated for Test Campus · 2025-2026, 1st Semester.');

    $this->post($route, schedulePayload($this->term, ['pre_closes_on' => '2026-10-20', 'post_opens_on' => '2026-11-01', 'post_closes_on' => '2026-11-10']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('pft_test_schedules', 2);
    expect(PftTestSchedule::where('phase', 'pre')->sole()->closes_on->toDateString())->toBe('2026-10-20')
        ->and(PftTestSchedule::where('phase', 'post')->sole()->opens_on->toDateString())->toBe('2026-11-01');
    $this->assertDatabaseCount('student_pft_results', 1);
});

test('saving a new schedule reports a created message', function () {
    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);

    $this->post(route('site-settings.physical-fitness.configuration.test-schedules.store'), schedulePayload($this->term))
        ->assertSessionHas('success', 'Pre-test and Post-test schedules saved for Test Campus · 2025-2026, 1st Semester.');
});

test('configuration page exposes one schedule row per campus term with dynamic statuses', function () {
    Gate::define('pft.configuration.view', fn () => true);
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $this->term->site_campus_id, 'term_id' => 'other', 'school_year' => '2025-2026', 'semester' => '2nd Semester', 'status' => 'Inactive']);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'pre', 'opens_on' => '2026-10-01', 'closes_on' => '2026-10-13']);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'post', 'opens_on' => '2026-10-14', 'closes_on' => '2026-10-20']);

    $this->get(route('site-settings.physical-fitness.configuration.index', ['tab' => 'permissions']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SiteSettings/PhysicalFitness/Configuration', false)
            ->where('scheduleToday', '2026-10-13')
            ->has('scheduleRows', 2)
            ->where('scheduleRows', fn ($rows) => collect($rows)->firstWhere('site_academic_term_id', $this->term->id)['pre']['status'] === 'open'
                && collect($rows)->firstWhere('site_academic_term_id', $this->term->id)['post']['status'] === 'upcoming'
                && collect($rows)->firstWhere('site_academic_term_id', $this->term->id)['is_scheduled'] === true
                && collect($rows)->firstWhere('site_academic_term_id', $otherTerm->id)['pre']['status'] === 'not_scheduled'
                && collect($rows)->firstWhere('site_academic_term_id', $otherTerm->id)['is_scheduled'] === false));
});


test('deleting schedules removes related campus term data and clearance uploads only', function () {
    Gate::define('pft.permission.manage', fn () => true);
    Gate::define('pft.configuration.view', fn () => true);
    \Illuminate\Support\Facades\Storage::fake('public');
    $type = prepareScheduledPftSubmission($this);
    $otherStudent = User::factory()->create(['campus_id' => '2']);
    foreach ([$this->student, $otherStudent] as $student) {
        foreach (['test', 'other'] as $termId) {
            PftHealthQuestionnaire::firstOrCreate(['user_id' => $student->id, 'term_id' => $termId]);
            PftParq::firstOrCreate(['user_id' => $student->id, 'term_id' => $termId]);
            PftDataPrivacyConsent::firstOrCreate(['user_id' => $student->id, 'term_id' => $termId], ['accepted_at' => now()]);
            foreach (['pre', 'post'] as $phase) {
                StudentPftResult::create(['user_id' => $student->id, 'term_id' => $termId, 'campus_id' => $student->campus_id, 'test_phase' => $phase, 'pft_test_type_id' => $type->id, 'results_json' => ['score' => 1]]);
            }
        }
    }
    PftHealthQuestionnaire::where('user_id', $this->student->id)->where('term_id', 'test')->update(['medical_clearance_path' => 'clearances/health.pdf']);
    PftParq::where('user_id', $this->student->id)->where('term_id', 'test')->update(['medical_clearance_path' => 'clearances/parq.pdf']);
    foreach (['health', 'parq', 'unrelated'] as $name) {
        \Illuminate\Support\Facades\Storage::disk('public')->put("clearances/{$name}.pdf", 'test');
    }
    $otherTerm = SiteAcademicTerm::create(['site_campus_id' => $this->term->site_campus_id, 'term_id' => 'other', 'school_year' => '2025-2026', 'semester' => '2nd Semester']);
    foreach ([$this->term, $otherTerm] as $term) {
        foreach (['pre', 'post'] as $phase) {
            PftTestSchedule::create(['site_academic_term_id' => $term->id, 'phase' => $phase, 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
        }
    }
    $this->delete(route('site-settings.physical-fitness.configuration.test-schedules.destroy', $this->term))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseCount('student_pft_results', 6);
    foreach ([PftHealthQuestionnaire::class, PftParq::class, PftDataPrivacyConsent::class] as $model) {
        expect($model::count())->toBe(3);
        expect($model::where('user_id', $this->student->id)->where('term_id', 'test')->exists())->toBeFalse();
    }
    $this->assertDatabaseCount('pft_test_schedules', 2);
    $this->assertModelExists($this->term);
    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing(['clearances/health.pdf', 'clearances/parq.pdf']);
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists('clearances/unrelated.pdf');
});

test('schedule deletion requires management permission', function () {
    Gate::define('pft.configuration.view', fn () => true);
    Gate::define('pft.permission.manage', fn () => false);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'pre', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    $this->delete(route('site-settings.physical-fitness.configuration.test-schedules.destroy', $this->term))->assertForbidden();
    $this->assertDatabaseCount('pft_test_schedules', 1);
});

test('schedule deletion refuses unlinked terms without deleting data', function () {
    Gate::define('pft.configuration.view', fn () => true);
    Gate::define('pft.permission.manage', fn () => true);
    $this->term->update(['term_id' => null]);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'pre', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    $this->delete(route('site-settings.physical-fitness.configuration.test-schedules.destroy', $this->term))->assertSessionHasErrors('schedule');
    $this->assertDatabaseCount('pft_test_schedules', 1);
});


test('post-test requires its own health and PAR-Q submissions for the same term', function () {
    $type = prepareScheduledPftSubmission($this);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'post', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    $payload = ['term_id' => 'test', 'test_phase' => 'post', 'results' => ['score' => 1]];
    $route = route('student-profile.physical-fitness.store', $type);
    $this->post($route, $payload)->assertSessionHasErrors('term_id');
    PftHealthQuestionnaire::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post']);
    $this->post($route, $payload)->assertSessionHasErrors('term_id');
    PftParq::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post', 'clearance_status' => 'verified']);
    $this->post($route, $payload)->assertSessionHasNoErrors();
    expect(PftHealthQuestionnaire::where('user_id', $this->student->id)->count())->toBe(2);
    expect(PftParq::where('user_id', $this->student->id)->count())->toBe(2);
    $this->assertDatabaseHas('student_pft_results', ['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post']);
});

test('pre-test clearance does not grant post-test health clearance', function () {
    $type = prepareScheduledPftSubmission($this);
    PftTestSchedule::create(['site_academic_term_id' => $this->term->id, 'phase' => 'post', 'opens_on' => '2026-10-13', 'closes_on' => '2026-10-14']);
    PftHealthQuestionnaire::where('user_id', $this->student->id)->update(['has_medical_condition' => true, 'clearance_granted' => true]);
    $health = PftHealthQuestionnaire::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post', 'has_medical_condition' => true, 'clearance_granted' => false]);
    PftParq::create(['user_id' => $this->student->id, 'term_id' => 'test', 'test_phase' => 'post', 'clearance_status' => 'verified']);
    $route = route('student-profile.physical-fitness.store', $type);
    $payload = ['term_id' => 'test', 'test_phase' => 'post', 'results' => ['score' => 1]];
    $this->post($route, $payload)->assertSessionHasErrors('term_id');
    $health->update(['clearance_granted' => true]);
    $this->post($route, $payload)->assertSessionHasNoErrors();
});
