<?php

use App\Http\Controllers\CcdCaresEvaluationSubmissionController;
use App\Http\Controllers\SiteSettings\CcdCaresEvaluationController;
use App\Models\CcdCaresEvaluationPeriod;
use App\Models\CcdCaresEvaluationSubmission;
use App\Models\EvaluationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

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
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
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
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
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
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
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
