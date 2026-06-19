<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CcdCaresEvaluationPeriod;
use App\Models\User;

$user = User::where('email', 'nelson.balneg@usm.edu.ph')->first();
echo "User ID: " . $user->id . "\n";
echo "User Name: " . $user->name . "\n";

$periods = CcdCaresEvaluationPeriod::query()
    ->whereIn('status', [
        CcdCaresEvaluationPeriod::STATUS_ACTIVE,
        CcdCaresEvaluationPeriod::STATUS_CLOSED,
    ])
    ->with([
        'template',
        'submissions' => fn ($query) => $query->where('student_id', $user->id),
    ])
    ->get();

foreach ($periods as $period) {
    echo "Period ID: " . $period->id . "\n";
    echo "Period Title: " . $period->title . "\n";
    echo "Period Status: " . $period->status . "\n";
    echo "Template ID: " . ($period->template ? $period->template->id : 'none') . "\n";
    echo "Submission count: " . $period->submissions->count() . "\n";
    if ($period->submissions->first()) {
        echo "  Submitted at: " . $period->submissions->first()->submitted_at . "\n";
        print_r($period->submissions->first()->getInterpretationResults());
    }
}
