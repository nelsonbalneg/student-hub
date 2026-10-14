<?php

use App\Http\Controllers\Admin\Reporting\PftResultController;
use App\Models\StudentPftResult;
use App\Services\AcademicApiService;
use Illuminate\Support\Facades\Cache;

test('fitness college profiles resolve external college names with tenant scoped caching', function () {
    Cache::flush();
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('college')->once()->with('32', '3')
        ->andReturn(['data' => ['collegeId' => 32, 'collegeCode' => 'CED', 'collegeName' => 'College of Education'], 'error' => null]);
    $this->app->instance(AcademicApiService::class, $api);
    $controller = $this->app->make(PftResultController::class);
    $resolve = new ReflectionMethod($controller, 'resolveCollegeName');
    $label = $resolve->invoke($controller, '32', '3');
    expect($resolve->invoke($controller, '32', '3'))->toBe('CED - College of Education');
    $profiles = new ReflectionMethod($controller, 'collegeComponentProfiles');
    $result = $profiles->invoke($controller, collect([
        new StudentPftResult(['college_id' => '32', 'tenant_id' => '3', 'user_id' => 1, 'term_id' => '70']),
    ]), ['32' => $label]);
    expect($result[0]['label'])->toBe('CED - College of Education');
});

test('failed college lookup can recover without caching an unavailable name', function () {
    Cache::flush();
    $api = Mockery::mock(AcademicApiService::class);
    $api->shouldReceive('college')->with('32', '1')->once()->andReturn(['data' => null, 'error' => 'Unavailable']);
    $api->shouldReceive('college')->with('32', '1')->once()->andReturn(['data' => ['collegeName' => 'College of Education'], 'error' => null]);
    $this->app->instance(AcademicApiService::class, $api);
    $controller = $this->app->make(PftResultController::class);
    $resolve = new ReflectionMethod($controller, 'resolveCollegeName');
    expect($resolve->invoke($controller, '32', null))->toBeNull()
        ->and($resolve->invoke($controller, '32', null))->toBe('College of Education');
});
