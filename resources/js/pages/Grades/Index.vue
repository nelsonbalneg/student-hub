<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpenCheck,
    CalendarDays,
    ChevronDown,
    FileText,
    GraduationCap,
    MessageSquareQuote,
    Printer,
    TrendingUp,
    User,
} from 'lucide-vue-next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

type Student = {
    name: string;
    student_no: string | null;
    campus_name: string | null;
    tenant_id: string | null;
};

type GradeRecord = Record<string, unknown>;

type TermGroup = {
    term: string;
    section: string;
    creditUnits: string;
    gpa: string;
    rows: GradeRecord[];
};

type EvaluationSubject = {
    facultyId: string;
    facultyName: string;
    subjectId: number | string;
    subjectCode: string;
    subjectDescription: string;
    notCounted: boolean;
    status: string;
};

type EvaluationData = {
    studentNo: string;
    evaluationStatus: string;
    campusId: number;
    termId: number;
    totalSubjectsEnrolled: number;
    totalFacultyEvaluated: number;
    totalFacultyForEvaluation: number;
    subjects: EvaluationSubject[];
};

const props = defineProps<{
    student: Student;
    gradeReport: {
        data: GradeRecord[] | Record<string, unknown>;
        error: string | null;
    };
    evaluations: EvaluationData[] | EvaluationData | null;
}>();

const expandedTerms = ref<Record<string, boolean>>({});

const asArray = (value: unknown): GradeRecord[] => {
    if (Array.isArray(value)) {
        return value.filter(
            (item): item is GradeRecord =>
                item !== null && typeof item === 'object',
        );
    }

    if (value && typeof value === 'object') {
        const record = value as Record<string, unknown>;
        const nested = [
            'grades',
            'data',
            'subjects',
            'records',
            'studentGrades',
        ]
            .map((key) => record[key])
            .find(Array.isArray);

        return nested ? asArray(nested) : [record];
    }

    return [];
};

const pick = (row: GradeRecord, keys: string[]): string => {
    const value = keys
        .map((key) => row[key])
        .find((item) => item !== null && item !== undefined && item !== '');

    return value === null || value === undefined ? '-' : String(value);
};

const groupedGrades = computed<TermGroup[]>(() => {
    const groups = asArray(props.gradeReport.data)
        .map((term) => {
            const termName = pick(term, [
                'termName',
                'semesterName',
                'semester_name',
                'semester',
                'term',
            ]);
            const academicYear = pick(term, [
                'academicYear',
                'academic_year',
                'schoolYear',
                'school_year',
            ]);

            return {
                termId: pick(term, [
                    'termId',
                    'term_id',
                    'semesterId',
                    'semester_id',
                    'id',
                ]),
                term:
                    [academicYear, termName]
                        .filter((value) => value !== '-')
                        .join(' - ') || 'Grade Report',
                section: pick(term, ['sectionName', 'section_name', 'section']),
                creditUnits: pick(term, [
                    'totalCreditUnits',
                    'total_credit_units',
                    'totalUnits',
                    'total_units',
                ]),
                gpa: pick(term, ['gpa', 'GPA']),
                rows: asArray(term.grades),
            };
        })
        .filter((group) => group.rows.length);

    const finalGroups = [...groups].reverse();

    if (
        finalGroups.length > 0 &&
        Object.keys(expandedTerms.value).length === 0
    ) {
        expandedTerms.value[finalGroups[0].term] = true;
    }

    return finalGroups;
});

const records = computed(() =>
    groupedGrades.value.flatMap((group) => group.rows),
);

const totalCourses = computed(() => records.value.length);

const latestTerm = computed(() => groupedGrades.value[0]?.term ?? '-');

const totalUnits = computed(() => {
    const units = groupedGrades.value
        .map((group) => Number(group.creditUnits))
        .filter((unit) => Number.isFinite(unit));

    return units.length ? units.reduce((sum, unit) => sum + unit, 0) : '-';
});

const columns = [
    {
        label: 'Course',
        keys: [
            'courseCode',
            'course_code',
            'subjectCode',
            'subject_code',
            'code',
        ],
    },
    {
        label: 'Description',
        keys: [
            'courseTitle',
            'course_title',
            'courseDescription',
            'course_description',
            'subjectDescription',
            'subject_description',
            'description',
            'title',
        ],
    },
    {
        label: 'Units',
        keys: ['unit', 'units', 'creditUnits', 'credit_units', 'credits'],
    },
    {
        label: 'Midterm',
        keys: ['midTerm', 'midterm', 'mid_term'],
    },
    {
        label: 'Final',
        keys: ['final', 'final_exam'],
    },
    {
        label: 'Grade',
        keys: ['finalGrade', 'final_grade', 'grade', 'rating'],
    },
    {
        label: 'Remarks',
        keys: ['remarks', 'remark', 'status'],
    },
];

