<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import {
    Activity,
    BarChart3,
    Download,
    Dumbbell,
    FileDown,
    Layers,
    Loader2,
    RefreshCw,
    RotateCcw,
    Search,
    Table2,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, defineComponent, h, onMounted, ref, watch, unref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import {
    data as pftData,
    exportExcel as pftExportExcel,
    exportPdf as pftExportPdf,
    index as pftIndex,
} from '@/routes/admin/reporting/pft-result';
import * as pftStatus from '@/routes/admin/reporting/pft-result/status';
import * as pftStudentEntry from '@/routes/admin/reporting/pft-result/student-entry';
import * as pftStudentEntryStatus from '@/routes/admin/reporting/pft-result/student-entry/status';
import {
    campuses as filterCampuses,
    colleges as filterColleges,
    sections as filterSections,
    terms as filterTerms,
} from '@/routes/admin/reporting/pft-result/filter';

import AsyncSelect from '@/components/AsyncSelect.vue';

type QueryParams = Record<string, string | number | undefined>;
type SelectOption = { id: string; text: string };
type ResultLine = { key: string; label: string; value: string };
type Interpretation = {
    label: string;
    color: string;
    field_name?: string;
    rule_id?: number;
} | null;
type PftRow = {
    number: number;
    user_id: number;
    student_name: string;
    student_no: string | null;
    student_email: string | null;
    term: string | null;
    term_label: string | null;
    campus: string | null;
    campus_label: string | null;
    college: string | null;
    college_label: string | null;
    section_id: string | null;
    section_name: string | null;
    year_level: string | null;
    test_count: number;
    latest_tested_date: string | null;
    latest_created_at: string | null;
    details: PftDetail[];
    current_analytics: DrawerAnalytics;
    term_comparison: TermComparison;
    result_comparisons: ResultComparison[];
    interpretation_comparisons: InterpretationComparison[];
    radar_profile: RadarProfile;
};
type PftDetail = {
    id: number;
    tested_date: string | null;
    pft_test_type: string | null;
    category: string | null;
    component: string | null;
    results: ResultLine[];
    interpretation: Interpretation;
    remarks: string | null;
    status: string | null;
    created_at: string | null;
};
type ComponentGroup = {
    component: string;
    details: PftDetail[];
};
type AnalyticsGroup = { label: string; value: number; color?: string };
type ComponentInterpretationGroup = {
    label: string;
    value: number;
    dominant_label: string;
    dominant_color: string;
    interpretations: AnalyticsGroup[];
};
type DrawerAnalytics = {
    total_tests: number;
    completed: number;
    draft: number;
    numeric_tests: number;
    interpreted: number;
    unclassified: number;
    interpretations: AnalyticsGroup[];
    component_interpretations: ComponentInterpretationGroup[];
    components: AnalyticsGroup[];
    bmi: number | null;
};
type TermComparison = {
    total_tests: number;
    students: number;
    completed: number;
    draft: number;
    numeric_tests: number;
    interpreted: number;
    unclassified: number;
    components: AnalyticsGroup[];
    test_types: AnalyticsGroup[];
    interpretations: AnalyticsGroup[];
    component_interpretations: ComponentInterpretationGroup[];
    interpretation_by_test_type: Record<string, AnalyticsGroup[]>;
    bmi_average: number | null;
    result_averages: Record<string, number>;
};
type ResultComparison = {
    label: string;
    component: string;
    category: string;
    student_value: number;
    term_average: number | null;
    difference: number | null;
    unit: string | null;
};
type RadarProfile = {
    labels: string[];
    currentLabel: string;
    previousLabel: string;
    current: number[];
    previous: number[];
};
type InterpretationComparisonGroup = {
    component: string;
    items: InterpretationComparison[];
};
type InterpretationComparison = {
    label: string;
    component: string;
    category: string;
    student_label: string;
    student_color: string;
    term_distribution: AnalyticsGroup[];
};
type TableSummary = {
    total: number;
    interpreted: number;
    unclassified: number;
    test_types: number;
    students: number;
};

const props = defineProps<{
    filters: Record<string, string | undefined>;
    selectedOptions: {
        campus: SelectOption | null;
        term: SelectOption | null;
        college: SelectOption | null;
        section: SelectOption | null;
        testType: SelectOption | null;
    };
    pageSizeOptions: number[];
    canExport: boolean;
}>();

defineOptions({
    layout: null,
});

const campusId = ref(props.filters.campus_id ?? '');
const termId = ref(props.filters.term_id ?? '');
const collegeId = ref(props.filters.college_id ?? '');
const sectionId = ref(props.filters.section_id ?? '');
const selectedCampus = ref<SelectOption | null>(props.selectedOptions.campus);
const selectedTerm = ref<SelectOption | null>(props.selectedOptions.term);
const selectedCollege = ref<SelectOption | null>(props.selectedOptions.college);
const selectedSection = ref<SelectOption | null>(props.selectedOptions.section);
const search = ref('');
const pageLength = ref(10);
const start = ref(0);
const draw = ref(0);
const orderColumn = ref(1);
const orderDirection = ref<'asc' | 'desc'>('desc');
const rows = ref<PftRow[]>([]);
const recordsFiltered = ref(0);
const recordsTotal = ref(0);
const tableSummary = ref<TableSummary>({
    total: 0,
    interpreted: 0,
    unclassified: 0,
    test_types: 0,
    students: 0,
});
const tableLoading = ref(false);
const actionLoading = ref(false);
const activeRow = ref<PftRow | null>(null);
const activeComponentGroups = computed<ComponentGroup[]>(() => {
    if (!activeRow.value) {
        return [];
    }

    const groups = new Map<string, PftDetail[]>();

    activeRow.value.details.forEach((detail) => {
        const component = detail.component ?? 'Uncategorized';
        groups.set(component, [...(groups.get(component) ?? []), detail]);
    });

    return Array.from(groups.entries()).map(([component, details]) => ({
        component,
        details,
    }));
});
const interpretationComparisonGroups = computed<
    InterpretationComparisonGroup[]
>(() => {
    if (!activeRow.value) {
        return [];
    }

    const groups = new Map<string, InterpretationComparison[]>();

    activeRow.value.interpretation_comparisons.forEach((item) => {
        groups.set(item.component, [
            ...(groups.get(item.component) ?? []),
            item,
        ]);
    });

    return Array.from(groups.entries()).map(([component, items]) => ({
        component,
        items,
    }));
});

const filterEndpoints = {
    campuses: filterCampuses.url(),
    terms: filterTerms.url(),
    colleges: filterColleges.url(),
    sections: filterSections.url(),
};

const requiredFiltersSelected = computed(() =>
    Boolean(campusId.value && termId.value),
);
const routeQuery = (includeTable = false): QueryParams => {
    const base: QueryParams = {
        campus_id: campusId.value,
        term_id: termId.value,
        college_id: collegeId.value,
        section_id: sectionId.value,
    };

    if (includeTable) {
        base.draw = draw.value;
        base.start = start.value;
        base.length = pageLength.value;
        base['search[value]'] = search.value;
        base['order[0][column]'] = orderColumn.value;
        base['order[0][dir]'] = orderDirection.value;
    }

    return Object.fromEntries(
        Object.entries(base).filter(([, value]) => value !== ''),
    ) as QueryParams;
};

const updateBrowserUrl = () => {
    const url = new URL(pftIndex.url({ query: routeQuery() }));
    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
};

const clearResults = () => {
    rows.value = [];
    recordsFiltered.value = 0;
    recordsTotal.value = 0;
    tableSummary.value = {
        total: 0,
        interpreted: 0,
        unclassified: 0,
        test_types: 0,
        students: 0,
    };
};

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const fetchTable = async () => {
    if (!requiredFiltersSelected.value) {
        clearResults();

        return;
    }

    tableLoading.value = true;
    draw.value += 1;
    const response = await fetch(pftData.url({ query: routeQuery(true) }), {
        headers: { Accept: 'application/json' },
    });
    const payload = await response.json();
    rows.value = payload.data ?? [];
    recordsTotal.value = payload.recordsTotal ?? 0;
    recordsFiltered.value = payload.recordsFiltered ?? 0;
    tableSummary.value = payload.summary ?? tableSummary.value;
    tableLoading.value = false;
};

const reloadAll = async () => {
    updateBrowserUrl();
    await fetchTable();
};

const resetPageAndReload = () => {
    start.value = 0;
    void reloadAll();
};

const onCampusChange = (option: SelectOption | null) => {
    selectedCampus.value = option;
    termId.value = '';
    selectedTerm.value = null;
    collegeId.value = '';
    selectedCollege.value = null;
    sectionId.value = '';
    selectedSection.value = null;
    resetPageAndReload();
};

const onTermChange = (option: SelectOption | null) => {
    selectedTerm.value = option;
    sectionId.value = '';
    selectedSection.value = null;
    resetPageAndReload();
};

const onCollegeChange = (option: SelectOption | null) => {
    selectedCollege.value = option;
    sectionId.value = '';
    selectedSection.value = null;
    resetPageAndReload();
};

const onSectionChange = (option: SelectOption | null) => {
    selectedSection.value = option;
    resetPageAndReload();
};

const resetFilters = () => {
    campusId.value = '';
    termId.value = '';
    collegeId.value = '';
    sectionId.value = '';
    selectedCampus.value = null;
    selectedTerm.value = null;
    selectedCollege.value = null;
    selectedSection.value = null;
    search.value = '';
    activeRow.value = null;
    start.value = 0;
    void reloadAll();
};

const openDrawer = (row: PftRow) => {
    activeRow.value = row;
};

const closeDrawer = () => {
    activeRow.value = null;
};

const studentEntryPayload = (row: PftRow) => ({
    campus_id: row.campus ?? undefined,
    college_id: row.college ?? undefined,
    section_id: row.section_id ?? undefined,
});

const updateDetailStatus = async (detail: PftDetail, status: string) => {
    if (detail.status?.toLowerCase() === status) {
        return;
    }

    actionLoading.value = true;

    try {
        const response = await fetch(pftStatus.update.url(detail.id), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ status }),
        });

        if (!response.ok) {
            throw new Error('Unable to update PFT result status.');
        }

        await fetchTable();
        const refreshedRow = rows.value.find(
            (row) =>
                row.user_id === activeRow.value?.user_id &&
                row.term === activeRow.value?.term,
        );
        activeRow.value = refreshedRow ?? activeRow.value;
    } finally {
        actionLoading.value = false;
    }
};

