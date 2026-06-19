<?php

use App\Http\Controllers\SiteSettings\EvaluationTemplateController;
use App\Models\EvaluationInterpretationRange;
use App\Models\EvaluationStatementCategory;
use App\Models\EvaluationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('evaluation template builder routes are registered', function () {
    expect(Route::getRoutes()->getByName('site-settings.evaluation.index')?->getActionName())
        ->toBe(EvaluationTemplateController::class.'@index')
        ->and(Route::getRoutes()->getByName('site-settings.evaluation.templates.store')?->methods())
        ->toContain('POST')
        ->and(Route::getRoutes()->getByName('site-settings.evaluation.templates.clone')?->methods())
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
        'site-settings.evaluation.templates.clone',
        'site-settings.evaluation.templates.update',
        'site-settings.evaluation.templates.destroy',
        'site-settings.evaluation.categories.reorder',
        'site-settings.evaluation.statements.reorder',
        'site-settings.evaluation.scale-sets.store',
        'site-settings.evaluation.scale-sets.update',
        'site-settings.evaluation.scale-sets.destroy',
        'site-settings.evaluation.scales.reorder',
        'site-settings.evaluation.choices.reorder',
        'site-settings.evaluation.interpretation-ranges.store',
        'site-settings.evaluation.interpretation-ranges.update',
        'site-settings.evaluation.interpretation-ranges.destroy',
    );
});

test('authorized user can manage interpretation ranges', function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo('evaluation.templates.create');
    $user->givePermissionTo('evaluation.templates.update');
    $user->givePermissionTo('evaluation.templates.delete');

    $template = EvaluationTemplate::forceCreate([
        'name' => 'Test Template',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $category = EvaluationStatementCategory::forceCreate([
        'template_id' => $template->id,
        'name' => 'Depression',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    // 1. Create interpretation range
    $response = $this->actingAs($user)
        ->from(route('site-settings.evaluation.index', ['template' => $template->id]))
        ->post(route('site-settings.evaluation.interpretation-ranges.store'), [
            'template_id' => $template->id,
            'category_id' => $category->id,
            'min_value' => 0,
            'max_value' => 9,
            'interpretation' => 'Normal',
            'suggested_intervention' => 'Self-care',
            'sort_order' => 1,
            'status' => 'active',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('site-settings.evaluation.index', ['template' => $template->id]));

    $range = EvaluationInterpretationRange::first();
    expect($range)->not->toBeNull()
        ->and($range->interpretation)->toBe('Normal')
        ->and($range->suggested_intervention)->toBe('Self-care')
        ->and((float) $range->min_value)->toBe(0.0)
        ->and((float) $range->max_value)->toBe(9.0);

    // 2. Update interpretation range
    $response = $this->actingAs($user)
        ->from(route('site-settings.evaluation.index', ['template' => $template->id]))
        ->patch(route('site-settings.evaluation.interpretation-ranges.update', $range->id), [
            'template_id' => $template->id,
            'category_id' => $category->id,
            'min_value' => 0,
            'max_value' => 9,
            'interpretation' => 'Normal Level',
            'suggested_intervention' => 'Self-care & Mindfulness',
            'sort_order' => 1,
            'status' => 'active',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('site-settings.evaluation.index', ['template' => $template->id]));

    $range->refresh();
    expect($range->interpretation)->toBe('Normal Level')
        ->and($range->suggested_intervention)->toBe('Self-care & Mindfulness');

    // 3. Delete interpretation range
    $response = $this->actingAs($user)
        ->from(route('site-settings.evaluation.index', ['template' => $template->id]))
        ->delete(route('site-settings.evaluation.interpretation-ranges.destroy', $range->id));

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('site-settings.evaluation.index', ['template' => $template->id]));
    expect(EvaluationInterpretationRange::count())->toBe(0);
});