const numericValue = (row: GradeRecord, keys: string[]) => {
    const raw = pick(row, keys);
    const value = Number(raw);

    return Number.isFinite(value) ? value : null;
};

const printCOR = () => {
    window.print();
};

const termUnits = (group: TermGroup) => {
    const value = Number(group.creditUnits);

    if (Number.isFinite(value)) {
        return value;
    }

    return group.rows.reduce((sum, row) => {
        const units = Number(pick(row, columns[2].keys));

        return sum + (Number.isFinite(units) ? units : 0);
    }, 0);
};

const calculatedGpa = (rows: GradeRecord[]) => {
    const gradedRows = rows
        .map((row) => ({
            grade: numericValue(row, columns[5].keys),
            units: numericValue(row, columns[2].keys),
        }))
        .filter((row): row is { grade: number; units: number | null } => {
            return row.grade !== null;
        });

    if (!gradedRows.length) {
        return '-';
    }

    const rowsWithUnits = gradedRows.filter(
        (row) => row.units !== null && row.units > 0,
    );

    if (rowsWithUnits.length) {
        const totalWeighted = rowsWithUnits.reduce(
            (sum, row) => sum + row.grade * row.units,
            0,
        );
        const totalUnits = rowsWithUnits.reduce(
            (sum, row) => sum + row.units,
            0,
        );

        return (totalWeighted / totalUnits).toFixed(2);
    }

    return (
        gradedRows.reduce((sum, row) => sum + row.grade, 0) / gradedRows.length
    ).toFixed(2);
};

const averageGrade = computed(() => calculatedGpa(records.value));

