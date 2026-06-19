<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\StudentProfileController;

$user = User::where('email', 'nelson.balneg@usm.edu.ph')->first();
$request = Request::create('/student-profile', 'GET');
$request->setUserResolver(fn() => $user);

$controller = $app->make(StudentProfileController::class);
$response = $controller($request);

// Inspect the props of Inertia Response directly using reflection
$ref = new ReflectionProperty($response, 'props');
$ref->setAccessible(true);
$props = $ref->getValue($response);

echo "PROPS RECEIVED:\n";
echo "ccdCares assessments count: " . count($props['ccdCares']['assessments']) . "\n";
print_r($props['ccdCares']);
