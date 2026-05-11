<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Training;
use App\Services\AcademicApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function __construct(private readonly AcademicApiService $academicApi) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;

        return Inertia::render('StudentProfile/Index', [
            'profile' => $this->academicApi->profileForStudent($studentNo, $tenantId),
            'achievements' => Achievement::where('user_id', $user->id)->latest('date_received')->get(),
            'trainings' => Training::where('user_id', $user->id)->latest('date_from')->get(),
        ]);
    }
}
