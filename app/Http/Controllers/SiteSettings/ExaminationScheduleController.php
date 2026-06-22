<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettings\StoreExaminationScheduleRequest;
use App\Http\Requests\SiteSettings\UpdateExaminationScheduleRequest;
use App\Models\ExaminationSchedule;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ExaminationScheduleController extends Controller
{
    public function index()
    {
        Gate::authorize('examination-schedule.view');

        $schedules = ExaminationSchedule::with(['academicTerm', 'campus'])
            ->latest()
            ->paginate(15);

        $schedules->getCollection()->each(
            fn (ExaminationSchedule $schedule) => $this->normalizeSchedule($schedule),
        );

        $campuses = SiteCampus::select('id', 'campus_name as name')->orderBy('campus_name')->get();
        $terms = SiteAcademicTerm::selectRaw("id, site_campus_id, CONCAT(semester, ' - A.Y. ', school_year) as name")->orderBy('start_date', 'desc')->get();

        return Inertia::render('SiteSettings/ExaminationSchedule/Index', [
            'schedules' => $schedules,
            'campuses' => $campuses,
            'terms' => $terms,
        ]);
    }

    public function store(StoreExaminationScheduleRequest $request)
    {
        $this->ensureEndDateColumn();

        ExaminationSchedule::create($this->schedulePayload($request->validated()));

        return back()->with('success', 'Examination schedule created successfully.');
    }

    public function show(ExaminationSchedule $examinationSchedule)
    {
        Gate::authorize('examination-schedule.view');

        $examinationSchedule->load(['academicTerm', 'campus']);
        $this->normalizeSchedule($examinationSchedule);

        $campuses = SiteCampus::select('id', 'campus_name as name')->orderBy('campus_name')->get();
        $terms = SiteAcademicTerm::selectRaw("id, site_campus_id, CONCAT(semester, ' - A.Y. ', school_year) as name")
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('SiteSettings/ExaminationSchedule/Show', [
            'examinationSchedule' => $examinationSchedule,
            'campuses' => $campuses,
            'terms' => $terms,
        ]);
    }

    public function update(UpdateExaminationScheduleRequest $request, ExaminationSchedule $examinationSchedule)
    {
        $this->ensureEndDateColumn();

        $examinationSchedule->update($this->schedulePayload($request->validated()));

        return back()->with('success', 'Examination schedule updated successfully.');
    }

    public function destroy(ExaminationSchedule $examinationSchedule)
    {
        Gate::authorize('examination-schedule.delete');

        $examinationSchedule->delete();

        return redirect()->route('site-settings.examination-schedules.index')
            ->with('success', 'Examination schedule deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function schedulePayload(array $validated): array
    {
        if (Schema::hasColumn('examination_schedules', 'start_date')) {
            return $validated;
        }

        return [
            'title' => $validated['title'],
            'academic_term_id' => $validated['academic_term_id'],
            'campus_id' => $validated['campus_id'],
            'examination_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'start_time' => '00:00',
            'end_time' => '00:01',
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] === 'Published' ? 'Active' : 'Draft',
        ];
    }

    private function normalizeSchedule(ExaminationSchedule $schedule): void
    {
        if (! array_key_exists('start_date', $schedule->getAttributes())) {
            $schedule->setAttribute('start_date', $schedule->getAttribute('examination_date'));
        }

        if (in_array($schedule->status, ['Active', 'Closed'], true)) {
            $schedule->setAttribute('status', 'Published');
        }
    }

    private function ensureEndDateColumn(): void
    {
        if (Schema::hasColumn('examination_schedules', 'end_date')) {
            return;
        }

        Schema::table('examination_schedules', function ($table): void {
            $table->date('end_date')->nullable();
        });
    }
}
