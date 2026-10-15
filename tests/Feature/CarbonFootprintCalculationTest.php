<?php

use App\Http\Middleware\TrackCarbonFootprint;
use App\Models\CarbonFootprintLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

test('small carbon values are persisted as fixed decimals for SQL Server', function () {
    config(['carbon.kwh_per_gb' => 0.194, 'carbon.grams_co2e_per_kwh' => 494]);
    $user = User::factory()->create();
    $request = Request::create('/my-carbon-footprint', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));
    $captured = [];
    CarbonFootprintLog::creating(function (CarbonFootprintLog $log) use (&$captured) {
        $captured = $log->getAttributes();
    });

    try {
        (new TrackCarbonFootprint)->handle($request, fn () => new Response(str_repeat('a', 134130)));
        expect($captured['estimated_data_kb'])->toBe('134.13')
            ->and($captured['estimated_energy_kwh'])->toBe('0.00002602')
            ->and($captured['estimated_co2e_grams'])->toBe('0.012854');
    } finally {
        CarbonFootprintLog::flushEventListeners();
    }
});

test('carbon tracking measures response bytes and uses decimal gigabytes', function () {
    config(['carbon.kwh_per_gb' => 0.194, 'carbon.grams_co2e_per_kwh' => 494]);
    $user = User::factory()->create();
    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));
    (new TrackCarbonFootprint)->handle($request, fn () => new Response(str_repeat('a', 1000000)));
    $log = CarbonFootprintLog::sole();
    expect((float) $log->estimated_data_kb)->toBe(1000.0)
        ->and((float) $log->estimated_energy_kwh)->toBe(0.000194)
        ->and((float) $log->estimated_co2e_grams)->toBe(0.095836);
});

test('response measurement handles small bodies headers empty responses and unknown streams', function () {
    config(['carbon.default_page_kb' => 850]);
    $method = new ReflectionMethod(TrackCarbonFootprint::class, 'estimatedKilobytes');
    $request = Request::create('/dashboard');
    $middleware = new TrackCarbonFootprint;
    expect($method->invoke($middleware, $request, new Response(str_repeat('a', 100))))->toBe(0.1)
        ->and($method->invoke($middleware, $request, new Response('')))->toBe(0.0)
        ->and($method->invoke($middleware, $request, new Response('body', 200, ['Content-Length' => '100'])))->toBe(0.1)
        ->and($method->invoke($middleware, $request, new StreamedResponse(fn () => null)))->toBe(850.0);
});

test('redirects errors and non page requests do not count as page visits', function (string $verb, int $status, bool $json) {
    $request = Request::create('/dashboard', $verb);
    if ($json) {
        $request->headers->set('Accept', 'application/json');
    }
    $method = new ReflectionMethod(TrackCarbonFootprint::class, 'shouldTrack');
    expect($method->invoke(new TrackCarbonFootprint, $request, new Response('', $status)))->toBeFalse();
})->with([['GET', 302, false], ['GET', 404, false], ['POST', 200, false], ['GET', 200, true]]);
