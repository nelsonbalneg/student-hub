<?php

use App\Http\Controllers\GradesController;
use App\Http\Controllers\SiteSettings\SiteGradeViewingController;
use App\Models\GradeViewingRule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\GradeViewingRuleResolver;
use App\Services\PhysicalFitnessPermissionService;
use App\Services\StudentEvaluationApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = SiteCampus::create(['campus_name' => 'Main', 'real_campus_id' => '41']);
    SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'term_id' => '501', 'school_year' => '2026-2027', 'semester' => '1st Semester', 'status' => 'Active']);
    $this->student = User::factory()->create(['campus_id' => 41, 'tenant_id' => '1']);
    $this->rule = GradeViewingRule::create(['site_campus_id' => $this->campus->id, 'rule_name' => 'Test rule', 'grade_evaluation_mode' => 'disabled_hidden', 'access_scope' => 'all_grades', 'is_active' => true, 'bypass_evaluation' => false]);
});

test('grade overrides enable evaluation while preserving grade visibility policy', function (string $mode) {
    $this->rule->update(['grade_evaluation_mode' => $mode]);
    $this->rule->overrideUsers()->attach($this->student->id);
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 41]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [['termId' => '501', 'grades' => [['courseId' => '11', 'final' => 1.5]]]], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldReceive('findStudentByStudentNo')->once()->andReturn('E1');
    $evaluation->shouldReceive('getStudentEvaluationDetails')->andReturn([]);
    $evaluation->shouldReceive('buildEvaluationLookup')->andReturn(['501-11' => ['requires_evaluation' => true, 'status' => 'Pending', 'evaluation_period_id' => '1', 'subject_for_evaluation_id' => '1', 'subject_id' => '11', 'subject_title' => 'Subject', 'pending_evaluations' => [['id' => '1']], 'evaluated_evaluations' => [], 'faculty_names' => [], 'evaluation_payload' => []]]);
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, app(GetActiveAcademicTermService::class), new GradeComputationService, $fitness);
    $request = Request::create('/grades');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $this->student);
    $props = $controller($request)->toResponse($request)->getData(true)['props'];
    expect($props['student']['evaluation_user_override'])->toBeTrue()
        ->and($props['gradeReport']['data'][0]['grades'][0]['pending_evaluations'])->toHaveCount(1)
        ->and($props['gradeReport']['data'][0]['grades'][0]['final'])->toBe(in_array($mode, ['disabled_hidden', 'disabled_evaluated'], true) ? 'LOCKED' : 1.5);
})->with(['disabled_hidden', 'disabled_visible', 'disabled_evaluated']);

test('grade override picker searches users by partial name email and student number', function () {
    Gate::define('site_settings.manage', fn () => true);
    $user = User::factory()->create(['name' => 'Henry, Jr. M. Lee', 'email' => 'henry@example.test', 'student_no' => '23-68057']);
    foreach (['lee', 'henry@example.test', '68057'] as $search) {
        $request = Request::create('/students', 'GET', ['q' => $search]);
        $request->setUserResolver(fn () => $this->student);
        $data = app(SiteGradeViewingController::class)->searchOverrideStudents($request, $this->rule)->getData(true);
        expect(collect($data['data'])->pluck('id')->all())->toContain($user->id);
    }
});

