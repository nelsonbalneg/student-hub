<?php

use App\Models\User;
use App\Models\Office;
use App\Models\SiteCampus;
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

    $siteCampus1 = SiteCampus::create([
        'campus_name' => 'USM Antipas Campus',
        'campus_id' => 1,
        'tenant_id' => '1',
        'status' => 'Active',
    ]);

    $office1 = Office::create([
        'name' => 'Registrar Antipas',
        'code' => 'REG-A',
        'campus_id' => $siteCampus1->id,
    ]);

    $siteCampus3 = SiteCampus::create([
        'campus_name' => 'USM KCC',
        'campus_id' => 3,
        'tenant_id' => '3',
        'status' => 'Active',
    ]);

    $office3 = Office::create([
        'name' => 'Accounting KCC',
        'code' => 'ACC-K',
        'campus_id' => $siteCampus3->id,
    ]);

    $user = User::factory()->create([
        'tenant_id' => null,
        'campus_id' => null,
        'office_id' => null,
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
            ->where('campuses.0.campus_id', 1)
            ->has('officesByCampus')
            ->where('officesByCampus.5.0.id', $office1->id)
            ->where('officesByCampus.5.0.name', 'Registrar Antipas')
            ->where('officesByCampus.3.0.id', $office3->id)
            ->where('officesByCampus.3.0.name', 'Accounting KCC')
        );
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

    $siteCampus = SiteCampus::create([
        'campus_name' => 'USM KCC',
        'campus_id' => 3,
        'tenant_id' => '3',
        'status' => 'Active',
    ]);

    $office = Office::create([
        'name' => 'Accounting KCC',
        'code' => 'ACC-K',
        'campus_id' => $siteCampus->id,
    ]);

    $user = User::factory()->create([
        'tenant_id' => null,
        'campus_id' => null,
        'campus_name' => null,
        'office_id' => null,
        'office' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.campus.assign'), [
            'campus_record_id' => 3,
            'office_id' => $office->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect((int) $user->tenant_id)->toBe(3)
        ->and($user->campus_id)->toBe(3)
        ->and($user->campus_name)->toBe('USM KCC')
        ->and($user->office_id)->toBe($office->id)
        ->and($user->office)->toBe('Accounting KCC');
});

test('user with an existing tenant cannot reassign their campus', function () {
    mock(SsoCampusDirectory::class)->shouldNotReceive('find');

    $siteCampus = SiteCampus::create([
        'campus_name' => 'USM Main',
        'campus_id' => 1,
        'tenant_id' => '1',
        'status' => 'Active',
    ]);

    $office = Office::create([
        'name' => 'Registrar Main',
        'code' => 'REG-M',
        'campus_id' => $siteCampus->id,
    ]);

    $user = User::factory()->create([
        'tenant_id' => 1,
        'campus_id' => 1,
        'office_id' => $office->id,
    ]);

    $this->actingAs($user)
        ->patch(route('profile.campus.assign'), [
            'campus_record_id' => 3,
            'office_id' => 999,
        ])
        ->assertForbidden();

    $user->refresh();
    expect((int) $user->tenant_id)->toBe(1)
        ->and($user->campus_id)->toBe(1)
        ->and($user->office_id)->toBe($office->id);
});
