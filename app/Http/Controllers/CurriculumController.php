<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function __construct(private readonly AcademicApiService $academicApi) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;
        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $termId = $activeSemester['termId'] ?? null;

        return Inertia::render('Curriculum/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
            ],
            'activeTerm' => [
                'term_id' => $termId,
                'semester' => $activeSemester['semester'] ?? null,
            ],
            'curriculum' => $this->academicApi->curriculumForStudent($studentNo, $termId, $tenantId),
        ]);
    }
}
