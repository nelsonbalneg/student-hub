<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    Calendar,
    CheckCircle2,
    ChevronDown,
    Clock,
    FileText,
    GraduationCap,
    Layers,
    Library,
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

type CurriculumRecord = Record<string, any>;

type SemesterGroup = {
    semester: string;
    rows: CurriculumRecord[];
};

type YearGroup = {
    year: string;
    semesters: SemesterGroup[];
};

const props = defineProps<{
    student: Student;
    curriculum: {
        data: CurriculumRecord[] | Record<string, any>;
        error: string | null;
    };
}>();

const expandedSems = ref<Record<string, boolean>>({});

const asArray = (value: any): CurriculumRecord[] => {
    if (!value) return [];

    if (value.yearAndLevel && Array.isArray(value.yearAndLevel)) {
        return value.yearAndLevel.flatMap((group: any) =>
            (group.subjects || []).map((subject: any) => ({
                ...subject,
                yearTermDesc: group.yearTermDesc,
                yearLevelId: group.yearLevelId,
                yearTermId: group.yearTermId,
            })),
        );
    }

    if (Array.isArray(value)) {
        return value.filter(
            (item) => item !== null && typeof item === 'object',
        );
    }

    if (typeof value === 'object') {
        for (const key of [
            'curriculum',
            'data',
            'subjects',
            'records',
            'items',
            'details',
            'curriculumDetails',
            'list',
        ]) {
            if (Array.isArray(value[key])) {
                return asArray(value[key]);
            }
        }
    }

    return [];
};

const pick = (row: CurriculumRecord, keys: string[]): string => {
    const value = keys
        .map((key) => row[key])
        .find((item) => item !== null && item !== undefined && item !== '');
    return value === null || value === undefined ? '-' : String(value);
};

const processedData = computed(() => {
    const rawData = props.curriculum.data;
    let groups: YearGroup[] = [];

    if (
        rawData &&
        rawData.yearAndLevel &&
        Array.isArray(rawData.yearAndLevel)
    ) {
        const yearGroups: Record<
            string,
            Record<string, CurriculumRecord[]>
        > = {};

        rawData.yearAndLevel.forEach((group: any) => {
            const desc = group.yearTermDesc || 'Other';
            const [yearPart, semPart] = desc.split(' - ');
            const year = yearPart || 'Other';
            const semester = semPart || 'Other';

            if (!yearGroups[year]) yearGroups[year] = {};
            yearGroups[year][semester] = group.subjects || [];
        });

        groups = Object.entries(yearGroups).map(([year, semesters]) => ({
            year,
            semesters: Object.entries(semesters).map(([semester, rows]) => ({
                semester,
                rows,
            })),
        }));
    } else {
        const raw = asArray(rawData);
        const yearGroups: Record<
            string,
            Record<string, CurriculumRecord[]>
        > = {};

        raw.forEach((row) => {
            let year = pick(row, [
                'yearLevel',
                'year_level',
                'year',
                'yearLevelName',
                'year_level_name',
                'yearLevelId',
                'year_level_id',
            ]);
            if (year === '-' || year === '0') year = 'Other';
            if (year === '1') year = '1st Year';
            if (year === '2') year = '2nd Year';
            if (year === '3') year = '3rd Year';
            if (year === '4') year = '4th Year';

            let semester = pick(row, [
                'semester',
                'semesterName',
                'semester_name',
                'term',
                'semester_id',
                'semesterId',
            ]);
            if (semester === '-' || semester === '0') semester = 'Other';
            if (semester === '1') semester = '1st Semester';
            if (semester === '2') semester = '2nd Semester';
            if (semester === '3') semester = 'Summer';

            if (!yearGroups[year]) yearGroups[year] = {};
            if (!yearGroups[year][semester]) yearGroups[year][semester] = [];

            yearGroups[year][semester].push(row);
        });

        groups = Object.entries(yearGroups)
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([year, semesters]) => ({
                year,
                semesters: Object.entries(semesters)
                    .sort(([a], [b]) => a.localeCompare(b))
                    .map(([semester, rows]) => ({
                        semester,
                        rows,
                    })),
            }));
    }

    if (
        groups.length > 0 &&
        groups[0].semesters.length > 0 &&
        Object.keys(expandedSems.value).length === 0
    ) {
        expandedSems.value[
            `${groups[0].year}-${groups[0].semesters[0].semester}`
        ] = true;
    }

    return groups;
});

const totalSubjects = computed(() => asArray(props.curriculum.data).length);

const totalSemesters = computed(() =>
    processedData.value.reduce((sum, year) => sum + year.semesters.length, 0),
);