test('grade user overrides can be granted and revoked independently of SAR', function () {
    Gate::define('site_settings.manage', fn () => true);
    $controller = app(SiteGradeViewingController::class);
    foreach ([true, false] as $add) {
        $request = Request::create('/overrides', 'POST', ['add_user_ids' => $add ? [$this->student->id] : [], 'remove_user_ids' => $add ? [] : [$this->student->id]]);
        $request->setUserResolver(fn () => $this->student);
        $controller->saveOverrides($request, $this->rule);
        expect($this->rule->overrideUsers()->whereKey($this->student->id)->exists())->toBe($add);
    }
    expect($this->student->sarOverrideRules()->exists())->toBeFalse();
});
test('evaluation-off evaluated policy unlocks only verified completed evaluations', function (string $state) {
    $mode = 'disabled_evaluated';
    $this->rule->update(['grade_evaluation_mode' => $mode]);
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 41]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [['termId' => '501', 'grades' => [['courseId' => '11', 'final' => 1.5]]]], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldReceive('findStudentByStudentNo')->once()->andReturn('E1');
    $evaluation->shouldReceive('getStudentEvaluationDetails')->andReturn([]);
    $evaluation->shouldReceive('buildEvaluationLookup')->andReturn($state === 'missing' ? [] : ['501-11' => ['requires_evaluation' => $state !== 'evaluated', 'status' => $state === 'evaluated' ? 'Evaluated' : 'Pending', 'evaluation_period_id' => '1', 'subject_for_evaluation_id' => '1', 'subject_id' => '11', 'subject_title' => 'Subject', 'pending_evaluations' => [['id' => '1']], 'evaluated_evaluations' => $state === 'evaluated' ? [['id' => '1']] : [], 'faculty_names' => [], 'evaluation_payload' => []]]);
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, app(GetActiveAcademicTermService::class), new GradeComputationService, $fitness);
    $request = Request::create('/grades');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $this->student);
    $props = $controller($request)->toResponse($request)->getData(true)['props'];
    expect($props['student']['evaluation_user_override'])->toBeFalse()
        ->and($props['gradeReport']['data'][0]['grades'][0]['final'])->toBe($state === 'evaluated' ? 1.5 : 'LOCKED');
})->with(['evaluated', 'pending', 'missing']);

test('grades apply a selected previous-term policy only to its term id', function () {
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'term_id' => '500', 'school_year' => '2025-2026', 'semester' => '1st Semester']);
    $this->rule->update(['access_scope' => 'specific_term', 'site_academic_term_id' => $previous->id]);
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 41]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [
        ['termId' => '500', 'grades' => [['final' => 1.5]]],
        ['termId' => '501', 'grades' => [['final' => 1.25]]],
    ], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldNotReceive('findStudentByStudentNo');
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, app(GetActiveAcademicTermService::class), new GradeComputationService, $fitness);
    $request = Request::create('/grades');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $this->student);
    $props = $controller($request)->toResponse($request)->getData(true)['props'];
    expect($props['student']['policy_term_id'])->toBe('500')
        ->and($props['gradeReport']['data'][0]['grades'][0]['final'])->toBe('LOCKED')
        ->and($props['gradeReport']['data'][1]['grades'][0]['final'])->toBe(1.25);
});

test('grade rule selected term must belong to the selected campus', function () {
    $other = SiteCampus::create(['campus_name' => 'Other', 'real_campus_id' => '42']);
    $term = SiteAcademicTerm::create(['site_campus_id' => $other->id, 'term_id' => '500', 'school_year' => '2025-2026', 'semester' => '1st Semester']);
    $request = Request::create('/rules', 'POST', ['site_campus_id' => $this->campus->id, 'rule_name' => 'Test', 'grade_evaluation_mode' => 'disabled_hidden', 'access_scope' => 'specific_term', 'site_academic_term_id' => $term->id, 'bypass_evaluation' => false, 'show_gwa_gpa' => true, 'is_active' => true]);
    expect(fn () => app(SiteGradeViewingController::class)->store($request))->toThrow(ValidationException::class);
});

