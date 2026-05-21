<?php

use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Services\GetActiveAcademicTermService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it resolves an active term by site campus id', function () {
    $campus = SiteCampus::create([
        'campus_name' => 'Main Campus',
        'real_campus_id' => '12',
    ]);

    $term = SiteAcademicTerm::create([
        'site_campus_id' => $campus->id,
        'school_year' => '2025-2026',
        'semester' => '1st Semester',
        'term_id' => '101',
        'status' => 'Active',
    ]);

    expect(app(GetActiveAcademicTermService::class)->execute($campus->id)?->is($term))->toBeTrue();
});

test('it resolves an active term by real campus id', function () {
    $campus = SiteCampus::create([
        'campus_name' => 'KCC Campus',
        'real_campus_id' => '3',
    ]);

    $term = SiteAcademicTerm::create([
        'site_campus_id' => $campus->id,
        'school_year' => '2025-2026',
        'semester' => '1st Semester',
        'term_id' => '202',
        'status' => 'Active',
    ]);

    expect(app(GetActiveAcademicTermService::class)->execute(3)?->is($term))->toBeTrue();
});

test('it prefers a real campus id over a colliding site campus id', function () {
    $realCampus = SiteCampus::create([
        'campus_name' => 'KCC Campus',
        'real_campus_id' => '3',
    ]);

    SiteCampus::create([
        'campus_name' => 'Placeholder Campus',
        'real_campus_id' => '2',
    ]);

    $collidingSiteCampus = SiteCampus::create([
        'campus_name' => 'Other Campus',
        'real_campus_id' => '4',
    ]);

    $realCampusTerm = SiteAcademicTerm::create([
        'site_campus_id' => $realCampus->id,
        'school_year' => '2025-2026',
        'semester' => '1st Semester',
        'term_id' => '202',
        'status' => 'Active',
    ]);

    SiteAcademicTerm::create([
        'site_campus_id' => $collidingSiteCampus->id,
        'school_year' => '2025-2026',
        'semester' => '1st Semester',
        'term_id' => '303',
        'status' => 'Active',
    ]);

    expect(app(GetActiveAcademicTermService::class)->execute(3)?->is($realCampusTerm))->toBeTrue();
});