const subjectLookup = computed(() => {
    const lookup = new Map<string, string>();

    asArray(props.curriculum.data).forEach((row) => {
        const subjectId = pick(row, ['subjectId', 'subject_id', 'id']);
        const subjectCode = pick(row, [
            'subjectCode',
            'courseCode',
            'course_code',
            'subject_code',
            'code',
        ]);

        if (subjectId !== '-' && subjectCode !== '-') {
            lookup.set(subjectId, subjectCode);
        }
    });

    return lookup;
});

const columns = [
    {
        label: 'Code',
        keys: [
            'subjectCode',
            'courseCode',
            'course_code',
            'subject_code',
            'code',
            'subjectId',
            'subject_id',
        ],
    },
    {
        label: 'Description',
        keys: [
            'subjectDesc',
            'courseTitle',
            'course_title',
            'courseDescription',
            'course_description',
            'subjectDescription',
            'subject_description',
            'description',
            'title',
            'subjectName',
            'subject_name',
        ],
    },
    {
        label: 'Units',
        keys: [
            'acadUnits',
            'academicUnits',
            'academic_units',
            'totalUnits',
            'total_units',
            'totalCreditUnits',
            'total_credit_units',
            'unit',
            'units',
            'creditUnits',
            'credit_units',
            'credits',
            'units_load',
            'unitsLoad',
        ],
    },
    {
        label: 'Prerequisites',
        keys: [
            'preReqs',
            'prerequisites',
            'pre_requisites',
            'prereq',
            'pre_req',
            'preRequisite',
            'pre_requisite',
        ],
    },
    {
        label: 'Taken',
        keys: ['subjectTaken', 'subject_taken', 'taken', 'isTaken'],
    },
    {
        label: 'Final Grade',
        keys: ['finalGrade', 'final_grade', 'grade', 'rating'],
    },
    {
        label: 'Final Remarks',
        keys: ['finalRemarks', 'final_remarks', 'remarks', 'remark'],
    },
];

const lectureUnitKeys = [
    'acadUnits',
    'academicUnits',
    'academic_units',
    'lecUnits',
    'lectureUnits',
    'lecture_units',
    'lec_units',
];

const labUnitKeys = [
    'labUnits',
    'laboratoryUnits',
    'laboratory_units',
    'lab_units',
];

const totalUnitKeys = [
    'totalUnits',
    'total_units',
    'totalCreditUnits',
    'total_credit_units',
    'unit',
    'units',
    'creditUnits',
    'credit_units',
    'credits',
    'units_load',
    'unitsLoad',
];

const numericPick = (row: CurriculumRecord, keys: string[]): number | null => {
    for (const key of keys) {
        const value = row[key];

        if (value !== null && value !== undefined && value !== '') {
            const numeric = Number(value);

            if (Number.isFinite(numeric)) {
                return numeric;
            }
        }
    }

    return null;
};

const subjectLectureUnits = (row: CurriculumRecord) =>
    numericPick(row, lectureUnitKeys) ?? 0;

const subjectLabUnits = (row: CurriculumRecord) =>
    numericPick(row, labUnitKeys) ?? 0;

const subjectUnits = (row: CurriculumRecord) => {
    const lectureUnits = subjectLectureUnits(row);
    const labUnits = subjectLabUnits(row);

    if (lectureUnits > 0 || labUnits > 0) {
        return lectureUnits + labUnits;
    }

    return numericPick(row, totalUnitKeys) ?? 0;
};

const formatUnit = (units: number) =>
    units > 0 ? String(units).replace(/\.0$/, '') : '-';

const totalLectureUnits = computed(() =>
    asArray(props.curriculum.data).reduce(
        (sum, row) => sum + subjectLectureUnits(row),
        0,
    ),
);

const totalLabUnits = computed(() =>
    asArray(props.curriculum.data).reduce(
        (sum, row) => sum + subjectLabUnits(row),
        0,
    ),
);

const totalUnits = computed(() =>
    asArray(props.curriculum.data).reduce(
        (sum, row) => sum + subjectUnits(row),
        0,
    ),
);

const subjectLectureUnitsDisplay = (row: CurriculumRecord) =>
    formatUnit(subjectLectureUnits(row));

const subjectLabUnitsDisplay = (row: CurriculumRecord) =>
    formatUnit(subjectLabUnits(row));

