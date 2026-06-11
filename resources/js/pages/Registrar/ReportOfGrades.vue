<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    FileSearch,
    Printer,
    RefreshCw,
    Search,
    ServerCrash,
    User,
    UserX,
    WifiOff,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    index as reportOfGradesIndex,
    search as searchReportOfGrades,
} from '@/routes/admin/registrar/report-of-grades';

type Campus = {
    id: number | string;
    name: string;
    tenant_id: number | null;
};

type GradeRow = Record<string, any>;

type TermRecord = {
    termId?: number | string;
    termName?: string;
    academicYear?: string;
    sectionName?: string;
    enrolledSubjects?: number;
    totalCreditUnits?: number | string;
    totalLectureUnits?: number | string;
    totalLabUnits?: number | string;
    gpa?: number | string;
    computed_gpa_display?: string;
    computed_counted_units_display?: string;
    grades?: GradeRow[];
    [key: string]: any;
};

type SearchResult = {
    student_no: string;
    campus: Campus;
    terms: TermRecord[];
    summary?: Record<string, any>;
    profile?: Record<string, any> | null;
} | null;

const props = defineProps<{
    campuses: Campus[];
    filters: {
        student_no: string;
        campus_id: string | number;
    };
    result: SearchResult;
    error: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Registrar', href: '/admin/registrar/report-of-grades' },
            {
                title: 'Report of Grades',
                href: '/admin/registrar/report-of-grades',
            },
        ],
    },
});

const loading = ref(false);
const form = useForm({
    student_no: props.filters.student_no ?? '',
    campus_id: props.filters.campus_id ? String(props.filters.campus_id) : '',
});

const selectedCampus = computed(
    () => props.campuses.find((campus) => String(campus.id) === form.campus_id) ?? null,
);

