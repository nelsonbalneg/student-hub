<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.sso.base_url', 'https://sso.test');
    Config::set('services.sso.client_id', 'studenthub');
    Config::set('services.sso.client_secret', 'secret');
    Config::set('services.sso.redirect_uri', 'https://studenthub.test/auth/sso/callback');
    Config::set('services.sso.user_url', 'https://sso.test/api/user');

    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

test('employee sso users receive the student role by default', function () {
    Http::fake([
        'https://sso.test/oauth/token' => Http::response(['access_token' => 'token'], 200),
        'https://sso.test/api/user' => Http::response([
            'id' => 123,
            'name' => 'Employee User',
            'email' => 'employee@example.com',
            'account_type' => 'employee',
            'employee_no' => 'EMP-001',
        ], 200),
    ]);

    $this->get(route('auth.sso.callback', ['code' => 'valid-code']))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'employee@example.com')->firstOrFail();

    expect($user->hasRole('Super Admin'))->toBeFalse()
        ->and($user->hasRole('Student'))->toBeTrue();
});

test('student sso users receive the student role by default', function () {
    Http::fake([
        'https://sso.test/oauth/token' => Http::response(['access_token' => 'token'], 200),
        'https://sso.test/api/user' => Http::response([
            'id' => 456,
            'name' => 'Student User',
            'email' => 'student@example.com',
            'account_type' => 'student',
            'student_no' => 'STU-001',
        ], 200),
    ]);

    $this->get(route('auth.sso.callback', ['code' => 'valid-code']))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'student@example.com')->firstOrFail();

    expect($user->hasRole('Student'))->toBeTrue();
});

test('admin sso users receive the super admin role by default', function () {
    Http::fake([
        'https://sso.test/oauth/token' => Http::response(['access_token' => 'token'], 200),
        'https://sso.test/api/user' => Http::response([
            'id' => 789,
            'name' => 'Admin User',
            'email' => 'new-admin@example.com',
            'account_type' => 'admin',
        ], 200),
    ]);

    $this->get(route('auth.sso.callback', ['code' => 'valid-code']))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'new-admin@example.com')->firstOrFail();
    $superAdmin = Role::query()->where('name', 'Super Admin')->firstOrFail();

    expect($user->hasRole($superAdmin))->toBeTrue();
});
