<?php

use App\Http\Controllers\SiteEvaluationResponseController;
use App\Http\Controllers\SiteSettings\SiteEvaluationController;
use App\Models\SiteEvaluationDismissal;
use App\Models\SiteEvaluationPeriod;
use App\Models\SiteEvaluationSubmission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

test('site evaluation routes are registered', function () {
    expect(Route::getRoutes()->getByName('site-settings.site-evaluation.index')?->getActionName())
        ->toBe(SiteEvaluationController::class.'@index')
        ->and(Route::getRoutes()->getByName('site-settings.site-evaluation.periods.store')?->methods())
        ->toContain('POST')
        ->and(Route::getRoutes()->getByName('site-settings.site-evaluation.results')?->methods())
        ->toContain('GET')
        ->and(Route::getRoutes()->getByName('site-settings.site-evaluation.analytics')?->methods())
        ->toContain('GET')
        ->and(Route::getRoutes()->getByName('site-settings.site-evaluation.periods.update')?->methods())
        ->toContain('PATCH')
        ->and(Route::getRoutes()->getByName('site-settings.site-evaluation.periods.destroy')?->methods())
        ->toContain('DELETE')
        ->and(Route::getRoutes()->getByName('site-evaluation.submit')?->getActionName())
        ->toBe(SiteEvaluationResponseController::class.'@store')
        ->and(Route::getRoutes()->getByName('site-evaluation.dismiss')?->getActionName())
        ->toBe(SiteEvaluationResponseController::class.'@dismiss');
});

test('site evaluation models expose their relationships', function () {
    $period = new SiteEvaluationPeriod;
    $submission = new SiteEvaluationSubmission;
    $dismissal = new SiteEvaluationDismissal;

    expect($period->template())->toBeInstanceOf(BelongsTo::class)
        ->and($period->creator())->toBeInstanceOf(BelongsTo::class)
        ->and($period->submissions())->toBeInstanceOf(HasMany::class)
        ->and($period->dismissals())->toBeInstanceOf(HasMany::class)
        ->and($submission->period())->toBeInstanceOf(BelongsTo::class)
        ->and($submission->template())->toBeInstanceOf(BelongsTo::class)
        ->and($submission->user())->toBeInstanceOf(BelongsTo::class)
        ->and($dismissal->period())->toBeInstanceOf(BelongsTo::class)
        ->and($dismissal->user())->toBeInstanceOf(BelongsTo::class);
});
