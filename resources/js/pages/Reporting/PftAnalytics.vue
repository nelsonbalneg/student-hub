<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import {
    Activity,
    BarChart3,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Dumbbell,
    FileDown,
    Layers,
    Loader2,
    Printer,
    RefreshCw,
    Search,
    Table2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import {
    analyticsPage as pftAnalyticsPage,
    analyticsData as pftAnalyticsData,
    analyticsDrilldown as pftAnalyticsDrilldown,
    exportDrilldownExcel as pftExportDrilldownExcel,
} from '@/routes/admin/reporting/pft-result';
import {
    campuses as filterCampuses,
    colleges as filterColleges,
    sections as filterSections,
    terms as filterTerms,
} from '@/routes/admin/reporting/pft-result/filter';

type SelectOption = { id: string; text: string };
type Select2Payload = {
    results: SelectOption[];
    pagination: { more: boolean };
};

type ExecutiveStats = {
    total_students: number;
    total_components: number;
    total_test_types: number;
    total_campuses: number;
    total_colleges: number;
    total_sections: number;
    requiring_intervention: number;
    target_performance: number;
};

type ClassificationStat = {
    id: number;
    test_type_id: number;
    test_type: string;
    component: string;
    classification: string;
    interpretation: string | null;
    suggested_intervention: string | null;
    color_class: string;
    student_count: number;
    percentage: number;
};

type InterventionItem = {
    classification: string;
    test_type: string;
    component: string;
    suggested_intervention: string;
    student_count: number;
    percentage: number;
    priority: string;
    priority_weight: number;
    color_class: string;
};

type AnalyticsData = {
    campuses: { id: string; name: string; total_students: number; total_results: number }[];
    components: { id: number; name: string; total_results: number; classifications: Record<string, number> }[];
    test_types: { id: number; name: string; component_id: number; total_results: number; classifications: Record<string, number> }[];
    classifications: ClassificationStat[];
    interventions: InterventionItem[];
    executive_stats: ExecutiveStats;
    college_comparison: { college: string; score: number }[];
    section_comparison: { section: string; score: number }[];
    term_trends: { term: string; score: number }[];
    component_radar: { component: string; score: number }[];
    overall_distribution: { classification: string; color_class: string; total: number }[];
    component_distribution: { component: string; classification: string; total: number }[];
};

type DrilldownRow = {
    student_no: string | null;
    student_name: string;
    campus: string;
    college: string;
    section: string;
    year_level: string;
    component: string;
    test_type: string;
    raw_result: string;
    classification: string;
    remarks: string | null;
    test_date: string | null;
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
            { title: 'Analytics', href: '/admin/reporting/pft-result/analytics' },
        ],
    },
});

// Reusable AsyncSelect helper
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
                h('div', { class: 'relative animate-fade-in' }, [
                    h('input', {
                        value: term.value,
                        disabled: componentProps.disabled,
                        placeholder: componentProps.placeholder,
                        class: 'report-input pr-8 bg-white/70 backdrop-blur-sm focus:bg-white transition-all duration-300 border-slate-200 dark:border-slate-800 dark:bg-slate-900/50',
                        onInput: (event: Event) => {
                            term.value = (
                                event.target as HTMLInputElement
                            ).value;
                        },
                        onFocus: () => void fetchOptions(true),
                    }),
                    loading.value
                        ? h(Loader2, {
                              class: 'absolute top-2.5 right-2.5 h-4 w-4 animate-spin text-emerald-600',
                          })
                        : null,
                ]),
                h('div', { class: 'relative min-w-0' }, [
                    h(
                        'select',
                        {
                            value: componentProps.modelValue,
                            disabled: componentProps.disabled,
                            class: 'report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900',
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
                              class: 'report-link-btn text-[11px] mt-1 text-emerald-600 font-semibold text-left',
                              onClick: () => void fetchOptions(false),
                          },
                          'Load more...',
                      )
                    : null,
            ]);
    },
});

// Page level state filters
const campusId = ref(props.filters.campus_id ?? '');
const termId = ref(props.filters.term_id ?? '');
const collegeId = ref(props.filters.college_id ?? '');
const sectionId = ref(props.filters.section_id ?? '');
const componentId = ref('');
const testTypeId = ref('');
const yearLevelId = ref('');
const sex = ref('');

const selectedCampus = ref<SelectOption | null>(props.selectedOptions.campus);
const selectedTerm = ref<SelectOption | null>(props.selectedOptions.term);
const selectedCollege = ref<SelectOption | null>(props.selectedOptions.college);
const selectedSection = ref<SelectOption | null>(props.selectedOptions.section);

const apiData = ref<AnalyticsData | null>(null);
const loading = ref(false);

const filterEndpoints = {
    campuses: filterCampuses.url(),
    terms: filterTerms.url(),
    colleges: filterColleges.url(),
    sections: filterSections.url(),
};

const interpretationColor = (color: string | null) => {
    return (
        {
            emerald: '#10b981',
            green: '#10b981',
            lime: '#84cc16',
            amber: '#f59e0b',
            orange: '#f97316',
            red: '#ef4444',
            rose: '#f43f5e',
            slate: '#64748b',
            blue: '#3b82f6',
            violet: '#8b5cf6',
        }[color ?? 'slate'] ?? '#64748b'
    );
};

