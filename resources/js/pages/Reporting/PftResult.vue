<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import {
    Activity,
    BarChart3,
    ChevronDown,
    ChevronRight,
    Download,
    Dumbbell,
    FileDown,
    Layers,
    Loader2,
    RefreshCw,
    Search,
    Table2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import {
    analytics as pftAnalytics,
    data as pftData,
    exportExcel as pftExportExcel,
    exportPdf as pftExportPdf,
    exportAnalyticsPdf as pftExportAnalyticsPdf,
    index as pftIndex,
} from '@/routes/admin/reporting/pft-result';
import {
    campuses as filterCampuses,
    colleges as filterColleges,
    sections as filterSections,
    terms as filterTerms,
} from '@/routes/admin/reporting/pft-result/filter';

type QueryParams = Record<string, string | number | undefined>;
type SelectOption = { id: string; text: string };
type Select2Payload = {
    results: SelectOption[];
    pagination: { more: boolean };
};
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
type HierarchyTestType = {
    label: string;
    value: number;
    students: number;
    interpretations: AnalyticsGroup[];
};
type HierarchyCategory = {
    label: string;
    value: number;
    students: number;
    interpretations: AnalyticsGroup[];
    test_types: HierarchyTestType[];
};
type HierarchyComponent = {
    label: string;
    value: number;
    students: number;
    interpretations: AnalyticsGroup[];
    categories: HierarchyCategory[];
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
type Analytics = {
    stats: {
        total: number;
        completed: number;
        draft: number;
        interpreted: number;
        unclassified: number;
        test_types: number;
        students: number;
        sections: number;
    };
    interpretations: AnalyticsGroup[];
    componentInterpretations: ComponentInterpretationGroup[];
    testTypeInterpretations: ComponentInterpretationGroup[];
    hierarchy: HierarchyComponent[];
    status: AnalyticsGroup[];
    testTypes: AnalyticsGroup[];
    campuses: AnalyticsGroup[];
    colleges: AnalyticsGroup[];
    yearLevels: AnalyticsGroup[];
    bmi: {
        average: number | null;
        distribution: AnalyticsGroup[];
    };
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
    layout: {
        breadcrumbs: [
            { title: 'Reporting', href: '/admin/reporting/overview' },
            { title: 'PFT Result', href: '/admin/reporting/pft-result' },
        ],
    },
});

const AsyncSelect = defineComponent({
    props: {
        modelValue: { type: String, default: '' },
        selected: { type: Object as () => SelectOption | null, default: null },
        endpoint: { type: String, required: true },
        params: {
            type: Object as () => Record<string, string | number | undefined>,
            default: () => ({}),
        },
        placeholder: { type: String, required: true },
        disabled: { type: Boolean, default: false },
        minInput: { type: Number, default: 0 },
    },
    emits: ['update:modelValue', 'select'],
    setup(componentProps, { emit }) {
        const term = ref('');
        const page = ref(1);
        const options = ref<SelectOption[]>(
            componentProps.selected ? [componentProps.selected] : [],
        );
        const loading = ref(false);
        const more = ref(false);
        let timer: ReturnType<typeof setTimeout> | null = null;

        const fetchOptions = async (reset = true) => {
            if (componentProps.disabled) {
                return;
            }

            if (term.value.length < componentProps.minInput) {
                options.value = componentProps.selected
                    ? [componentProps.selected]
                    : [];
                more.value = false;

                return;
            }

            loading.value = true;
            const nextPage = reset ? 1 : page.value + 1;
            const params = new URLSearchParams();
            params.set('page', String(nextPage));

            if (term.value) {
                params.set('q', term.value);
            }

            Object.entries(componentProps.params).forEach(([key, value]) => {
                if (value !== undefined && value !== '') {
                    params.set(key, String(value));
                }
            });

            const response = await fetch(
                `${componentProps.endpoint}?${params.toString()}`,
                {
                    headers: { Accept: 'application/json' },
                },
            );
            const payload = (await response.json()) as Select2Payload;
            page.value = nextPage;
            options.value = reset
                ? payload.results
                : [...options.value, ...payload.results];
            more.value = payload.pagination.more;
            loading.value = false;
        };

        const debouncedFetch = () => {
            if (timer) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => void fetchOptions(true), 300);
        };

        watch(
            () => componentProps.selected,
            (selected) => {
                options.value = selected ? [selected] : [];
            },
        );

        watch(
            () => componentProps.params,
            () => {
                term.value = '';
                options.value = componentProps.selected
                    ? [componentProps.selected]
                    : [];
                more.value = false;
            },
            { deep: true },
        );

        watch(term, debouncedFetch);

        onMounted(() => void fetchOptions(true));

        return () =>
            h('div', { class: 'grid gap-1' }, [
                h('div', { class: 'relative' }, [
                    h('input', {
                        value: term.value,
                        disabled: componentProps.disabled,
                        placeholder: componentProps.placeholder,
                        class: 'report-input',
                        onInput: (event: Event) => {
                            term.value = (
                                event.target as HTMLInputElement
                            ).value;
                        },
                        onFocus: () => void fetchOptions(true),
                    }),
                    loading.value
                        ? h(Loader2, {
                              class: 'absolute top-2.5 right-2 h-4 w-4 animate-spin text-emerald-600',
                          })
                        : null,
                ]),
                h('div', { class: 'relative min-w-0' }, [
                    h(
                        'select',
                        {
                            value: componentProps.modelValue,
                            disabled: componentProps.disabled,
                            class: 'report-input',
                            onChange: (event: Event) => {
                                const value = (
                                    event.target as HTMLSelectElement
                                ).value;
                                const selected =
                                    options.value.find(
                                        (option) => option.id === value,
                                    ) ?? null;
                                emit('update:modelValue', value);
                                emit('select', selected);
                            },
                            onFocus: () => void fetchOptions(true),
                            onMousedown: () => void fetchOptions(true),
                        },
                        [
                            h(
                                'option',
                                { value: '' },
                                componentProps.disabled
                                    ? 'Select previous filter first'
                                    : 'Select option',
                            ),
                            ...options.value.map((option) =>
                                h(
                                    'option',
                                    { key: option.id, value: option.id },
                                    option.text,
                                ),
                            ),
                        ],
                    ),
                ]),
                more.value
                    ? h(
                          'button',
                          {
                              type: 'button',
                              class: 'report-link-btn',
                              onClick: () => void fetchOptions(false),
                          },
                          'Load more',
                      )
                    : null,
            ]);
    },
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
const analytics = ref<Analytics | null>(null);
const tableLoading = ref(false);
const analyticsLoading = ref(false);
const activeRow = ref<PftRow | null>(null);
const openHierarchyComponents = ref<string[]>([]);
const openHierarchyCategories = ref<string[]>([]);
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
const analyticsScopeLabel = computed(() => {
    if (selectedSection.value?.text) {
        return `Section: ${selectedSection.value.text}`;
    }

    if (selectedCollege.value?.text) {
        return `College: ${selectedCollege.value.text}`;
    }

    return 'Selected campus and term';
});

const hierarchyCategoryKey = (component: string, category: string) =>
    `${component}::${category}`;

const toggleHierarchyComponent = (component: string) => {
    openHierarchyComponents.value = openHierarchyComponents.value.includes(
        component,
    )
        ? openHierarchyComponents.value.filter((item) => item !== component)
        : [...openHierarchyComponents.value, component];
};

const toggleHierarchyCategory = (component: string, category: string) => {
    const key = hierarchyCategoryKey(component, category);
    openHierarchyCategories.value = openHierarchyCategories.value.includes(key)
        ? openHierarchyCategories.value.filter((item) => item !== key)
        : [...openHierarchyCategories.value, key];
};

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

const emptyAnalytics = (): Analytics => ({
    stats: {
        total: 0,
        completed: 0,
        draft: 0,
        interpreted: 0,
        unclassified: 0,
        test_types: 0,
        students: 0,
        sections: 0,
    },
    interpretations: [],
    componentInterpretations: [],
    testTypeInterpretations: [],
    hierarchy: [],
    status: [],
    testTypes: [],
    campuses: [],
    colleges: [],
    yearLevels: [],
    bmi: {
        average: null,
        distribution: [
            { label: 'Underweight', value: 0 },
            { label: 'Normal', value: 0 },
            { label: 'Overweight', value: 0 },
            { label: 'Obese', value: 0 },
        ],
    },
});

const clearResults = () => {
    rows.value = [];
    recordsFiltered.value = 0;
    recordsTotal.value = 0;
    analytics.value = emptyAnalytics();
    openHierarchyComponents.value = [];
    openHierarchyCategories.value = [];
};

const fetchAnalytics = async () => {
    if (!requiredFiltersSelected.value) {
        clearResults();

        return;
    }

    analyticsLoading.value = true;
    const response = await fetch(pftAnalytics.url({ query: routeQuery() }), {
        headers: { Accept: 'application/json' },
    });
    analytics.value = await response.json();
    analyticsLoading.value = false;
};

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
    tableLoading.value = false;
};

const reloadAll = async () => {
    updateBrowserUrl();
    await Promise.all([fetchAnalytics(), fetchTable()]);
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
    openHierarchyComponents.value = [];
    openHierarchyCategories.value = [];
    start.value = 0;
    void reloadAll();
};

const openDrawer = (row: PftRow) => {
    activeRow.value = row;
};

const closeDrawer = () => {
    activeRow.value = null;
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
const exportAnalyticsPdfUrl = computed(() =>
    requiredFiltersSelected.value
        ? pftExportAnalyticsPdf.url({ query: routeQuery() })
        : '#',
);
const analyticsPdfPreviewOpen = ref(false);

const getSparklineOptions = (labels: string[]) => ({
    chart: {
        sparkline: { enabled: true },
        animations: { enabled: true },
    },
    stroke: { curve: 'smooth' as const, width: 2 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 100],
        },
    },
    colors: ['#059669'],
    labels: labels.length ? labels : ['None'],
    tooltip: {
        fixed: { enabled: false },
        x: { show: true },
        y: {
            title: {
                formatter: () => 'Results: ',
            },
        },
        marker: { show: true },
    },
});

