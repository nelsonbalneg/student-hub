<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Http\Requests\Society\ReviewAccreditationRequest;
use App\Http\Requests\Society\StoreAccreditationRequest;
use App\Http\Requests\Society\StoreRequirementSubmissionRequest;
use App\Http\Requests\Society\UpdateRequirementReviewRequest;
use App\Models\Society;
use App\Models\SocietyAccreditationLog;
use App\Models\SocietyAccreditationRequest;
use App\Models\SocietyAccreditationRequirement;
use App\Models\SocietyOfficerPosition;
use App\Models\SocietyRequirementSubmission;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SocietyAccreditationController extends Controller
{
    public function index(Society $society)
    {
        return Inertia::render('Society/Student/Application', $this->studentPayload($society, 'application'));
    }

    public function store(StoreAccreditationRequest $request, Society $society)
    {
        $validated = $request->validated();

        if ($society->status === 'draft') {
            throw ValidationException::withMessages([
                'society' => 'Publish the society registration before creating an accreditation application.',
            ]);
        }

        $existing = $society->accreditationRequests()
            ->where('semester', $validated['semester'])
            ->where('school_year', $validated['school_year'])
            ->first();

        if ($existing) {
            return back()->with('error', 'This society already has an accreditation application for that semester and school year.');
        }

        $missingOfficerPositions = $this->missingRequiredOfficerPositions($society, $validated['school_year']);

        if ($missingOfficerPositions !== []) {
            throw ValidationException::withMessages([
                'school_year' => 'Set active officers for '.$validated['school_year'].' before creating an accreditation application. Missing: '.implode(', ', $missingOfficerPositions).'.',
            ]);
        }

        DB::transaction(function () use ($society, $validated): void {
            $accreditation = $society->accreditationRequests()->create($validated + [
                'status' => 'draft',
            ]);

            SocietyAccreditationRequirement::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->each(fn (SocietyAccreditationRequirement $requirement) => $accreditation->submissions()->firstOrCreate([
                    'requirement_id' => $requirement->id,
                ], [
                    'status' => 'pending',
                ]));

            $this->log($accreditation, 'created', 'Accreditation draft created.');
        });

        return back()->with('success', 'Accreditation application draft created.');
    }

    public function submit(Society $society, SocietyAccreditationRequest $accreditationRequest)
    {
        abort_unless($accreditationRequest->society_id === $society->id, 404);
        abort_if($accreditationRequest->isLocked(), 403);

        $accreditationRequest->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->log($accreditationRequest, 'submitted', 'Application submitted to OSA.');

        return back()->with('success', 'Application submitted to OSA.');
    }

    public function uploadRequirement(StoreRequirementSubmissionRequest $request, Society $society, SocietyAccreditationRequest $accreditationRequest)
    {
        abort_unless($accreditationRequest->society_id === $society->id, 404);
        abort_if($accreditationRequest->isLocked(), 403);

        $validated = $request->validated();
        $submission = $accreditationRequest->submissions()->firstOrCreate([
            'requirement_id' => $validated['requirement_id'],
        ]);

        $history = $submission->resubmission_history ?? [];
        if ($submission->file_path || $submission->remarks) {
            $history[] = [
                'file_path' => $submission->file_path,
                'original_file_name' => $submission->original_file_name,
                'remarks' => $submission->remarks,
                'status' => $submission->status,
                'submitted_at' => optional($submission->submitted_at)->toISOString(),
            ];
        }

        $path = $submission->file_path;
        $originalName = $submission->original_file_name;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("societies/{$society->id}/accreditations/{$accreditation->id}", 'public');
            $originalName = $file->getClientOriginalName();
        }

        $submission->update([
            'file_path' => $path,
            'original_file_name' => $originalName,
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'resubmission_count' => count($history),
            'resubmission_history' => $history,
        ]);

        $this->log($accreditation, 'requirement_submitted', 'Requirement submitted.', [
            'requirement_id' => $validated['requirement_id'],
        ]);

        return back()->with('success', 'Requirement submission saved.');
    }

    public function adminIndex(Request $request)
    {
        $status = $request->string('status')->toString();

        $applications = SocietyAccreditationRequest::query()
            ->with(['society', 'submissions'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Society/Admin/Applications', [
            'applications' => $applications,
            'filters' => ['status' => $status],
            'stats' => $this->adminStats(),
        ]);
    }

    public function pendingReview()
    {
        return $this->adminStatusPage('submitted', 'Pending Review');
    }

    public function returned()
    {
        return $this->adminStatusPage('returned', 'Returned Applications');
    }

    public function approved()
    {
        return $this->adminStatusPage('approved', 'Approved Societies');
    }

    public function rejected()
    {
        return $this->adminStatusPage('rejected', 'Rejected Applications');
    }

    public function reviewPage(SocietyAccreditationRequest $accreditationRequest)
    {
        $accreditationRequest->load([
            'society',
            'officers',
            'advisers',
            'members',
            'submissions.requirement',
            'logs.user',
            'approver',
            'receiver',
        ]);

        return Inertia::render('Society/Admin/Review', [
            'application' => $this->applicationSummary($accreditationRequest),
            'requirements' => SocietyAccreditationRequirement::query()->orderBy('sort_order')->get(),
            'stats' => $this->applicationStats($accreditationRequest),
        ]);
    }

    public function reviewRequirement(UpdateRequirementReviewRequest $request, SocietyAccreditationRequest $accreditationRequest, SocietyRequirementSubmission $submission)
    {
        abort_unless($submission->accreditation_request_id === $accreditationRequest->id, 404);

        $submission->update($request->validated() + [
            'checked_by' => $request->user()->id,
            'checked_at' => now(),
        ]);

        $this->log($accreditationRequest, 'requirement_reviewed', 'Requirement marked '.$request->validated('status').'.', [
            'submission_id' => $submission->id,
        ]);

        return back()->with('success', 'Requirement review saved.');
    }

    public function review(ReviewAccreditationRequest $request, SocietyAccreditationRequest $accreditationRequest)
    {
        $validated = $request->validated();
        $number = $accreditationRequest->accreditation_request_no ?: $this->nextRequestNumber();

        $updates = [
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'accreditation_request_no' => $number,
            'date_received' => $accreditationRequest->date_received ?: now()->toDateString(),
            'received_checked_by' => $accreditationRequest->received_checked_by ?: $request->user()->id,
        ];

        if ($validated['status'] === 'approved') {
            $updates['approved_at'] = now();
            $updates['approved_by'] = $request->user()->id;
        }

        if ($validated['status'] === 'returned') {
            $updates['returned_at'] = now();
        }

        $accreditation->update($updates);

        if ($validated['status'] === 'approved') {
            $accreditation->society()->update(['status' => 'accredited']);
        }

        $this->log($accreditation, $validated['status'], $validated['remarks'] ?? null);

        return back()->with('success', 'Accreditation application updated.');
    }

    public function manageRequirements()
    {
        return Inertia::render('Society/Admin/Requirements', [
            'requirements' => SocietyAccreditationRequirement::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeRequirement(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:120'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        SocietyAccreditationRequirement::query()->create($validated + [
            'sort_order' => ((int) SocietyAccreditationRequirement::max('sort_order')) + 1,
        ]);

        return back()->with('success', 'Requirement added.');
    }

    public function print(SocietyAccreditationRequest $accreditationRequest, string $type = 'summary')
    {
        $accreditationRequest->load(['society', 'officers', 'advisers', 'members', 'submissions.requirement', 'logs.user']);

        return Inertia::render('Society/Print/Accreditation', [
            'application' => $this->applicationSummary($accreditationRequest),
            'type' => $type,
            'generatedAt' => now()->format('F d, Y h:i A'),
        ]);
    }

    private function studentPayload(Society $society, string $section): array
    {
        $society->load([
            'accreditationRequests' => fn ($query) => $query->latest(),
            'accreditationRequests.submissions.requirement',
            'officers' => fn ($query) => $query->latest(),
            'advisers' => fn ($query) => $query->latest(),
            'members' => fn ($query) => $query->latest(),
            'bylaws',
            'announcements',
            'events',
        ]);

        $current = $society->accreditationRequests->first();

        return [
            'society' => $society,
            'section' => $section,
            'activeTerm' => $this->activeTermForUser(request()),
            'submitter' => [
                'name' => request()->user()?->name ?? '',
                'position' => request()->user()?->position
                    ?? request()->user()?->office
                    ?? request()->user()?->department
                    ?? '',
            ],
            'currentApplication' => $current ? $this->applicationSummary($current) : null,
            'requirements' => SocietyAccreditationRequirement::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'stats' => $current ? $this->applicationStats($current) : null,
            'officerReadiness' => [
                'school_year' => $this->activeTermForUser(request())['school_year'] ?? null,
                'missing_positions' => $this->missingRequiredOfficerPositions($society, $this->activeTermForUser(request())['school_year'] ?? null),
            ],
        ];
    }

    private function adminStatusPage(string $status, string $title)
    {
        return Inertia::render('Society/Admin/Applications', [
            'applications' => SocietyAccreditationRequest::query()
                ->with(['society', 'submissions'])
                ->where('status', $status)
                ->latest()
                ->paginate(12),
            'filters' => ['status' => $status],
            'title' => $title,
            'stats' => $this->adminStats(),
        ]);
    }

    private function applicationSummary(SocietyAccreditationRequest $accreditation): array
    {
        $submissions = $accreditation->submissions;
        $total = max($submissions->count(), 1);
        $complete = $submissions->whereIn('status', ['complete', 'not_applicable'])->count();

        return $accreditation->toArray() + [
            'society' => $accreditation->society,
            'officers' => $accreditation->officers,
            'advisers' => $accreditation->advisers,
            'members' => $accreditation->members,
            'submissions' => $submissions->values(),
            'logs' => $accreditation->logs,
            'completion_percentage' => round(($complete / $total) * 100),
        ];
    }

    private function applicationStats(SocietyAccreditationRequest $accreditation): array
    {
        $submissions = $accreditation->submissions;

        return [
            'total_requirements' => $submissions->count(),
            'complete_requirements' => $submissions->whereIn('status', ['complete', 'not_applicable'])->count(),
            'returned_requirements' => $submissions->where('status', 'returned')->count(),
            'officers' => $accreditation->officers()->count(),
            'advisers' => $accreditation->advisers()->count(),
            'members' => $accreditation->members()->count(),
        ];
    }

    private function adminStats(): array
    {
        return [
            'submitted' => SocietyAccreditationRequest::where('status', 'submitted')->count(),
            'under_review' => SocietyAccreditationRequest::where('status', 'under_review')->count(),
            'returned' => SocietyAccreditationRequest::where('status', 'returned')->count(),
            'approved' => SocietyAccreditationRequest::where('status', 'approved')->count(),
            'rejected' => SocietyAccreditationRequest::where('status', 'rejected')->count(),
        ];
    }

    private function nextRequestNumber(): string
    {
        return 'OSA-SCO-'.now()->format('Y').'-'.Str::padLeft((string) (SocietyAccreditationRequest::whereYear('created_at', now()->year)->count() + 1), 4, '0');
    }

    private function log(SocietyAccreditationRequest $accreditation, string $action, ?string $remarks = null, array $metadata = []): void
    {
        SocietyAccreditationLog::query()->create([
            'society_id' => $accreditation->society_id,
            'accreditation_request_id' => $accreditation->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => $metadata,
        ]);
    }

    private function activeTermForUser(Request $request): ?array
    {
        $campusId = $request->user()?->campus_id;

        if (! $campusId) {
            return null;
        }

        $campus = SiteCampus::query()
            ->where('id', $campusId)
            ->orWhere('real_campus_id', (string) $campusId)
            ->first();

        if (! $campus) {
            return null;
        }

        $term = SiteAcademicTerm::query()
            ->where('site_campus_id', $campus->id)
            ->where('status', 'Active')
            ->latest('start_date')
            ->latest()
            ->first();

        if (! $term) {
            return null;
        }

        return [
            'id' => $term->id,
            'campus_id' => $campus->id,
            'campus_name' => $campus->campus_name,
            'school_year' => $term->school_year,
            'semester' => $term->semester,
            'term_id' => $term->term_id,
            'status' => $term->status,
        ];
    }

    private function missingRequiredOfficerPositions(Society $society, ?string $schoolYear): array
    {
        if (! $schoolYear) {
            return SocietyOfficerPosition::query()
                ->where('is_required', true)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name')
                ->all();
        }

        $requiredPositions = SocietyOfficerPosition::query()
            ->where('is_required', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['name', 'slug']);

        $activeOfficerSlugs = $society->officers()
            ->where('school_year', $schoolYear)
            ->whereIn('status', ['Active', 'active'])
            ->pluck('position')
            ->map(fn (string $position) => Str::slug($position))
            ->all();

        return $requiredPositions
            ->reject(fn (SocietyOfficerPosition $position) => in_array($position->slug, $activeOfficerSlugs, true))
            ->pluck('name')
            ->values()
            ->all();
    }
}