const fetchAnalyticsData = async () => {
    loading.value = true;
    try {
        const queryParams = new URLSearchParams();
        if (campusId.value) queryParams.set('campus_id', campusId.value);
        if (termId.value) queryParams.set('term_id', termId.value);
        if (collegeId.value) queryParams.set('college_id', collegeId.value);
        if (sectionId.value) queryParams.set('section_id', sectionId.value);
        if (componentId.value) queryParams.set('component_id', componentId.value);
        if (testTypeId.value) queryParams.set('test_type_id', testTypeId.value);
        if (yearLevelId.value) queryParams.set('year_level_id', yearLevelId.value);
        if (sex.value) queryParams.set('sex', sex.value);

        const response = await fetch(`${pftAnalyticsData.url()}?${queryParams.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        apiData.value = (await response.json()) as AnalyticsData;
        
        // update browser URL representation without reload
        const url = new URL(window.location.href);
        queryParams.forEach((val, key) => url.searchParams.set(key, val));
        window.history.replaceState({}, '', url.toString());
    } catch (err) {
        console.error('Failed fetching PFT analytics details:', err);
    } finally {
        loading.value = false;
    }
};

const onCampusChange = (selected: SelectOption | null) => {
    selectedCampus.value = selected;
    campusId.value = selected?.id ?? '';
    termId.value = '';
    selectedTerm.value = null;
    collegeId.value = '';
    selectedCollege.value = null;
    sectionId.value = '';
    selectedSection.value = null;
    apiData.value = null;
};

const onTermChange = (selected: SelectOption | null) => {
    selectedTerm.value = selected;
    termId.value = selected?.id ?? '';
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
};

const onCollegeChange = (selected: SelectOption | null) => {
    selectedCollege.value = selected;
    collegeId.value = selected?.id ?? '';
    sectionId.value = '';
    selectedSection.value = null;
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
};

const onSectionChange = (selected: SelectOption | null) => {
    selectedSection.value = selected;
    sectionId.value = selected?.id ?? '';
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
};

const resetFilters = () => {
    campusId.value = '';
    selectedCampus.value = null;
    termId.value = '';
    selectedTerm.value = null;
    collegeId.value = '';
    selectedCollege.value = null;
    sectionId.value = '';
    selectedSection.value = null;
    componentId.value = '';
    testTypeId.value = '';
    yearLevelId.value = '';
    sex.value = '';
    apiData.value = null;
    const url = new URL(window.location.href);
    url.search = '';
    window.history.replaceState({}, '', url.toString());
};

const expandedComponents = ref<number[]>([]);
const expandedCategories = ref<number[]>([]);
const expandedTestTypes = ref<number[]>([]);

const toggleComponent = (id: number) => {
    if (expandedComponents.value.includes(id)) {
        expandedComponents.value = expandedComponents.value.filter((x) => x !== id);
    } else {
        expandedComponents.value.push(id);
    }
};

const toggleCategory = (id: number) => {
    if (expandedCategories.value.includes(id)) {
        expandedCategories.value = expandedCategories.value.filter((x) => x !== id);
    } else {
        expandedCategories.value.push(id);
    }
};

const toggleTestType = (id: number) => {
    if (expandedTestTypes.value.includes(id)) {
        expandedTestTypes.value = expandedTestTypes.value.filter((x) => x !== id);
    } else {
        expandedTestTypes.value.push(id);
    }
};

const getClassificationColor = (name: string) => {
    const found = apiData.value?.classifications.find(
        (c) => c.classification === name || c.classification?.toLowerCase() === name.toLowerCase()
    );
    return found?.color_class ?? 'slate';
};

// Drilldown Modal logic
const drilldownOpen = ref(false);
const drilldownLoading = ref(false);
const drilldownRows = ref<DrilldownRow[]>([]);
const drilldownTotal = ref(0);
const drilldownFilteredCount = ref(0);
const drilldownSearch = ref('');
const drilldownPage = ref(1);
const drilldownPageLength = ref(10);
const drilldownDraw = ref(1);

const drilldownCriteria = ref<{
    classification?: string;
    componentId?: string;
    testTypeId?: string;
    title: string;
}>({ title: '' });

const openDrilldown = (options: {
    classification?: string;
    componentId?: string;
    testTypeId?: string;
    title: string;
}) => {
    drilldownCriteria.value = options;
    drilldownOpen.value = true;
    drilldownPage.value = 1;
    drilldownSearch.value = '';
    void fetchDrilldown();
};

const fetchDrilldown = async () => {
    drilldownLoading.value = true;
    try {
        const queryParams = new URLSearchParams();
        if (campusId.value) queryParams.set('campus_id', campusId.value);
        if (termId.value) queryParams.set('term_id', termId.value);
        if (collegeId.value) queryParams.set('college_id', collegeId.value);
        if (sectionId.value) queryParams.set('section_id', sectionId.value);
        if (componentId.value) queryParams.set('component_id', componentId.value);
        if (testTypeId.value) queryParams.set('test_type_id', testTypeId.value);
        if (yearLevelId.value) queryParams.set('year_level_id', yearLevelId.value);
        if (sex.value) queryParams.set('sex', sex.value);

        if (drilldownCriteria.value.classification) {
            queryParams.set('classification', drilldownCriteria.value.classification);
        }
        if (drilldownCriteria.value.componentId) {
            queryParams.set('component_id', drilldownCriteria.value.componentId);
        }
        if (drilldownCriteria.value.testTypeId) {
            queryParams.set('test_type_id', drilldownCriteria.value.testTypeId);
        }

        queryParams.set('start', String((drilldownPage.value - 1) * drilldownPageLength.value));
        queryParams.set('length', String(drilldownPageLength.value));
        queryParams.set('search', drilldownSearch.value);
        queryParams.set('draw', String(drilldownDraw.value++));

        const response = await fetch(`${pftAnalyticsDrilldown.url()}?${queryParams.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const res = await response.json();
        drilldownRows.value = res.data as DrilldownRow[];
        drilldownFilteredCount.value = res.recordsFiltered;
        drilldownTotal.value = res.recordsTotal;
    } catch (err) {
        console.error('Drilldown fetch error:', err);
    } finally {
        drilldownLoading.value = false;
    }
};

const exportDrilldownExcel = () => {
    const queryParams = new URLSearchParams();
    if (campusId.value) queryParams.set('campus_id', campusId.value);
    if (termId.value) queryParams.set('term_id', termId.value);
    if (collegeId.value) queryParams.set('college_id', collegeId.value);
    if (sectionId.value) queryParams.set('section_id', sectionId.value);
    if (componentId.value) queryParams.set('component_id', componentId.value);
    if (testTypeId.value) queryParams.set('test_type_id', testTypeId.value);
    if (yearLevelId.value) queryParams.set('year_level_id', yearLevelId.value);
    if (sex.value) queryParams.set('sex', sex.value);

    if (drilldownCriteria.value.classification) {
        queryParams.set('classification', drilldownCriteria.value.classification);
    }
    if (drilldownCriteria.value.componentId) {
        queryParams.set('component_id', drilldownCriteria.value.componentId);
    }
    if (drilldownCriteria.value.testTypeId) {
        queryParams.set('test_type_id', drilldownCriteria.value.testTypeId);
    }
    
    window.location.href = `${pftExportDrilldownExcel.url()}?${queryParams.toString()}`;
};

const printDrilldown = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    
    let rowsHtml = '';
    drilldownRows.value.forEach((row) => {
        rowsHtml += `
            <tr>
                <td>${row.student_no ?? '-'}</td>
                <td>${row.student_name}</td>
                <td>${row.campus}</td>
                <td>${row.college}</td>
                <td>${row.section}</td>
                <td>${row.year_level}</td>
                <td>${row.component}</td>
                <td>${row.test_type}</td>
                <td>${row.raw_result}</td>
                <td>${row.classification}</td>
                <td>${row.test_date ?? '-'}</td>
            </tr>
        `;
    });

    printWindow.document.write(`
        <html>
        <head>
            <title>PFT Drilldown - ${drilldownCriteria.value.title}</title>
            <style>
                body { font-family: sans-serif; font-size: 12px; color: #333; padding: 20px; }
                h1 { font-size: 16px; color: #111; margin-bottom: 2px; }
                h2 { font-size: 12px; color: #666; margin-top: 0; margin-bottom: 20px; font-weight: normal; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <h1>Physical Fitness Intelligence List</h1>
            <h2>Criteria: ${drilldownCriteria.value.title} &middot; Generated: ${new Date().toLocaleString()}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student No</th>
                        <th>Name</th>
                        <th>Campus</th>
                        <th>College</th>
                        <th>Section</th>
                        <th>Year</th>
                        <th>Component</th>
                        <th>Test Type</th>
                        <th>Result</th>
                        <th>Classification</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        </body>
        </html>
    `);
    printWindow.document.close();
};

const printInterventionList = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    let rowsHtml = '';
    drilldownRows.value.forEach((row, i) => {
        // match interpretation suggested intervention
        const rule = apiData.value?.classifications.find(
            (c) => c.test_type === row.test_type && c.classification === row.classification
        );
        const intervention = rule?.suggested_intervention ?? 'Standard wellness activities & fitness monitoring.';
        
        rowsHtml += `
            <div style="page-break-inside: avoid; border-bottom: 1px solid #eee; padding: 12px 0;">
                <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 4px;">
                    <div>${i + 1}. ${row.student_name} (${row.student_no ?? '-'})</div>
                    <div style="color: #c2410c; text-transform: uppercase; font-size: 11px;">${row.classification}</div>
                </div>
                <div style="color: #666; font-size: 11px; margin-bottom: 6px;">
                    Campus: ${row.campus} &middot; College: ${row.college} &middot; Section: ${row.section} &middot; Test: ${row.test_type} (${row.component})
                </div>
                <div style="background-color: #fff7ed; border-left: 3px solid #f97316; padding: 6px 10px; font-size: 11.5px; color: #7c2d12;">
                    <strong>Suggested Intervention:</strong> ${intervention}
                </div>
            </div>
        `;
    });

    printWindow.document.write(`
        <html>
        <head>
            <title>PFT Intervention Plan - ${drilldownCriteria.value.title}</title>
            <style>
                body { font-family: sans-serif; font-size: 12px; color: #333; padding: 30px; }
                .header { border-bottom: 2px solid #ea580c; padding-bottom: 10px; margin-bottom: 20px; }
                h1 { font-size: 18px; color: #ea580c; margin: 0; }
                h2 { font-size: 12px; color: #666; margin: 4px 0 0 0; font-weight: normal; }
                .sub-title { font-size: 13px; font-weight: bold; color: #111; margin-bottom: 15px; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="header">
                <h1>Physical Fitness Intervention Plan</h1>
                <h2>Accreditation & Wellness Development Report</h2>
            </div>
            <div class="sub-title">Target Group: ${drilldownCriteria.value.title} &middot; Affected Students: ${drilldownRows.value.length}</div>
            <div>
                ${rowsHtml}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
};

const handleChartClick = (event: any, chartContext: any, config: any) => {
    // Determine what was clicked
    if (config.dataPointIndex === undefined || config.seriesIndex === undefined) return;
    
    // Check if it's the classification bar chart
    if (chartContext.opts.chart.id === 'overall-distribution-chart') {
        const item = apiData.value?.overall_distribution[config.dataPointIndex];
        if (item) {
            openDrilldown({
                classification: item.classification,
                title: `Classification: ${item.classification}`,
            });
        }
    } else if (chartContext.opts.chart.id === 'component-performance-chart') {
        // Stacked Component Distribution
        const compName = chartContext.opts.xaxis.categories[config.dataPointIndex];
        const classificationLabel = chartContext.opts.series[config.seriesIndex].name;
        const compObj = apiData.value?.components.find(c => c.name === compName);
        if (compObj) {
            openDrilldown({
                classification: classificationLabel,
                componentId: String(compObj.id),
                title: `${compName} - ${classificationLabel}`,
            });
        }
    }
};

// Standard ApexCharts options mapper helper
const makeOptions = (config: ApexOptions): ApexOptions => ({
    chart: {
        fontFamily: 'Inter, sans-serif',
        background: 'transparent',
        toolbar: { show: false },
    },
    theme: { mode: 'light' },
    ...config,
});

// Overall distribution (Campus Physical Fitness Profile)
const overallDistributionCategories = computed(() =>
    apiData.value?.overall_distribution.map((item) => item.classification) ?? []
);
const overallDistributionSeries = computed(() => [
    {
        name: 'Students',
        data: apiData.value?.overall_distribution.map((item) => item.total) ?? [],
    },
]);
const overallDistributionOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: {
            id: 'overall-distribution-chart',
            events: { click: handleChartClick },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '60%',
                distributed: true,
                borderRadius: 4,
            },
        },
        colors: apiData.value?.overall_distribution.map((item) =>
            interpretationColor(item.color_class)
        ) ?? [],
        dataLabels: { enabled: true, formatter: (val) => String(val) },
        legend: { show: false },
        xaxis: { categories: overallDistributionCategories.value },
    })
);

// Component Performance Stacked Bar Chart
const componentPerformanceCategories = computed(() => {
    if (!apiData.value) return [];
    return apiData.value.components.map((c) => c.name);
});
const componentPerformanceSeries = computed(() => {
    if (!apiData.value) return [];
    // Get unique classifications
    const classifications = Array.from(
        new Set(apiData.value.component_distribution.map((item) => item.classification))
    );
    
    return classifications.map((className) => {
        const data = apiData.value!.components.map((comp) => {
            const match = apiData.value!.component_distribution.find(
                (item) => item.component === comp.name && item.classification === className
            );
            return match ? match.total : 0;
        });

        // resolve first matching rule color
        const rule = apiData.value!.classifications.find((c) => c.classification === className);
        const color = interpretationColor(rule?.color_class ?? 'slate');

        return {
            name: className,
            data,
            color,
        };
    });
});
const componentPerformanceOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: {
            id: 'component-performance-chart',
            stacked: true,
            events: { click: handleChartClick },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '50%',
                borderRadius: 4,
            },
        },
        xaxis: { categories: componentPerformanceCategories.value },
        yaxis: { title: { text: 'Student Count' } },
        legend: { position: 'top', horizontalAlign: 'left' },
    })
);

// Radar Component Ranking
const componentRadarCategories = computed(() =>
    apiData.value?.component_radar.map((item) => item.component) ?? []
);
const componentRadarSeries = computed(() => [
    {
        name: 'Average score',
        data: apiData.value?.component_radar.map((item) => item.score) ?? [],
    },
]);
const componentRadarOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'radar' },
        colors: ['#0f766e'],
        xaxis: { categories: componentRadarCategories.value },
        yaxis: { min: 0, max: 100 },
        fill: { opacity: 0.2 },
        stroke: { width: 2 },
    })
);

// College comparison
const collegeComparisonCategories = computed(() =>
    apiData.value?.college_comparison.map((item) => item.college) ?? []
);
const collegeComparisonSeries = computed(() => [
    {
        name: 'Average performance score',
        data: apiData.value?.college_comparison.map((item) => item.score) ?? [],
    },
]);
const collegeComparisonOptions = computed<ApexOptions>(() =>
    makeOptions({
        colors: ['#059669'],
        plotOptions: { bar: { columnWidth: '40%', borderRadius: 4 } },
        xaxis: { categories: collegeComparisonCategories.value },
        yaxis: { min: 0, max: 100 },
    })
);

// Section comparison
const sectionComparisonCategories = computed(() =>
    apiData.value?.section_comparison.map((item) => item.section) ?? []
);
const sectionComparisonSeries = computed(() => [
    {
        name: 'Average performance score',
        data: apiData.value?.section_comparison.map((item) => item.score) ?? [],
    },
]);
const sectionComparisonOptions = computed<ApexOptions>(() =>
    makeOptions({
        colors: ['#0d9488'],
        plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
        xaxis: { categories: sectionComparisonCategories.value },
        yaxis: { min: 0, max: 100 },
    })
);

// Term Trend Analysis
const termTrendCategories = computed(() =>
    apiData.value?.term_trends.map((item) => item.term) ?? []
);
const termTrendSeries = computed(() => [
    {
        name: 'Campus fitness average',
        data: apiData.value?.term_trends.map((item) => item.score) ?? [],
    },
]);
const termTrendOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'line' },
        colors: ['#0f766e'],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 4 },
        xaxis: { categories: termTrendCategories.value },
        yaxis: { min: 0, max: 100 },
    })
);

// Campus Comparison
const campusCategories = computed(() =>
    apiData.value?.campuses.map((item) => item.name) ?? []
);
const campusComparisonSeries = computed(() => [
    {
        name: 'Tested Students',
        data: apiData.value?.campuses.map((item) => item.total_students) ?? [],
    },
    {
        name: 'Total PFT Results',
        data: apiData.value?.campuses.map((item) => item.total_results) ?? [],
    },
]);
const campusComparisonOptions = computed<ApexOptions>(() =>
    makeOptions({
        colors: ['#0f766e', '#14b8a6'],
        plotOptions: { bar: { horizontal: true, barHeight: '50%', borderRadius: 4 } },
        xaxis: { categories: campusCategories.value },
    })
);

const getComponentTestTypeData = (componentName: string, testTypeName: string) => {
    const stats = apiData.value?.classifications.filter(
        (c) => c.component === componentName && c.test_type === testTypeName
    ) ?? [];
    
    const categories = stats.map(s => s.classification);
    const data = stats.map(s => s.student_count);
    const colors = stats.map(s => interpretationColor(s.color_class));
    const total = data.reduce((a, b) => a + b, 0);

    return {
        series: [{ name: 'Students', data }],
        donutSeries: data,
        total,
        barOptions: makeOptions({
            plotOptions: { bar: { horizontal: false, columnWidth: '60%', distributed: true, borderRadius: 4 } },
            colors,
            xaxis: { categories },
            legend: { show: false },
        }),
        donutOptions: makeOptions({
            labels: categories,
            colors,
            legend: { position: 'bottom' },
        }),
    };
};

watch(
    [componentId, testTypeId, yearLevelId, sex],
    () => {
        if (campusId.value && termId.value) {
            void fetchAnalyticsData();
        }
    }
);

onMounted(() => {
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
});
</script>

<template>
    <Head title="Physical Fitness Intelligence Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950/80">
        <!-- Dashboard Header -->
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <Link
                        class="report-btn py-1 px-2 text-xs flex items-center gap-1 border-slate-200 hover:bg-slate-100"
                        href="/admin/reporting/pft-result"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />Back to Results
                    </Link>
                </div>
                <h1 class="text-lg font-extrabold text-slate-900 dark:text-white mt-1">
                    Physical Fitness Intelligence Dashboard
                </h1>
                <p class="text-xs text-slate-500">
                    Accreditation-ready overall campus analytics, comparison modules, and interventions.
                </p>
            </div>
            
            <div v-if="apiData && canExport" class="flex items-center gap-2">
                <a
                    class="report-btn-primary py-2 px-4 flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium shadow-md shadow-emerald-600/10"
                    :href="`/admin/reporting/pft-result/export/analytics-pdf?campus_id=${campusId}&term_id=${termId}&college_id=${collegeId}&section_id=${sectionId}`"
                    target="_blank"
                >
                    <FileDown class="h-4 w-4" />Export Analytics PDF
                </a>
            </div>
        </div>

        <!-- Filters Section -->
        <section class="report-card rounded-xl border border-slate-200/80 bg-white/70 backdrop-blur-md p-4 dark:border-white/5 dark:bg-slate-900/50 shadow-sm">
            <h2 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3">
                Dashboard Queries & Filters
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Campus</label>
                    <AsyncSelect
                        v-model="campusId"
                        :selected="selectedCampus"
                        :endpoint="filterEndpoints.campuses"
                        placeholder="Search campus"
                        @select="onCampusChange"
                    />
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Academic Term</label>
                    <AsyncSelect
                        v-model="termId"
                        :selected="selectedTerm"
                        :endpoint="filterEndpoints.terms"
                        :params="{ campus_id: campusId }"
                        :disabled="!campusId"
                        placeholder="Search academic term"
                        @select="onTermChange"
                    />
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">College</label>
                    <AsyncSelect
                        v-model="collegeId"
                        :selected="selectedCollege"
                        :endpoint="filterEndpoints.colleges"
                        :params="{ campus_id: campusId }"
                        :disabled="!campusId"
                        placeholder="Search college"
                        @select="onCollegeChange"
                    />
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Section</label>
                    <AsyncSelect
                        v-model="sectionId"
                        :selected="selectedSection"
                        :endpoint="filterEndpoints.sections"
                        :params="{ campus_id: campusId, term_id: termId, college_id: collegeId }"
                        :disabled="!campusId || !termId || !collegeId"
                        placeholder="Search section"
                        @select="onSectionChange"
                    />
                </div>
            </div>

            <!-- Optional secondary filters -->
            <div v-if="campusId && termId" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Year Level</label>
                    <select v-model="yearLevelId" class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900">
                        <option value="">All Years</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Sex</label>
                    <select v-model="sex" class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900">
                        <option value="">All Genders</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Component</label>
                    <select v-model="componentId" class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900">
                        <option value="">All Components</option>
                        <option v-for="c in apiData?.components ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Test Type</label>
                    <select v-model="testTypeId" class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900">
                        <option value="">All Test Types</option>
                        <option v-for="t in apiData?.test_types ?? []" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">
                    <span v-if="!campusId || !termId" class="text-amber-600 font-bold">Please select Campus and Academic Term to query statistics.</span>
                    <span v-else class="text-emerald-600">Filters applied. Dashboard refreshed.</span>
                </p>
                <button class="report-btn text-xs py-1 px-3" @click="resetFilters">
                    <RefreshCw class="h-3 w-3 mr-1" />Reset
                </button>
            </div>
        </section>

        <!-- Loading spinner -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-white/50 dark:bg-slate-950/20 backdrop-blur-sm rounded-xl">
            <Loader2 class="h-10 w-10 animate-spin text-emerald-600 mb-3" />
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">Loading fitness intelligence...</p>
        </div>

        <!-- No data panel -->
        <div v-else-if="!apiData" class="flex flex-col items-center justify-center py-20 bg-white border border-slate-200 rounded-xl dark:bg-slate-950 dark:border-slate-900">
            <BarChart3 class="h-12 w-12 text-slate-300 mb-3" />
            <p class="text-sm font-bold text-slate-600 dark:text-slate-400">No Query Results</p>
            <p class="text-xs text-slate-400 mt-1">Provide query parameters to aggregate reporting data.</p>
        </div>

        <!-- Dashboard Contents -->
        <div v-else class="flex flex-col gap-4 animate-fade-in">
            <!-- Executive Statistics Cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Students Tested</span>
                        <Users class="h-5 w-5 text-emerald-600" />
                    </div>
                    <strong class="text-2xl font-black text-slate-800 dark:text-white mt-2">{{ apiData.executive_stats.total_students }}</strong>
                </div>

                <div class="stat-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Components Covered</span>
                        <Dumbbell class="h-5 w-5 text-violet-600" />
                    </div>
                    <strong class="text-2xl font-black text-slate-800 dark:text-white mt-2">{{ apiData.executive_stats.total_components }}</strong>
                </div>

                <div class="stat-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm flex flex-col justify-between cursor-pointer hover:border-orange-200 transition-colors"
                     @click="openDrilldown({ title: 'Students Requiring Intervention' })">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Requiring Intervention</span>
                        <Layers class="h-5 w-5 text-orange-600 animate-pulse" />
                    </div>
                    <strong class="text-2xl font-black text-orange-600 mt-2">{{ apiData.executive_stats.requiring_intervention }}</strong>
                </div>

                <div class="stat-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Target Performance</span>
                        <Activity class="h-5 w-5 text-emerald-600" />
                    </div>
                    <strong class="text-2xl font-black text-emerald-600 mt-2">{{ apiData.executive_stats.target_performance }}</strong>
                </div>
            </div>

            <!-- Executive Graphs -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Campus Physical Fitness Profile (Overall Classification Distribution) -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Campus Physical Fitness Profile</h2>
                            <p class="text-[11px] text-slate-400">Distribution of students across classification rules. Click bars to drill down.</p>
                        </div>
                    </div>
                    <VueApexCharts
                        height="300"
                        type="bar"
                        :options="overallDistributionOptions"
                        :series="overallDistributionSeries"
                    />
                </section>

                <!-- Component Performance Distribution (Stacked Classification per Component) -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <div class="mb-3">
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Component Performance Distribution</h2>
                        <p class="text-[11px] text-slate-400">Identify strong and weak campus components. Click segments to drill down.</p>
                    </div>
                    <VueApexCharts
                        height="300"
                        type="bar"
                        :options="componentPerformanceOptions"
                        :series="componentPerformanceSeries"
                    />
                </section>
            </div>

            <!-- Radar Ranking and Comparisons -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Radar Component Ranking -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Component Strength Radar</h2>
                    <p class="text-[11px] text-slate-400 mb-3">Overall visual campus strengths and weaknesses ranking.</p>
                    <VueApexCharts
                        height="280"
                        type="radar"
                        :options="componentRadarOptions"
                        :series="componentRadarSeries"
                    />
                </section>

                <!-- College Comparison -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">College Comparison</h2>
                    <p class="text-[11px] text-slate-400 mb-3">Average performance score across campus colleges.</p>
                    <VueApexCharts
                        height="280"
                        type="bar"
                        :options="collegeComparisonOptions"
                        :series="collegeComparisonSeries"
                    />
                </section>

                <!-- Section Comparison -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Section Comparison</h2>
                    <p class="text-[11px] text-slate-400 mb-3">Average performance scores for top 15 sections.</p>
                    <VueApexCharts
                        height="280"
                        type="bar"
                        :options="sectionComparisonOptions"
                        :series="sectionComparisonSeries"
                    />
                </section>
            </div>

            <!-- Trend and Campuses comparisons -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Term trends line -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Academic Term Trend Analysis</h2>
                    <p class="text-[11px] text-slate-400 mb-3">Historical changes of fitness scores over terms.</p>
                    <VueApexCharts
                        height="280"
                        type="line"
                        :options="termTrendOptions"
                        :series="termTrendSeries"
                    />
                </section>

                <!-- Campuses comparison list -->
                <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Campus Comparisons</h2>
                    <p class="text-[11px] text-slate-400 mb-3">Compare PFT results counts across regional campuses.</p>
                    <VueApexCharts
                        height="280"
                        type="bar"
                        :options="campusComparisonOptions"
                        :series="campusComparisonSeries"
                    />
                </section>
            </div>

            <!-- Intervention Priority Dashboard -->
            <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Intervention Planning Panel</h2>
                    <p class="text-[11px] text-slate-400">Classifications flagged for immediate wellness intervention. Sorted highest priority first.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="item in apiData.interventions" :key="`${item.component}-${item.classification}`"
                         class="p-4 rounded-xl border transition-all hover:shadow-md cursor-pointer flex flex-col justify-between"
                         :class="{
                             'bg-rose-50/50 border-rose-200/80 hover:border-rose-300 dark:bg-rose-950/10 dark:border-rose-900/50': item.priority === 'High',
                             'bg-orange-50/50 border-orange-200/80 hover:border-orange-300 dark:bg-orange-950/10 dark:border-orange-900/50': item.priority === 'Medium',
                             'bg-amber-50/50 border-amber-200/80 hover:border-amber-300 dark:bg-amber-950/10 dark:border-amber-900/50': item.priority === 'Low'
                         }"
                         @click="openDrilldown({ classification: item.classification, title: `Intervention: ${item.classification}` })"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                      :class="{
                                          'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300': item.priority === 'High',
                                          'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300': item.priority === 'Medium',
                                          'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300': item.priority === 'Low',
                                      }"
                                >
                                    {{ item.priority }} Priority
                                </span>
                                <span class="text-xs text-slate-500 font-bold">
                                    {{ item.student_count }} Students ({{ item.percentage }}%)
                                </span>
                            </div>
                            <h3 class="text-xs font-bold mt-2.5 text-slate-800 dark:text-white">
                                {{ item.classification }}
                            </h3>
                            <p class="text-[10.5px] text-slate-500 dark:text-slate-400 mt-1">
                                Component: {{ item.component }} &middot; Test Type: {{ item.test_type }}
                            </p>
                        </div>
                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/50">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Suggested Intervention:</span>
                            <p class="text-[11px] font-medium text-slate-700 dark:text-slate-300 mt-0.5 line-clamp-2">
                                {{ item.suggested_intervention }}
                            </p>
                        </div>
                    </div>
                    <div v-if="!apiData.interventions.length" class="col-span-full py-8 text-center text-xs text-slate-400">
                        No students are currently flagged for priority interventions based on the current search query.
                    </div>
                </div>
            </section>

            <!-- Component & Test Type Analysis Cards (Collapsible summary Component -> Category -> Test Type) -->
            <section class="report-card p-4 rounded-xl border border-slate-200 bg-white dark:border-white/5 dark:bg-slate-900 shadow-sm animate-fade-in">
                <div class="mb-4">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Component & Test Type Intelligence Profile</h2>
                    <p class="text-[11px] text-slate-400">Detailed metric performance distribution for each physical fitness category. Expand each component and category to view the test types and interpretation rules.</p>
                </div>

                <div class="flex flex-col gap-4">
                    <div v-for="comp in apiData.components" :key="comp.id" 
                         class="border border-slate-100 rounded-xl overflow-hidden bg-slate-50/20 dark:border-slate-800/80 dark:bg-slate-950/10"
                    >
                        <!-- Component Header Toggle -->
                        <button
                            type="button"
                            class="w-full flex items-center justify-between p-4 bg-slate-50/50 hover:bg-slate-50 dark:bg-slate-900/50 dark:hover:bg-slate-900/80 transition-colors text-left"
                            @click="toggleComponent(comp.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                <span class="text-xs sm:text-sm font-extrabold text-slate-800 dark:text-white uppercase tracking-wider">{{ comp.name }}</span>
                                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-400 dark:text-slate-500">
                                    ({{ comp.total_results }} results · {{ comp.total_students }} students)
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="hidden sm:flex items-center gap-1.5 flex-wrap">
                                    <span
                                        v-for="(val, label) in comp.classifications"
                                        :key="label"
                                        class="text-[9px] font-bold px-2 py-0.5 rounded-md border"
                                        :style="{
                                            color: interpretationColor(getClassificationColor(label)),
                                            borderColor: interpretationColor(getClassificationColor(label)) + '33',
                                            backgroundColor: interpretationColor(getClassificationColor(label)) + '14',
                                        }"
                                    >
                                        {{ label }}: {{ val }}
                                    </span>
                                </div>
                                <ChevronDown v-if="expandedComponents.includes(comp.id)" class="h-4 w-4 text-slate-500" />
                                <ChevronRight v-else class="h-4 w-4 text-slate-500" />
                            </div>
                        </button>

                        <!-- Collapsible Category Section -->
                        <div v-if="expandedComponents.includes(comp.id)" class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-3">
                            <div v-for="cat in comp.categories" :key="cat.id" 
                                 class="border border-slate-100 rounded-lg overflow-hidden bg-white dark:border-slate-800/50 dark:bg-slate-900/20"
                            >
                                <!-- Category Header Toggle -->
                                <button
                                    type="button"
                                    class="w-full flex items-center justify-between p-3 hover:bg-slate-50/50 dark:hover:bg-slate-850/50 transition-colors text-left"
                                    @click="toggleCategory(cat.id)"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ cat.name }}</span>
                                        <span class="text-[10px] text-slate-400">
                                            ({{ cat.total_results }} results · {{ cat.total_students }} students)
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="hidden md:flex items-center gap-1.5 flex-wrap">
                                            <span
                                                v-for="(val, label) in cat.classifications"
                                                :key="label"
                                                class="text-[8px] font-medium px-1.5 py-0.5 rounded border"
                                                :style="{
                                                    color: interpretationColor(getClassificationColor(label)),
                                                    borderColor: interpretationColor(getClassificationColor(label)) + '33',
                                                    backgroundColor: interpretationColor(getClassificationColor(label)) + '14',
                                                }"
                                            >
                                                {{ label }}: {{ val }}
                                            </span>
                                        </div>
                                        <ChevronDown v-if="expandedCategories.includes(cat.id)" class="h-3.5 w-3.5 text-slate-400" />
                                        <ChevronRight v-else class="h-3.5 w-3.5 text-slate-400" />
                                    </div>
                                </button>

                                <!-- Collapsible Test Types list (Component -> Category -> Test Type based on interpretation rule) -->
                                <div v-if="expandedCategories.includes(cat.id)" class="p-3 bg-slate-50/20 dark:bg-slate-950/10 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-3">
                                    <div v-for="type in cat.test_types" :key="type.id"
                                         class="border border-slate-150 rounded-xl overflow-hidden bg-white dark:border-slate-800 dark:bg-slate-900 shadow-sm"
                                    >
                                        <!-- Test Type Header Toggle -->
                                        <button
                                            type="button"
                                            class="w-full flex items-center justify-between p-3 hover:bg-slate-50/50 dark:hover:bg-slate-850/50 transition-colors text-left"
                                            @click="toggleTestType(type.id)"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ type.name }}</span>
                                                <span class="text-[10px] text-slate-400">
                                                    ({{ type.total_results }} results)
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="hidden sm:flex items-center gap-1.5 flex-wrap">
                                                    <span
                                                        v-for="(val, label) in type.classifications"
                                                        :key="label"
                                                        class="text-[8px] font-bold px-1.5 py-0.5 rounded border"
                                                        :style="{
                                                            color: interpretationColor(getClassificationColor(label)),
                                                            borderColor: interpretationColor(getClassificationColor(label)) + '33',
                                                            backgroundColor: interpretationColor(getClassificationColor(label)) + '14',
                                                        }"
                                                    >
                                                        {{ label }}: {{ val }}
                                                    </span>
                                                </div>
                                                <ChevronDown v-if="expandedTestTypes.includes(type.id)" class="h-3.5 w-3.5 text-slate-400" />
                                                <ChevronRight v-else class="h-3.5 w-3.5 text-slate-400" />
                                            </div>
                                        </button>

                                        <!-- Collapsible Content (Charts and Interpretations) -->
                                        <div v-if="expandedTestTypes.includes(type.id)" class="p-4 border-t border-slate-100 dark:border-slate-800">
                                            <!-- Show details from classifications -->
                                            <div class="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-2">Student Count</h5>
                                                    <VueApexCharts
                                                        height="160"
                                                        type="bar"
                                                        :options="getComponentTestTypeData(comp.name, type.name).barOptions"
                                                        :series="getComponentTestTypeData(comp.name, type.name).series"
                                                    />
                                                </div>
                                                <div>
                                                    <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-2">Percentage Share</h5>
                                                    <VueApexCharts
                                                        height="160"
                                                        type="donut"
                                                        :options="getComponentTestTypeData(comp.name, type.name).donutOptions"
                                                        :series="getComponentTestTypeData(comp.name, type.name).donutSeries"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Dynamic Interpretation details -->
                                            <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                                                <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-wider mb-2">Dynamic Interpretations (Based on Rules)</h5>
                                                <div class="flex flex-col gap-2">
                                                    <div v-for="rule in apiData.classifications.filter(c => c.test_type_id === type.id && c.student_count > 0)" :key="rule.id"
                                                         class="p-2.5 rounded-lg border border-slate-100 bg-slate-50/50 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950/50 cursor-pointer hover:border-emerald-300 transition-all shadow-sm"
                                                         @click="openDrilldown({ classification: rule.classification, testTypeId: String(type.id), title: `${type.name} - ${rule.classification}` })"
                                                    >
                                                        <div class="flex justify-between items-center">
                                                            <span class="text-[10.5px] font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                                                <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: interpretationColor(rule.color_class) }"></span>
                                                                {{ rule.classification }}
                                                            </span>
                                                            <span class="text-[9.5px] text-slate-400 font-bold">
                                                                {{ rule.student_count }} Students ({{ rule.percentage }}%)
                                                            </span>
                                                        </div>
                                                        <p class="text-[9.5px] text-slate-500 dark:text-slate-400 mt-1"><strong>Interpretation:</strong> {{ rule.interpretation }}</p>
                                                        <p v-if="rule.suggested_intervention" class="text-[9.5px] text-orange-650 dark:text-orange-400 mt-0.5"><strong>Intervention Plan:</strong> {{ rule.suggested_intervention }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Student Drilldown Modal -->
    <div v-if="drilldownOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 animate-fade-in">
        <div class="bg-white dark:bg-slate-900 w-full max-w-6xl rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[85vh] border border-slate-100 dark:border-slate-800 animate-slide-up">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">
                        Student Intelligence Drilldown
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-0.5 font-medium">
                        Target Group: {{ drilldownCriteria.title }}
                    </p>
                </div>
                <button class="p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="drilldownOpen = false">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Modal Filters/Actions -->
            <div class="px-6 py-3 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <!-- Search bar -->
                <div class="relative w-full sm:max-w-xs">
                    <input
                        v-model="drilldownSearch"
                        type="text"
                        placeholder="Search student no or name..."
                        class="report-input pl-8 py-1.5 text-xs border-slate-200 focus:border-emerald-500"
                        @input="fetchDrilldown"
                    />
                    <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                </div>
                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-2">
                    <button class="report-btn py-1.5 px-3 text-xs flex items-center gap-1 border-slate-200" @click="exportDrilldownExcel">
                        <Download class="h-3.5 w-3.5" />Export Excel
                    </button>
                    <button class="report-btn py-1.5 px-3 text-xs flex items-center gap-1 border-slate-200" @click="printDrilldown">
                        <Printer class="h-3.5 w-3.5" />Print List
                    </button>
                    <button class="report-btn bg-orange-600 hover:bg-orange-700 text-white border-none py-1.5 px-3 text-xs flex items-center gap-1 rounded-lg shadow-sm"
                            @click="printInterventionList"
                    >
                        <Printer class="h-3.5 w-3.5" />Generate Intervention List
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Loading spinner -->
                <div v-if="drilldownLoading" class="flex flex-col items-center justify-center py-20">
                    <Loader2 class="h-8 w-8 animate-spin text-emerald-600 mb-2" />
                    <p class="text-xs text-slate-500 font-semibold">Loading student records...</p>
                </div>
                <!-- Empty list -->
                <div v-else-if="!drilldownRows.length" class="text-center py-20 text-slate-400 text-xs">
                    No student records found matching this drilldown filter query.
                </div>
                <!-- Student Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-[11px] text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 font-bold uppercase tracking-wider">
                                <th class="p-3">Student No</th>
                                <th class="p-3">Name</th>
                                <th class="p-3">Campus</th>
                                <th class="p-3">College</th>
                                <th class="p-3">Section</th>
                                <th class="p-3">Year</th>
                                <th class="p-3">Component</th>
                                <th class="p-3">Test Type</th>
                                <th class="p-3">Raw Result</th>
                                <th class="p-3">Classification</th>
                                <th class="p-3">Remarks</th>
                                <th class="p-3">Test Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in drilldownRows" :key="row.student_no ?? ''" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/50">
                                <td class="p-3 font-semibold text-slate-900 dark:text-white">{{ row.student_no ?? '-' }}</td>
                                <td class="p-3">{{ row.student_name }}</td>
                                <td class="p-3 text-slate-500">{{ row.campus }}</td>
                                <td class="p-3 text-slate-500">{{ row.college }}</td>
                                <td class="p-3 text-slate-500">{{ row.section }}</td>
                                <td class="p-3 text-slate-500">{{ row.year_level }}</td>
                                <td class="p-3">{{ row.component }}</td>
                                <td class="p-3">{{ row.test_type }}</td>
                                <td class="p-3 font-mono text-slate-600 dark:text-slate-400">{{ row.raw_result }}</td>
                                <td class="p-3 font-bold">{{ row.classification }}</td>
                                <td class="p-3 text-slate-400">{{ row.remarks ?? '-' }}</td>
                                <td class="p-3 text-slate-400">{{ row.test_date ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Footer (Paging controls) -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-500">
                    Showing {{ (drilldownPage - 1) * drilldownPageLength + 1 }} to {{ Math.min(drilldownPage * drilldownPageLength, drilldownFilteredCount) }} of {{ drilldownFilteredCount }} students
                </span>
                <div class="flex items-center gap-1">
                    <button
                        class="report-btn py-1 px-2.5"
                        :disabled="drilldownPage <= 1"
                        @click="drilldownPage--; fetchDrilldown()"
                    >
                        Previous
                    </button>
                    <button
                        class="report-btn py-1 px-2.5"
                        :disabled="drilldownPage * drilldownPageLength >= drilldownFilteredCount"
                        @click="drilldownPage++; fetchDrilldown()"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