const chartOptions = (labels: string[], colors: string[]): ApexOptions => ({
    chart: {
        toolbar: { show: false },
        fontFamily: 'Instrument Sans, sans-serif',
        foreColor: '#64748b',
        background: 'transparent',
    },
    colors,
    dataLabels: { enabled: false },
    labels,
    legend: { position: 'bottom' as const, fontSize: '11px' },
    stroke: { width: 2 },
    xaxis: { categories: labels },
    yaxis: {
        labels: { formatter: (value: number) => String(Math.round(value)) },
    },
    grid: { borderColor: '#e2e8f0' },
});

const interpretationColor = (color?: string) =>
    ({
        emerald: '#059669',
        green: '#16a34a',
        lime: '#65a30d',
        amber: '#f59e0b',
        orange: '#f97316',
        red: '#ef4444',
        rose: '#e11d48',
        slate: '#64748b',
        blue: '#2563eb',
        violet: '#7c3aed',
    })[color ?? 'slate'] ?? '#64748b';

const interpretationBadgeStyle = (color?: string) => {
    const accent = interpretationColor(color);

    return {
        '--interpretation-accent': accent,
        '--interpretation-bg': `${accent}14`,
        '--interpretation-border': `${accent}55`,
        color: accent,
        borderColor: `${accent}55`,
        backgroundColor: `${accent}14`,
    };
};

