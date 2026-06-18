<?php

namespace App\Http\Controllers\SiteSettings;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\SiteSetting;
use App\Models\Training;
use App\Models\User;
use App\Services\PhysicalFitnessPermissionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteStudentProfileController extends Controller
{
    public function index(Request $request): Response
    {
        ($request->user()->can('site-settings.student-profile.view') || $request->user()->can('site-settings.view')) || abort(403);

        $requestedTab = $request->string('tab')->value();
        $activeTab = in_array($requestedTab, ['awards', 'trainings', 'physical-fitness'], true)
            ? $requestedTab
            : 'awards';
        $search = trim($request->string('search')->value());

        return Inertia::render('SiteSettings/StudentProfile/Index', [
            'activeTab' => $activeTab,
            'filters' => [
                'search' => $search,
            ],
            'students' => $this->studentOptions($search),
            'awards' => $this->awardPaginator($search),
            'trainings' => $this->trainingPaginator($search),
            'physicalFitnessSetting' => [
                'permission' => SiteSetting::query()
                    ->where('key', PhysicalFitnessPermissionService::SETTING_KEY)
                    ->value('value') ?? PhysicalFitnessPermissionService::PERMISSION_PE_PATHFIT_ONLY,
                'options' => [
                    [
                        'label' => 'PE/PATHFIT Students Only',
                        'value' => PhysicalFitnessPermissionService::PERMISSION_PE_PATHFIT_ONLY,
                    ],
                    [
                        'label' => 'All Students',
                        'value' => PhysicalFitnessPermissionService::PERMISSION_ALL_STUDENTS,
                    ],
                ],
            ],
            'can' => [
                'create' => $request->user()->can('site-settings.student-profile.create'),
                'update' => $request->user()->can('site-settings.student-profile.update'),
                'delete' => $request->user()->can('site-settings.student-profile.delete'),
                'managePhysicalFitnessPermission' => $request->user()->can('pft.permission.manage'),
            ],
        ]);
    }

    public function storeAward(Request $request): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.create') || abort(403);

        Achievement::query()->create($this->validateAward($request));

        return $this->toIndex('awards', 'Award created successfully.');
    }

    public function updateAward(Request $request, Achievement $achievement): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.update') || abort(403);

        $achievement->update($this->validateAward($request, includeStudent: false));

        return $this->toIndex('awards', 'Award updated successfully.');
    }

    public function searchStudents(Request $request): JsonResponse
    {
        ($request->user()->can('site-settings.student-profile.create')
            || $request->user()->can('site-settings.student-profile.update')) || abort(403);

        $search = trim($request->string('search')->value());

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $terms = collect(preg_split('/\s+/', $search))
            ->filter()
            ->take(3)
            ->values();

        $students = User::query()
            ->select(['id', 'name', 'email', 'student_no'])
            ->whereNotNull('student_no')
            ->where(function ($query) use ($search, $terms): void {
                $query->where('student_no', 'like', $search.'%')
                    ->orWhere('email', 'like', $search.'%')
                    ->orWhere('name', 'like', $search.'%');

                foreach ($terms as $term) {
                    $query->orWhere('name', 'like', $term.'%');
                }
            })
            ->orderByRaw(
                'CASE
                    WHEN student_no LIKE ? THEN 0
                    WHEN email LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END',
                [$search.'%', $search.'%', $search.'%'],
            )
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($students);
    }

    public function destroyAward(Request $request, Achievement $achievement): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.delete') || abort(403);

        $achievement->delete();

        return $this->toIndex('awards', 'Award deleted successfully.');
    }

    public function storeTraining(Request $request): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.create') || abort(403);

        Training::query()->create($this->validateTraining($request));

        return $this->toIndex('trainings', 'Training created successfully.');
    }

    public function updateTraining(Request $request, Training $training): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.update') || abort(403);

        $training->update($this->validateTraining($request));

        return $this->toIndex('trainings', 'Training updated successfully.');
    }

    public function destroyTraining(Request $request, Training $training): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.delete') || abort(403);

        $training->delete();

        return $this->toIndex('trainings', 'Training deleted successfully.');
    }

    public function updatePhysicalFitnessPermission(Request $request): RedirectResponse
    {
        $request->user()->can('site-settings.student-profile.update') || abort(403);

        $validated = $request->validate([
            'permission' => [
                'required',
                'string',
                'in:'.implode(',', [
                    PhysicalFitnessPermissionService::PERMISSION_PE_PATHFIT_ONLY,
                    PhysicalFitnessPermissionService::PERMISSION_ALL_STUDENTS,
                ]),
            ],
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => PhysicalFitnessPermissionService::SETTING_KEY],
            [
                'value' => $validated['permission'],
                'type' => 'string',
                'updated_by' => $request->user()->id,
            ],
        );

        return $this->toIndex('physical-fitness', 'Physical Fitness permission updated successfully.');
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null, student_no: string|null}>
     */
    private function studentOptions(string $search): array
    {
        return User::query()
            ->select(['id', 'name', 'email', 'student_no'])
            ->where(function ($query): void {
                $query->where('user_type', 'Student')
                    ->orWhereNotNull('student_no')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Student'));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_no', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_no' => $user->student_no,
            ])
            ->all();
    }

    private function awardPaginator(string $search): LengthAwarePaginator
    {
        return Achievement::query()
            ->with(['user:id,name,email,student_no'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('awarder', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('student_no', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('date_received')
            ->paginate(10, ['*'], 'awards_page')
            ->withQueryString();
    }

    private function trainingPaginator(string $search): LengthAwarePaginator
    {
        return Training::query()
            ->with(['user:id,name,email,student_no'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('organizer', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('student_no', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('date_from')
            ->paginate(10, ['*'], 'trainings_page')
            ->withQueryString();
    }

    /**
     * @return array{user_id?: int, title: string, date_received: string, awarder?: string|null, description?: string|null}
     */
    private function validateAward(Request $request, bool $includeStudent = true): array
    {
        return $request->validate([
            ...($includeStudent ? ['user_id' => ['required', 'integer', 'exists:users,id']] : []),
            'title' => ['required', 'string', 'max:255'],
            'date_received' => ['required', 'date'],
            'awarder' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array{user_id: int, title: string, date_from: string, date_to?: string|null, venue?: string|null, organizer?: string|null}
     */
    private function validateTraining(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'venue' => ['nullable', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function toIndex(string $tab, string $message): RedirectResponse
    {
        return to_route('site-settings.student-profile.index', ['tab' => $tab])
            ->with('success', $message);
    }
}
