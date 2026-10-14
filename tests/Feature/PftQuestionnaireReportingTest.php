<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftHealthQuestionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('health reporting filters answers by student and term', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => true);
    $student = User::factory()->create(['name' => 'Health Report Student']);
    PftHealthQuestionnaire::create([
        'user_id' => $student->id, 'term_id' => 'first',
        'has_medical_condition' => true, 'specific_conditions' => ['Asthma'],
        'has_medication' => true, 'medication_details' => 'Reported medication',
    ]);
    PftHealthQuestionnaire::create(['user_id' => $student->id, 'term_id' => 'second']);
    $other = User::factory()->create(['name' => 'Other Student']);
    PftHealthQuestionnaire::create(['user_id' => $other->id, 'term_id' => 'first']);

    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->get(route('admin.reporting.pft-parq.index', ['tab' => 'health', 'search' => 'Health Report', 'term' => 'first']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reporting/PftParq')
            ->where('filters.tab', 'health')
            ->where('healthQuestionnaires.total', 1)
            ->where('healthQuestionnaires.data.0.has_medical_condition', true)
            ->where('healthQuestionnaires.data.0.specific_conditions', ['Asthma'])
            ->where('healthQuestionnaires.data.0.medication_details', 'Reported medication'));
});

test('health reporting requires reporting permission', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => false);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs(User::factory()->create())
        ->get(route('admin.reporting.pft-parq.index', ['tab' => 'health']))
        ->assertForbidden();
});
