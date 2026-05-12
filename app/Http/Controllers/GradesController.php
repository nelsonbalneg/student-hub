<?php

namespace App\Http\Controllers;

use App\Services\AcademicApiService;
use App\Services\StudentEvaluationApiService;
use App\Services\GetActiveAcademicTermService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class GradesController extends Controller
{
    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly StudentEvaluationApiService $evaluationApi,
        private readonly GetActiveAcademicTermService $activeTermService
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $studentNo = $this->academicApi->studentNumberFor($user);
        $tenantId = blank($user->tenant_id) ? null : (string) $user->tenant_id;

        $activeSemester = $this->academicApi->getActiveSemesterForUser($user);
        $campusId = $activeSemester['campusId'] ?: 1;
        
        // Check for grade viewing bypass rule
        $bypassEvaluation = \App\Models\GradeViewingRule::where('site_campus_id', $user->campus_id)
            ->where('is_active', true)
            ->where('bypass_evaluation', true)
            ->exists();

        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);
        
        $evaluationError = null;
        $evaluationLookup = [];
        $activeTerm = $this->activeTermService->execute($user->campus_id);
        $activeTermId = $activeTerm?->term_id;

        if (!$bypassEvaluation && $studentNo) {
            try {
                $evaluationId = $this->evaluationApi->findStudentByStudentNo($studentNo);
                
                if ($evaluationId) {
                    $details = $this->evaluationApi->getStudentEvaluationDetails($evaluationId);
                    $evaluationLookup = $this->evaluationApi->buildEvaluationLookup($details['evaluationPeriods'] ?? []);
                }
            } catch (\Exception $e) {
                Log::error('Evaluation API error in GradesController', [
                    'student_no' => $studentNo,
                    'message' => $e->getMessage()
                ]);
                $evaluationError = 'Faculty evaluation status could not be verified. Please try again later.';
            }
        }

        // Enrich grade data with evaluation status
        if (!empty($gradeReport['data']) && is_array($gradeReport['data'])) {
            $gradeReport['data'] = array_map(function ($term) use ($evaluationLookup, $evaluationId, $activeTermId) {
                if (!isset($term['grades']) || !is_array($term['grades'])) {
                    return $term;
                }

                $termId = $term['termId'] ?? $term['term_id'] ?? null;

                $term['grades'] = array_map(function ($grade) use ($evaluationLookup, $evaluationId, $termId, $activeTermId) {
                    // Match the termId and subjectId/courseId
                    // Use the termId from the parent term if not in the grade record
                    $rowTermId = $grade['termId'] ?? $grade['term_id'] ?? $termId;
                    
                    // ONLY trap if the term matches the ACTIVE TERM in site settings
                    if ($activeTermId && (string) $rowTermId !== (string) $activeTermId) {
                        $grade['requires_evaluation'] = false;
                        return $grade;
                    }

                    $courseId = $grade['courseId'] ?? $grade['course_id'] ?? $grade['subjectId'] ?? $grade['subject_id'] ?? null;
                    
                    $key = "{$rowTermId}-{$courseId}";
                    $eval = $evaluationLookup[$key] ?? null;
                    
                    $grade['requires_evaluation'] = $eval ? $eval['requires_evaluation'] : false;
                    $grade['evaluation_status'] = $eval['status'] ?? 'Not Found';
                    
                    if ($eval) {
                        $grade['evaluation_period_id'] = $eval['evaluation_period_id'];
                        $grade['subject_for_evaluation_id'] = $eval['subject_for_evaluation_id'];
                        $grade['subject_id'] = $eval['subject_id'];
                        $grade['subject_title'] = $eval['subject_title'];
                        $grade['pending_evaluations'] = $eval['pending_evaluations'];
                        $grade['faculty_names'] = $eval['faculty_names'];
                        
                        // Build the evaluation payload for the frontend
                        $grade['evaluation_payload'] = array_merge($eval['evaluation_payload'], [
                            'studentId' => $evaluationId,
                        ]);
                    }

                    return $grade;
                }, $term['grades']);

                return $term;
            }, $gradeReport['data']);
        }

        return Inertia::render('Grades/Index', [
            'student' => [
                'name' => $user->name,
                'student_no' => $studentNo,
                'campus_name' => $user->campus_name,
                'tenant_id' => $tenantId,
                'bypass_evaluation' => $bypassEvaluation,
                'evaluation_id' => $evaluationId,
            ],
            'gradeReport' => $gradeReport,
            'evaluation_error' => $evaluationError,
        ]);
    }
}
