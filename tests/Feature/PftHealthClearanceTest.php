<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftDataPrivacyConsent;
use App\Models\PftHealthQuestionnaire;
use App\Models\PftParq;
use App\Models\PftTestSchedule;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use App\Services\PhysicalFitnessPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('students cannot edit health answers or replace a submitted certificate', function () {
    Gate::define('pft.submit', fn (User $user) => true);
    $student = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => $student->id, 'term_id' => 'test', 'has_medical_condition' => true, 'medical_clearance_path' => 'pft-clearances/submitted.pdf']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->post(route('student-profile.physical-fitness.health-questionnaire.store'), ['term_id' => 'test', 'has_medical_condition' => false, 'has_medication' => false])
        ->assertForbidden();
    $this->post(route('student-profile.physical-fitness.health-questionnaire.clearance', $health), ['medical_clearance' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf')])
        ->assertForbidden();
    expect($health->refresh()->has_medical_condition)->toBeTrue()
        ->and($health->medical_clearance_path)->toBe('pft-clearances/submitted.pdf');
});

test('medical certificate uploads work when the temporary real path cannot be resolved', function (bool $fullQuestionnaire) {
    Storage::fake('public');
    Gate::define('pft.submit', fn (User $user) => true);
    $student = User::factory()->create(['campus_id' => '1']);
    $campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '1', 'campus_id' => '1', 'tenant_id' => '1', 'status' => 'Active']);
    SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'test', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    $health = PftHealthQuestionnaire::create(['user_id' => $student->id, 'term_id' => 'test']);
    $temporary = UploadedFile::fake()->createWithContent('certificate.pdf', '%PDF-1.4 certificate');
    $upload = new class($temporary->getPathname(), 'certificate.pdf', 'application/pdf', UPLOAD_ERR_OK, true) extends UploadedFile
    {
        public function getRealPath(): string|false
        {
            return false;
        }

        public function getMimeType(): ?string
        {
            return 'application/pdf';
        }
    };

    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->post($fullQuestionnaire ? route('student-profile.physical-fitness.health-questionnaire.store') : route('student-profile.physical-fitness.health-questionnaire.clearance', $health), [
            'medical_clearance' => $upload, 'term_id' => 'test', 'has_medical_condition' => true, 'has_medication' => false,
        ])
        ->assertSessionHasNoErrors()->assertRedirect();
    Storage::disk('public')->assertExists($health->refresh()->medical_clearance_path);
    expect(Storage::disk('public')->get($health->medical_clearance_path))->toBe('%PDF-1.4 certificate');
})->with([false, true]);

test('fitness submission requires granted health clearance when risk is reported', function (bool $granted) {
    Gate::define('pft.submit', fn (User $user) => true);
    $this->mock(PhysicalFitnessPermissionService::class, function ($mock) {
        $mock->shouldReceive('canFillUp')->andReturnTrue();
    });
    $student = User::factory()->create(['campus_id' => '1']);
    $campus = SiteCampus::create(['campus_name' => 'Test Campus', 'real_campus_id' => '1', 'campus_id' => '1', 'tenant_id' => '1', 'status' => 'Active']);
    $term = SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'test', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    PftTestSchedule::create(['site_academic_term_id' => $term->id, 'phase' => 'pre', 'opens_on' => today('Asia/Singapore')->toDateString(), 'closes_on' => today('Asia/Singapore')->toDateString()]);
    PftDataPrivacyConsent::create(['user_id' => $student->id, 'term_id' => 'test', 'accepted_at' => now()]);
    PftHealthQuestionnaire::create(['user_id' => $student->id, 'term_id' => 'test', 'has_medical_condition' => true, 'clearance_granted' => $granted]);
    PftParq::create(['user_id' => $student->id, 'term_id' => 'test', 'clearance_status' => 'verified']);
    $component = PftComponent::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
    $category = PftCategory::create(['pft_component_id' => $component->id, 'name' => 'Test', 'slug' => 'test', 'is_active' => true]);
    $test = PftTestType::create(['pft_category_id' => $category->id, 'name' => 'Test', 'slug' => 'test', 'is_active' => true]);

    $response = $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->post(route('student-profile.physical-fitness.store', $test), ['term_id' => 'test']);
    if ($granted) {
        $response->assertSessionHasErrors('results')->assertSessionDoesntHaveErrors('term_id');
    } else {
        $response->assertSessionHasErrors('term_id')->assertSessionDoesntHaveErrors('results');
    }
})->with([false, true]);

