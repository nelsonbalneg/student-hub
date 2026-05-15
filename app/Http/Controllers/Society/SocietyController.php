<?php

namespace App\Http\Controllers\Society;

use App\Http\Controllers\Controller;
use App\Http\Requests\Society\StoreSocietyProfileRequest;
use App\Models\Society;
use App\Models\SocietyAccreditation;
use App\Models\SocietyAccreditationRequirement;
use App\Models\SocietyAdviser;
use App\Models\SocietyMember;
use App\Models\SocietyOfficer;
use App\Models\SocietyOfficerPosition;
use App\Models\SiteAcademicTerm;
use App\Models\SiteCampus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SocietyController extends Controller
{
    public function index(Request $request)
    {
        $query = Society::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('acronym', 'like', "%{$request->search}%");
            });
        }

        $societies = $query->with(['accreditationRequests' => function($q) {
            $q->where('status', 'approved')->latest();
        }])->paginate(12)->withQueryString();

        return Inertia::render('Society/Student/Index', [
            'societies' => $societies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(Society $society)
    {
        return Inertia::render('Society/Student/Show', [
            'society' => $society->load([
                'officers.student',
                'accreditations' => fn($q) => $q->where('status', 'Approved')->latest(),
            ])->loadCount(['memberships' => fn($q) => $q->where('status', 'Approved')]),
            'is_member' => $society->memberships()->where('student_id', auth()->id())->exists(),
            'membership_status' => $society->memberships()->where('student_id', auth()->id())->first()?->status,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Society::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('acronym', 'like', "%{$request->search}%");
            });
        }

        $societies = $query->whereHas('accreditationRequests', fn ($q) => $q->where('status', 'approved'))
            ->withCount(['members', 'events'])
            ->with(['accreditationRequests' => fn($q) => $q->latest()])
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Society/Admin/Accredited', [
            'societies' => $societies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function registration()
    {
        $activeTerm = $this->activeTermForUser(request());

        return Inertia::render('Society/Student/Profile', [
            'society' => null,
            'section' => 'profile',
            'activeTerm' => $activeTerm,
            'registeredSocieties' => Society::query()
                ->where('created_by', auth()->id())
                ->with(['accreditationRequests' => fn ($query) => $query->latest()])
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreSocietyProfileRequest $request)
    {
        $existingSociety = Society::query()
            ->where('created_by', $request->user()->id)
            ->first();

        if ($existingSociety) {
            throw ValidationException::withMessages([
                'full_name' => 'You already registered a society. Open the existing society and renew its accreditation for the active semester.',
            ]);
        }

        $validated = $request->validated();

        $society = Society::query()->create([
            'full_name' => $validated['full_name'],
            'name' => $validated['full_name'],
            'abbreviation' => $validated['abbreviation'] ?? null,
            'acronym' => $validated['abbreviation'] ?? null,
            'category' => $validated['category'] ?? null,
            'society_type' => $validated['category'] ?? null,
            'college_unit' => $validated['college_unit'] ?? null,
            'college' => $validated['college_unit'] ?? null,
            'description' => $validated['description'] ?? null,
            'facebook_page_url' => $validated['facebook_page_url'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('societies.manage.accreditation.index', $society)->with('success', 'Society profile created.');
    }

    public function update(StoreSocietyProfileRequest $request, Society $society)
    {
        abort_if($society->accreditationRequests()->where('status', 'approved')->latest()->first()?->isLocked(), 403);

        $validated = $request->validated();
        $society->update([
            'full_name' => $validated['full_name'],
            'name' => $validated['full_name'],
            'abbreviation' => $validated['abbreviation'] ?? null,
            'acronym' => $validated['abbreviation'] ?? null,
            'category' => $validated['category'] ?? null,
            'society_type' => $validated['category'] ?? null,
            'college_unit' => $validated['college_unit'] ?? null,
            'college' => $validated['college_unit'] ?? null,
            'description' => $validated['description'] ?? null,
            'facebook_page_url' => $validated['facebook_page_url'] ?? null,
        ]);

        return back()->with('success', 'Society profile updated.');
    }

    public function destroy(Request $request, Society $society)
    {
        abort_unless((int) $society->created_by === (int) $request->user()->id, 403);

        $hasAccreditationHistory = $society->accreditationRequests()
            ->where('status', '!=', 'draft')
            ->exists();

        if ($hasAccreditationHistory || $society->accreditations()->exists()) {
            throw ValidationException::withMessages([
                'society' => 'This society already has accreditation history and cannot be deleted. Ask OSA to review or archive it instead.',
            ]);
        }

        DB::transaction(function () use ($society) {
            $applicationIds = $society->accreditationRequests()->pluck('id');

            DB::table('society_event_attendances')->where('society_id', $society->id)->delete();
            DB::table('society_events')->where('society_id', $society->id)->delete();
            DB::table('society_announcements')->where('society_id', $society->id)->delete();
            DB::table('society_bylaws')->where('society_id', $society->id)->delete();
            DB::table('society_documents')->where('society_id', $society->id)->delete();
            DB::table('society_settings')->where('society_id', $society->id)->delete();
            DB::table('society_memberships')->where('society_id', $society->id)->delete();
            DB::table('society_accreditations')->where('society_id', $society->id)->delete();
            DB::table('society_officers')->where('society_id', $society->id)->delete();
            DB::table('society_advisers')->where('society_id', $society->id)->delete();
            DB::table('society_members')->where('society_id', $society->id)->delete();
            DB::table('society_accreditation_logs')->where('society_id', $society->id)->delete();

            if ($applicationIds->isNotEmpty()) {
                DB::table('society_requirement_submissions')
                    ->whereIn('accreditation_request_id', $applicationIds)
                    ->delete();
                DB::table('society_accreditation_logs')
                    ->whereIn('accreditation_request_id', $applicationIds)
                    ->delete();
                DB::table('society_accreditation_requests')
                    ->whereIn('id', $applicationIds)
                    ->delete();
            }

            $society->delete();
        });

        return redirect()->route('societies.registration')->with('success', 'Society registration deleted.');
    }

    public function mySociety()
    {
        $society = Society::query()
            ->where('created_by', auth()->id())
            ->with(['accreditationRequests.submissions.requirement', 'officers', 'advisers', 'members'])
            ->latest()
            ->first();

        return Inertia::render('Society/Student/MySociety', [
            'society' => $society,
            'requirements' => SocietyAccreditationRequirement::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function manageProfile(Society $society)
    {
        $activeTerm = $this->activeTermForUser(request());

        return Inertia::render('Society/Student/Profile', [
            'society' => $society,
            'section' => 'profile',
            'activeTerm' => $activeTerm,
            'registeredSocieties' => Society::query()
                ->where('created_by', auth()->id())
                ->with(['accreditationRequests' => fn ($query) => $query->latest()])
                ->latest()
                ->get(),
        ]);
    }

    public function manageOfficers(Society $society)
    {
        return Inertia::render('Society/Manage/Officers', $this->rosterPayload($society, 'officers'));
    }

    public function manageAdvisers(Society $society)
    {
        return Inertia::render('Society/Manage/Advisers', $this->rosterPayload($society, 'advisers'));
    }

    public function manageMembers(Society $society)
    {
        return Inertia::render('Society/Manage/Members', $this->rosterPayload($society, 'members'));
    }

    public function searchStudents(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $terms = collect(preg_split('/\s+/', $search))
            ->filter()
            ->take(3)
            ->values();

        $students = User::query()
            ->select(['id', 'name', 'email', 'student_no', 'campus_id', 'campus_name'])
            ->whereNotNull('student_no')
            ->where(function ($query) use ($search, $terms) {
                $query->where('student_no', 'like', $search.'%')
                    ->orWhere('email', 'like', $search.'%')
                    ->orWhere('name', 'like', $search.'%');

                foreach ($terms as $term) {
                    $query->orWhere('name', 'like', $term.'%');
                }
            })
            ->orderByRaw("
                CASE
                    WHEN student_no LIKE ? THEN 0
                    WHEN email LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END
            ", [$search.'%', $search.'%', $search.'%'])
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'student_no' => $student->student_no,
                'campus_id' => $student->campus_id,
                'campus_name' => $student->campus_name,
            ]);

        return response()->json($students);
    }

    public function storeOfficer(Request $request, Society $society)
    {
        $validated = $request->validate([
            'accreditation_request_id' => ['nullable', 'exists:society_accreditation_requests,id'],
            'position' => ['required', 'string', 'max:120'],
            'student_id' => ['nullable', 'exists:users,id'],
            'student_identifier' => ['nullable', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'max:255'],
            'year_course_section' => ['nullable', 'string', 'max:120'],
            'permanent_address' => ['nullable', 'string'],
            'usm_email' => ['nullable', 'email', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:80'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', 'max:80'],
        ]);

        SocietyOfficer::query()->create($validated + [
            'society_id' => $society->id,
            'position' => $validated['position'],
            'status' => 'Active',
        ]);

        return back()->with('success', 'Officer record saved.');
    }

    public function updateOfficer(Request $request, Society $society, SocietyOfficer $officer)
    {
        abort_unless((int) $officer->society_id === (int) $society->id, 404);

        $validated = $request->validate([
            'accreditation_request_id' => ['nullable', 'exists:society_accreditation_requests,id'],
            'position' => ['required', 'string', 'max:120'],
            'student_id' => ['nullable', 'exists:users,id'],
            'student_identifier' => ['nullable', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'max:255'],
            'year_course_section' => ['nullable', 'string', 'max:120'],
            'permanent_address' => ['nullable', 'string'],
            'usm_email' => ['nullable', 'email', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:80'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', 'max:80'],
        ]);

        $officer->update($validated + [
            'status' => $officer->status ?? 'Active',
        ]);

        return back()->with('success', 'Officer record updated.');
    }

    public function destroyOfficer(Society $society, SocietyOfficer $officer)
    {
        abort_unless((int) $officer->society_id === (int) $society->id, 404);

        $officer->delete();

        return back()->with('success', 'Officer record deleted.');
    }

    public function storeAdviser(Request $request, Society $society)
    {
        $validated = $request->validate([
            'accreditation_request_id' => ['nullable', 'exists:society_accreditation_requests,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'college_unit' => ['nullable', 'string', 'max:255'],
            'usm_email' => ['nullable', 'email', 'max:255'],
            'signature' => ['nullable', 'string', 'max:255'],
            'commitment_form_accepted' => ['accepted'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', 'max:80'],
        ]);

        SocietyAdviser::query()->create($validated + [
            'society_id' => $society->id,
            'commitment_date' => now()->toDateString(),
            'commitment_acknowledgements' => [
                'supervise_organization',
                'attend_important_meetings_activities',
                'prohibit_hazing',
                'observe_membership_limitations',
                'accept_welfare_responsibility',
            ],
        ]);

        return back()->with('success', 'Adviser commitment saved.');
    }

    public function storeMember(Request $request, Society $society)
    {
        $validated = $request->validate([
            'accreditation_request_id' => ['nullable', 'exists:society_accreditation_requests,id'],
            'student_id' => ['nullable', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'max:255'],
            'year_course_section' => ['nullable', 'string', 'max:120'],
            'usm_email' => ['nullable', 'email', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:80'],
            'membership_type' => ['nullable', 'string', 'max:80'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', 'max:80'],
        ]);

        if ($validated['semester'] !== '1st Semester') {
            return back()->with('error', 'Recruitment and initiation of new members is allowed only at the start of the 1st semester.');
        }

        SocietyMember::query()->create($validated + [
            'society_id' => $society->id,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        return back()->with('success', 'Member record saved.');
    }

    public function manageBylaws(Society $society)
    {
        return Inertia::render('Society/Student/Workspace', $this->workspacePayload($society, 'bylaws'));
    }

    public function adminShow(Society $society)
    {
        return Inertia::render('Society/Admin/Details', $this->adminPayload($society, 'details'));
    }

    public function adminOfficers(Society $society)
    {
        return Inertia::render('Society/Admin/Details', $this->adminPayload($society, 'officers'));
    }

    public function adminBylaws(Society $society)
    {
        return Inertia::render('Society/Admin/Details', $this->adminPayload($society, 'bylaws'));
    }

    public function reopen(Society $society)
    {
        $application = $society->accreditationRequests()->where('status', 'approved')->latest()->firstOrFail();
        $application->update([
            'status' => 'returned',
            'reopened_at' => now(),
            'remarks' => 'Reopened by OSA for correction.',
        ]);

        return back()->with('success', 'Approved application reopened for correction.');
    }

    private function rosterPayload(Society $society, string $section): array
    {
        $society->load(['accreditationRequests' => fn ($query) => $query->latest(), 'officers', 'advisers', 'members']);

        return [
            'society' => $society,
            'section' => $section,
            'activeTerm' => $this->activeTermForUser(request()),
            'currentApplication' => $society->accreditationRequests->first(),
            'officerPositions' => $section === 'officers'
                ? SocietyOfficerPosition::query()->where('is_active', true)->orderBy('sort_order')->get()
                : [],
        ];
    }

    private function workspacePayload(Society $society, string $section): array
    {
        $society->load(['bylaws', 'announcements', 'events']);

        return [
            'society' => $society,
            'section' => $section,
        ];
    }

    private function adminPayload(Society $society, string $section): array
    {
        $society->load([
            'accreditationRequests.submissions.requirement',
            'accreditationRequests.logs.user',
            'officers',
            'advisers',
            'members',
            'bylaws',
            'announcements',
            'events',
        ])->loadCount(['members', 'officers', 'advisers', 'events']);

        return [
            'society' => $society,
            'section' => $section,
        ];
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
}
