<?php

use App\Http\Controllers\CcdCaresEvaluationSubmissionController;
use App\Http\Controllers\SiteSettings\CcdCaresEvaluationController;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\CcdCaresEvaluationSubmission;
use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationScoringRule;
use App\Models\EvaluationStatement;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('ccd cares evaluation routes are registered', function () {
    expect(Route::getRoutes()->getByName('site-settings.ccd-cares.index')?->getActionName())
        ->toBe(CcdCaresEvaluationController::class.'@index')
        ->and(Route::getRoutes()->getByName('site-settings.ccd-cares.periods.store')?->methods())
        ->toContain('POST')
        ->and(Route::getRoutes()->getByName('site-settings.ccd-cares.periods.update')?->methods())
        ->toContain('PATCH')
        ->and(Route::getRoutes()->getByName('site-settings.ccd-cares.periods.destroy')?->methods())
        ->toContain('DELETE')
        ->and(Route::getRoutes()->getByName('site-settings.ccd-cares.periods.submissions')?->methods())
        ->toContain('GET')
        ->and(Route::getRoutes()->getByName('student-profile.ccd-cares.evaluation.store')?->getActionName())
        ->toBe(CcdCaresEvaluationSubmissionController::class);
});

test('ccd cares evaluation models expose their relationships', function () {
    $period = new CcdCaresEvaluationPeriod;
    $submission = new CcdCaresEvaluationSubmission;

    expect($period->template())->toBeInstanceOf(BelongsTo::class)
        ->and($period->creator())->toBeInstanceOf(BelongsTo::class)
        ->and($period->submissions())->toBeInstanceOf(HasMany::class)
        ->and($submission->period())->toBeInstanceOf(BelongsTo::class)
        ->and($submission->template())->toBeInstanceOf(BelongsTo::class)
        ->and($submission->student())->toBeInstanceOf(BelongsTo::class);
});

test('student cannot submit closed evaluation period due to past date', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('student-profile.view');

    $template = EvaluationTemplate::forceCreate([
        'name' => 'CCD Cares Template',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $period = CcdCaresEvaluationPeriod::forceCreate([
        'evaluation_template_id' => $template->id,
        'title' => 'Closed Evaluation',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from(route('student-profile.index'))
        ->post(route('student-profile.ccd-cares.evaluation.store'), [
            'period_id' => $period->id,
            'answers' => ['dummy' => 'value'],
        ]);

    $response->assertSessionHas('error', 'This evaluation period is closed and no longer accepts submissions.');
    $response->assertRedirect(route('student-profile.index'));
    expect(CcdCaresEvaluationSubmission::count())->toBe(0);
});

test('student cannot submit draft evaluation period', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('student-profile.view');

    $template = EvaluationTemplate::forceCreate([
        'name' => 'CCD Cares Template',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $period = CcdCaresEvaluationPeriod::forceCreate([
        'evaluation_template_id' => $template->id,
        'title' => 'Draft Evaluation',
        'start_date' => now()->subDays(1)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from(route('student-profile.index'))
        ->post(route('student-profile.ccd-cares.evaluation.store'), [
            'period_id' => $period->id,
            'answers' => ['dummy' => 'value'],
        ]);

    $response->assertSessionHas('error', 'This evaluation period is closed and no longer accepts submissions.');
    $response->assertRedirect(route('student-profile.index'));
    expect(CcdCaresEvaluationSubmission::count())->toBe(0);
});

test('student can submit open evaluation period', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('student-profile.view');

    $template = EvaluationTemplate::forceCreate([
        'name' => 'CCD Cares Template',
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $period = CcdCaresEvaluationPeriod::forceCreate([
        'evaluation_template_id' => $template->id,
        'title' => 'Open Evaluation',
        'start_date' => now()->subDays(1)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->from(route('student-profile.index'))
        ->post(route('student-profile.ccd-cares.evaluation.store'), [
            'period_id' => $period->id,
            'answers' => ['dummy' => 'value'],
        ]);

    $response->assertSessionHas('success', 'CCD Cares evaluation submitted.');
    $response->assertRedirect(route('student-profile.index', ['ccd' => 1]));
    expect(CcdCaresEvaluationSubmission::count())->toBe(1);
});

test('admin can filter and paginate ccd cares submissions with analytics', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo('evaluation.templates.view');

    $template = EvaluationTemplate::forceCreate([
        'name' => 'Wellness Assessment',
        'status' => 'active',
        'created_by' => $admin->id,
    ]);
    $category = EvaluationStatementCategory::forceCreate([
        'template_id' => $template->id,
        'name' => 'Wellness',
        'sort_order' => 1,
        'status' => 'active',
    ]);
    $statement = EvaluationStatement::forceCreate([
        'template_id' => $template->id,
        'category_id' => $category->id,
        'statement' => 'How are you feeling?',
        'statement_type' => 'rating_scale',
        'is_required' => true,
        'is_visible' => true,
        'scoring_enabled' => true,
        'sort_order' => 1,
        'status' => 'active',
    ]);
    EvaluationScoringRule::forceCreate([
        'template_id' => $template->id,
        'category_id' => $category->id,
        'statement_id' => $statement->id,
        'formula_type' => 'sum',
        'multiplier' => 1,
        'status' => 'active',
    ]);
    EvaluationInterpretationRange::forceCreate([
        'template_id' => $template->id,
        'category_id' => $category->id,
        'min_value' => 1,
        'max_value' => 5,
        'interpretation' => 'Healthy',
        'sort_order' => 1,
        'status' => 'active',
    ]);
    $period = CcdCaresEvaluationPeriod::forceCreate([
        'evaluation_template_id' => $template->id,
        'title' => 'June Wellness',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => 'active',
        'created_by' => $admin->id,
    ]);
    $matchedStudent = User::factory()->create([
        'name' => 'Matched Student',
        'student_no' => '2026-001',
        'campus_name' => 'Main Campus',
    ]);
    $otherStudent = User::factory()->create([
        'name' => 'Other Student',
        'student_no' => '2026-002',
        'campus_name' => 'North Campus',
    ]);

    foreach ([[$matchedStudent, 4], [$otherStudent, 2]] as [$student, $answer]) {
        CcdCaresEvaluationSubmission::forceCreate([
            'ccd_cares_evaluation_period_id' => $period->id,
            'evaluation_template_id' => $template->id,
            'student_id' => $student->id,
            'answers_json' => [$statement->id => $answer],
            'submitted_at' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('site-settings.ccd-cares.periods.submissions', [
            'period' => $period,
            'search' => '2026-001',
            'campus' => 'Main Campus',
            'per_page' => 10,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SiteSettings/CcdCares/Submissions')
            ->where('period.id', $period->id)
            ->where('submissions.total', 1)
            ->where('submissions.data.0.student.id', $matchedStudent->id)
            ->where('submissions.data.0.interpretations.0.interpretation', 'Healthy')
            ->where('analytics.total_submissions', 2)
            ->has('analytics.categories', 1)
            ->where('filters.per_page', 10));
});