const prerequisiteItems = (row: CurriculumRecord): string[] => {
    const prerequisites = row.preRequisites ?? row.pre_requisites;

    if (Array.isArray(prerequisites) && prerequisites.length > 0) {
        return prerequisites
            .map((prerequisite) => {
                if (!prerequisite || typeof prerequisite !== 'object') {
                    return null;
                }

                const subjectId = pick(prerequisite, [
                    'subjectId',
                    'subject_id',
                    'prerequisiteSubjectId',
                    'prerequisite_subject_id',
                ]);

                if (subjectId === '-') {
                    return null;
                }

                return subjectLookup.value.get(subjectId) ?? subjectId;
            })
            .filter((item): item is string => Boolean(item));
    }

    const textValue = pick(row, columns[3].keys);

    if (textValue === '-') {
        return [];
    }

    return textValue
        .split(/[,\n;/]+/)
        .map((item) => item.trim())
        .filter(Boolean);
};

const semesterUnits = (rows: CurriculumRecord[]) =>
    rows.reduce((sum, row) => sum + subjectUnits(row), 0);

const isTruthy = (value: unknown) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
        return ['true', '1', 'yes', 'y'].includes(value.trim().toLowerCase());
    }

    return false;
};

const takenStatus = (row: CurriculumRecord) => {
    const finalGrade = pick(row, columns[5].keys);
    const finalRemarks = pick(row, columns[6].keys);

    if (finalGrade !== '-' && finalRemarks.trim().toLowerCase() === 'failed') {
        return 'Failed';
    }

    const takenValue = columns[4].keys
        .map((key) => row[key])
        .find((value) => value !== null && value !== undefined && value !== '');

    return isTruthy(takenValue) ? 'Taken' : '';
};

