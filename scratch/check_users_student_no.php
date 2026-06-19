<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$users = User::where('student_no', '23-68057')->get();
echo "Number of users with student_no 23-68057: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "ID: " . $u->id . ", Name: " . $u->name . ", Email: " . $u->email . "\n";
}