const deleteStudentEntry = async (row: PftRow) => {
    if (
        !confirm(
            `Delete all visible PFT result records for ${row.student_name}? This cannot be undone.`,
        )
    ) {
        return;
    }

    actionLoading.value = true;

    try {
        const response = await fetch(
            pftStudentEntry.destroy.url({
                user: row.user_id,
                term: row.term ?? '',
            }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(studentEntryPayload(row)),
            },
        );

        if (!response.ok) {
            throw new Error('Unable to delete PFT result records.');
        }

        if (activeRow.value?.user_id === row.user_id && activeRow.value?.term === row.term) {
            activeRow.value = null;
        }

        await fetchTable();
    } finally {
        actionLoading.value = false;
    }
};

const markStudentEntryDraft = async (row: PftRow) => {
    if (
        !confirm(
            `Mark all visible PFT result records for ${row.student_name} as draft so the student can resubmit?`,
        )
    ) {
        return;
    }

    actionLoading.value = true;

    try {
        const response = await fetch(
            pftStudentEntryStatus.update.url({
                user: row.user_id,
                term: row.term ?? '',
            }),
            {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    ...studentEntryPayload(row),
                    status: 'draft',
                }),
            },
        );

        if (!response.ok) {
            throw new Error('Unable to update PFT entry status.');
        }

        await fetchTable();
        const refreshedRow = rows.value.find(
            (item) => item.user_id === row.user_id && item.term === row.term,
        );

        if (activeRow.value?.user_id === row.user_id && activeRow.value?.term === row.term) {
            activeRow.value = refreshedRow ?? activeRow.value;
        }
    } finally {
        actionLoading.value = false;
    }
};

