<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $users = DB::connection('sqlsrv') // Wait, the SSO connection is DB_SSO_CONNECTION=sqlsrv? Or DB_SSO_DATABASE=ssodb?
        ->table('ssodb.dbo.users') // Wait, does ssodb have a users table? Let's check.
        ->get();
    
    foreach ($users as $u) {
        echo "ID: " . $u->id . ", Name: " . $u->name . ", Email: " . $u->email . ", Username: " . ($u->username ?? 'n/a') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
