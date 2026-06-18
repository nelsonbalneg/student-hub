<?php

use App\Http\Controllers\SiteSettings\EvaluationTemplateController;
use Illuminate\Support\Facades\Route;

test('evaluation template builder routes are registered', function () {
    expect(Route::getRoutes()->getByName('site-settings.evaluation.index')?->getActionName())
        ->toBe(EvaluationTemplateController::class.'@index')
        ->and(Route::getRoutes()->getByName('site-settings.evaluation.templates.store')?->methods())
        ->toContain('POST')
        ->and(Route::getRoutes()->getByName('site-settings.evaluation.statements.reorder')?->methods())
        ->toContain('PATCH')
        ->and(Route::getRoutes()->getByName('site-settings.evaluation.choices.destroy')?->methods())
        ->toContain('DELETE');
});

test('evaluation template builder exposes all configuration routes', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn (?string $name) => str_starts_with((string) $name, 'site-settings.evaluation.'))
        ->values();

    expect($names)->toContain(
        'site-settings.evaluation.templates.store',
        'site-settings.evaluation.templates.update',
        'site-settings.evaluation.templates.destroy',
        'site-settings.evaluation.categories.reorder',
        'site-settings.evaluation.statements.reorder',
        'site-settings.evaluation.scales.reorder',
        'site-settings.evaluation.choices.reorder',
    );
});