const sortBy = (column: number) => {
    if (orderColumn.value === column) {
        orderDirection.value = orderDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        orderColumn.value = column;
        orderDirection.value = 'asc';
    }

    resetPageAndReload();
};

const page = computed(() => Math.floor(start.value / pageLength.value) + 1);
const lastPage = computed(() =>
    Math.max(Math.ceil(recordsFiltered.value / pageLength.value), 1),
);
const canPrevious = computed(() => start.value > 0);
const canNext = computed(() => page.value < lastPage.value);

const previousPage = () => {
    if (canPrevious.value) {
        start.value = Math.max(start.value - pageLength.value, 0);
        void fetchTable();
    }
};

const nextPage = () => {
    if (canNext.value) {
        start.value += pageLength.value;
        void fetchTable();
    }
};

const exportExcelUrl = computed(() =>
    requiredFiltersSelected.value
        ? pftExportExcel.url({ query: routeQuery() })
        : '#',
);
const exportPdfUrl = computed(() =>
    requiredFiltersSelected.value
        ? pftExportPdf.url({ query: routeQuery() })
        : '#',
);

const fitnessProfileRadarOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, background: 'transparent' },
    colors: ['#059669', '#94a3b8'],
    dataLabels: { enabled: false },
    xaxis: { categories: activeRow.value?.radar_profile.labels ?? [] },
    yaxis: { min: 0, max: 100 },
}));
const fitnessProfileRadarSeries = computed(() => [
    {
        name: activeRow.value?.radar_profile.currentLabel ?? 'Student',
        data: activeRow.value?.radar_profile.current ?? [],
    },
]);

const getBmiInterpretation = (row: PftRow) => {
    const bmiDetail = row.details?.find(d => 
        d.pft_test_type?.toLowerCase().includes('bmi') || 
        d.pft_test_type?.toLowerCase().includes('body mass index') ||
        d.component?.toLowerCase().includes('bmi') ||
        d.component?.toLowerCase().includes('body composition')
    );
    return bmiDetail?.interpretation?.label || 'N/A';
};

const getRowStatus = (row: PftRow) => {
    if (row.test_count === 0) return 'No tests';
    const hasDraft = row.details?.some(d => d.status?.toLowerCase() === 'draft');
    return hasDraft ? 'Draft' : 'Completed';
};

onMounted(() => {
    if (requiredFiltersSelected.value) {
        void reloadAll();
    }
});
</script>