test('historical term actions show evaluation status without locking their grades', function () {
    $this->rule->update(['access_scope' => 'current_term', 'grade_evaluation_mode' => 'disabled_evaluated']);
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 41]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [
        ['termId' => '501', 'grades' => [['courseId' => 11, 'final' => 1.5]]],
        ['termId' => '500', 'grades' => [['courseId' => 11, 'final' => 1.25]]],
        ['termId' => '499', 'grades' => [['courseId' => 11, 'final' => 1.75]]],
    ], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldReceive('findStudentByStudentNo')->andReturn('E1');
    $evaluation->shouldReceive('getStudentEvaluationDetails')->andReturn([]);
    $evaluation->shouldReceive('buildEvaluationLookup')->andReturn([
        '500-11' => ['requires_evaluation' => false, 'status' => 'Evaluated', 'evaluated_evaluations' => [['id' => 1]]],
        '499-11' => ['requires_evaluation' => true, 'status' => 'Not Evaluated', 'evaluated_evaluations' => []],
    ]);
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, app(GetActiveAcademicTermService::class), new GradeComputationService, $fitness);
    $request = Request::create('/grades');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $this->student);
    $terms = $controller($request)->toResponse($request)->getData(true)['props']['gradeReport']['data'];
    expect($terms[0]['grades'][0]['final'])->toBe('LOCKED')
        ->and($terms[1]['grades'][0]['final'])->toBe(1.25)
        ->and($terms[1]['grades'][0]['evaluation_completed'])->toBeTrue()
        ->and($terms[2]['grades'][0]['final'])->toBe(1.75)
        ->and($terms[2]['grades'][0]['evaluation_completed'])->toBeFalse();
});

test('two active grade rules apply independently to current and previous terms', function () {
    $this->rule->update(['access_scope' => 'current_term', 'grade_evaluation_mode' => 'disabled_hidden']);
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'term_id' => '500', 'school_year' => '2025-2026', 'semester' => '1st Semester']);
    $pastRule = GradeViewingRule::create(['site_campus_id' => $this->campus->id, 'rule_name' => 'Past grades visible', 'access_scope' => 'specific_term', 'site_academic_term_id' => $previous->id, 'grade_evaluation_mode' => 'disabled_visible', 'is_active' => true, 'bypass_evaluation' => false, 'show_gwa_gpa' => false]);
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S100');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 41]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [
        ['termId' => '501', 'grades' => [['final' => 1.5]]],
        ['termId' => '500', 'grades' => [['final' => 1.25]]],
        ['termId' => '499', 'grades' => [['final' => 1.75]]],
    ], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldNotReceive('findStudentByStudentNo');
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, app(GetActiveAcademicTermService::class), new GradeComputationService, $fitness);
    $request = Request::create('/grades');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $this->student);
    $terms = $controller($request)->toResponse($request)->getData(true)['props']['gradeReport']['data'];
    expect($terms[0]['grades'][0]['final'])->toBe('LOCKED')
        ->and($terms[0]['grades'][0]['applied_rule_id'])->toBe($this->rule->id)
        ->and($terms[1]['grades'][0]['final'])->toBe(1.25)
        ->and($terms[1]['grades'][0]['applied_rule_id'])->toBe($pastRule->id)
        ->and($terms[1]['show_gwa_gpa'])->toBeFalse()
        ->and($terms[2]['grades'][0]['final'])->toBe(1.75);
});

test('term-specific rules take priority and inactive rules are ignored', function () {
    $previous = SiteAcademicTerm::create(['site_campus_id' => $this->campus->id, 'term_id' => '500', 'school_year' => '2025-2026', 'semester' => '1st Semester']);
    $specific = GradeViewingRule::create(['site_campus_id' => $this->campus->id, 'rule_name' => 'Specific', 'access_scope' => 'specific_term', 'site_academic_term_id' => $previous->id, 'is_active' => true]);
    $newBroad = GradeViewingRule::create(['site_campus_id' => $this->campus->id, 'rule_name' => 'Broad', 'access_scope' => 'all_grades', 'is_active' => true]);
    $current = GradeViewingRule::create(['site_campus_id' => $this->campus->id, 'rule_name' => 'Current', 'access_scope' => 'current_term', 'is_active' => true]);
    $rules = GradeViewingRule::with('selectedAcademicTerm')->get();
    $resolver = new GradeViewingRuleResolver;
    expect($resolver->resolve($rules, '500', '501')->id)->toBe($specific->id)
        ->and($resolver->resolve($rules, '501', '501')->id)->toBe($current->id)
        ->and($resolver->resolve($rules, '499', '501')->id)->toBe($newBroad->id);
    $specific->update(['is_active' => false]);
    expect($resolver->resolve(GradeViewingRule::with('selectedAcademicTerm')->get(), '500', '501')->id)->toBe($newBroad->id);
});