const takenStatusClass = (status: string) => {
    if (status === 'Failed') {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
    }

    if (status === 'Taken') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    return '';
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
</script>

<template>

    <Head title="My Curriculum" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                        <Library class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold text-slate-950 dark:text-white">
                            Curriculum
                        </h1>
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">
                            Program structure, subjects, units, prerequisites,
                            and academic sequence.
                        </p>
                    </div>
                </div>

                <!-- <span
                    class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                >
                    <BookOpen class="size-4" />
                    Academic Program
                </span> -->
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-sky-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-sky-500/10">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <Layers class="size-4 text-sky-600" />
                        Subjects
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ totalSubjects }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-emerald-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-emerald-500/10">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <CheckCircle2 class="size-4 text-emerald-600" />
                        Total Units
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ formatUnit(totalUnits) }}
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5 text-[10px] font-bold uppercase">
                        <span
                            class="rounded-full border border-emerald-200 bg-white/80 px-2 py-0.5 text-emerald-700 dark:border-emerald-400/30 dark:bg-white/10 dark:text-emerald-300">
                            Lec {{ formatUnit(totalLectureUnits) }}
                        </span>
                        <span
                            class="rounded-full border border-cyan-200 bg-white/80 px-2 py-0.5 text-cyan-700 dark:border-cyan-400/30 dark:bg-white/10 dark:text-cyan-300">
                            Lab {{ formatUnit(totalLabUnits) }}
                        </span>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-amber-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-amber-500/10">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <GraduationCap class="size-4 text-amber-600" />
                        Year Levels
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ processedData.length }}
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-violet-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-violet-500/10">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <Calendar class="size-4 text-violet-600" />
                        Terms
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ totalSemesters }}
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <section
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                    <div>
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                            Program Sequence
                        </h2>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            {{ processedData.length }} year levels /
                            {{ totalSemesters }} terms
                        </p>
                    </div>
                </div>

                <div v-if="curriculum.error"
                    class="m-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ curriculum.error }}</span>
                </div>

                <div v-else-if="!processedData.length"
                    class="flex min-h-[320px] flex-col items-center justify-center gap-2 p-6 text-center">
                    <Library class="size-10 text-slate-300 dark:text-slate-700" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        No curriculum data
                    </h3>
                    <p class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400">
                        No curriculum records are currently available for your
                        program.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-200 dark:divide-white/10">
                    <div v-for="year in processedData" :key="year.year" class="bg-white dark:bg-slate-950">
                        <div class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-2.5 dark:bg-white/5">
                            <div class="flex min-w-0 items-center gap-2">
                                <GraduationCap class="size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                <h3 class="truncate text-xs font-bold text-slate-700 uppercase dark:text-slate-200">
                                    {{ year.year }}
                                </h3>
                            </div>
                            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">
                                {{ year.semesters.length }} terms
                            </span>
                        </div>

                        <Collapsible v-for="sem in year.semesters" :key="sem.semester" v-model:open="expandedSems[`${year.year}-${sem.semester}`]
                            " class="border-t border-slate-100 first:border-t-0 dark:border-white/10">
                            <CollapsibleTrigger as-child>
                                <button type="button"
                                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-white/5">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="truncate text-sm font-bold text-slate-950 dark:text-white">
                                                {{ sem.semester }}
                                            </h4>
                                            <span
                                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                                {{ sem.rows.length }} subjects
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                                            <Clock class="size-3" />
                                            {{ semesterUnits(sem.rows) }} units
                                        </p>
                                    </div>
                                    <ChevronDown class="size-4 shrink-0 text-slate-400 transition" :class="expandedSems[
                                            `${year.year}-${sem.semester}`
                                        ]
                                            ? 'rotate-180'
                                            : ''
                                        " />
                                </button>
                            </CollapsibleTrigger>

                            <CollapsibleContent>
                                <div class="overflow-x-auto border-t border-slate-200 dark:border-white/10">
                                    <table class="w-full min-w-[920px] text-sm">
                                        <thead
                                            class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400">
                                            <tr>
                                                <th class="px-4 py-3">
                                                    Subject
                                                </th>
                                                <th class="px-4 py-3">
                                                    Description
                                                </th>
                                                <th class="px-4 py-3 text-center">
                                                    Lecture Unit
                                                </th>
                                                <th class="px-4 py-3 text-center">
                                                    Lab Unit
                                                </th>
                                                <th class="px-4 py-3">
                                                    Prerequisites
                                                </th>
                                                <th class="px-4 py-3 text-center">
                                                    Is Taken
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                            <tr v-for="(row, index) in sem.rows" :key="index"
                                                class="hover:bg-slate-50/80 dark:hover:bg-white/5">
                                                <td
                                                    class="px-4 py-3 align-top text-xs font-bold text-slate-900 dark:text-white">
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[0].keys,
                                                        )
                                                    }}
                                                </td>
                                                <td
                                                    class="max-w-md px-4 py-3 align-top text-xs font-medium text-slate-600 dark:text-slate-300">
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[1].keys,
                                                        )
                                                    }}
                                                </td>
                                                <td class="px-4 py-3 text-center align-top">
                                                    <span
                                                        class="inline-flex min-w-9 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800 dark:bg-white/10 dark:text-slate-100">
                                                        {{ subjectLectureUnitsDisplay(row) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center align-top">
                                                    <span
                                                        class="inline-flex min-w-9 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800 dark:bg-white/10 dark:text-slate-100">
                                                        {{ subjectLabUnitsDisplay(row) }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-4 py-3 align-top text-xs font-medium text-slate-500 dark:text-slate-400">
                                                    <div v-if="prerequisiteItems(row).length"
                                                        class="flex max-w-xs flex-wrap gap-1.5">
                                                        <span v-for="prerequisite in prerequisiteItems(row)"
                                                            :key="prerequisite"
                                                            class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300">
                                                            {{ prerequisite }}
                                                        </span>
                                                    </div>
                                                    <span v-else class="text-slate-400">
                                                        None
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center align-top">
                                                    <span v-if="
                                                        takenStatus(row) !==
                                                        ''
                                                    "
                                                        class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                        :class="takenStatusClass(
                                                            takenStatus(
                                                                row,
                                                            ),
                                                        )
                                                            ">
                                                        {{ takenStatus(row) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </div>
            </section>

            <aside class="grid content-start gap-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            <User class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-bold text-slate-950 dark:text-white">
                                {{ student.name }}
                            </h2>
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">
                                Synced from One USM SSO
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-2 text-xs">
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5">
                            <dt class="font-bold text-slate-500 dark:text-slate-400">
                                Student No.
                            </dt>
                            <dd class="truncate font-bold text-slate-900 dark:text-white">
                                {{ student.student_no || '-' }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5">
                            <dt class="font-bold text-slate-500 dark:text-slate-400">
                                Campus
                            </dt>
                            <dd class="truncate font-bold text-slate-900 dark:text-white">
                                {{ student.campus_name || '-' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="can('curriculum.view-record-health')"
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                        Curriculum Health
                    </h2>
                    <div class="mt-3 grid gap-2">
                        <div
                            class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-xs dark:border-white/10">
                            <span class="font-bold text-slate-500 dark:text-slate-400">
                                Source
                            </span>
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                Academic API
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-xs dark:border-white/10">
                            <span class="font-bold text-slate-500 dark:text-slate-400">
                                Tenant
                            </span>
                            <span class="truncate font-bold text-slate-900 dark:text-white">
                                {{ student.tenant_id || '-' }}
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
