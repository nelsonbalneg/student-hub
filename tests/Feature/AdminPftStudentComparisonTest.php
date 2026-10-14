<?php

use App\Http\Middleware\EnsureTermsAccepted;
use App\Models\PftCategory;
use App\Models\PftComponent;
use App\Models\PftTestType;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\StudentPftResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('admin student comparison separates phases and excludes other students campuses and drafts', function () {
    Gate::define('reporting.pft_result.view', fn () => true);
    $student = User::factory()->create();
    $other = User::factory()->create();
    $campus = SiteCampus::create(['campus_name' => 'Test', 'real_campus_id' => '1']);
    SiteAcademicTerm::create(['site_campus_id' => $campus->id, 'term_id' => 'first', 'school_year' => '2025-2026', 'semester' => '1st Semester', 'status' => 'Active']);
    $component = PftComponent::create(['name' => 'Health', 'slug' => 'health', 'is_active' => true]);
    $category = PftCategory::create(['pft_component_id' => $component->id, 'name' => 'Strength', 'slug' => 'strength', 'is_active' => true]);
    $type = PftTestType::create(['pft_category_id' => $category->id, 'name' => 'Push-Up', 'slug' => 'push-up', 'is_active' => true]);
    foreach ([[$student->id, '1', 'pre', 'completed'], [$student->id, '1', 'post', 'completed'], [$other->id, '1', 'pre', 'completed'], [$student->id, '2', 'pre', 'completed'], [$student->id, '1', 'pre', 'draft']] as [$userId, $campusId, $phase, $status]) {
        StudentPftResult::create(['user_id' => $userId, 'campus_id' => $campusId, 'term_id' => $status === 'draft' ? 'draft-term' : ($campusId === '2' ? 'other-campus-term' : 'first'), 'test_phase' => $phase, 'status' => $status, 'pft_test_type_id' => $type->id, 'results_json' => ['score' => 12]]);
    }
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->getJson(route('admin.reporting.pft-result.student-comparison', ['user' => $student, 'campus_id' => '1']))
        ->assertSuccessful()->assertJsonCount(2, 'results')->assertJsonPath('terms.0.school_year', '2025-2026')
        ->assertJsonPath('results.first:pre:'.$type->id.'.results_json.score', 12)
        ->assertJsonPath('results.first:post:'.$type->id.'.status', 'completed')
        ->assertJsonPath('components.0.categories.0.test_types.0.name', 'Push-Up');
});

test('admin student comparison requires reporting permission', function () {
    Gate::define('reporting.pft_result.view', fn () => false);
    $student = User::factory()->create();
    $this->withoutMiddleware(EnsureTermsAccepted::class)->actingAs($student)
        ->getJson(route('admin.reporting.pft-result.student-comparison', ['user' => $student, 'campus_id' => '1']))->assertForbidden();
});


