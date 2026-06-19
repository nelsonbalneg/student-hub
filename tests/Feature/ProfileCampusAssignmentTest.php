<?php

use App\Models\User;
use App\Services\SsoCampusDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('profile asks users without a tenant to select an SSO campus', function () {
    mock(SsoCampusDirectory::class)
        ->shouldReceive('all')
        ->once()
        ->andReturn([
            [
                'record_id' => 5,
                'name' => 'USM Antipas Campus',
                'tenant_id' => 1,
                'campus_id' => 1,
            ],
            [
                'record_id' => 3,
                'name' => 'USM KCC',
                'tenant_id' => 3,
                'campus_id' => 3,
            ],
        ]);

    $user = User::factory()->create([
        'tenant_id' => null,
        'campus_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('requiresCampusSelection', true)
            ->has('campuses', 2)
            ->where('campuses.0.name', 'USM Antipas Campus')
            ->where('campuses.0.tenant_id', 1)
            ->where('campuses.0.campus_id', 1));
});

test('user can assign tenant and campus from the selected SSO campus', function () {
    mock(SsoCampusDirectory::class)
        ->shouldReceive('find')
        ->once()
        ->with(3)
        ->andReturn([
            'record_id' => 3,
            'name' => 'USM KCC',
            'tenant_id' => 3,
            'campus_id' => 3,
        ]);

    $user = User::factory()->create([
        'tenant_id' => null,
        'campus_id' => null,
        'campus_name' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.campus.assign'), [
            'campus_record_id' => 3,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect((int) $user->tenant_id)->toBe(3)
        ->and($user->campus_id)->toBe(3)
        ->and($user->campus_name)->toBe('USM KCC');
});

test('user with an existing tenant cannot reassign their campus', function () {
    mock(SsoCampusDirectory::class)->shouldNotReceive('find');

    $user = User::factory()->create([
        'tenant_id' => 1,
        'campus_id' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.campus.assign'), [
            'campus_record_id' => 3,
        ])
        ->assertForbidden();

    expect((int) $user->refresh()->tenant_id)->toBe(1)
        ->and($user->campus_id)->toBe(1);
});