const remarkClass = (remark: string) => {
    const normalized = remark.toLowerCase();

    if (normalized.includes('pass') || normalized === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (normalized === '-' || normalized.includes('progress')) {
        return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
    }

    return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
};

const page = usePage();

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const can = (permission?: string | string[]): boolean => {
    if (!permission) {
        return true;
    }

    const requiredPermissions = Array.isArray(permission)
        ? permission
        : [permission];

    return (
        roles.value.includes('Super Admin') ||
        requiredPermissions.some((requiredPermission) =>
            permissions.value.includes(requiredPermission),
        )
    );
};

/**
 * Evaluation Lookup Logic
 *
 * Pattern: Build a lookup map where key is `${termId}-${subjectId}`
 * Rule: grade.courseId === evaluationSubject.subjectId && grade.termId === evaluation.termId
 */
const evaluationLookup = computed(() => {
    if (!props.evaluations) return {};

    const map: Record<string, any> = {};

    // Normalize to array for consistent processing
    const evalArray = Array.isArray(props.evaluations)
        ? props.evaluations
        : [props.evaluations];

    evalArray.forEach((evalData) => {
        if (!evalData?.subjects || !evalData?.termId) return;

        const termId = String(evalData.termId);
        evalData.subjects.forEach((s) => {
            const subjectId = String(s.subjectId || '');
            if (subjectId) {
                map[`${termId}-${subjectId}`] = s;
            }
        });
    });

    return map;
});

const getEvaluationForGrade = (row: GradeRecord, termId?: string) => {
    // Determine the term ID for this specific grade row
    const rowTermId = String(termId || row.termId || row.term_id || '');
    const rowCourseId = String(
        row.courseId || row.course_id || row.subjectId || '',
    );

    if (!rowTermId || !rowCourseId) return null;

    // Check if we have a matching evaluation in our lookup map
    return evaluationLookup.value[`${rowTermId}-${rowCourseId}`] || null;
};

const isPendingEvaluation = (row: GradeRecord, termId?: string) => {
    const rowTermId = String(termId || row.termId || row.term_id || '');

    // ONLY apply this logic to Term 102 as requested
    if (rowTermId !== '102') return false;

    const evaluation = getEvaluationForGrade(row, rowTermId);
    if (!evaluation) return false;

    const status = (evaluation.status || '').toLowerCase().trim();

    // Hide grades if status matches "Pending for evaluation"
    return (
        status === 'pending for evaluation' ||
        status === 'pending' ||
        status.includes('evaluation')
    );
};

const groupHasPendingEvaluations = (group: TermGroup) => {
    return group.rows.some((row) => isPendingEvaluation(row, group.termId));
};
</script>

<template>
    <Head title="My Grades" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section
            class="rounded-lg border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-sky-50/60 shadow-sm dark:border-white/10 dark:from-slate-950 dark:via-slate-950 dark:to-sky-950/20"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                    >
                        <GraduationCap class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-lg font-bold text-slate-950 dark:text-white"
                        >
                            Grade Report
                        </h1>
                        <p
                            class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Academic performance, terms, grades, and official
                            course outcomes.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                    @click="printCOR"
                >
                    <Printer class="size-4" />
                    Print
                </button>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-sky-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-sky-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <BookOpenCheck class="size-4 text-sky-600" />
                        Courses
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ totalCourses }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-emerald-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-emerald-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <TrendingUp class="size-4 text-emerald-600" />
                        Average GWA
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ averageGrade }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-amber-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-amber-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <CalendarDays class="size-4 text-amber-600" />
                        Terms
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ groupedGrades.length }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-violet-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-violet-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <FileText class="size-4 text-violet-600" />
                        Units
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ totalUnits }}
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <section
                class="min-w-0 rounded-lg border border-slate-200 bg-gradient-to-br from-white via-white to-slate-50 shadow-sm dark:border-white/10 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Term Records
                        </h2>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ latestTerm }}
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:border-white/10 dark:text-slate-300"
                    >
                        {{ records.length }} rows
                    </span>
                </div>

                <div
                    v-if="gradeReport.error"
                    class="m-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ gradeReport.error }}</span>
                </div>

                <div
                    v-else-if="!records.length"
                    class="flex min-h-[320px] flex-col items-center justify-center gap-2 p-6 text-center"
                >
                    <GraduationCap
                        class="size-10 text-slate-300 dark:text-slate-700"
                    />
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        No grades available
                    </h3>
                    <p
                        class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        No grade records are currently linked to this SSO
                        account.
                    </p>
                </div>

                <div
                    v-else
                    class="divide-y divide-slate-200 dark:divide-white/10"
                >
                    <Collapsible
                        v-for="group in groupedGrades"
                        :key="group.term"
                        v-model:open="expandedTerms[group.term]"
                    >
                        <CollapsibleTrigger as-child>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="truncate text-sm font-bold text-slate-950 dark:text-white"
                                        >
                                            {{ group.term }}
                                        </h3>
                                        <span
                                            v-if="group.section !== '-'"
                                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-white/10 dark:text-slate-300"
                                        >
                                            Section {{ group.section }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ group.rows.length }} courses /
                                        {{ termUnits(group) }} units /
                                        <template
                                            v-if="
                                                groupHasPendingEvaluations(
                                                    group,
                                                )
                                            "
                                        >
                                            <span
                                                class="font-bold text-amber-600 dark:text-amber-400"
                                            >
                                                GPA Hidden (Evaluation Required)
                                            </span>
                                        </template>
                                        <template v-else>
                                            Calculated GPA
                                            {{ calculatedGpa(group.rows) }}
                                        </template>
                                    </p>
                                </div>
                                <ChevronDown
                                    class="size-4 shrink-0 text-slate-400 transition"
                                    :class="
                                        expandedTerms[group.term]
                                            ? 'rotate-180'
                                            : ''
                                    "
                                />
                            </button>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <div
                                class="overflow-x-auto border-t border-slate-200 dark:border-white/10"
                            >
                                <table class="w-full min-w-[900px] text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                    >
                                        <tr>
                                            <th class="px-4 py-3">Course</th>
                                            <th class="px-4 py-3 text-center">
                                                Units
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Midterm
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Final
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Grade
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Remarks
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-white/10"
                                    >
                                        <tr
                                            v-for="(
                                                row, rowIndex
                                            ) in group.rows"
                                            :key="rowIndex"
                                            class="hover:bg-slate-50/80 dark:hover:bg-white/5"
                                        >
                                            <td class="px-4 py-3">
                                                <div
                                                    class="flex flex-col gap-0.5"
                                                >
                                                    <span
                                                        class="font-bold text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            pick(
                                                                row,
                                                                columns[0].keys,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        class="max-w-[400px] truncate text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            pick(
                                                                row,
                                                                columns[1].keys,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-bold text-slate-700 dark:text-slate-200"
                                            >
                                                {{ pick(row, columns[2].keys) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                <template
                                                    v-if="
                                                        isPendingEvaluation(row)
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] text-slate-300 dark:text-slate-600"
                                                        >-</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[3].keys,
                                                        )
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                <template
                                                    v-if="
                                                        isPendingEvaluation(
                                                            row,
                                                            group.termId,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] text-slate-300 dark:text-slate-600"
                                                        >-</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[4].keys,
                                                        )
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top"
                                            >
                                                <template
                                                    v-if="
                                                        isPendingEvaluation(
                                                            row,
                                                            group.termId,
                                                        )
                                                    "
                                                >
                                                    <div
                                                        class="flex flex-col items-center gap-1"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex h-7 items-center justify-center gap-1.5 rounded-md bg-sky-600 px-3 text-[10px] font-bold text-white transition hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400"
                                                        >
                                                            <MessageSquareQuote
                                                                class="size-3"
                                                            />
                                                            Evaluate
                                                        </button>
                                                        <span
                                                            class="max-w-[120px] truncate text-[9px] font-bold text-slate-600 dark:text-slate-400"
                                                            v-if="
                                                                getEvaluationForGrade(
                                                                    row,
                                                                    group.termId,
                                                                )?.facultyName
                                                            "
                                                        >
                                                            {{
                                                                getEvaluationForGrade(
                                                                    row,
                                                                    group.termId,
                                                                )
                                                                    ?.facultyName
                                                            }}
                                                        </span>
                                                    </div>
                                                </template>
                                                <span
                                                    v-else
                                                    class="inline-flex min-w-10 items-center justify-center rounded-md bg-slate-900 px-2 py-1 text-xs font-bold text-white dark:bg-white dark:text-slate-950"
                                                >
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[5].keys,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top"
                                            >
                                                <template
                                                    v-if="
                                                        isPendingEvaluation(
                                                            row,
                                                            group.termId,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                                                    >
                                                        Evaluation Required
                                                    </span>
                                                </template>
                                                <span
                                                    v-else
                                                    class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="
                                                        remarkClass(
                                                            pick(
                                                                row,
                                                                columns[6].keys,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[6].keys,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </section>

            <aside class="grid content-start gap-4">
                <section
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm dark:border-white/10 dark:from-slate-950 dark:to-slate-900"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200"
                        >
                            <User class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <h2
                                class="truncate text-sm font-bold text-slate-950 dark:text-white"
                            >
                                {{ student.name }}
                            </h2>
                            <p
                                class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                Verified academic identity
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-2 text-xs">
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5"
                        >
                            <dt
                                class="font-bold text-slate-500 dark:text-slate-400"
                            >
                                Student No.
                            </dt>
                            <dd
                                class="truncate font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.student_no || '-' }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5"
                        >
                            <dt
                                class="font-bold text-slate-500 dark:text-slate-400"
                            >
                                Campus
                            </dt>
                            <dd
                                class="truncate font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.campus_name || '-' }}
                            </dd>
                        </div>
                        <div
                            v-if="evaluations"
                            class="flex flex-col gap-2 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <dt
                                    class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400"
                                >
                                    Eval API Status
                                </dt>
                                <dd
                                    class="truncate text-[10px] font-bold text-slate-900 dark:text-white"
                                >
                                    {{
                                        Object.keys(evaluationLookup).length
                                    }}
                                    subjects
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt
                                    class="text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Terms loaded
                                </dt>
                                <dd
                                    class="truncate text-[10px] font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        Array.isArray(props.evaluations)
                                            ? props.evaluations.length
                                            : 1
                                    }}
                                </dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="can('grades.view-record-health')"
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm dark:border-white/10 dark:from-slate-950 dark:to-slate-900"
                >
                    <h2
                        class="text-sm font-bold text-slate-950 dark:text-white"
                    >
                        Record Health
                    </h2>
                    <div class="mt-3 grid gap-2">
                        <div
                            class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-xs dark:border-white/10"
                        >
                            <span
                                class="font-bold text-slate-500 dark:text-slate-400"
                            >
                                Source
                            </span>
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                Academic API
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-xs dark:border-white/10"
                        >
                            <span
                                class="font-bold text-slate-500 dark:text-slate-400"
                            >
                                Tenant
                            </span>
                            <span
                                class="truncate font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.tenant_id || '-' }}
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
