<?php

use App\Enums\GradeAccessScope;
use App\Enums\GradeEvaluationMode;
use App\Http\Controllers\GradesController;
use App\Http\Controllers\SiteSettings\SiteGradeViewingController;
use App\Models\GradeViewingRule;
use App\Models\User;
use App\Services\AcademicApiService;
use App\Services\GetActiveAcademicTermService;
use App\Services\GradeComputationService;
use App\Services\PhysicalFitnessPermissionService;
use App\Services\StudentEvaluationApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('grade access follows the evaluation mode and bypass override', function (string $mode, bool $evaluationEnabled, bool $pendingLocked, bool $completedLocked) {
    $policy = GradeEvaluationMode::from($mode);

    expect($policy->evaluationEnabled(false))->toBe($evaluationEnabled)
        ->and($policy->gradesLocked(false, true))->toBe($pendingLocked)
        ->and($policy->gradesLocked(false, false))->toBe($completedLocked)
        ->and($policy->gradesLocked(true, true))->toBeFalse()
        ->and($policy->gradesLocked(true, false))->toBeFalse()
        ->and($policy->evaluationEnabled(true))->toBeFalse();
})->with([
    'evaluation off, grades visible' => ['disabled_visible', false, false, false],
    'evaluation off, grades visible only after evaluation' => ['disabled_evaluated', false, true, false],
    'evaluation off, grades hidden' => ['disabled_hidden', false, true, true],
    'evaluation on, completion required' => ['enabled_required', true, true, false],
    'evaluation on, grades visible' => ['enabled_optional', true, false, false],
]);

test('grade policy scope targets only its configured terms', function (string $scope, int|string|null $termId, int|string|null $activeTermId, bool $included) {
    expect(GradeAccessScope::from($scope)->includes($termId, $activeTermId))->toBe($included);
})->with([
    'all grades includes previous terms' => ['all_grades', 10, 20, true],
    'all grades works without an active term' => ['all_grades', 10, null, true],
    'current term matches term IDs across API types' => ['current_term', '20', 20, true],
    'current term excludes earlier terms' => ['current_term', 10, 20, false],
    'current term requires configured active term' => ['current_term', 10, null, false],
    'current term excludes missing row term' => ['current_term', null, 20, false],
]);

test('scope and evaluation policy together control grade locking', function (string $mode) {
    $policy = GradeEvaluationMode::from($mode);
    $all = GradeAccessScope::AllGrades;
    $current = GradeAccessScope::CurrentTerm;

    expect($current->includes(10, 20) && $policy->gradesLocked(false, true))->toBeFalse()
        ->and($all->includes(10, 20) && $policy->gradesLocked(false, true))->toBe(in_array($mode, ['disabled_hidden', 'enabled_required'], true))
        ->and($all->includes(20, 20) && $policy->gradesLocked(true, true))->toBeFalse();
})->with(['disabled_visible', 'disabled_hidden', 'enabled_required', 'enabled_optional']);

test('active grade access rules cannot be deleted', function () {
    $rule = Mockery::mock(GradeViewingRule::class)->makePartial();
    $rule->is_active = true;
    $rule->shouldNotReceive('delete');

    try {
        app(SiteGradeViewingController::class)
            ->destroy(Request::create('/rules/1', 'DELETE'), $rule);
        $this->fail('Active rule deletion should be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getMessage())->toBe('Deactivate this rule before deleting it.');
    }
});

test('inactive grade access rules can be deleted with an audit record', function () {
    $rule = Mockery::mock(GradeViewingRule::class)->makePartial();
    $rule->id = 5;
    $rule->is_active = false;
    $rule->shouldReceive('delete')->once()->andReturn(true);
    Auth::shouldReceive('id')->once()->andReturn(1);
    $logs = Mockery::mock('alias:App\\Models\\GradeViewingLog');
    $logs->shouldReceive('create')->once()->with(Mockery::on(fn (array $data): bool => $data['rule_id'] === null && $data['action'] === 'deleted' && $data['changes']['id'] === 5
    ));

    $response = app(SiteGradeViewingController::class)
        ->destroy(Request::create('/rules/5', 'DELETE'), $rule);

    expect($response->getTargetUrl())->toBe(route('site-settings.grade-viewing.index'));
});

test('grades are hidden across all terms without an applicable campus rule', function () {
    $academic = Mockery::mock(AcademicApiService::class);
    $academic->shouldReceive('studentNumberFor')->andReturn('S1');
    $academic->shouldReceive('getActiveSemesterForUser')->andReturn(['campusId' => 1]);
    $academic->shouldReceive('gradeReportForStudent')->andReturn(['data' => [
        ['termId' => 10, 'grades' => [['midTerm' => 1.5, 'final' => 1.25, 'reExam' => 1.0]]],
        ['termId' => 20, 'grades' => [['midTerm' => 2.0, 'final' => 1.75, 'reExam' => 1.5]]],
    ], 'error' => null]);
    $evaluation = Mockery::mock(StudentEvaluationApiService::class);
    $evaluation->shouldNotReceive('findStudentByStudentNo');
    $terms = Mockery::mock(GetActiveAcademicTermService::class);
    $terms->shouldReceive('execute')->with(null)->andReturn(null);
    $fitness = Mockery::mock(PhysicalFitnessPermissionService::class);
    $fitness->shouldReceive('gradesShortcutEnabled')->andReturn(false);
    $controller = new GradesController($academic, $evaluation, $terms, new GradeComputationService, $fitness);
    $user = new User(['name' => 'Student', 'tenant_id' => '1']);
    $request = Request::create('/grades');
    $request->setUserResolver(fn () => $user);
    $request->headers->set('X-Inertia', 'true');
    $data = $controller($request)->toResponse($request)->getData(true)['props'];

    expect($data['student']['bypass_evaluation'])->toBeFalse()
        ->and($data['student']['show_gwa_gpa'])->toBeFalse()
        ->and($data['student']['access_scope'])->toBe('all_grades')
        ->and($data['student']['grade_evaluation_mode'])->toBe('disabled_hidden');
    foreach ($data['gradeReport']['data'] as $term) {
        expect($term['grades'][0]['final'])->toBe('LOCKED')
            ->and($term['grades'][0]['midTerm'])->toBe('LOCKED')
            ->and($term['grades'][0]['reExam'])->toBe('LOCKED');
    }
});

test('evaluation-off evaluated policy verifies completion without enabling the button', function () {
    $mode = GradeEvaluationMode::DisabledEvaluated;
    expect($mode->evaluationEnabled(false))->toBeFalse()
        ->and($mode->verificationRequired(false))->toBeTrue()
        ->and($mode->verificationRequired(true))->toBeFalse()
        ->and($mode->gradesLocked(false, true))->toBeTrue()
        ->and($mode->gradesLocked(false, false))->toBeFalse()
        ->and($mode->gradesLocked(true, true))->toBeFalse();
});

test('specific grade term can target a previous term without affecting the current term', function () {
    $scope = GradeAccessScope::SpecificTerm;
    expect($scope->includes('500', '501', 500))->toBeTrue()
        ->and($scope->includes('501', '501', 500))->toBeFalse()
        ->and($scope->includes('499', '501', 500))->toBeFalse()
        ->and($scope->includes('500', null, 500))->toBeTrue()
        ->and($scope->includes('500', '501', null))->toBeFalse();
});
