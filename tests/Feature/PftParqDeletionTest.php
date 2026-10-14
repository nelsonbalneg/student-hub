<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftParq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('authorized users can delete only the selected PAR-Q entry', function () {
    $user = User::factory()->create();
    Gate::define('reporting.pft_result.view', fn (User $user) => true);
    $parq = PftParq::create(['user_id' => $user->id, 'term_id' => 'first']);
    $other = PftParq::create(['user_id' => $user->id, 'term_id' => 'second']);

    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->from(route('admin.reporting.pft-parq.index'))
        ->delete(route('admin.reporting.pft-parq.destroy', $parq))
        ->assertRedirect(route('admin.reporting.pft-parq.index'))
        ->assertSessionHas('success');

    $this->assertModelMissing($parq);
    $this->assertModelExists($other);
    $this->assertModelExists($user);
});

test('users without reporting permission cannot delete PAR-Q entries', function () {
    $user = User::factory()->create();
    Gate::define('reporting.pft_result.view', fn (User $user) => false);
    $parq = PftParq::create(['user_id' => $user->id, 'term_id' => 'first']);

    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->delete(route('admin.reporting.pft-parq.destroy', $parq))
        ->assertForbidden();

    $this->assertModelExists($parq);
});
