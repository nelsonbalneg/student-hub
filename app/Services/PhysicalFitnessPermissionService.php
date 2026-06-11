<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PhysicalFitnessPermissionService
{
    public const SETTING_KEY = 'pft_fillup_permission';

    public const PERMISSION_PE_PATHFIT_ONLY = 'pe_pathfit_only';

    public const PERMISSION_ALL_STUDENTS = 'all_students';

    public function __construct(
        private readonly AcademicApiService $academicApi,
        private readonly GetActiveAcademicTermService $activeTermService,
    ) {}

    public function setting(): string
    {
        $value = SiteSetting::query()
            ->where('key', self::SETTING_KEY)
            ->value('value');

        return in_array($value, [self::PERMISSION_PE_PATHFIT_ONLY, self::PERMISSION_ALL_STUDENTS], true)
            ? $value
            : self::PERMISSION_PE_PATHFIT_ONLY;
    }

    public function canFillUp(User $student): bool
    {
        if ($this->setting() === self::PERMISSION_ALL_STUDENTS) {
            return true;
        }

        return $this->hasPhysicalFitnessSubjectInActiveTerm($student);
    }

    public function hasPhysicalFitnessSubjectInActiveTerm(User $student): bool
    {
        $activeTermId = $this->activeTermService->execute($student->campus_id)?->term_id;

        if (blank($activeTermId)) {
            return false;
        }

        $studentNo = $this->academicApi->studentNumberFor($student);
        $tenantId = blank($student->tenant_id) ? null : (string) $student->tenant_id;
        $gradeReport = $this->academicApi->gradeReportForStudent($studentNo, $tenantId);

        if (! empty($gradeReport['error']) || ! is_array($gradeReport['data'] ?? null)) {
            return false;
        }

        try {
            return collect($gradeReport['data'])
                ->filter(fn ($term): bool => $this->termMatches($term, $activeTermId))
                ->flatMap(fn ($term): array => $this->gradeRows($term))
                ->contains(fn ($grade): bool => $this->isPhysicalFitnessSubject($grade));
        } catch (Throwable $exception) {
            Log::warning('Unable to evaluate PFT fill-up permission from grade report.', [
                'user_id' => $student->id,
                'active_term_id' => $activeTermId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function isPhysicalFitnessSubject(mixed $grade): bool
    {
        if (! is_array($grade)) {
            return false;
        }

        $code = mb_strtoupper($this->firstFilled($grade, [
            'courseCode',
            'course_code',
            'subjectCode',
            'subject_code',
            'code',
        ]));
        $title = mb_strtolower($this->firstFilled($grade, [
            'courseTitle',
            'course_title',
            'courseDescription',
            'course_description',
            'subjectDescription',
            'subject_description',
            'description',
            'title',
        ]));

        return str_contains($code, 'PATHFIT')
            || str_contains($code, 'PATH FIT')
            || preg_match('/(^|[^A-Z0-9])P\.?\s*E\.?([^A-Z0-9]|$)/', $code) === 1
            || str_contains($title, 'pathfit')
            || str_contains($title, 'physical fitness')
            || str_contains($title, 'exercise-based fitness')
            || str_contains($title, 'exercise based fitness');
    }

    private function termMatches(mixed $term, int|string $activeTermId): bool
    {
        if (! is_array($term)) {
            return false;
        }

        $termId = Arr::get($term, 'termId')
            ?? Arr::get($term, 'term_id')
            ?? Arr::get($term, 'semesterId')
            ?? Arr::get($term, 'semester_id')
            ?? Arr::get($term, 'id');

        return filled($termId) && (string) $termId === (string) $activeTermId;
    }

    /**
     * @return array<int, mixed>
     */
    private function gradeRows(mixed $term): array
    {
        if (! is_array($term)) {
            return [];
        }

        foreach (['grades', 'data', 'subjects', 'records', 'studentGrades'] as $key) {
            $rows = Arr::get($term, $key);

            if (is_array($rows)) {
                return array_is_list($rows) ? $rows : [$rows];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function firstFilled(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $value = Arr::get($source, $key);

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