const interpretationChartOptions = computed(() =>
    chartOptions(
        analytics.value?.interpretations.map((item) => item.label) ?? [],
        analytics.value?.interpretations.map((item) =>
            interpretationColor(item.color),
        ) ?? ['#64748b'],
    ),
);
const interpretationSeries = computed(
    () => analytics.value?.interpretations.map((item) => item.value) ?? [],
);
const testTypeChartOptions = computed(() =>
    chartOptions(
        analytics.value?.testTypeInterpretations.map((item) => item.label) ??
            [],
        ['#059669'],
    ),
);
const testTypeSeries = computed(() => [
    {
        name: 'Interpreted Results',
        data:
            analytics.value?.testTypeInterpretations.map(
                (item) => item.value,
            ) ?? [],
    },
]);
const componentInterpretationChartOptions = computed(() =>
    chartOptions(
        analytics.value?.componentInterpretations.map((item) => item.label) ??
            [],
        analytics.value?.componentInterpretations.map((item) =>
            interpretationColor(item.dominant_color),
        ) ?? ['#64748b'],
    ),
);
const componentInterpretationSeries = computed(
    () =>
        analytics.value?.componentInterpretations.map((item) => item.value) ??
        [],
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

onMounted(() => {
    analytics.value = emptyAnalytics();

    if (requiredFiltersSelected.value) {
        void reloadAll();
    }
});
</script>

<template>
    <Head title="PFT Result" />

    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">
                    PFT Result
                </h1>
                <p class="text-xs text-slate-500">
                    Detailed physical fitness results with campus-first filters
                    and analytics.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    class="report-btn bg-slate-900 text-white dark:bg-white dark:text-slate-900 border-none flex items-center gap-1.5 hover:opacity-90 transition-opacity"
                    href="/admin/reporting/pft-result/analytics"
                >
                    <BarChart3 class="h-3.5 w-3.5 text-emerald-500" />View Analytics
                </Link>
                <div v-if="canExport" class="flex flex-wrap gap-2">
                    <a
                        class="report-btn"
                        :class="{
                            'pointer-events-none opacity-50':
                                !requiredFiltersSelected,
                        }"
                        :href="exportExcelUrl"
                    >
                        <Download class="h-3.5 w-3.5" />Excel
                    </a>
                    <a
                        class="report-btn-primary"
                        :class="{
                            'pointer-events-none opacity-50':
                                !requiredFiltersSelected,
                        }"
                        :href="exportPdfUrl"
                        target="_blank"
                    >
                        <FileDown class="h-3.5 w-3.5" />PDF
                    </a>
                </div>
            </div>
        </div>

        <section
            class="report-card rounded-lg border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
        >
            <div class="grid gap-2 md:grid-cols-2">
                <AsyncSelect
                    v-model="campusId"
                    :selected="selectedCampus"
                    :endpoint="filterEndpoints.campuses"
                    placeholder="Search campus"
                    :min-input="0"
                    @select="onCampusChange"
                />
                <AsyncSelect
                    v-model="termId"
                    :selected="selectedTerm"
                    :endpoint="filterEndpoints.terms"
                    :params="{ campus_id: campusId }"
                    :disabled="!campusId"
                    placeholder="Search academic term"
                    :min-input="0"
                    @select="onTermChange"
                />
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">
                    Campus and Academic Term are required before results load.
                </p>
                <button class="report-btn" @click="resetFilters">
                    <RefreshCw class="h-3.5 w-3.5" />Reset
                </button>
            </div>
        </section>

        <div class="grid gap-3 md:grid-cols-5">
            <div class="stat-card">
                <Table2 class="stat-icon text-emerald-600" /><span
                    >Results</span
                >
                <strong>{{ analytics?.stats.total ?? 0 }}</strong>
            </div>
            <div class="stat-card">
                <Activity class="stat-icon text-emerald-600" /><span
                    >Interpreted</span
                >
                <strong>{{ analytics?.stats.interpreted ?? 0 }}</strong>
            </div>
            <div class="stat-card">
                <Layers class="stat-icon text-amber-600" /><span
                    >Unclassified</span
                >
                <strong>{{ analytics?.stats.unclassified ?? 0 }}</strong>
            </div>
            <div class="stat-card">
                <Dumbbell class="stat-icon text-violet-600" /><span
                    >Test Types</span
                >
                <strong>{{ analytics?.stats.test_types ?? 0 }}</strong>
            </div>
            <div class="stat-card">
                <Users class="stat-icon text-rose-600" /><span>Students</span>
                <strong>{{ analytics?.stats.students ?? 0 }}</strong>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <section
                class="report-card rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="report-heading">Interpretation Distribution</h2>
                    <span
                        v-if="analyticsLoading"
                        class="text-[11px] font-semibold text-emerald-600"
                        >Loading</span
                    >
                </div>
                <VueApexCharts
                    height="240"
                    type="donut"
                    :options="interpretationChartOptions"
                    :series="interpretationSeries"
                />
            </section>
            <section
                class="report-card rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <h2 class="report-heading">Test Types by Interpretation</h2>
                <VueApexCharts
                    height="240"
                    type="bar"
                    :options="testTypeChartOptions"
                    :series="testTypeSeries"
                />
            </section>
            <section
                class="report-card rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="report-heading">Component Interpretation Mix</h2>
                    <span class="text-[11px] font-bold text-slate-500"
                        >{{ analytics?.componentInterpretations.length ?? 0 }}
                        components</span
                    >
                </div>
                <VueApexCharts
                    height="240"
                    type="donut"
                    :options="componentInterpretationChartOptions"
                    :series="componentInterpretationSeries"
                />
            </section>
        </div>

        <section
            class="report-card rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
        >
            <h2 class="report-heading mb-3">Interpretation Summary</h2>
            <div class="grid gap-3 lg:grid-cols-3">
                <div class="mini-list interpretation-list">
                    <strong>Overall Interpretation</strong>
                    <span
                        v-for="item in analytics?.interpretations ?? []"
                        :key="item.label"
                        :style="interpretationBadgeStyle(item.color)"
                    >
                        {{ item.label }} <b>{{ item.value }}</b>
                    </span>
                </div>
                <div class="mini-list interpretation-list">
                    <strong>Components</strong>
                    <span
                        v-for="item in analytics?.componentInterpretations ??
                        []"
                        :key="item.label"
                        :style="interpretationBadgeStyle(item.dominant_color)"
                    >
                        {{ item.label }}
                        <b>{{ item.dominant_label }} ({{ item.value }})</b>
                    </span>
                </div>
                <div class="mini-list interpretation-list">
                    <strong>Test Types</strong>
                    <span
                        v-for="item in analytics?.testTypeInterpretations ??
                        []"
                        :key="item.label"
                        :style="interpretationBadgeStyle(item.dominant_color)"
                    >
                        {{ item.label }}
                        <b>{{ item.dominant_label }}</b>
                    </span>
                </div>
            </div>
        </section>

        <section
            class="report-card rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h2 class="report-heading">
                        Component, Category, and Test Type Analytics
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ analyticsScopeLabel }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canExport"
                        class="report-btn-primary py-1.5 px-3 text-xs flex items-center gap-1.5 shrink-0"
                        :disabled="!requiredFiltersSelected"
                        :class="{ 'opacity-50 pointer-events-none': !requiredFiltersSelected }"
                        @click="analyticsPdfPreviewOpen = true"
                    >
                        <FileDown class="h-3.5 w-3.5" />Export
                    </button>
                    <span class="drawer-pill w-fit">
                        {{ analytics?.hierarchy.length ?? 0 }} components
                    </span>
                </div>
            </div>

            <div
                v-if="analytics?.hierarchy.length"
                class="grid gap-3"
            >
                <article
                    v-for="component in analytics.hierarchy"
                    :key="component.label"
                    class="hierarchy-card"
                >
                    <button
                        class="hierarchy-toggle"
                        type="button"
                        @click="toggleHierarchyComponent(component.label)"
                    >
                        <span class="min-w-0">
                            <strong>{{ component.label }}</strong>
                            <small>
                                {{ component.value }} results ·
                                {{ component.students }} students
                            </small>
                        </span>
                        <span class="hierarchy-chevron">
                            <ChevronDown
                                v-if="
                                    openHierarchyComponents.includes(
                                        component.label,
                                    )
                                "
                                class="h-4 w-4"
                            />
                            <ChevronRight v-else class="h-4 w-4" />
                            <small>
                                {{
                                    openHierarchyComponents.includes(
                                        component.label,
                                    )
                                        ? 'Collapse'
                                        : 'Expand'
                                }}
                            </small>
                        </span>
                    </button>

                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mt-2">
                        <div class="hierarchy-distribution flex-1">
                            <span
                                v-for="item in component.interpretations"
                                :key="`${component.label}-${item.label}`"
                                :style="interpretationBadgeStyle(item.color)"
                            >
                                {{ item.label }}
                                <b>{{ item.value }}</b>
                            </span>
                        </div>
                        <div v-if="component.interpretations.length" class="w-48 h-10 shrink-0">
                            <VueApexCharts
                                type="area"
                                height="40"
                                :options="getSparklineOptions(component.interpretations.map(i => i.label))"
                                :series="[{ name: 'Results', data: component.interpretations.map(i => i.value) }]"
                            />
                        </div>
                    </div>

                    <div
                        v-if="
                            openHierarchyComponents.includes(component.label)
                        "
                        class="mt-3 grid gap-2"
                    >
                        <section
                            v-for="category in component.categories"
                            :key="category.label"
                            class="hierarchy-category"
                        >
                            <button
                                class="hierarchy-toggle hierarchy-toggle-sm"
                                type="button"
                                @click="
                                    toggleHierarchyCategory(
                                        component.label,
                                        category.label,
                                    )
                                "
                            >
                                <span class="min-w-0">
                                    <strong>{{ category.label }}</strong>
                                    <small>
                                        {{ category.value }} results ·
                                        {{ category.students }} students
                                    </small>
                                </span>
                                <span class="hierarchy-chevron">
                                    <ChevronDown
                                        v-if="
                                            openHierarchyCategories.includes(
                                                hierarchyCategoryKey(
                                                    component.label,
                                                    category.label,
                                                ),
                                            )
                                        "
                                        class="h-4 w-4"
                                    />
                                    <ChevronRight v-else class="h-4 w-4" />
                                    <small>
                                        {{
                                            openHierarchyCategories.includes(
                                                hierarchyCategoryKey(
                                                    component.label,
                                                    category.label,
                                                ),
                                            )
                                                ? 'Collapse'
                                                : 'Expand'
                                        }}
                                    </small>
                                </span>
                            </button>

                             <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mt-2">
                                <div class="hierarchy-distribution flex-1">
                                    <span
                                        v-for="item in category.interpretations"
                                        :key="`${component.label}-${category.label}-${item.label}`"
                                        :style="interpretationBadgeStyle(item.color)"
                                    >
                                        {{ item.label }}
                                        <b>{{ item.value }}</b>
                                    </span>
                                </div>
                                <div v-if="category.interpretations.length" class="w-48 h-10 shrink-0">
                                    <VueApexCharts
                                        type="area"
                                        height="40"
                                        :options="getSparklineOptions(category.interpretations.map(i => i.label))"
                                        :series="[{ name: 'Results', data: category.interpretations.map(i => i.value) }]"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="
                                    openHierarchyCategories.includes(
                                        hierarchyCategoryKey(
                                            component.label,
                                            category.label,
                                        ),
                                    )
                                "
                                class="mt-2 grid gap-2 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <div
                                    v-for="testType in category.test_types"
                                    :key="testType.label"
                                    class="hierarchy-test"
                                >
                                    <div>
                                        <strong>{{ testType.label }}</strong>
                                        <small>
                                            {{ testType.value }} results ·
                                            {{ testType.students }} students
                                        </small>
                                    </div>
                                     <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mt-2">
                                        <div class="hierarchy-distribution flex-1">
                                            <span
                                                v-for="item in testType.interpretations"
                                                :key="`${component.label}-${category.label}-${testType.label}-${item.label}`"
                                                :style="
                                                    interpretationBadgeStyle(
                                                        item.color,
                                                    )
                                                "
                                            >
                                                {{ item.label }}
                                                <b>{{ item.value }}</b>
                                            </span>
                                        </div>
                                        <div v-if="testType.interpretations.length" class="w-36 h-8 shrink-0">
                                            <VueApexCharts
                                                type="area"
                                                height="32"
                                                :options="getSparklineOptions(testType.interpretations.map(i => i.label))"
                                                :series="[{ name: 'Results', data: testType.interpretations.map(i => i.value) }]"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
            <p v-else class="drawer-empty-chart">
                No component analytics for the selected filters.
            </p>
        </section>

        <section
            class="report-card overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-2 border-b border-slate-100 p-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
            >
                <div class="flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 text-emerald-600" />
                    <h2
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        Detailed Records
                    </h2>
                    <span
                        v-if="tableLoading"
                        class="text-[11px] font-semibold text-emerald-600"
                        >Loading</span
                    >
                </div>
                <div class="grid gap-2 md:grid-cols-[220px_100px_auto]">
                    <input
                        v-model="search"
                        class="report-input"
                        placeholder="Search records"
                        :disabled="!requiredFiltersSelected"
                        @keydown.enter="resetPageAndReload"
                    />
                    <select
                        v-model.number="pageLength"
                        class="report-input"
                        :disabled="!requiredFiltersSelected"
                        @change="resetPageAndReload"
                    >
                        <option
                            v-for="size in pageSizeOptions"
                            :key="size"
                            :value="size"
                        >
                            {{ size }}
                        </option>
                    </select>
                    <button
                        class="report-btn-primary"
                        :disabled="!requiredFiltersSelected"
                        @click="resetPageAndReload"
                    >
                        <Search class="h-3.5 w-3.5" />Search
                    </button>
                </div>
            </div>
            <div
                class="grid gap-2 border-b border-slate-100 p-3 md:grid-cols-2 dark:border-white/10"
            >
                <AsyncSelect
                    v-model="collegeId"
                    :selected="selectedCollege"
                    :endpoint="filterEndpoints.colleges"
                    :params="{ campus_id: campusId }"
                    :disabled="!campusId || !termId"
                    placeholder="Filter college ID"
                    :min-input="0"
                    @select="onCollegeChange"
                />
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
                    placeholder="Filter section ID"
                    :min-input="0"
                    @select="onSectionChange"
                />
            </div>

            <div
                v-if="!requiredFiltersSelected"
                class="p-8 text-center text-sm text-slate-500"
            >
                Select a campus and academic term to load PFT results.
            </div>
            <div v-else class="overflow-x-auto">
                <table
                    class="min-w-[1100px] divide-y divide-slate-100 text-sm dark:divide-white/10"
                >
                    <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                        <tr>
                            <th class="report-th">#</th>
                            <th class="report-th sortable" @click="sortBy(1)">
                                Name
                            </th>
                            <th class="report-th sortable" @click="sortBy(2)">
                                Term
                            </th>
                            <th class="report-th sortable" @click="sortBy(3)">
                                Campus
                            </th>
                            <th class="report-th sortable" @click="sortBy(4)">
                                College
                            </th>
                            <th class="report-th sortable" @click="sortBy(5)">
                                Section ID
                            </th>
                            <th class="report-th sortable" @click="sortBy(6)">
                                Section Name
                            </th>
                            <th class="report-th sortable" @click="sortBy(7)">
                                Year Level
                            </th>
                            <th class="report-th sortable" @click="sortBy(8)">
                                Test Count
                            </th>
                            <th class="report-th sortable" @click="sortBy(10)">
                                Latest Created
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr
                            v-for="row in rows"
                            :key="`${row.user_id}-${row.term}`"
                            class="hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                        >
                            <td class="report-td font-bold">
                                {{ row.number }}
                            </td>
                            <td class="report-td">
                                <button
                                    class="text-left font-bold text-emerald-700 hover:text-emerald-900"
                                    @click="openDrawer(row)"
                                >
                                    {{ row.student_name }}
                                </button>
                                <div class="text-[11px] text-slate-400">
                                    {{
                                        row.student_no ?? `User #${row.user_id}`
                                    }}
                                </div>
                            </td>
                            <td class="report-td">{{ row.term ?? '-' }}</td>
                            <td class="report-td">{{ row.campus ?? '-' }}</td>
                            <td class="report-td">{{ row.college ?? '-' }}</td>
                            <td class="report-td">
                                {{ row.section_id ?? '-' }}
                            </td>
                            <td class="report-td">
                                {{ row.section_name ?? '-' }}
                            </td>
                            <td class="report-td">
                                {{ row.year_level ?? '-' }}
                            </td>
                            <td class="report-td">
                                <span
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    {{ row.test_count }} tests
                                </span>
                            </td>
                            <td class="report-td">
                                {{ row.latest_created_at ?? '-' }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                colspan="10"
                                class="py-12 text-center text-sm text-slate-400"
                            >
                                No PFT results found for the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-2 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 md:flex-row md:items-center md:justify-between dark:border-white/10"
            >
                <span>
                    Showing {{ recordsFiltered === 0 ? 0 : start + 1 }}-{{
                        Math.min(start + pageLength, recordsFiltered)
                    }}
                    of {{ recordsFiltered }} filtered records
                    <span v-if="recordsTotal !== recordsFiltered"
                        >({{ recordsTotal }} total)</span
                    >
                </span>
                <div class="flex gap-1">
                    <button
                        class="page-btn"
                        :disabled="!canPrevious"
                        @click="previousPage"
                    >
                        Previous
                    </button>
                    <span class="page-btn page-btn-static"
                        >Page {{ page }} of {{ lastPage }}</span
                    >
                    <button
                        class="page-btn"
                        :disabled="!canNext"
                        @click="nextPage"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>

        <div
            v-if="activeRow"
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/35"
            @click.self="closeDrawer"
        >
            <aside
                class="h-full w-full max-w-5xl overflow-y-auto border-l border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-slate-100 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
                >
                    <div>
                        <h3
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{ activeRow.student_name }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{
                                activeRow.student_no ??
                                `User #${activeRow.user_id}`
                            }}
                            <span v-if="activeRow.student_email">
                                · {{ activeRow.student_email }}
                            </span>
                        </p>
                    </div>
                    <button class="report-btn shrink-0" @click="closeDrawer">
                        <X class="h-3.5 w-3.5" />Close
                    </button>
                </div>

                <div class="grid gap-3 p-4">
                    <div class="drawer-summary">
                        <div class="drawer-meta">
                            <span>Term</span
                            ><strong>{{
                                activeRow.term_label ?? activeRow.term ?? '-'
                            }}</strong>
                        </div>
                        <div class="drawer-meta">
                            <span>Campus</span
                            ><strong>{{
                                activeRow.campus_label ??
                                activeRow.campus ??
                                '-'
                            }}</strong>
                        </div>
                        <div class="drawer-meta">
                            <span>College</span
                            ><strong>{{
                                activeRow.college_label ??
                                activeRow.college ??
                                '-'
                            }}</strong>
                        </div>
                        <div class="drawer-meta">
                            <span>Section</span>
                            <strong>
                                {{
                                    activeRow.section_name ??
                                    activeRow.section_id ??
                                    '-'
                                }}
                            </strong>
                        </div>
                        <div class="drawer-meta">
                            <span>Year Level</span
                            ><strong>{{ activeRow.year_level ?? '-' }}</strong>
                        </div>
                    </div>

                    <section class="drawer-analytics-panel">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="report-heading">
                                Student Fitness Profile
                            </h4>
                            <span class="drawer-pill">Radar</span>
                        </div>
                        <div class="drawer-chart-shell">
                            <VueApexCharts
                                v-if="activeRow.radar_profile.labels.length > 0"
                                height="320"
                                type="radar"
                                :options="fitnessProfileRadarOptions"
                                :series="fitnessProfileRadarSeries"
                            />
                            <p v-else class="drawer-empty-chart">
                                No interpreted component data.
                            </p>
                        </div>
                    </section>

                    <section class="drawer-analytics-panel">
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="report-heading">
                                Interpretation Summary
                            </h4>
                            <span class="drawer-pill">Current student</span>
                        </div>
                        <div
                            v-if="
                                activeRow.current_analytics
                                    .component_interpretations.length
                            "
                            class="grid gap-3 xl:grid-cols-2"
                        >
                            <article
                                v-for="group in activeRow.current_analytics
                                    .component_interpretations"
                                :key="group.label"
                                class="drawer-component-card"
                            >
                                <div
                                    class="mb-3 flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h5
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ group.label }}
                                        </h5>
                                        <p class="text-xs text-slate-500">
                                            Dominant interpretation
                                        </p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <strong
                                            class="block text-lg text-emerald-700 dark:text-emerald-300"
                                        >
                                            {{ group.dominant_label }}
                                        </strong>
                                        <span class="text-slate-500">
                                            {{ group.value }} results
                                        </span>
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <div
                                        v-for="item in group.interpretations"
                                        :key="item.label"
                                        class="drawer-result-row"
                                    >
                                        <span>{{ item.label }}</span>
                                        <strong>
                                            {{ item.value }}
                                        </strong>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <p v-else class="drawer-empty-chart">
                            No interpreted result data.
                        </p>
                    </section>



                    <section
                        v-for="group in activeComponentGroups"
                        :key="group.component"
                        class="rounded-lg border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="mb-3 flex items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-white/10"
                        >
                            <h4
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ group.component }}
                            </h4>
                            <span
                                class="w-fit rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                {{ group.details.length }} tests
                            </span>
                        </div>

                        <div class="grid gap-3 xl:grid-cols-2">
                            <article
                                v-for="detail in group.details"
                                :key="detail.id"
                                class="rounded-lg bg-slate-50 p-3 dark:bg-white/[0.04]"
                            >
                                <div
                                    class="mb-3 flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                                >
                                    <div>
                                        <h5
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                        >
                                            {{
                                                detail.pft_test_type ??
                                                'PFT Test'
                                            }}
                                        </h5>
                                        <p class="text-xs text-slate-500">
                                            {{ detail.category ?? 'Category' }}
                                            · Tested
                                            {{ detail.tested_date ?? '-' }}
                                        </p>
                                    </div>
                                    <span
                                        class="w-fit rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 uppercase dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        {{
                                            detail.interpretation?.label ??
                                            'Unclassified'
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="detail.results.length"
                                    class="grid gap-1"
                                >
                                    <div
                                        v-for="line in detail.results"
                                        :key="line.key"
                                        class="text-xs text-slate-600 dark:text-slate-300"
                                    >
                                        <span
                                            class="font-bold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ line.label }}:
                                        </span>
                                        {{ line.value }}
                                    </div>
                                </div>
                                <p v-else class="text-xs text-slate-400">
                                    No result data.
                                </p>

                                <div
                                    class="mt-3 grid gap-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:border-white/10"
                                >
                                    <span
                                        >Remarks:
                                        {{ detail.remarks ?? '-' }}</span
                                    >
                                    <span
                                        >Created:
                                        {{ detail.created_at ?? '-' }}</span
                                    >
                                </div>
                            </article>
                        </div>
                    </section>
                    <p
                        v-if="activeComponentGroups.length === 0"
                        class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-white/10"
                    >
                        No PFT result details found.
                    </p>
                </div>
            </aside>
        </div>

        <!-- PDF Export Preview Modal -->
        <div
            v-if="analyticsPdfPreviewOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-xs"
            @click.self="analyticsPdfPreviewOpen = false"
        >
            <div
                class="flex h-[90vh] w-full max-w-5xl flex-col rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950 overflow-hidden"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-900"
                >
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            PFT Analytics PDF Report Preview
                        </h3>
                        <p class="text-xs text-slate-500">
                            Pre-rendering report using Browsershot
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="exportAnalyticsPdfUrl"
                            download
                            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700 transition"
                        >
                            <Download class="h-3.5 w-3.5" /> Download PDF
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                            @click="analyticsPdfPreviewOpen = false"
                        >
                            <X class="h-3.5 w-3.5" /> Close
                        </button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-100 dark:bg-slate-950 relative">
                    <iframe
                        v-if="exportAnalyticsPdfUrl !== '#'"
                        :src="exportAnalyticsPdfUrl"
                        class="w-full h-full border-none bg-white"
                    ></iframe>
                    <div
                        v-else
                        class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 gap-2 bg-slate-50 dark:bg-slate-950"
                    >
                        <Loader2 class="h-8 w-8 animate-spin text-emerald-600" />
                        <span class="text-xs">Preparing PDF preview...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.stat-card {
    @apply rounded-lg border border-slate-200 bg-white p-4 text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-950;
    background-color: #ffffff !important;
    color: #64748b !important;
}
.stat-card strong {
    @apply mt-2 block text-2xl text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.stat-icon {
    @apply mb-3 h-5 w-5;
}
.report-card {
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-heading {
    @apply text-xs font-bold tracking-wide text-slate-500 uppercase;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-emerald-400 focus:outline-none disabled:bg-slate-100 disabled:text-slate-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    color-scheme: light;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
}
.report-input:disabled {
    background-color: #f1f5f9 !important;
    color: #94a3b8 !important;
}
.report-input option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}
.report-btn,
.page-btn {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
    background-color: #ffffff !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.report-link-btn {
    @apply text-left text-[11px] font-bold text-emerald-700 hover:text-emerald-900;
}
.report-btn-primary {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50;
}
.report-th {
    @apply px-3 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}
.sortable {
    @apply cursor-pointer select-none hover:text-emerald-700;
}
.report-td {
    @apply px-3 py-2 text-xs text-slate-600 dark:text-slate-300;
    color: #334155 !important;
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
