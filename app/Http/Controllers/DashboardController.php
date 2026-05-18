<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AcademicApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AcademicApiService $academicApi) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $profile = $this->academicApi->profileForStudent($studentNo, (string) $user->tenant_id);

        $announcements = Announcement::published()
            ->with(['category', 'creator'])
            ->where(function ($query) use ($user) {
                $query->where('visibility', 'public')
                    ->orWhere(function ($q) use ($user) {
                        $q->whereHas('targets', function ($sub) use ($user) {
                            $sub->where(function ($inner) use ($user) {
                                $inner->whereNull('role_id')
                                    ->orWhereIn('role_id', $user->roles->pluck('id'));
                            })->where(function ($inner) use ($user) {
                                $inner->whereNull('office_id')
                                    ->orWhere('office_id', $user->office);
                            })->where(function ($inner) use ($user) {
                                $inner->whereNull('department_id')
                                    ->orWhere('department_id', $user->department);
                            })->where(function ($inner) use ($user) {
                                $inner->whereNull('campus_id')
                                    ->orWhere('campus_id', $user->campus_id);
                            });
                        });
                    });
            })
            ->orderBy('is_pinned', 'desc')
            ->orderBy('published_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'campus' => [
                'id' => $user->campus_id,
                'name' => $user->campus_name,
                'tenant_id' => $user->tenant_id,
            ],
            'activeSemesters' => $this->academicApi->activeSemestersForCampus($user->campus_id),
            'announcements' => $announcements,
            'studentProfile' => [
                'studentPicture' => data_get($profile, 'data.studentPicture'),
                'firstName' => data_get($profile, 'data.firstName'),
                'middlename' => data_get($profile, 'data.middlename'),
                'lastName' => data_get($profile, 'data.lastName'),
            ],
        ]);
    }
}