function healthClearancePayload(bool $grant = true): array
{
    return ['has_medical_condition' => true, 'has_medication' => false, 'clearance_granted' => $grant, 'specific_conditions' => ['Asthma']];
}

test('admin can grant and revoke health clearance after certificate submission', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => true);
    $admin = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => User::factory()->create()->id, 'term_id' => 'test', 'has_medical_condition' => true, 'medical_clearance_path' => 'pft-clearances/cert.pdf']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($admin)
        ->patch(route('admin.reporting.pft-parq.health.update', $health), [
            ...healthClearancePayload(),
            'civil_status' => 'Married',
            'household_monthly_income' => 'Less than ₱10,000',
            'father_occupation' => 'Farmer',
            'mother_occupation' => 'Teacher',
            'smoking_status' => 'Never',
            'alcohol_consumption' => 'Occasional',
        ])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($health->refresh()->clearance_granted)->toBeTrue()
        ->and($health->civil_status)->toBe('Married')
        ->and($health->household_monthly_income)->toBe('Less than ₱10,000')
        ->and($health->father_occupation)->toBe('Farmer')
        ->and($health->mother_occupation)->toBe('Teacher')
        ->and($health->smoking_status)->toBe('Never')
        ->and($health->alcohol_consumption)->toBe('Occasional')
        ->and($health->clearance_granted_by)->toBe($admin->id)
        ->and($health->clearance_granted_at)->not->toBeNull();
    $this->patch(route('admin.reporting.pft-parq.health.update', $health), healthClearancePayload(false))
        ->assertSessionHasNoErrors();
    expect($health->refresh()->clearance_granted)->toBeFalse()->and($health->clearance_granted_by)->toBeNull();
});

test('admin cannot grant health clearance without a certificate', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => true);
    $user = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => $user->id, 'term_id' => 'test']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->patch(route('admin.reporting.pft-parq.health.update', $health), healthClearancePayload())
        ->assertSessionHasErrors('clearance_granted');
    expect($health->refresh()->clearance_granted)->toBeFalse();
});

test('health update and delete require reporting permission', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => false);
    $user = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => $user->id, 'term_id' => 'test']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->patch(route('admin.reporting.pft-parq.health.update', $health), healthClearancePayload())->assertForbidden();
    $this->delete(route('admin.reporting.pft-parq.health.destroy', $health))->assertForbidden();
    $this->assertModelExists($health);
});

test('admin deletes only the selected health submission', function () {
    Gate::define('reporting.pft_result.view', fn (User $user) => true);
    $user = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => $user->id, 'term_id' => 'first']);
    $other = PftHealthQuestionnaire::create(['user_id' => $user->id, 'term_id' => 'second']);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($user)
        ->delete(route('admin.reporting.pft-parq.health.destroy', $health))->assertRedirect();
    $this->assertModelMissing($health);
    $this->assertModelExists($other);
});

test('student certificate replacement resets clearance and cannot update another student', function () {
    Storage::fake('public');
    Gate::define('pft.submit', fn (User $user) => true);
    $student = User::factory()->create();
    $health = PftHealthQuestionnaire::create(['user_id' => $student->id, 'term_id' => 'test', 'has_medical_condition' => true, 'clearance_granted' => true, 'clearance_granted_by' => $student->id, 'clearance_granted_at' => now()]);
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->post(route('student-profile.physical-fitness.health-questionnaire.clearance', $health), ['medical_clearance' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($health->refresh()->clearance_granted)->toBeFalse()->and($health->clearance_granted_by)->toBeNull()->and($health->clearance_granted_at)->toBeNull();
    Storage::disk('public')->assertExists($health->medical_clearance_path);
    $this->actingAs(User::factory()->create())
        ->post(route('student-profile.physical-fitness.health-questionnaire.clearance', $health), ['medical_clearance' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')])
        ->assertForbidden();
});