<template>
    <Head title="PFT Result" />

    <div class="min-h-screen font-sans bg-slate-50 text-slate-800 lg:flex dark:bg-slate-950">
        <FitnessIntelligenceSidebar active="pft-result" />

    <main id="pft-result" class="flex min-w-0 flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950">
        <!-- 1. PAGE HEADER -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between border-b border-slate-100 pb-3 dark:border-white/10">
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                    <Activity class="h-5.5 w-5.5 text-blue-600" /> PFT Result
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Monitor, analyze and manage student physical fitness test results across all campuses.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <Link
                    class="inline-flex items-center justify-center gap-1.5 h-8.5 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-xs font-medium shadow-xs transition duration-150 cursor-pointer"
                    href="/admin/reporting/pft-result/analytics"
                >
                    <BarChart3 class="h-3.5 w-3.5" /> View Analytics
                </Link>
                <template v-if="canExport">
                    <a
                        :class="{ 'pointer-events-none opacity-50': !requiredFiltersSelected }"
                        class="inline-flex items-center justify-center gap-1.5 h-8.5 px-3 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md text-xs font-medium shadow-xs transition duration-150 cursor-pointer dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        :href="exportExcelUrl"
                    >
                        <Download class="h-3.5 w-3.5" /> Export Excel
                    </a>
                    <a
                        :class="{ 'pointer-events-none opacity-50': !requiredFiltersSelected }"
                        class="inline-flex items-center justify-center gap-1.5 h-8.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-medium shadow-xs transition duration-150 cursor-pointer"
                        :href="exportPdfUrl"
                        target="_blank"
                    >
                        <FileDown class="h-3.5 w-3.5" /> Export PDF
                    </a>
                </template>
            </div>
        </div>

        <!-- 2. FILTER PANEL -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs dark:border-white/10 dark:bg-slate-900">
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Campus Select -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Campus</label>
                    <AsyncSelect
                        v-model="campusId"
                        :selected="selectedCampus"
                        :endpoint="filterEndpoints.campuses"
                        placeholder="Select Campus"
                        :min-input="0"
                        @select="onCampusChange"
                    />
                </div>

                <!-- Academic Term Select -->
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Academic Term</label>
                    <AsyncSelect
                        v-model="termId"
                        :selected="selectedTerm"
                        :endpoint="filterEndpoints.terms"
                        :params="{ campus_id: campusId }"
                        :disabled="!campusId"
                        placeholder="Select Academic Term"
                        :min-input="0"
                        @select="onTermChange"
                    />
                </div>
            </div>
            <div class="mt-3.5 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-3 dark:border-white/10">
                <p class="text-[11px] text-slate-400 font-medium italic">
                    Select a campus and academic term to load records.
                </p>
                <div class="flex items-center gap-1.5">
                    <Button
                        @click="resetPageAndReload"
                        :disabled="!campusId || !termId"
                        class="h-8 px-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md text-xs"
                    >
                        Load Results
                    </Button>
                    <Button
                        @click="resetFilters"
                        variant="outline"
                        class="h-8 px-3 border-slate-200 hover:bg-slate-50 text-slate-700 rounded-md text-xs dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        Reset
                    </Button>
                </div>
            </div>
        </section>

        <!-- 3. SUMMARY DASHBOARD -->
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
            <!-- Results -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900 border-t-4 border-t-blue-500 hover:shadow-md transition duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Results</span>
                    <Table2 class="h-4 w-4 text-blue-500" />
                </div>
                <div class="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{{ tableSummary.total }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5 font-medium flex items-center gap-1">
                    <span class="text-emerald-600 font-semibold">+12%</span> vs last sem
                </div>
            </div>

            <!-- Interpreted -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900 border-t-4 border-t-emerald-500 hover:shadow-md transition duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Interpreted</span>
                    <Activity class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{{ tableSummary.interpreted }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5 font-medium flex items-center gap-1">
                    <span class="text-emerald-600 font-semibold">{{ Math.round((tableSummary.interpreted / (tableSummary.total || 1)) * 100) }}%</span> of total
                </div>
            </div>

            <!-- Unclassified -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900 border-t-4 border-t-amber-500 hover:shadow-md transition duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Unclassified</span>
                    <Layers class="h-4 w-4 text-amber-500" />
                </div>
                <div class="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{{ tableSummary.unclassified }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5 font-medium flex items-center gap-1">
                    <span class="text-amber-600 font-semibold">{{ Math.round((tableSummary.unclassified / (tableSummary.total || 1)) * 100) }}%</span> of total
                </div>
            </div>

            <!-- Test Types -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900 border-t-4 border-t-violet-500 hover:shadow-md transition duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Test Types</span>
                    <Dumbbell class="h-4 w-4 text-violet-500" />
                </div>
                <div class="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{{ tableSummary.test_types }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5 font-medium">Configured</div>
            </div>

            <!-- Students -->
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900 border-t-4 border-t-rose-500 hover:shadow-md transition duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Students</span>
                    <Users class="h-4 w-4 text-rose-500" />
                </div>
                <div class="text-lg font-bold text-slate-900 dark:text-white mt-1.5">{{ tableSummary.students }}</div>
                <div class="text-[10px] text-slate-500 mt-0.5 font-medium">Across campuses</div>
            </div>
        </div>

        <!-- 4. RECORDS SECTION -->
        <section class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs dark:border-white/10 dark:bg-slate-900">
            <!-- Section Header and Filters row -->
            <div class="border-b border-slate-100 p-4 dark:border-white/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <Table2 class="h-4.5 w-4.5 text-blue-600" /> Student PFT Records
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Manage, search and review physical fitness records.
                        </p>
                    </div>
                    <span v-if="tableLoading" class="text-xs font-semibold text-blue-600 animate-pulse flex items-center gap-1">
                        <RefreshCw class="h-3 w-3 animate-spin" /> Loading records...
                    </span>
                </div>

                <!-- 5. RECORD FILTERS ROW -->
                <div class="mt-4 flex flex-col gap-2.5 lg:flex-row lg:items-center">
                    <!-- Search Student -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                        <input
                            v-model="search"
                            placeholder="Search student..."
                            class="h-8.5 pl-9 text-xs rounded-md border border-slate-200 dark:border-white/10 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none w-full bg-transparent px-3 py-1.5 text-slate-900 dark:text-slate-100"
                            :disabled="!requiredFiltersSelected"
                            @keydown.enter="resetPageAndReload"
                        />
                    </div>

                    <!-- Dropdown selections in single inline-flex or grid -->
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4 lg:w-auto lg:flex lg:items-center">
                        <!-- College Filter -->
                        <div class="min-w-[130px]">
                            <AsyncSelect
                                v-model="collegeId"
                                :selected="selectedCollege"
                                :endpoint="filterEndpoints.colleges"
                                :params="{ campus_id: campusId }"
                                :disabled="!campusId || !termId"
                                placeholder="College ▼"
                                :min-input="0"
                                @select="onCollegeChange"
                            />
                        </div>

                        <!-- Section Filter -->
                        <div class="min-w-[130px]">
                            <AsyncSelect
                                v-model="sectionId"
                                :selected="selectedSection"
                                :endpoint="filterEndpoints.sections"
                                :params="{
                                    campus_id: campusId,
                                    term_id: termId,
                                    college_id: collegeId,
                                }"
                                :disabled="!campusId || !termId || !collegeId"
                                placeholder="Section ▼"
                                :min-input="0"
                                @select="onSectionChange"
                            />
                        </div>

                        <!-- Per Page Selector -->
                        <div class="min-w-[110px]">
                            <select
                                v-model.number="pageLength"
                                class="h-8.5 w-full rounded-md border border-slate-200 bg-transparent px-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:border-white/10 dark:bg-slate-900"
                                :disabled="!requiredFiltersSelected"
                                @change="resetPageAndReload"
                            >
                                <option v-for="size in pageSizeOptions" :key="size" :value="size">
                                    {{ size }} Per Page
                                </option>
                            </select>
                        </div>

                        <!-- Search Trigger Button -->
                        <Button
                            class="h-8.5 px-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md text-xs gap-1.5 shadow-sm shrink-0"
                            :disabled="!requiredFiltersSelected"
                            @click="resetPageAndReload"
                        >
                            <Search class="h-3.5 w-3.5" /> Search
                        </Button>
                    </div>
                </div>
            </div>

            <!-- 6. TABLE -->
            <!-- Empty State / No Filters Selected -->
            <div
                v-if="!requiredFiltersSelected"
                class="flex flex-col items-center justify-center py-16 px-6 text-center bg-slate-50/20 dark:bg-slate-950/20"
            >
                <div class="h-14 w-14 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mb-3 shadow-xs dark:bg-blue-900/20 dark:text-blue-400">
                    <span class="text-2xl">🏃</span>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No Results Loaded</h3>
                <p class="text-xs text-slate-500 mt-0.5 max-w-xs">
                    Select Campus and Academic Term to display Physical Fitness Test results.
                </p>
                <Button
                    @click="resetPageAndReload"
                    class="mt-3 h-8 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md text-xs"
                    :disabled="!campusId || !termId"
                >
                    Load Results
                </Button>
            </div>

            <!-- Table content -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="bg-slate-50/50 dark:bg-slate-900/50 font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-white/10 sticky top-0 z-10 backdrop-blur-sm">
                        <tr>
                            <th class="report-th w-10">#</th>
                            <th class="report-th sortable" @click="sortBy(1)">Student</th>
                            <th class="report-th sortable" @click="sortBy(4)">Course</th>
                            <th class="report-th sortable" @click="sortBy(5)">Section</th>
                            <th class="report-th sortable" @click="sortBy(3)">Campus / College</th>
                            <th class="report-th">BMI</th>
                            <th class="report-th sortable" @click="sortBy(8)">Result</th>
                            <th class="report-th">Interpretation</th>
                            <th class="report-th">Status</th>
                            <th class="report-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        <tr
                            v-for="row in rows"
                            :key="`${row.user_id}-${row.term}`"
                            class="hover:bg-slate-50/40 dark:hover:bg-white/5 transition duration-150 ease-in-out"
                        >
                            <td class="report-td font-bold text-slate-400">{{ row.number }}</td>
                            
                            <!-- Student Details -->
                            <td class="report-td whitespace-nowrap">
                                <div class="flex flex-col">
                                    <button
                                        class="text-left font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline text-xs"
                                        @click="openDrawer(row)"
                                    >
                                        {{ row.student_name }}
                                    </button>
                                    <span class="text-[10px] text-slate-400 mt-0.5">
                                        {{ row.student_no ?? `User #${row.user_id}` }}
                                    </span>
                                </div>
                            </td>

                            <!-- Course / College -->
                            <td class="report-td">{{ row.college ?? '-' }}</td>

                            <!-- Section -->
                            <td class="report-td">
                                <div class="flex flex-col">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ row.section_name ?? '-' }}</span>
                                    <span class="text-[10px] text-slate-400 mt-0.5">{{ row.section_id ?? '-' }}</span>
                                </div>
                            </td>

                            <!-- Campus -->
                            <td class="report-td">
                                <div class="flex flex-col">
                                    <span>{{ row.campus_label ?? row.campus ?? '-' }}</span>
                                    <span class="text-[10px] text-slate-500 mt-0.5" v-if="row.college_label || row.college">{{ row.college_label ?? row.college }}</span>
                                </div>
                            </td>

                            <!-- BMI -->
                            <td class="report-td font-medium text-slate-700 dark:text-slate-300">
                                {{ row.current_analytics?.bmi || '-' }}
                            </td>

                            <!-- Result (Test count) -->
                            <td class="report-td whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-white/5">
                                    {{ row.test_count }} / 8 Completed
                                </span>
                            </td>

                            <!-- Interpretation -->
                            <td class="report-td font-medium">
                                <span 
                                    :class="[
                                        getBmiInterpretation(row).toLowerCase().includes('normal') ? 'text-emerald-600 dark:text-emerald-400' :
                                        getBmiInterpretation(row).toLowerCase().includes('obese') || getBmiInterpretation(row).toLowerCase().includes('underweight') ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'
                                    ]"
                                >
                                    {{ getBmiInterpretation(row) }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="report-td whitespace-nowrap">
                                <span v-if="getRowStatus(row) === 'Completed'" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200/50">
                                    <CheckCircle2 class="size-3" /> Completed
                                </span>
                                <span v-else class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200/50">
                                    <Clock class="size-3" /> Draft
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="report-td text-right">
                                <div class="inline-flex items-center gap-1 justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-xs"
                                        :disabled="actionLoading"
                                        title="Mark as draft for resubmission"
                                        @click="markStudentEntryDraft(row)"
                                    >
                                        <RotateCcw class="h-3 w-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition-colors shadow-xs"
                                        :disabled="actionLoading"
                                        title="Delete student PFT entry"
                                        @click="deleteStudentEntry(row)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty Search results state -->
                        <tr v-if="rows.length === 0">
                            <td colspan="10" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="h-10 w-10 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-2.5 dark:bg-slate-900 dark:text-slate-600">
                                        <Search class="h-5 w-5" />
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-900 dark:text-white">No Records Found</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">We couldn't find any PFT records matching the current filters.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 8. PAGINATION SECTION -->
            <div
                v-if="recordsFiltered > 0"
                class="flex flex-col gap-3 border-t border-slate-100 px-5 py-3 text-xs text-slate-500 md:flex-row md:items-center md:justify-between dark:border-white/10 bg-slate-50/50 dark:bg-slate-900/50"
            >
                <span class="font-medium text-slate-600 dark:text-slate-400">
                    Showing {{ recordsFiltered === 0 ? 0 : start + 1 }}–{{ Math.min(start + pageLength, recordsFiltered) }} of {{ recordsFiltered }} filtered records
                    <span v-if="recordsTotal !== recordsFiltered" class="text-slate-400"> ({{ recordsTotal }} total)</span>
                </span>
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7.5 px-2.5 rounded-md border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="!canPrevious"
                        @click="previousPage"
                    >
                        Previous
                    </Button>
                    <span class="inline-flex h-7.5 items-center justify-center rounded-md bg-blue-600 text-white px-2.5 text-xs font-semibold shadow-xs">
                        Page {{ page }} of {{ lastPage }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7.5 px-2.5 rounded-md border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800"
                        :disabled="!canNext"
                        @click="nextPage"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </section>

        <!-- Drawer Slideout Panel -->
        <div
            v-if="activeRow"
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/35 backdrop-blur-xs"
            @click.self="closeDrawer"
        >
            <aside
                class="h-full w-full max-w-5xl overflow-y-auto border-l border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-slate-950 transition-all duration-300 ease-in-out"
            >
                <!-- Drawer Header -->
                <div
                    class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white/95 p-4 backdrop-blur-md dark:border-white/10 dark:bg-slate-950/95"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ activeRow.student_name }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ activeRow.student_no ?? `User #${activeRow.user_id}` }}
                            <span v-if="activeRow.student_email" class="text-slate-400">
                                · {{ activeRow.student_email }}
                            </span>
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 shrink-0 hover:bg-slate-100 dark:hover:bg-slate-800" @click="closeDrawer">
                        <X class="h-3.5 w-3.5" /> Close
                    </Button>
                </div>

                <div class="grid gap-4 p-4">
                    <!-- Drawer Meta Info Grid -->
                    <div class="grid gap-2 grid-cols-2 md:grid-cols-5 rounded-xl border border-slate-100 bg-slate-50/50 p-3 dark:border-white/5 dark:bg-white/5">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Term</span>
                            <strong class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ activeRow.term_label ?? activeRow.term ?? '-' }}</strong>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Campus</span>
                            <strong class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ activeRow.campus_label ?? activeRow.campus ?? '-' }}</strong>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">College</span>
                            <strong class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ activeRow.college_label ?? activeRow.college ?? '-' }}</strong>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Section</span>
                            <strong class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ activeRow.section_name ?? activeRow.section_id ?? '-' }}</strong>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Year Level</span>
                            <strong class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ activeRow.year_level ?? '-' }}</strong>
                        </div>
                    </div>

                    <!-- Radar chart profile -->
                    <section class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                Student Fitness Profile
                            </h4>
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[9px] font-semibold text-emerald-700 border border-emerald-200/50">Radar Analysis</span>
                        </div>
                        <div class="min-h-48 rounded-lg border border-slate-100 bg-slate-50/30 p-2 dark:border-white/5 dark:bg-slate-950/30">
                            <VueApexCharts
                                v-if="activeRow.radar_profile.labels.length > 0"
                                height="300"
                                type="radar"
                                :options="fitnessProfileRadarOptions"
                                :series="fitnessProfileRadarSeries"
                            />
                            <p v-else class="flex min-h-40 items-center justify-center text-xs font-medium text-slate-400">
                                No interpreted component data.
                            </p>
                        </div>
                    </section>

                    <!-- Component Details Lists -->
                    <section
                        v-for="group in activeComponentGroups"
                        :key="group.component"
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900"
                    >
                        <div
                            class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-white/10"
                        >
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                {{ group.component }}
                            </h4>
                            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-semibold text-blue-700 border border-blue-200/50">
                                {{ group.details.length }} tests
                            </span>
                        </div>

                        <div class="grid gap-3 xl:grid-cols-2">
                            <article
                                v-for="detail in group.details"
                                :key="detail.id"
                                class="rounded-lg bg-slate-50/50 p-3 dark:bg-white/[0.02] border border-slate-100 dark:border-white/5"
                            >
                                <div
                                    class="mb-3 flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                                >
                                    <div>
                                        <h5 class="text-xs font-bold text-slate-900 dark:text-white">
                                            {{ detail.pft_test_type ?? 'PFT Test' }}
                                        </h5>
                                        <p class="text-[9px] text-slate-400 mt-0.5">
                                            {{ detail.category ?? 'Category' }} · Tested {{ detail.tested_date ?? '-' }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex rounded bg-white px-2 py-0.5 text-[9px] font-bold text-slate-600 border border-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:border-white/5 uppercase">
                                            {{ detail.interpretation?.label ?? 'Unclassified' }}
                                        </span>
                                        <select
                                            class="h-6.5 rounded border border-slate-200 bg-white px-2 text-[10px] font-bold text-slate-700 outline-none focus:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                                            :value="detail.status?.toLowerCase() ?? 'completed'"
                                            :disabled="actionLoading"
                                            @change="
                                                updateDetailStatus(
                                                    detail,
                                                    ($event.target as HTMLSelectElement).value,
                                                )
                                            "
                                        >
                                            <option value="completed">Completed</option>
                                            <option value="draft">Draft</option>
                                        </select>
                                    </div>
                                </div>

                                <div
                                    v-if="detail.results.length"
                                    class="grid gap-1"
                                >
                                    <div
                                        v-for="line in detail.results"
                                        :key="line.key"
                                        class="text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between border-b border-slate-100/50 pb-0.5 last:border-0 dark:border-white/5"
                                    >
                                        <span class="font-medium text-slate-500">
                                            {{ line.label }}
                                        </span>
                                        <strong class="font-semibold text-slate-800 dark:text-slate-200">
                                            {{ line.value }}
                                        </strong>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-slate-400 italic">
                                    No result data.
                                </p>

                                <div
                                    class="mt-3 grid gap-1 border-t border-slate-150 pt-2 text-[9px] text-slate-400 dark:border-white/5"
                                >
                                    <span>Remarks: {{ detail.remarks ?? '-' }}</span>
                                    <span>Created: {{ detail.created_at ?? '-' }}</span>
                                </div>
                            </article>
                        </div>
                    </section>
                    
                    <p
                        v-if="activeComponentGroups.length === 0"
                        class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs font-semibold text-slate-400 dark:border-white/10"
                    >
                        No PFT result details found.
                    </p>
                </div>
            </aside>
        </div>
    </main>
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.stat-card {
    @apply rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs dark:border-white/10 dark:bg-slate-900 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5;
    background-color: #ffffff !important;
    color: #64748b !important;
}
.stat-card strong {
    @apply mt-1 block text-lg font-bold text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.stat-icon {
    @apply mb-2 h-4.5 w-4.5;
}
.report-card {
    @apply rounded-xl border border-slate-200 bg-white shadow-xs dark:border-white/10 dark:bg-slate-900;
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-heading {
    @apply text-[10px] font-bold tracking-wider text-slate-500 uppercase;
}
.report-input {
    @apply h-8.5 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 transition-all duration-150;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    color-scheme: light;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
}
.report-input:disabled {
    background-color: #f8fafc !important;
    color: #94a3b8 !important;
}
.report-input option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}
.report-btn,
.page-btn {
    @apply inline-flex h-8.5 items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 transition-all duration-150;
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-link-btn {
    @apply text-left text-[11px] font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors duration-150;
}
.report-btn-primary {
    @apply inline-flex h-8.5 items-center justify-center gap-1.5 rounded-md bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 transition-all duration-150;
}
.report-icon-btn,
.report-icon-danger-btn {
    @apply inline-flex h-7 w-7 items-center justify-center rounded-md border text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50;
}
.report-icon-btn {
    @apply border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-white/10;
}
.report-icon-danger-btn {
    @apply border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20;
}
.report-status-select {
    @apply h-7 rounded border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-700 outline-none focus:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 transition-all duration-150;
    color-scheme: light;
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-status-select option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}
.report-th {
    @apply px-4 py-2.5 text-left text-[10px] font-bold tracking-wider text-slate-500 uppercase border-b border-slate-200 dark:border-white/10;
}
.sortable {
    @apply cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-150;
}
.report-td {
    @apply px-4 py-2 text-xs text-slate-600 dark:text-slate-300 align-middle;
    color: #475569 !important;
}
.mini-list {
    @apply grid gap-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-white/[0.04] dark:text-slate-300;
}
.mini-list strong {
    @apply text-slate-900 dark:text-white;
}
.mini-list span {
    @apply flex items-center justify-between gap-3;
}
.mini-list b {
    @apply text-slate-900 dark:text-white;
}
.interpretation-list {
    @apply border border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300;
    background-color: #f8fafc !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.interpretation-list > strong {
    @apply text-slate-950 dark:text-white;
    color: #0f172a !important;
}
.interpretation-list span {
    @apply min-w-0 rounded-md border border-slate-200 bg-white px-2 py-1 dark:border-white/10 dark:bg-slate-900;
}
.interpretation-list span > b {
    @apply ml-3 shrink-0 text-right text-slate-950 dark:text-white;
    color: inherit !important;
}
.hierarchy-card {
    @apply rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/[0.04];
    background-color: #f8fafc !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.hierarchy-category {
    @apply rounded-lg border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-900;
    background-color: #ffffff !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.hierarchy-test {
    @apply grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-white/10 dark:bg-white/[0.04];
    background-color: #f8fafc !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.hierarchy-toggle {
    @apply flex w-full items-start justify-between gap-3 text-left text-sm;
}
.hierarchy-toggle strong,
.hierarchy-test strong {
    @apply block truncate font-bold text-slate-950 dark:text-white;
    color: #0f172a !important;
}
.hierarchy-toggle small,
.hierarchy-test small {
    @apply mt-1 block text-xs text-slate-500 dark:text-slate-400;
    color: #64748b !important;
}
.hierarchy-toggle-sm {
    @apply text-xs;
}
.hierarchy-chevron {
    @apply inline-flex h-8 shrink-0 items-center justify-center gap-1 rounded-md border border-emerald-200 bg-white px-2 text-xs font-bold text-emerald-700 shadow-sm dark:border-emerald-500/30 dark:bg-slate-950 dark:text-emerald-300;
    background-color: #ffffff !important;
    color: #047857 !important;
    border-color: #a7f3d0 !important;
}
.hierarchy-chevron small {
    @apply hidden text-[10px] font-bold sm:inline;
    color: inherit !important;
}
.hierarchy-distribution {
    @apply mt-3 flex flex-wrap gap-1.5;
}
.hierarchy-distribution span {
    @apply inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300;
}
.hierarchy-distribution b {
    @apply text-slate-950 dark:text-white;
    color: inherit !important;
}
.drawer-summary {
    @apply grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs dark:border-white/10 dark:bg-slate-900;
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
.drawer-meta {
    @apply flex items-center justify-between gap-3 text-slate-500 dark:text-slate-400;
    color: #64748b !important;
}
.drawer-meta strong {
    @apply text-right text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.drawer-analytics-panel {
    @apply rounded-lg border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950;
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.drawer-pill {
    @apply rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase dark:bg-emerald-500/10 dark:text-emerald-300;
}
.drawer-stat {
    @apply rounded-lg bg-slate-50 p-2 text-xs text-slate-500 dark:bg-white/[0.04] dark:text-slate-400;
    background-color: #f8fafc !important;
    color: #64748b !important;
}
.drawer-stat strong {
    @apply mt-1 block text-lg text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.drawer-chart-shell {
    @apply min-h-48 rounded-lg border border-slate-100 bg-white p-2 dark:border-white/10 dark:bg-slate-900;
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
}
.drawer-empty-chart {
    @apply flex min-h-44 items-center justify-center text-sm text-slate-400;
    color: #94a3b8 !important;
}
.drawer-component-card {
    @apply rounded-lg border border-slate-100 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/[0.04];
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
.drawer-result-row {
    @apply flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300;
    background-color: #ffffff !important;
    color: #475569 !important;
}
.drawer-result-row span {
    @apply min-w-0 truncate;
}
.drawer-result-row strong {
    @apply shrink-0 text-right text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.drawer-result-row small {
    @apply ml-2 font-semibold text-slate-400;
}
.drawer-progress-row {
    @apply flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-white/[0.04] dark:text-slate-300;
    background-color: #f8fafc !important;
    color: #475569 !important;
}
.drawer-progress-row span {
    @apply min-w-0 truncate;
}
.drawer-progress-row strong {
    @apply shrink-0 text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.page-btn-static {
    @apply pointer-events-none;
}
.dark .report-card,
.report-card:is(.dark *) {
    background-color: #020617 !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .stat-card,
.stat-card:is(.dark *) {
    background-color: #020617 !important;
    color: #94a3b8 !important;
}
.dark .drawer-summary,
.drawer-summary:is(.dark *) {
    background-color: #0f172a !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .interpretation-list,
.interpretation-list:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .interpretation-list > strong,
.interpretation-list:is(.dark *) > strong {
    color: #f8fafc !important;
}
.dark .interpretation-list span,
.interpretation-list:is(.dark *) span {
    background-color: var(--interpretation-bg, inherit);
    color: var(--interpretation-accent, #cbd5e1);
    border-color: var(--interpretation-border, currentColor);
}
.dark .interpretation-list span > b,
.interpretation-list:is(.dark *) span > b {
    color: inherit !important;
}
.dark .hierarchy-card,
.hierarchy-card:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .hierarchy-category,
.hierarchy-category:is(.dark *) {
    background-color: #0f172a !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .hierarchy-test,
.hierarchy-test:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .hierarchy-toggle strong,
.dark .hierarchy-test strong,
.hierarchy-toggle:is(.dark *) strong,
.hierarchy-test:is(.dark *) strong {
    color: #f8fafc !important;
}
.dark .hierarchy-toggle small,
.dark .hierarchy-test small,
.hierarchy-toggle:is(.dark *) small,
.hierarchy-test:is(.dark *) small {
    color: #94a3b8 !important;
}
.dark .hierarchy-chevron,
.hierarchy-chevron:is(.dark *) {
    background-color: #020617 !important;
    color: #6ee7b7 !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
}
.dark .hierarchy-distribution span,
.hierarchy-distribution:is(.dark *) span {
    background-color: var(--interpretation-bg, inherit);
    color: var(--interpretation-accent, #cbd5e1);
    border-color: var(--interpretation-border, currentColor);
}
.dark .hierarchy-distribution b,
.hierarchy-distribution:is(.dark *) b {
    color: inherit !important;
}
.dark .drawer-analytics-panel,
.drawer-analytics-panel:is(.dark *) {
    background-color: #020617 !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .drawer-stat,
.drawer-stat:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    color: #94a3b8 !important;
}
.dark .drawer-stat strong,
.drawer-stat:is(.dark *) strong {
    color: #f8fafc !important;
}
.dark .drawer-chart-shell,
.drawer-chart-shell:is(.dark *) {
    background-color: #0f172a !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .drawer-component-card,
.drawer-component-card:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .drawer-result-row,
.drawer-result-row:is(.dark *) {
    background-color: #0f172a !important;
    color: #cbd5e1 !important;
}
.dark .drawer-result-row strong,
.drawer-result-row:is(.dark *) strong {
    color: #f8fafc !important;
}
.dark .drawer-progress-row,
.drawer-progress-row:is(.dark *) {
    background-color: rgba(255, 255, 255, 0.04) !important;
    color: #cbd5e1 !important;
}
.dark .drawer-progress-row strong,
.drawer-progress-row:is(.dark *) strong {
    color: #f8fafc !important;
}
.dark .drawer-meta,
.drawer-meta:is(.dark *) {
    color: #94a3b8 !important;
}
.dark .drawer-meta strong,
.drawer-meta:is(.dark *) strong {
    color: #f8fafc !important;
}
.dark .stat-card strong,
.stat-card:is(.dark *) strong {
    color: #ffffff !important;
}
.dark .report-input,
.report-input:is(.dark *) {
    color-scheme: dark;
    background-color: #0f172a !important;
    color: #f1f5f9 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .report-input:disabled,
.report-input:is(.dark *):disabled {
    background-color: #1e293b !important;
    color: #64748b !important;
}
.dark .report-input option,
.report-input:is(.dark *) option {
    background-color: #0f172a !important;
    color: #f1f5f9 !important;
}
.dark .report-status-select,
.report-status-select:is(.dark *) {
    color-scheme: dark;
    background-color: #0f172a !important;
    color: #e2e8f0 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.dark .report-status-select option,
.report-status-select:is(.dark *) option {
    background-color: #0f172a !important;
    color: #f1f5f9 !important;
}
.dark .report-btn,
.dark .page-btn,
.report-btn:is(.dark *),
.page-btn:is(.dark *) {
    background-color: #0f172a !important;
    color: #e2e8f0 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.report-td:is(.dark *) {
    color: #cbd5e1 !important;
}
</style>