const submit = () => {
    form.post(searchReportOfGrades.url(), {
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const reset = () => {
    form.reset();
    router.get(reportOfGradesIndex.url());
};

const valueFrom = (row: Record<string, any>, keys: string[], fallback = '-') => {
    const value = keys
        .map((key) => row[key])
        .find((item) => item !== null && item !== undefined && item !== '');

    return value === null || value === undefined || value === '' ? fallback : String(value);
};

const termTitle = (term: TermRecord) =>
    [
        valueFrom(term, ['academicYear', 'academic_year', 'schoolYear', 'school_year'], ''),
        valueFrom(term, ['termName', 'term_name', 'semester', 'schoolTerm'], ''),
    ]
        .filter(Boolean)
        .join(' - ') || 'Academic Term';

const termGrades = (term: TermRecord) => (Array.isArray(term.grades) ? term.grades : []);

const normalizedRemark = (grade: GradeRow) =>
    valueFrom(grade, ['remarks', 'remark', 'status'], '').trim().toUpperCase();

const rowStatusClass = (grade: GradeRow) => {
    const remark = normalizedRemark(grade);

    if (remark.includes('FAILED') || remark === 'FAIL') {
        return 'registrar-row-failed';
    }

    if (remark === 'IP' || remark.includes('IN PROGRESS')) {
        return 'registrar-row-ip';
    }

    if (remark === 'INC' || remark.includes('INCOMPLETE')) {
        return 'registrar-row-inc';
    }

    return '';
};

const remarkBadgeClass = (grade: GradeRow) => {
    const remark = normalizedRemark(grade);

    if (remark.includes('FAILED') || remark === 'FAIL') {
        return 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30';
    }

    if (remark === 'IP' || remark.includes('IN PROGRESS')) {
        return 'bg-yellow-50 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-300 dark:ring-yellow-500/30';
    }

    if (remark === 'INC' || remark.includes('INCOMPLETE')) {
        return 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/30';
    }

    return 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10';
};

const formatDateTime = (grade: GradeRow) => {
    const rawValue = valueFrom(
        grade,
        [
            'datePosted',
            'date_posted',
            'postedAt',
            'posted_at',
            'datePostedAt',
            'date_posted_at',
            'postedDate',
            'posted_date',
            'lastModifiedDate',
            'last_modified_date',
        ],
        '',
    );

    if (!rawValue) return '-';

    const date = new Date(rawValue);

    if (Number.isNaN(date.getTime())) {
        return rawValue;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date);
};

const printTerm = (term: TermRecord) => {
    const id = `registrar-term-${term.termId ?? term.regId ?? ''}`;
    const element = document.getElementById(id);

    if (!element) return;

    const printWindow = window.open('', '_blank', 'width=1100,height=800');
    if (!printWindow) return;

    printWindow.document.write(`
        <html>
            <head>
                <title>${termTitle(term)}</title>
                <style>
                    body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
                    th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
                    .registrar-row-failed { background: #fee2e2; }
                    .registrar-row-ip { background: #fef9c3; }
                    .registrar-row-inc { background: #ffedd5; }
                    .no-print { display: none !important; }
                    .print-card { border: 0 !important; }
                </style>
            </head>
            <body>${element.innerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
};

const termHasPendingEvaluations = (term: TermRecord) => {
    return termGrades(term).some((grade) => grade.requires_evaluation === true);
};

const studentPictureUrl = computed(() => {
    const raw = String(props.result?.profile?.studentPicture ?? '').trim();
    if (!raw) return '';
    if (raw.startsWith('data:image')) return raw;
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    if (/^[A-Za-z0-9+/=\r\n]+$/.test(raw)) {
        return `data:image/jpeg;base64,${raw.replace(/\s+/g, '')}`;
    }
    return '';
});

const studentFullName = computed(() => {
    if (!props.result?.profile) return '';
    const { firstName, middleInitial, lastName, extName } = props.result.profile;
    return [
        firstName,
        middleInitial ? `${middleInitial}` : '',
        lastName,
        extName,
    ]
        .filter(Boolean)
        .join(' ');
});

const formatDate = (dateString?: string) => {
    if (!dateString) return '-';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
};

const genderLabel = computed(() => {
    const gender = props.result?.profile?.gender?.trim().toUpperCase();
    if (gender === 'M') return 'Male';
    if (gender === 'F') return 'Female';
    return gender || '-';
});
</script>

<template>
    <Head title="Registrar - Report of Grades" />

    <div class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">
                    Report of Grades
                </h1>
                <p class="text-xs text-slate-500">
                    Search a student grade report from the Academic API.
                </p>
            </div>
            <span
                v-if="result"
                class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ result.terms.length }} term{{ result.terms.length === 1 ? '' : 's' }} found
            </span>
        </div>

        <form
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            @submit.prevent="submit"
        >
            <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
                <label class="grid gap-1.5">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                        Student Number
                    </span>
                    <input
                        v-model="form.student_no"
                        type="text"
                        maxlength="50"
                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                        placeholder="e.g. 25-15340"
                    />
                    <span v-if="form.errors.student_no" class="text-xs text-red-600">
                        {{ form.errors.student_no }}
                    </span>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                        Campus
                    </span>
                    <select
                        v-model="form.campus_id"
                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                    >
                        <option value="">Select campus</option>
                        <option
                            v-for="campus in campuses"
                            :key="campus.id"
                            :value="String(campus.id)"
                        >
                            {{ campus.name }}
                            <template v-if="campus.tenant_id">
                                (Tenant {{ campus.tenant_id }})
                            </template>
                        </option>
                    </select>
                    <span v-if="form.errors.campus_id" class="text-xs text-red-600">
                        {{ form.errors.campus_id }}
                    </span>
                </label>

                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing || loading || campuses.length === 0"
                >
                    <Search class="h-4 w-4" />
                    {{ loading || form.processing ? 'Searching...' : 'Search Grade Report' }}
                </button>

                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                    @click="reset"
                >
                    <RefreshCw class="h-4 w-4" />
                    Reset
                </button>
            </div>

            <div
                v-if="selectedCampus"
                class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-white/[0.03] dark:text-slate-300"
            >
                Selected tenant source:
                <span class="font-bold">{{ selectedCampus.name }}</span>
                <span class="pl-2">Tenant ID: {{ selectedCampus.tenant_id ?? '-' }}</span>
            </div>
        </form>

        <div
            v-if="campuses.length === 0"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            Campus list is unavailable. Please check the SSO database connection.
        </div>

        <div
            v-if="error"
            class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            {{ error }}
        </div>

        <!-- Evaluation Warning Banner -->
        <div
            v-if="result && result.evaluation_error"
            class="rounded-xl border p-4 shadow-sm"
            :class="{
                'border-red-200 bg-red-50 dark:border-red-400/30 dark:bg-red-500/10':
                    result.evaluation_error_type === 'connectivity',
                'border-amber-200 bg-amber-50 dark:border-amber-400/30 dark:bg-amber-500/10':
                    result.evaluation_error_type !== 'connectivity',
            }"
        >
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                    :class="{
                        'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400':
                            result.evaluation_error_type === 'connectivity',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400':
                            result.evaluation_error_type === 'no_data',
                        'bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400':
                            result.evaluation_error_type === 'missing_context',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400':
                            !result.evaluation_error_type,
                    }"
                >
                    <WifiOff
                        v-if="result.evaluation_error_type === 'connectivity'"
                        class="size-5 shrink-0"
                    />
                    <ServerCrash
                        v-else-if="result.evaluation_error_type === 'no_data'"
                        class="size-5 shrink-0"
                    />
                    <UserX
                        v-else-if="result.evaluation_error_type === 'missing_context'"
                        class="size-5 shrink-0"
                    />
                    <AlertCircle v-else class="size-5 shrink-0" />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-sm font-semibold"
                        :class="{
                            'text-red-800 dark:text-red-300':
                                result.evaluation_error_type === 'connectivity',
                            'text-amber-800 dark:text-amber-200':
                                result.evaluation_error_type !== 'connectivity',
                        }"
                    >
                        <span v-if="result.evaluation_error_type === 'connectivity'"
                            >Evaluation Service Unavailable</span
                        >
                        <span v-else-if="result.evaluation_error_type === 'no_data'"
                            >Evaluation Data Not Found</span
                        >
                        <span
                            v-else-if="
                                result.evaluation_error_type === 'missing_context'
                            "
                            >Account Setup Incomplete</span
                        >
                        <span v-else>Grades Temporarily Locked</span>
                    </p>
                    <p
                        class="mt-1 text-xs"
                        :class="{
                            'text-red-700 dark:text-red-400':
                                result.evaluation_error_type === 'connectivity',
                            'text-amber-700 dark:text-amber-300':
                                result.evaluation_error_type !== 'connectivity',
                        }"
                    >
                        {{ result.evaluation_error }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Student Profile Card -->
        <div
            v-if="result && result.profile"
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <!-- Profile Image -->
                <div class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-100 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                    <img
                        v-if="studentPictureUrl"
                        :src="studentPictureUrl"
                        alt="Student profile image"
                        class="h-full w-full object-cover"
                    />
                    <User v-else class="h-10 w-10 text-slate-400" />
                </div>

                <!-- Profile Details -->
                <div class="min-w-0 flex-1">
                    <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-bold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                        Student Profile
                    </span>
                    <h2 class="mt-1 text-base font-bold text-slate-900 dark:text-white sm:text-lg">
                        {{ studentFullName }}
                    </h2>
                    
                    <div class="mt-3 grid gap-x-4 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <span class="text-slate-400">Gender / DOB</span>
                            <p class="font-bold text-slate-700 dark:text-slate-200">
                                {{ genderLabel }} / {{ formatDate(result.profile.dateOfBirth) }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Place of Birth</span>
                            <p class="truncate font-bold text-slate-700 dark:text-slate-200" :title="result.profile.placeOfBirth">
                                {{ result.profile.placeOfBirth || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Email Address</span>
                            <p class="truncate font-bold text-slate-700 dark:text-slate-200" :title="result.profile.email">
                                {{ result.profile.email || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Student Number</span>
                            <p class="font-bold text-slate-700 dark:text-slate-200">
                                {{ result.student_no }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section
            v-if="result"
            class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div class="border-b border-slate-100 p-4 dark:border-white/10">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                    Grade Report Result
                </p>
                <div class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <span class="text-xs text-slate-500">Student Number</span>
                        <p class="font-bold text-slate-900 dark:text-white">
                            {{ result.student_no }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500">Selected Campus</span>
                        <p class="font-bold text-slate-900 dark:text-white">
                            {{ result.campus.name }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500">Tenant ID</span>
                        <p class="font-bold text-slate-900 dark:text-white">
                            {{ result.campus.tenant_id }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="result.terms.length === 0" class="p-10 text-center">
                <FileSearch class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                    No grade report found
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    The Academic API returned no term records for this student and campus.
                </p>
            </div>

            <div v-else class="space-y-4 p-4">
                <article
                    v-for="term in result.terms"
                    :key="`${term.termId ?? term.regId ?? termTitle(term)}`"
                    :id="`registrar-term-${term.termId ?? term.regId ?? ''}`"
                    class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                >
                    <div class="bg-slate-50 px-4 py-3 dark:bg-white/[0.03]">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ termTitle(term) }}
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Section: {{ valueFrom(term, ['sectionName', 'section_name']) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-white px-2.5 py-1 text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10">
                                    Subjects: {{ valueFrom(term, ['enrolledSubjects', 'subjectsEnrolled'], '0') }}
                                </span>
                                <span class="rounded-full bg-white px-2.5 py-1 text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10">
                                    Counted Units: {{ valueFrom(term, ['computed_counted_units_display', 'totalCreditUnits', 'total_credit_units'], '0') }}
                                </span>
                                <span
                                    v-if="termHasPendingEvaluations(term)"
                                    class="rounded-full bg-amber-50 px-2.5 py-1 font-bold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20"
                                >
                                    GPA Hidden (Evaluation Required)
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20"
                                >
                                    GPA: {{ valueFrom(term, ['computed_gpa_display', 'gpa', 'GPA'], '0.0000') }}
                                </span>
                                <button
                                    type="button"
                                    class="no-print inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                                    @click="printTerm(term)"
                                >
                                    <Printer class="h-3.5 w-3.5" />
                                    Print Grade Per Sem
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                            <thead class="bg-white dark:bg-slate-950">
                                <tr>
                                    <th class="registrar-th">Course Code</th>
                                    <th class="registrar-th">Course Title</th>
                                    <th class="registrar-th">Class Section</th>
                                    <th class="registrar-th">Units</th>
                                    <th class="registrar-th">Midterm</th>
                                    <th class="registrar-th">Final</th>
                                    <th class="registrar-th">Reexam</th>
                                    <th class="registrar-th">Remarks</th>
                                    <th class="registrar-th">Date Posted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-if="termGrades(term).length === 0">
                                    <td colspan="9" class="px-4 py-8 text-center text-xs text-slate-500">
                                        No course-level grades in this term.
                                    </td>
                                </tr>
                                <tr
                                    v-for="grade in termGrades(term)"
                                    :key="`${grade.courseId ?? grade.courseCode}:${grade.schedId ?? grade.regId}`"
                                    :class="[
                                        rowStatusClass(grade),
                                        'hover:bg-slate-50/70 dark:hover:bg-white/[0.03]',
                                    ]"
                                >
                                    <td class="registrar-td font-bold text-slate-900 dark:text-white">
                                        {{ valueFrom(grade, ['courseCode', 'course_code']) }}
                                    </td>
                                    <td class="registrar-td min-w-64">
                                        {{ valueFrom(grade, ['courseTitle', 'course_title']) }}
                                    </td>
                                    <td class="registrar-td">
                                        {{ valueFrom(grade, ['classSection', 'class_section']) }}
                                    </td>
                                    <td class="registrar-td">
                                        {{ valueFrom(grade, ['unit', 'units']) }}
                                    </td>
                                    <td class="registrar-td">
                                        <template v-if="grade.requires_evaluation">
                                            <span class="text-[10px] font-bold text-slate-300 dark:text-slate-600">LOCKED</span>
                                        </template>
                                        <template v-else>
                                            {{ valueFrom(grade, ['midTerm', 'midterm', 'mid_term']) }}
                                        </template>
                                    </td>
                                    <td class="registrar-td">
                                        <template v-if="grade.requires_evaluation">
                                            <span class="text-[10px] font-bold text-slate-300 dark:text-slate-600">LOCKED</span>
                                        </template>
                                        <template v-else>
                                            {{ valueFrom(grade, ['finalGrade', 'final_grade', 'grade', 'final']) }}
                                        </template>
                                    </td>
                                    <td class="registrar-td">
                                        <template v-if="grade.requires_evaluation">
                                            <span class="text-[10px] font-bold text-slate-300 dark:text-slate-600">LOCKED</span>
                                        </template>
                                        <template v-else>
                                            {{ valueFrom(grade, ['reeExam', 'reExam', 'reexam']) }}
                                        </template>
                                    </td>
                                    <td class="registrar-td">
                                        <template v-if="grade.requires_evaluation">
                                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 uppercase dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300">
                                                Evaluate faculty first
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="rounded-full px-2 py-1 text-[11px] font-bold ring-1"
                                                :class="remarkBadgeClass(grade)"
                                            >
                                                {{ valueFrom(grade, ['remarks', 'remark', 'status']) }}
                                            </span>
                                        </template>
                                    </td>
                                    <td class="registrar-td">
                                        {{ formatDateTime(grade) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<style scoped>
.registrar-th {
    padding: 0.75rem;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.registrar-td {
    padding: 0.75rem;
    font-size: 0.75rem;
    color: rgb(71 85 105);
}

.registrar-row-failed {
    background-color: rgb(254 242 242);
}

.registrar-row-ip {
    background-color: rgb(254 252 232);
}

.registrar-row-inc {
    background-color: rgb(255 247 237);
}

:global(.dark) .registrar-th {
    color: rgb(148 163 184);
}

:global(.dark) .registrar-td {
    color: rgb(203 213 225);
}

:global(.dark) .registrar-row-failed {
    background-color: rgb(127 29 29 / 0.22);
}

:global(.dark) .registrar-row-ip {
    background-color: rgb(113 63 18 / 0.22);
}

:global(.dark) .registrar-row-inc {
    background-color: rgb(124 45 18 / 0.22);
}
</style>
