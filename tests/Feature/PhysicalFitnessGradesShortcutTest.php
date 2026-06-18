<?php

use App\Services\PhysicalFitnessPermissionService;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('physical fitness grades shortcut follows the site setting', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')
        ->times(3)
        ->with('key', PhysicalFitnessPermissionService::SHORTCUT_ENABLED_SETTING_KEY)
        ->andReturnSelf();
    $query->shouldReceive('value')
        ->times(3)
        ->with('value')
        ->andReturn(null, '1', '0');

    mock('alias:App\Models\SiteSetting', function (MockInterface $model) use ($query): void {
        $model->shouldReceive('query')
            ->times(3)
            ->andReturn($query);
    });

    $service = app(PhysicalFitnessPermissionService::class);

    expect($service->gradesShortcutEnabled())
        ->toBeFalse()
        ->and($service->gradesShortcutEnabled())
        ->toBeTrue()
        ->and($service->gradesShortcutEnabled())
        ->toBeFalse();
});
