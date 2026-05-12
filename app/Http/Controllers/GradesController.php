<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradesController extends Controller
{
    public function __construct(private readonly AcademicApiService $academicApi) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $termId = $activeSemester['termId'] ?: 102;
        $campusId = $activeSemester['campusId'];

        $evaluations = $this->academicApi->facultyEvaluations($campusId, (int) $termId, $studentNo);

        return Inertia::render('Grades/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
            ],
            'gradeReport' => $this->academicApi->gradeReportForStudent($studentNo, $tenantId),
            'evaluations' => $evaluations['data'],
        ]);
    }
}
