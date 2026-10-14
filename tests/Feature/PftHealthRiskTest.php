<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftHealthQuestionnaire;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('health questionnaire saves risk answers awaiting administrator clearance', function (string $field) {
    Gate::define('pft.submit', fn (User $user) => true);
    $campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '1', 'campus_id' => '1', 'tenant_id' => '1', 'status' => 'Active']);
    SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'test', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    $user = User::factory()->create(['campus_id' => '1']);
    PftHealthQuestionnaire::create([
        'user_id' => $user->id, 'term_id' => 'test', 'clearance_granted' => true,
        'clearance_granted_by' => $user->id, 'clearance_granted_at' => now(),
    ]);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->post('/student-profile/physical-fitness/health-questionnaire', [
            'term_id' => 'test',
            'has_medical_condition' => false,
            'has_medication' => false,
            $field => true,
            'clearance_granted' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
    expect(PftHealthQuestionnaire::first()->{$field})->toBeTrue()
        ->and(PftHealthQuestionnaire::first()->clearance_granted)->toBeFalse()
        ->and(PftHealthQuestionnaire::first()->clearance_granted_by)->toBeNull()
        ->and(PftHealthQuestionnaire::first()->clearance_granted_at)->toBeNull();
})->with(['has_medical_condition', 'has_medication']);

test('PAR-Q requires a clearance document for any single yes answer', function (string $field) {
    Gate::define('pft.submit', fn (User $user) => true);
    $answers = array_fill_keys(['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7'], false);
    $answers[$field] = true;
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs(User::factory()->create())
        ->post('/student-profile/physical-fitness/parq', ['term_id' => 'test', ...$answers])
        ->assertSessionHasErrors('medical_clearance');
    $this->assertDatabaseCount('pft_parqs', 0);
})->with(['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7']);

test('PAR-Q yes answers with a document are submitted for administrator review', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    Gate::define('pft.submit', fn (User $user) => true);
    $campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '1', 'campus_id' => '1', 'tenant_id' => '1', 'status' => 'Active']);
    SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'test', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    $student = User::factory()->create(['campus_id' => '1']);
    $answers = array_fill_keys(['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7'], false);
    $answers['q7'] = true;
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->post('/student-profile/physical-fitness/parq', ['term_id' => 'test', ...$answers, 'medical_clearance' => \Illuminate\Http\UploadedFile::fake()->create('clearance.pdf', 20, 'application/pdf')])
        ->assertSessionHasNoErrors()->assertRedirect();
    $parq = \App\Models\PftParq::first();
    expect($parq->clearance_status)->toBe('pending_evaluation')->and($parq->q7)->toBeTrue();
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($parq->medical_clearance_path);
});
