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

        // ONLY fetch evaluations for Term 102 as requested
        $terms = collect($gradeReport['data'] ?? []);
        foreach ($terms as $termGroup) {
            $termId = $termGroup['termId'] ?? $termGroup['semesterId'] ?? $termGroup['id'] ?? null;
            if ($termId == 102) {
                $result = $this->academicApi->facultyEvaluations($campusId, (int) $termId, $studentNo);
                if (! empty($result['data']['subjects'])) {
                    $evaluations[] = $result['data'];
                }
            }
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
