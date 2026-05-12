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
        $campusId = $activeSemester['campusId'] ?: 1;
        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);
        $evaluations = [];

        // Fetch evaluations for the active term resolved by the API service
        $result = $this->academicApi->facultyEvaluations($campusId, null, $studentNo);
        if (! empty($result['data']['subjects'])) {
            $evaluations = [
                'term_name' => $result['term_name'],
                'term_id' => $result['term_id'],
                'data' => $result['data']
            ];
        }

        return Inertia::render('Grades/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
            ],
            'gradeReport' => $gradeReport,
            'evaluations' => $evaluations,
        ]);
    }
}
