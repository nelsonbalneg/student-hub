<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
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

type IntelligenceInterpretation = {
    label: string;
    value: number;
    color?: string;
};

type IntelligenceTestType = {
    label: string;
    value: number;
    students: number;
    interpretations: IntelligenceInterpretation[];
};

type IntelligenceCategory = {
    label: string;
    value: number;
    students: number;
    interpretations: IntelligenceInterpretation[];
    test_types: IntelligenceTestType[];
};

type IntelligenceComponent = {
    label: string;
    value: number;
    students: number;
    interpretations: IntelligenceInterpretation[];
    categories: IntelligenceCategory[];
};

type CollegeComponentProfile = {
    id: string;
    label: string;
    results: number;
    students: number;
    hierarchy: IntelligenceComponent[];
};

type AnalyticsData = {
    campuses: {
        id: string;
        name: string;
        total_students: number;
        total_results: number;
    }[];
    components: {
        id: number;
        name: string;
        total_results: number;
        classifications: Record<string, number>;
    }[];
    test_types: {
        id: number;
        name: string;
        component_id: number;
        total_results: number;
        classifications: Record<string, number>;
    }[];
    classifications: ClassificationStat[];
    interventions: InterventionItem[];
    executive_stats: ExecutiveStats;
    college_comparison: { college: string; score: number }[];
    section_comparison: { section: string; score: number }[];
    term_trends: { term: string; score: number }[];
    component_radar: { component: string; score: number }[];
    overall_distribution: {
        classification: string;
        color_class: string;
        total: number;
    }[];
    component_distribution: {
        component: string;
        classification: string;
        total: number;
    }[];
    college_component_profiles?: CollegeComponentProfile[];
};

type DrilldownRow = {
    user_id: number;
    student_no: string | null;
    student_name: string;
    campus_id: string | null;
    campus: string;
    campus_name: string;
    college_id: string | null;
    college: string;
    college_name: string;
    section: string;
    year_level: string;
    component: string;
    test_type: string;
    raw_result: string;
    classification: string;
    remarks: string | null;
    test_date: string | null;
    student_key: string;
};

type DrilldownCampusNode = {
    key: string;
    campus_id: string;
    campus_name: string;
    total_results: number;
    total_students: number;
};

type DrilldownCollegeNode = {
    key: string;
    campus_id: string;
    campus_name: string;
    college_id: string;
    college_name: string;
    total_students: number;
    total_results: number;
};

type DrilldownStudentNode = {
    key: string;
    campus_id: string;
    campus_name: string;
    college_id: string;
    college_name: string;
    user_id: number;
    student_key: string;
    student_no: string | null;
    student_name: string;
    section: string;
    year_level: string;
    total_results: number;
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

const page = usePage();
const siteSettings = computed(
    () =>
        page.props.siteSettings as
            | {
                  site_name?: string;
                  site_footer_name?: string;
                  site_logo_url?: string | null;
              }
            | undefined,
);
const reportLogoUrl = computed(() => siteSettings.value?.site_logo_url ?? '');
const reportFooterName = computed(
    () =>
        siteSettings.value?.site_footer_name ||
        'University of Southern Mindanao',
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Reporting', href: '/admin/reporting/overview' },
            { title: 'PFT Result', href: '/admin/reporting/pft-result' },
            {
                title: 'Analytics',
                href: '/admin/reporting/pft-result/analytics',
            },
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
        if (componentId.value)
            queryParams.set('component_id', componentId.value);
        if (testTypeId.value) queryParams.set('test_type_id', testTypeId.value);
        if (yearLevelId.value)
            queryParams.set('year_level_id', yearLevelId.value);
        if (sex.value) queryParams.set('sex', sex.value);

        const response = await fetch(
            `${pftAnalyticsData.url()}?${queryParams.toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        apiData.value = (await response.json()) as AnalyticsData;
        if (
            !selectedCollege.value &&
            apiData.value?.college_component_profiles?.length
        ) {
            expandedCollegeProfiles.value = [
                collegeProfileKey(
                    apiData.value.college_component_profiles[0].id,
                ),
            ];
        } else {
            expandedCollegeProfiles.value = [];
            expandedCollegeComponents.value = [];
            expandedCollegeCategories.value = [];
            expandedCollegeTestTypes.value = [];
        }

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
const expandedCollegeProfiles = ref<string[]>([]);
const expandedCollegeComponents = ref<string[]>([]);
const expandedCollegeCategories = ref<string[]>([]);
const expandedCollegeTestTypes = ref<string[]>([]);

const toggleComponent = (id: number) => {
    if (expandedComponents.value.includes(id)) {
        expandedComponents.value = expandedComponents.value.filter(
            (x) => x !== id,
        );
    } else {
        expandedComponents.value.push(id);
    }
};

const toggleCategory = (id: number) => {
    if (expandedCategories.value.includes(id)) {
        expandedCategories.value = expandedCategories.value.filter(
            (x) => x !== id,
        );
    } else {
        expandedCategories.value.push(id);
    }
};

const toggleTestType = (id: number) => {
    if (expandedTestTypes.value.includes(id)) {
        expandedTestTypes.value = expandedTestTypes.value.filter(
            (x) => x !== id,
        );
    } else {
        expandedTestTypes.value.push(id);
    }
};

const collegeProfileKey = (profileId: string) => profileId;
const collegeComponentKey = (profileId: string, componentLabel: string) =>
    `${profileId}::${componentLabel}`;
const collegeCategoryKey = (
    profileId: string,
    componentLabel: string,
    categoryLabel: string,
) => `${profileId}::${componentLabel}::${categoryLabel}`;
const collegeTestTypeKey = (
    profileId: string,
    componentLabel: string,
    categoryLabel: string,
    testTypeLabel: string,
) => `${profileId}::${componentLabel}::${categoryLabel}::${testTypeLabel}`;

const toggleCollegeProfile = (profileId: string) => {
    const key = collegeProfileKey(profileId);
    if (expandedCollegeProfiles.value.includes(key)) {
        expandedCollegeProfiles.value = expandedCollegeProfiles.value.filter(
            (x) => x !== key,
        );
    } else {
        expandedCollegeProfiles.value.push(key);
    }
};

const toggleCollegeComponent = (profileId: string, componentLabel: string) => {
    const key = collegeComponentKey(profileId, componentLabel);
    if (expandedCollegeComponents.value.includes(key)) {
        expandedCollegeComponents.value =
            expandedCollegeComponents.value.filter((x) => x !== key);
    } else {
        expandedCollegeComponents.value.push(key);
    }
};

const toggleCollegeCategory = (
    profileId: string,
    componentLabel: string,
    categoryLabel: string,
) => {
    const key = collegeCategoryKey(profileId, componentLabel, categoryLabel);
    if (expandedCollegeCategories.value.includes(key)) {
        expandedCollegeCategories.value =
            expandedCollegeCategories.value.filter((x) => x !== key);
    } else {
        expandedCollegeCategories.value.push(key);
    }
};

const toggleCollegeTestType = (
    profileId: string,
    componentLabel: string,
    categoryLabel: string,
    testTypeLabel: string,
) => {
    const key = collegeTestTypeKey(
        profileId,
        componentLabel,
        categoryLabel,
        testTypeLabel,
    );
    if (expandedCollegeTestTypes.value.includes(key)) {
        expandedCollegeTestTypes.value = expandedCollegeTestTypes.value.filter(
            (x) => x !== key,
        );
    } else {
        expandedCollegeTestTypes.value.push(key);
    }
};

const groupedCollegeProfiles = computed(
    () => apiData.value?.college_component_profiles ?? [],
);
const showGroupedCollegeProfiles = computed(
    () => !selectedCollege.value && groupedCollegeProfiles.value.length > 0,
);

const getClassificationColor = (name: string) => {
    const found = apiData.value?.classifications.find(
        (c) =>
            c.classification === name ||
            c.classification?.toLowerCase() === name.toLowerCase(),
    );
    return found?.color_class ?? 'slate';
};

// Drilldown Modal logic
const drilldownOpen = ref(false);
const drilldownLoading = ref(false);
const drilldownRootCount = ref(0);
const drilldownCampuses = ref<DrilldownCampusNode[]>([]);
const drilldownCollegesByCampus = ref<Record<string, DrilldownCollegeNode[]>>(
    {},
);
const drilldownStudentsByCollege = ref<Record<string, DrilldownStudentNode[]>>(
    {},
);
const drilldownRowsByStudent = ref<Record<string, DrilldownRow[]>>({});
const drilldownCollegeLoading = ref<Record<string, boolean>>({});
const drilldownStudentLoading = ref<Record<string, boolean>>({});
const drilldownRowsLoading = ref<Record<string, boolean>>({});
const drilldownSearch = ref('');
const drilldownDraw = ref(1);
const expandedDrilldownCampuses = ref<string[]>([]);
const expandedDrilldownColleges = ref<string[]>([]);
const expandedDrilldownStudents = ref<string[]>([]);
const drilldownReportLoading = ref(false);

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
    drilldownSearch.value = '';
    void fetchDrilldown();
};

const resetDrilldownTree = () => {
    drilldownCampuses.value = [];
    drilldownCollegesByCampus.value = {};
    drilldownStudentsByCollege.value = {};
    drilldownRowsByStudent.value = {};
    drilldownCollegeLoading.value = {};
    drilldownStudentLoading.value = {};
    drilldownRowsLoading.value = {};
    expandedDrilldownCampuses.value = [];
    expandedDrilldownColleges.value = [];
    expandedDrilldownStudents.value = [];
};

const drilldownBaseParams = () => {
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
        queryParams.set(
            'classification',
            drilldownCriteria.value.classification,
        );
    }
    if (drilldownCriteria.value.componentId) {
        queryParams.set('component_id', drilldownCriteria.value.componentId);
    }
    if (drilldownCriteria.value.testTypeId) {
        queryParams.set('test_type_id', drilldownCriteria.value.testTypeId);
    }

    queryParams.set('search', drilldownSearch.value);
    return queryParams;
};

const fetchDrilldownTree = async (queryParams: URLSearchParams) => {
    const response = await fetch(
        `${pftAnalyticsDrilldown.url()}?${queryParams.toString()}`,
        {
            headers: { Accept: 'application/json' },
        },
    );

    return (await response.json()) as {
        level?: string;
        data: any[];
        recordsFiltered?: number;
    };
};

const fetchDrilldown = async () => {
    drilldownLoading.value = true;
    resetDrilldownTree();
    try {
        const queryParams = drilldownBaseParams();
        queryParams.set('node_level', 'campus');

        const res = await fetchDrilldownTree(queryParams);
        drilldownCampuses.value = res.data as DrilldownCampusNode[];
        drilldownRootCount.value = res.recordsFiltered ?? res.data.length;
    } catch (err) {
        console.error('Drilldown fetch error:', err);
    } finally {
        drilldownLoading.value = false;
    }
};

const campusNodeKey = (campusId: string) => campusId;
const collegeNodeKey = (campusId: string, collegeId: string) =>
    `${campusId}::${collegeId}`;
const studentNodeKey = (campusId: string, collegeId: string, userId: number) =>
    `${campusId}::${collegeId}::${userId}`;

const toggleDrilldownCampus = async (campus: DrilldownCampusNode) => {
    const key = campusNodeKey(campus.campus_id);
    if (expandedDrilldownCampuses.value.includes(key)) {
        expandedDrilldownCampuses.value =
            expandedDrilldownCampuses.value.filter((item) => item !== key);
        return;
    }

    expandedDrilldownCampuses.value = [...expandedDrilldownCampuses.value, key];

    if (!drilldownCollegesByCampus.value[key]) {
        drilldownCollegeLoading.value = {
            ...drilldownCollegeLoading.value,
            [key]: true,
        };
        try {
            const queryParams = drilldownBaseParams();
            queryParams.set('node_level', 'college');
            queryParams.set('campus_id', campus.campus_id);

            const res = await fetchDrilldownTree(queryParams);
            drilldownCollegesByCampus.value = {
                ...drilldownCollegesByCampus.value,
                [key]: res.data as DrilldownCollegeNode[],
            };
        } finally {
            drilldownCollegeLoading.value = {
                ...drilldownCollegeLoading.value,
                [key]: false,
            };
        }
    }
};

const toggleDrilldownCollege = async (
    campus: DrilldownCampusNode,
    college: DrilldownCollegeNode,
) => {
    const key = collegeNodeKey(campus.campus_id, college.college_id);
    if (expandedDrilldownColleges.value.includes(key)) {
        expandedDrilldownColleges.value =
            expandedDrilldownColleges.value.filter((item) => item !== key);
        return;
    }

    expandedDrilldownColleges.value = [...expandedDrilldownColleges.value, key];

    if (!drilldownStudentsByCollege.value[key]) {
        drilldownStudentLoading.value = {
            ...drilldownStudentLoading.value,
            [key]: true,
        };
        try {
            const queryParams = drilldownBaseParams();
            queryParams.set('node_level', 'student');
            queryParams.set('campus_id', campus.campus_id);
            queryParams.set('college_id', college.college_id);

            const res = await fetchDrilldownTree(queryParams);
            drilldownStudentsByCollege.value = {
                ...drilldownStudentsByCollege.value,
                [key]: res.data as DrilldownStudentNode[],
            };
        } finally {
            drilldownStudentLoading.value = {
                ...drilldownStudentLoading.value,
                [key]: false,
            };
        }
    }
};

const toggleDrilldownStudent = async (
    campus: DrilldownCampusNode,
    college: DrilldownCollegeNode,
    student: DrilldownStudentNode,
) => {
    const key = studentNodeKey(
        campus.campus_id,
        college.college_id,
        student.user_id,
    );
    if (expandedDrilldownStudents.value.includes(key)) {
        expandedDrilldownStudents.value =
            expandedDrilldownStudents.value.filter((item) => item !== key);
        return;
    }

    expandedDrilldownStudents.value = [...expandedDrilldownStudents.value, key];

    if (!drilldownRowsByStudent.value[key]) {
        drilldownRowsLoading.value = {
            ...drilldownRowsLoading.value,
            [key]: true,
        };
        try {
            const queryParams = drilldownBaseParams();
            queryParams.set('node_level', 'detail');
            queryParams.set('campus_id', campus.campus_id);
            queryParams.set('college_id', college.college_id);
            queryParams.set('user_id', String(student.user_id));

            const res = await fetchDrilldownTree(queryParams);
            drilldownRowsByStudent.value = {
                ...drilldownRowsByStudent.value,
                [key]: res.data as DrilldownRow[],
            };
        } finally {
            drilldownRowsLoading.value = {
                ...drilldownRowsLoading.value,
                [key]: false,
            };
        }
    }
};

const fetchDrilldownReportRows = async (): Promise<DrilldownRow[]> => {
    drilldownReportLoading.value = true;
    try {
        const queryParams = drilldownBaseParams();

        queryParams.set('report', '1');
        queryParams.set('start', '0');
        queryParams.set('length', '10000');
        queryParams.set('search', drilldownSearch.value);
        queryParams.set('draw', String(drilldownDraw.value++));

        const response = await fetch(
            `${pftAnalyticsDrilldown.url()}?${queryParams.toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const res = await response.json();
        return res.data as DrilldownRow[];
    } catch (err) {
        console.error('Drilldown report fetch error:', err);
        return [];
    } finally {
        drilldownReportLoading.value = false;
    }
};

type DrilldownGroupedStudent = {
    key: string;
    student_no: string | null;
    student_name: string;
    section: string;
    year_level: string;
    total_results: number;
    rows: DrilldownRow[];
};

type DrilldownGroupedCollege = {
    key: string;
    college_name: string;
    total_students: number;
    total_results: number;
    students: DrilldownGroupedStudent[];
};

type DrilldownGroupedCampus = {
    key: string;
    campus_name: string;
    total_students: number;
    total_results: number;
    colleges: DrilldownGroupedCollege[];
};

const buildGroupedDrilldownRows = (
    rows: DrilldownRow[],
): DrilldownGroupedCampus[] => {
    const campuses = new Map<string, DrilldownGroupedCampus>();

    rows.forEach((row) => {
        const campusKey = row.campus_id ?? 'unassigned';
        const campusName = row.campus_name || 'Unassigned Campus';
        const collegeKey = row.college_id ?? 'unassigned';
        const collegeName = row.college_name || 'Unassigned College';
        const studentKey = row.student_key || `${row.user_id}`;

        if (!campuses.has(campusKey)) {
            campuses.set(campusKey, {
                key: campusKey,
                campus_name: campusName,
                total_students: 0,
                total_results: 0,
                colleges: [],
            });
        }

        const campus = campuses.get(campusKey)!;
        let college = campus.colleges.find((entry) => entry.key === collegeKey);
        if (!college) {
            college = {
                key: collegeKey,
                college_name: collegeName,
                total_students: 0,
                total_results: 0,
                students: [],
            };
            campus.colleges.push(college);
        }

        let student = college.students.find(
            (entry) => entry.key === studentKey,
        );
        if (!student) {
            student = {
                key: studentKey,
                student_no: row.student_no,
                student_name: row.student_name,
                section: row.section,
                year_level: row.year_level,
                total_results: 0,
                rows: [],
            };
            college.students.push(student);
            college.total_students += 1;
            campus.total_students += 1;
        }

        student.rows.push(row);
        student.total_results += 1;
        college.total_results += 1;
        campus.total_results += 1;
    });

    return Array.from(campuses.values());
};

const reportHeaderMarkup = (title: string, subtitle: string) => `
    <header class="report-header">
        <div class="report-brand">
            ${
                reportLogoUrl.value
                    ? `<img src="${reportLogoUrl.value}" alt="Report logo" class="report-logo" />`
                    : '<div class="report-logo-placeholder">USM</div>'
            }
            <div class="report-brand-copy">
                <div class="report-brand-title">${title}</div>
                <div class="report-brand-subtitle">University of Southern Mindanao</div>
                <div class="report-brand-subtitle">Kabacan, Cotabato</div>
            </div>
        </div>
        <div class="report-meta">
            <div>${subtitle}</div>
            <div>${new Date().toLocaleString()}</div>
        </div>
    </header>
`;

const reportFooterMarkup = () => `
    <footer class="report-footer">
        <span>${reportFooterName.value}</span>
        <span>Kabacan, Cotabato</span>
        <span>Confidential</span>
    </footer>
`;

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
        queryParams.set(
            'classification',
            drilldownCriteria.value.classification,
        );
    }
    if (drilldownCriteria.value.componentId) {
        queryParams.set('component_id', drilldownCriteria.value.componentId);
    }
    if (drilldownCriteria.value.testTypeId) {
        queryParams.set('test_type_id', drilldownCriteria.value.testTypeId);
    }

    window.location.href = `${pftExportDrilldownExcel.url()}?${queryParams.toString()}`;
};

const printDrilldown = async () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const rows = await fetchDrilldownReportRows();
    if (!rows.length) {
        printWindow.close();
        return;
    }

    const groupedRows = buildGroupedDrilldownRows(rows);
    let rowsHtml = '';

    groupedRows.forEach((campus) => {
        rowsHtml += `
            <section style="page-break-inside: avoid; margin: 0 0 18px 0; width: 100%;">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; border-bottom:2px solid #0f766e; padding-bottom:6px; margin-bottom:10px; width:100%;">
                    <div style="min-width:0; flex:1 1 auto;">
                        <div style="max-width:100%; white-space:normal; overflow-wrap:anywhere; word-break:break-word; font-size:14px; font-weight:800; color:#0f172a; line-height:1.2;">${campus.campus_name}</div>
                        <div style="font-size:11px; color:#64748b;">${campus.total_students} students • ${campus.total_results} results</div>
                    </div>
                </div>
        `;

        campus.colleges.forEach((college) => {
            rowsHtml += `
                <div style="margin:0 0 14px 0; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; width:100%;">
                    <div style="background:#f8fafc; padding:10px 12px; border-bottom:1px solid #e2e8f0;">
                        <div style="max-width:100%; white-space:normal; overflow-wrap:anywhere; word-break:break-word; font-size:12px; font-weight:700; color:#0f172a; line-height:1.2;">${college.college_name}</div>
                        <div style="font-size:10px; color:#64748b;">${college.total_students} students • ${college.total_results} results</div>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                        <thead>
                            <tr style="background:#f8fafc; color:#475569; text-transform:uppercase; letter-spacing:.06em;">
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Student</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Section</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Year</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Component</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Test Type</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Classification</th>
                                <th style="text-align:left; padding:8px; border-bottom:1px solid #e2e8f0;">Result</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            college.students.forEach((student) => {
                student.rows.forEach((row, index) => {
                    rowsHtml += `
                        <tr>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0;">
                                <div style="font-weight:700; color:#0f172a;">${index === 0 ? student.student_name : ''}</div>
                                <div style="color:#64748b;">${index === 0 ? (student.student_no ?? '-') : ''}</div>
                            </td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0;">${row.section}</td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0;">${row.year_level}</td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0;">${row.component}</td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0;">${row.test_type}</td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0; font-weight:700;">${row.classification}</td>
                            <td style="padding:8px; border-bottom:1px solid #e2e8f0; font-family: monospace;">${row.raw_result}</td>
                        </tr>
                    `;
                });
            });

            rowsHtml += `
                        </tbody>
                    </table>
                </div>
            `;
        });

        rowsHtml += `</section>`;
    });

    printWindow.document.write(`
        <html>
        <head>
            <title>PFT Drilldown - ${drilldownCriteria.value.title}</title>
            <style>
                @page { size: auto; margin: 18mm 18mm 22mm; }
                * { box-sizing: border-box; }
                body { font-family: Arial, sans-serif; font-size: 12px; color: #334155; margin: 0; padding: 0; }
                .report-shell { padding-top: 108px; padding-bottom: 52px; }
                .report-header {
                    position: fixed;
                    inset: 14mm 18mm auto;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #0f766e;
                    background: #fff;
                    z-index: 10;
                }
                .report-brand {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex: 1 1 auto;
                    min-width: 0;
                }
                .report-logo {
                    width: 48px;
                    height: 48px;
                    object-fit: contain;
                    flex: 0 0 auto;
                }
                .report-logo-placeholder {
                    width: 48px;
                    height: 48px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid #99f6e4;
                    border-radius: 999px;
                    color: #0f766e;
                    font-size: 10px;
                    font-weight: 700;
                    flex: 0 0 auto;
                }
                .report-brand-copy {
                    min-width: 0;
                    max-width: 520px;
                }
                .report-brand-title {
                    font-size: 14px;
                    font-weight: 800;
                    color: #0f172a;
                    text-transform: uppercase;
                    line-height: 1.1;
                }
                .report-brand-subtitle {
                    font-size: 10px;
                    color: #475569;
                    line-height: 1.2;
                }
                .report-meta {
                    flex: 0 0 auto;
                    text-align: right;
                    font-size: 10px;
                    color: #64748b;
                    line-height: 1.4;
                    white-space: nowrap;
                }
                .report-footer {
                    position: fixed;
                    inset: auto 18mm 12mm;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding-top: 8px;
                    border-top: 1px solid #e2e8f0;
                    color: #64748b;
                    font-size: 9px;
                    background: #fff;
                    z-index: 10;
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${reportHeaderMarkup(
                'Physical Fitness Intelligence List',
                `Criteria: ${drilldownCriteria.value.title}`,
            )}
            <main class="report-shell">
                ${rowsHtml}
            </main>
            ${reportFooterMarkup()}
        </body>
        </html>
    `);
    printWindow.document.close();
};

const printInterventionList = async () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const rows = await fetchDrilldownReportRows();
    if (!rows.length) {
        printWindow.close();
        return;
    }

    const groupedRows = buildGroupedDrilldownRows(rows);
    let rowsHtml = '';

    groupedRows.forEach((campus) => {
        rowsHtml += `
            <section style="page-break-inside: avoid; margin: 0 0 18px 0; width: 100%;">
                <div style="border-bottom:2px solid #ea580c; padding-bottom:6px; margin-bottom:10px; width:100%;">
                    <div style="max-width:100%; white-space:normal; overflow-wrap:anywhere; word-break:break-word; font-size:14px; font-weight:800; color:#0f172a; line-height:1.2;">${campus.campus_name}</div>
                    <div style="font-size:11px; color:#64748b;">${campus.total_students} students • ${campus.total_results} results</div>
                </div>
        `;

        campus.colleges.forEach((college) => {
            rowsHtml += `
                <div style="margin-bottom:14px; border:1px solid #fed7aa; border-radius:10px; overflow:hidden; width:100%;">
                    <div style="background:#fff7ed; padding:10px 12px; border-bottom:1px solid #fed7aa;">
                        <div style="max-width:100%; white-space:normal; overflow-wrap:anywhere; word-break:break-word; font-size:12px; font-weight:700; color:#9a3412; line-height:1.2;">${college.college_name}</div>
                        <div style="font-size:10px; color:#b45309;">${college.total_students} students • ${college.total_results} results</div>
                    </div>
            `;

            college.students.forEach((student) => {
                rowsHtml += `
                    <div style="padding:12px; border-top:1px solid #fde68a; page-break-inside: avoid;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:6px;">
                            <div>
                                <div style="font-weight:800; color:#0f172a;">${student.student_name}</div>
                                <div style="font-size:11px; color:#64748b;">${student.student_no ?? '-'} • ${student.section} • Year ${student.year_level}</div>
                            </div>
                            <div style="font-size:10px; font-weight:800; color:#c2410c; text-transform:uppercase;">${student.total_results} result(s)</div>
                        </div>
                        <div style="display:grid; gap:8px;">
                `;

                student.rows.forEach((row) => {
                    const rule = apiData.value?.classifications.find(
                        (c) =>
                            c.test_type === row.test_type &&
                            c.classification === row.classification,
                    );
                    const intervention =
                        rule?.suggested_intervention ??
                        'Standard wellness activities & fitness monitoring.';

                    rowsHtml += `
                        <div style="border:1px solid #fed7aa; border-radius:8px; background:#fff; padding:10px;">
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:4px;">
                                <div style="font-weight:700; color:#0f172a;">${row.component} • ${row.test_type}</div>
                                <div style="font-size:10px; font-weight:800; color:#c2410c; text-transform:uppercase;">${row.classification}</div>
                            </div>
                            <div style="font-size:11px; color:#475569; margin-bottom:6px;">
                                Raw Result: ${row.raw_result}
                            </div>
                            <div style="background:#fff7ed; border-left:3px solid #f97316; padding:8px 10px; font-size:11px; color:#7c2d12;">
                                <strong>Suggested Intervention:</strong> ${intervention}
                            </div>
                        </div>
                    `;
                });

                rowsHtml += `
                        </div>
                    </div>
                `;
            });

            rowsHtml += `
                </div>
            `;
        });

        rowsHtml += `</section>`;
    });

    printWindow.document.write(`
        <html>
        <head>
            <title>PFT Intervention Plan - ${drilldownCriteria.value.title}</title>
            <style>
                @page { size: auto; margin: 18mm 18mm 22mm; }
                * { box-sizing: border-box; }
                body { font-family: Arial, sans-serif; font-size: 12px; color: #334155; margin: 0; padding: 0; }
                .report-shell { padding-top: 108px; padding-bottom: 52px; }
                .report-header {
                    position: fixed;
                    inset: 14mm 18mm auto;
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #ea580c;
                    background: #fff;
                    z-index: 10;
                }
                .report-brand {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex: 1 1 auto;
                    min-width: 0;
                }
                .report-logo {
                    width: 48px;
                    height: 48px;
                    object-fit: contain;
                    flex: 0 0 auto;
                }
                .report-logo-placeholder {
                    width: 48px;
                    height: 48px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid #fed7aa;
                    border-radius: 999px;
                    color: #9a3412;
                    font-size: 10px;
                    font-weight: 700;
                    flex: 0 0 auto;
                }
                .report-brand-copy {
                    min-width: 0;
                    max-width: 520px;
                }
                .report-brand-title {
                    font-size: 14px;
                    font-weight: 800;
                    color: #0f172a;
                    text-transform: uppercase;
                    line-height: 1.1;
                }
                .report-brand-subtitle {
                    font-size: 10px;
                    color: #475569;
                    line-height: 1.2;
                }
                .report-meta {
                    flex: 0 0 auto;
                    text-align: right;
                    font-size: 10px;
                    color: #64748b;
                    line-height: 1.4;
                    white-space: nowrap;
                }
                .report-footer {
                    position: fixed;
                    inset: auto 18mm 12mm;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding-top: 8px;
                    border-top: 1px solid #e2e8f0;
                    color: #64748b;
                    font-size: 9px;
                    background: #fff;
                    z-index: 10;
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${reportHeaderMarkup(
                'Physical Fitness Intervention Plan',
                `Target Group: ${drilldownCriteria.value.title}`,
            )}
            <main class="report-shell">
                ${rowsHtml}
            </main>
            ${reportFooterMarkup()}
        </body>
        </html>
    `);
    printWindow.document.close();
};

const handleChartClick = (event: any, chartContext: any, config: any) => {
    // Determine what was clicked
    if (config.dataPointIndex === undefined || config.seriesIndex === undefined)
        return;

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
        const compName =
            chartContext.opts.xaxis.categories[config.dataPointIndex];
        const classificationLabel =
            chartContext.opts.series[config.seriesIndex].name;
        const compObj = apiData.value?.components.find(
            (c) => c.name === compName,
        );
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
const overallDistributionCategories = computed(
    () =>
        apiData.value?.overall_distribution.map(
            (item) => item.classification,
        ) ?? [],
);
const overallDistributionSeries = computed(() => [
    {
        name: 'Students',
        data:
            apiData.value?.overall_distribution.map((item) => item.total) ?? [],
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
        colors:
            apiData.value?.overall_distribution.map((item) =>
                interpretationColor(item.color_class),
            ) ?? [],
        dataLabels: { enabled: true, formatter: (val) => String(val) },
        legend: { show: false },
        xaxis: { categories: overallDistributionCategories.value },
    }),
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
        new Set(
            apiData.value.component_distribution.map(
                (item) => item.classification,
            ),
        ),
    );

    return classifications.map((className) => {
        const data = apiData.value!.components.map((comp) => {
            const match = apiData.value!.component_distribution.find(
                (item) =>
                    item.component === comp.name &&
                    item.classification === className,
            );
            return match ? match.total : 0;
        });

        // resolve first matching rule color
        const rule = apiData.value!.classifications.find(
            (c) => c.classification === className,
        );
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
    }),
);

// Radar Component Ranking
const componentRadarCategories = computed(
    () => apiData.value?.component_radar.map((item) => item.component) ?? [],
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
    }),
);

// College comparison
const collegeComparisonCategories = computed(
    () => apiData.value?.college_comparison.map((item) => item.college) ?? [],
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
    }),
);

// Section comparison
const sectionComparisonCategories = computed(
    () => apiData.value?.section_comparison.map((item) => item.section) ?? [],
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
    }),
);

// Term Trend Analysis
const termTrendCategories = computed(
    () => apiData.value?.term_trends.map((item) => item.term) ?? [],
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
    }),
);

// Campus Comparison
const campusCategories = computed(
    () => apiData.value?.campuses.map((item) => item.name) ?? [],
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
        plotOptions: {
            bar: { horizontal: true, barHeight: '50%', borderRadius: 4 },
        },
        xaxis: { categories: campusCategories.value },
    }),
);

const getComponentTestTypeData = (
    componentName: string,
    testTypeName: string,
) => {
    const stats =
        apiData.value?.classifications.filter(
            (c) =>
                c.component === componentName && c.test_type === testTypeName,
        ) ?? [];

    const categories = stats.map((s) => s.classification);
    const data = stats.map((s) => s.student_count);
    const colors = stats.map((s) => interpretationColor(s.color_class));
    const total = data.reduce((a, b) => a + b, 0);

    return {
        series: [{ name: 'Students', data }],
        donutSeries: data,
        total,
        barOptions: makeOptions({
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '60%',
                    distributed: true,
                    borderRadius: 4,
                },
            },
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

watch([componentId, testTypeId, yearLevelId, sex], () => {
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
});

onMounted(() => {
    if (campusId.value && termId.value) {
        void fetchAnalyticsData();
    }
});
</script>

<template>
    <Head title="Physical Fitness Intelligence Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950/80"
    >
        <!-- Dashboard Header -->
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <Link
                        class="report-btn flex items-center gap-1 border-slate-200 px-2 py-1 text-xs hover:bg-slate-100"
                        href="/admin/reporting/pft-result"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />Back to Results
                    </Link>
                </div>
                <h1
                    class="mt-1 text-lg font-extrabold text-slate-900 dark:text-white"
                >
                    Physical Fitness Intelligence Dashboard
                </h1>
                <p class="text-xs text-slate-500">
                    Accreditation-ready overall campus analytics, comparison
                    modules, and interventions.
                </p>
            </div>

            <div v-if="apiData && canExport" class="flex items-center gap-2">
                <a
                    class="report-btn-primary flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white shadow-md shadow-emerald-600/10 hover:bg-emerald-700"
                    :href="`/admin/reporting/pft-result/export/analytics-pdf?campus_id=${campusId}&term_id=${termId}&college_id=${collegeId}&section_id=${sectionId}`"
                    target="_blank"
                >
                    <FileDown class="h-4 w-4" />Export Analytics PDF
                </a>
            </div>
        </div>

        <!-- Filters Section -->
        <section
            class="report-card rounded-xl border border-slate-200/80 bg-white/70 p-4 shadow-sm backdrop-blur-md dark:border-white/5 dark:bg-slate-900/50"
        >
            <h2
                class="mb-3 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
            >
                Dashboard Queries & Filters
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Campus</label
                    >
                    <AsyncSelect
                        v-model="campusId"
                        :selected="selectedCampus"
                        :endpoint="filterEndpoints.campuses"
                        placeholder="Search campus"
                        @select="onCampusChange"
                    />
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Academic Term</label
                    >
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
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >College</label
                    >
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
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Section</label
                    >
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
                        placeholder="Search section"
                        @select="onSectionChange"
                    />
                </div>
            </div>

            <!-- Optional secondary filters -->
            <div
                v-if="campusId && termId"
                class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800"
            >
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Year Level</label
                    >
                    <select
                        v-model="yearLevelId"
                        class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <option value="">All Years</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                    </select>
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Sex</label
                    >
                    <select
                        v-model="sex"
                        class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <option value="">All Genders</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Component</label
                    >
                    <select
                        v-model="componentId"
                        class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <option value="">All Components</option>
                        <option
                            v-for="c in apiData?.components ?? []"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                        >Test Type</label
                    >
                    <select
                        v-model="testTypeId"
                        class="report-input border-slate-200 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <option value="">All Test Types</option>
                        <option
                            v-for="t in apiData?.test_types ?? []"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-500">
                    <span
                        v-if="!campusId || !termId"
                        class="font-bold text-amber-600"
                        >Please select Campus and Academic Term to query
                        statistics.</span
                    >
                    <span v-else class="text-emerald-600"
                        >Filters applied. Dashboard refreshed.</span
                    >
                </p>
                <button
                    class="report-btn px-3 py-1 text-xs"
                    @click="resetFilters"
                >
                    <RefreshCw class="mr-1 h-3 w-3" />Reset
                </button>
            </div>
        </section>

        <!-- Loading spinner -->
        <div
            v-if="loading"
            class="flex flex-col items-center justify-center rounded-xl bg-white/50 py-20 backdrop-blur-sm dark:bg-slate-950/20"
        >
            <Loader2 class="mb-3 h-10 w-10 animate-spin text-emerald-600" />
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">
                Loading fitness intelligence...
            </p>
        </div>

        <!-- No data panel -->
        <div
            v-else-if="!apiData"
            class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white py-20 dark:border-slate-900 dark:bg-slate-950"
        >
            <BarChart3 class="mb-3 h-12 w-12 text-slate-300" />
            <p class="text-sm font-bold text-slate-600 dark:text-slate-400">
                No Query Results
            </p>
            <p class="mt-1 text-xs text-slate-400">
                Provide query parameters to aggregate reporting data.
            </p>
        </div>

        <!-- Dashboard Contents -->
        <div v-else class="animate-fade-in flex flex-col gap-4">
            <!-- Executive Statistics Cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="stat-card flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Students Tested</span
                        >
                        <Users class="h-5 w-5 text-emerald-600" />
                    </div>
                    <strong
                        class="mt-2 text-2xl font-black text-slate-800 dark:text-white"
                        >{{ apiData.executive_stats.total_students }}</strong
                    >
                </div>

                <div
                    class="stat-card flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Components Covered</span
                        >
                        <Dumbbell class="h-5 w-5 text-violet-600" />
                    </div>
                    <strong
                        class="mt-2 text-2xl font-black text-slate-800 dark:text-white"
                        >{{ apiData.executive_stats.total_components }}</strong
                    >
                </div>

                <div
                    class="stat-card flex cursor-pointer flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-colors hover:border-orange-200 dark:border-white/5 dark:bg-slate-900"
                    @click="
                        openDrilldown({
                            title: 'Students Requiring Intervention',
                        })
                    "
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Requiring Intervention</span
                        >
                        <Layers class="h-5 w-5 animate-pulse text-orange-600" />
                    </div>
                    <strong class="mt-2 text-2xl font-black text-orange-600">{{
                        apiData.executive_stats.requiring_intervention
                    }}</strong>
                </div>

                <div
                    class="stat-card flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Target Performance</span
                        >
                        <Activity class="h-5 w-5 text-emerald-600" />
                    </div>
                    <strong class="mt-2 text-2xl font-black text-emerald-600">{{
                        apiData.executive_stats.target_performance
                    }}</strong>
                </div>
            </div>

            <!-- Executive Graphs -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Campus Physical Fitness Profile (Overall Classification Distribution) -->
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-800 dark:text-white"
                            >
                                Campus Physical Fitness Profile
                            </h2>
                            <p class="text-[11px] text-slate-400">
                                Distribution of students across classification
                                rules. Click bars to drill down.
                            </p>
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
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <div class="mb-3">
                        <h2
                            class="text-sm font-bold text-slate-800 dark:text-white"
                        >
                            Component Performance Distribution
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Identify strong and weak campus components. Click
                            segments to drill down.
                        </p>
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
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Component Strength Radar
                    </h2>
                    <p class="mb-3 text-[11px] text-slate-400">
                        Overall visual campus strengths and weaknesses ranking.
                    </p>
                    <VueApexCharts
                        height="280"
                        type="radar"
                        :options="componentRadarOptions"
                        :series="componentRadarSeries"
                    />
                </section>

                <!-- College Comparison -->
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        College Comparison
                    </h2>
                    <p class="mb-3 text-[11px] text-slate-400">
                        Average performance score across campus colleges.
                    </p>
                    <VueApexCharts
                        height="280"
                        type="bar"
                        :options="collegeComparisonOptions"
                        :series="collegeComparisonSeries"
                    />
                </section>

                <!-- Section Comparison -->
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Section Comparison
                    </h2>
                    <p class="mb-3 text-[11px] text-slate-400">
                        Average performance scores for top 15 sections.
                    </p>
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
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Academic Term Trend Analysis
                    </h2>
                    <p class="mb-3 text-[11px] text-slate-400">
                        Historical changes of fitness scores over terms.
                    </p>
                    <VueApexCharts
                        height="280"
                        type="line"
                        :options="termTrendOptions"
                        :series="termTrendSeries"
                    />
                </section>

                <!-- Campuses comparison list -->
                <section
                    class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
                >
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Campus Comparisons
                    </h2>
                    <p class="mb-3 text-[11px] text-slate-400">
                        Compare PFT results counts across regional campuses.
                    </p>
                    <VueApexCharts
                        height="280"
                        type="bar"
                        :options="campusComparisonOptions"
                        :series="campusComparisonSeries"
                    />
                </section>
            </div>

            <!-- Intervention Priority Dashboard -->
            <section
                class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
            >
                <div class="mb-4">
                    <h2
                        class="text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Intervention Planning Panel
                    </h2>
                    <p class="text-[11px] text-slate-400">
                        Classifications flagged for immediate wellness
                        intervention. Sorted highest priority first.
                    </p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="item in apiData.interventions"
                        :key="`${item.component}-${item.classification}`"
                        class="flex cursor-pointer flex-col justify-between rounded-xl border p-4 transition-all hover:shadow-md"
                        :class="{
                            'border-rose-200/80 bg-rose-50/50 hover:border-rose-300 dark:border-rose-900/50 dark:bg-rose-950/10':
                                item.priority === 'High',
                            'border-orange-200/80 bg-orange-50/50 hover:border-orange-300 dark:border-orange-900/50 dark:bg-orange-950/10':
                                item.priority === 'Medium',
                            'border-amber-200/80 bg-amber-50/50 hover:border-amber-300 dark:border-amber-900/50 dark:bg-amber-950/10':
                                item.priority === 'Low',
                        }"
                        @click="
                            openDrilldown({
                                classification: item.classification,
                                title: `Intervention: ${item.classification}`,
                            })
                        "
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[10px] font-black tracking-wider uppercase"
                                    :class="{
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300':
                                            item.priority === 'High',
                                        'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300':
                                            item.priority === 'Medium',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300':
                                            item.priority === 'Low',
                                    }"
                                >
                                    {{ item.priority }} Priority
                                </span>
                                <span class="text-xs font-bold text-slate-500">
                                    {{ item.student_count }} Students ({{
                                        item.percentage
                                    }}%)
                                </span>
                            </div>
                            <h3
                                class="mt-2.5 text-xs font-bold text-slate-800 dark:text-white"
                            >
                                {{ item.classification }}
                            </h3>
                            <p
                                class="mt-1 text-[10.5px] text-slate-500 dark:text-slate-400"
                            >
                                Component: {{ item.component }} &middot; Test
                                Type: {{ item.test_type }}
                            </p>
                        </div>
                        <div
                            class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800/50"
                        >
                            <span
                                class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >Suggested Intervention:</span
                            >
                            <p
                                class="mt-0.5 line-clamp-2 text-[11px] font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ item.suggested_intervention }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="!apiData.interventions.length"
                        class="col-span-full py-8 text-center text-xs text-slate-400"
                    >
                        No students are currently flagged for priority
                        interventions based on the current search query.
                    </div>
                </div>
            </section>

            <!-- Component & Test Type Analysis Cards (Collapsible summary Component -> Category -> Test Type) -->
            <section
                v-if="showGroupedCollegeProfiles"
                class="report-card animate-fade-in rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
            >
                <div class="mb-4">
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Component & Test Type Intelligence Profile
                    </h2>
                    <p class="text-[11px] text-slate-400">
                        Detailed metric performance distribution grouped by
                        college. Expand a college to inspect its component,
                        category, and test type intelligence profile.
                    </p>
                </div>

                <div class="flex flex-col gap-4">
                    <div
                        v-for="profile in groupedCollegeProfiles"
                        :key="profile.id"
                        class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50/20 dark:border-slate-800/80 dark:bg-slate-950/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-slate-50/50 p-4 text-left transition-colors hover:bg-slate-50 dark:bg-slate-900/50 dark:hover:bg-slate-900/80"
                            @click="toggleCollegeProfile(profile.id)"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-600"
                                ></span>
                                <span
                                    class="truncate text-xs font-extrabold tracking-wider text-slate-800 uppercase sm:text-sm dark:text-white"
                                >
                                    {{ profile.label }}
                                </span>
                                <span
                                    class="text-[10px] font-semibold text-slate-400 sm:text-[11px] dark:text-slate-500"
                                >
                                    ({{ profile.results }} results &middot;
                                    {{ profile.students }} students)
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="hidden flex-wrap items-center gap-1.5 sm:flex"
                                >
                                    <span
                                        v-for="component in profile.hierarchy"
                                        :key="`${profile.id}-${component.label}`"
                                        class="rounded-md border px-2 py-0.5 text-[9px] font-bold"
                                        :style="{
                                            color: interpretationColor(
                                                component.interpretations[0]
                                                    ?.color ?? 'slate',
                                            ),
                                            borderColor:
                                                interpretationColor(
                                                    component.interpretations[0]
                                                        ?.color ?? 'slate',
                                                ) + '33',
                                            backgroundColor:
                                                interpretationColor(
                                                    component.interpretations[0]
                                                        ?.color ?? 'slate',
                                                ) + '14',
                                        }"
                                    >
                                        {{ component.label }}:
                                        {{ component.value }}
                                    </span>
                                </span>
                                <ChevronDown
                                    v-if="
                                        expandedCollegeProfiles.includes(
                                            profile.id,
                                        )
                                    "
                                    class="h-4 w-4 text-slate-500"
                                />
                                <ChevronRight
                                    v-else
                                    class="h-4 w-4 text-slate-500"
                                />
                            </div>
                        </button>

                        <div
                            v-if="expandedCollegeProfiles.includes(profile.id)"
                            class="flex flex-col gap-3 border-t border-slate-100 p-4 dark:border-slate-800"
                        >
                            <div
                                v-for="component in profile.hierarchy"
                                :key="`${profile.id}-${component.label}`"
                                class="overflow-hidden rounded-lg border border-slate-100 bg-white dark:border-slate-800/50 dark:bg-slate-900/20"
                            >
                                <button
                                    type="button"
                                    class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50/50"
                                    @click="
                                        toggleCollegeComponent(
                                            profile.id,
                                            component.label,
                                        )
                                    "
                                >
                                    <div class="min-w-0">
                                        <strong
                                            class="block truncate text-xs font-bold tracking-wider text-slate-800 uppercase dark:text-white"
                                        >
                                            {{ component.label }}
                                        </strong>
                                        <small
                                            class="text-[10px] text-slate-400"
                                        >
                                            {{ component.value }} results
                                            &middot;
                                            {{ component.students }} students
                                        </small>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="hidden flex-wrap items-center gap-1.5 md:flex"
                                        >
                                            <span
                                                v-for="item in component.interpretations"
                                                :key="`${profile.id}-${component.label}-${item.label}`"
                                                class="rounded-md border px-1.5 py-0.5 text-[8px] font-bold"
                                                :style="{
                                                    color: interpretationColor(
                                                        item.color ?? 'slate',
                                                    ),
                                                    borderColor:
                                                        interpretationColor(
                                                            item.color ??
                                                                'slate',
                                                        ) + '33',
                                                    backgroundColor:
                                                        interpretationColor(
                                                            item.color ??
                                                                'slate',
                                                        ) + '14',
                                                }"
                                            >
                                                {{ item.label }}:
                                                {{ item.value }}
                                            </span>
                                        </span>
                                        <ChevronDown
                                            v-if="
                                                expandedCollegeComponents.includes(
                                                    collegeComponentKey(
                                                        profile.id,
                                                        component.label,
                                                    ),
                                                )
                                            "
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                        <ChevronRight
                                            v-else
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                    </div>
                                </button>

                                <div
                                    v-if="
                                        expandedCollegeComponents.includes(
                                            collegeComponentKey(
                                                profile.id,
                                                component.label,
                                            ),
                                        )
                                    "
                                    class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/20 p-3 dark:border-slate-800 dark:bg-slate-950/10"
                                >
                                    <div
                                        v-for="category in component.categories"
                                        :key="`${profile.id}-${component.label}-${category.label}`"
                                        class="overflow-hidden rounded-md border border-slate-100 bg-white dark:border-slate-800/50 dark:bg-slate-900/20"
                                    >
                                        <button
                                            type="button"
                                            class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50/50"
                                            @click="
                                                toggleCollegeCategory(
                                                    profile.id,
                                                    component.label,
                                                    category.label,
                                                )
                                            "
                                        >
                                            <div class="min-w-0">
                                                <strong
                                                    class="block truncate text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                                                >
                                                    {{ category.label }}
                                                </strong>
                                                <small
                                                    class="text-[10px] text-slate-400"
                                                >
                                                    {{ category.value }} results
                                                    &middot;
                                                    {{ category.students }}
                                                    students
                                                </small>
                                            </div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="hidden flex-wrap items-center gap-1.5 lg:flex"
                                                >
                                                    <span
                                                        v-for="item in category.interpretations"
                                                        :key="`${profile.id}-${component.label}-${category.label}-${item.label}`"
                                                        class="rounded border px-1.5 py-0.5 text-[8px] font-bold"
                                                        :style="{
                                                            color: interpretationColor(
                                                                item.color ??
                                                                    'slate',
                                                            ),
                                                            borderColor:
                                                                interpretationColor(
                                                                    item.color ??
                                                                        'slate',
                                                                ) + '33',
                                                            backgroundColor:
                                                                interpretationColor(
                                                                    item.color ??
                                                                        'slate',
                                                                ) + '14',
                                                        }"
                                                    >
                                                        {{ item.label }}:
                                                        {{ item.value }}
                                                    </span>
                                                </span>
                                                <ChevronDown
                                                    v-if="
                                                        expandedCollegeCategories.includes(
                                                            collegeCategoryKey(
                                                                profile.id,
                                                                component.label,
                                                                category.label,
                                                            ),
                                                        )
                                                    "
                                                    class="h-3.5 w-3.5 text-slate-400"
                                                />
                                                <ChevronRight
                                                    v-else
                                                    class="h-3.5 w-3.5 text-slate-400"
                                                />
                                            </div>
                                        </button>

                                        <div
                                            v-if="
                                                expandedCollegeCategories.includes(
                                                    collegeCategoryKey(
                                                        profile.id,
                                                        component.label,
                                                        category.label,
                                                    ),
                                                )
                                            "
                                            class="grid gap-3 border-t border-slate-100 p-3 sm:grid-cols-2 xl:grid-cols-3 dark:border-slate-800"
                                        >
                                            <div
                                                v-for="testType in category.test_types"
                                                :key="`${profile.id}-${component.label}-${category.label}-${testType.label}`"
                                                class="overflow-hidden rounded-lg border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-900/20"
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center justify-between text-left"
                                                    @click="
                                                        toggleCollegeTestType(
                                                            profile.id,
                                                            component.label,
                                                            category.label,
                                                            testType.label,
                                                        )
                                                    "
                                                >
                                                    <div class="min-w-0">
                                                        <strong
                                                            class="block truncate text-xs font-extrabold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                                                        >
                                                            {{ testType.label }}
                                                        </strong>
                                                        <small
                                                            class="text-[10px] text-slate-400"
                                                        >
                                                            {{ testType.value }}
                                                            results &middot;
                                                            {{
                                                                testType.students
                                                            }}
                                                            students
                                                        </small>
                                                    </div>
                                                    <ChevronDown
                                                        v-if="
                                                            expandedCollegeTestTypes.includes(
                                                                collegeTestTypeKey(
                                                                    profile.id,
                                                                    component.label,
                                                                    category.label,
                                                                    testType.label,
                                                                ),
                                                            )
                                                        "
                                                        class="h-3.5 w-3.5 text-slate-400"
                                                    />
                                                    <ChevronRight
                                                        v-else
                                                        class="h-3.5 w-3.5 text-slate-400"
                                                    />
                                                </button>

                                                <div
                                                    v-if="
                                                        expandedCollegeTestTypes.includes(
                                                            collegeTestTypeKey(
                                                                profile.id,
                                                                component.label,
                                                                category.label,
                                                                testType.label,
                                                            ),
                                                        )
                                                    "
                                                    class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                                                >
                                                    <div
                                                        class="flex flex-wrap gap-1.5"
                                                    >
                                                        <span
                                                            v-for="item in testType.interpretations"
                                                            :key="`${profile.id}-${component.label}-${category.label}-${testType.label}-${item.label}`"
                                                            class="rounded border px-2 py-0.5 text-[8px] font-bold"
                                                            :style="{
                                                                color: interpretationColor(
                                                                    item.color ??
                                                                        'slate',
                                                                ),
                                                                borderColor:
                                                                    interpretationColor(
                                                                        item.color ??
                                                                            'slate',
                                                                    ) + '33',
                                                                backgroundColor:
                                                                    interpretationColor(
                                                                        item.color ??
                                                                            'slate',
                                                                    ) + '14',
                                                            }"
                                                        >
                                                            {{ item.label }}:
                                                            {{ item.value }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="mt-3 grid gap-2"
                                                    >
                                                        <div
                                                            v-for="item in apiData?.classifications.filter(
                                                                (c) =>
                                                                    c.component ===
                                                                        component.label &&
                                                                    c.test_type ===
                                                                        testType.label &&
                                                                    c.student_count >
                                                                        0,
                                                            ) ?? []"
                                                            :key="`${profile.id}-${component.label}-${category.label}-${testType.label}-${item.id}`"
                                                            class="cursor-pointer rounded-lg border border-slate-100 bg-white p-2.5 shadow-sm transition-all hover:border-emerald-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950/50 dark:hover:border-emerald-400/60"
                                                            @click="
                                                                openDrilldown({
                                                                    classification:
                                                                        item.classification,
                                                                    testTypeId:
                                                                        String(
                                                                            item.test_type_id,
                                                                        ),
                                                                    title: `${testType.label} - ${item.classification}`,
                                                                })
                                                            "
                                                        >
                                                            <div
                                                                class="flex items-center justify-between gap-2"
                                                            >
                                                                <span
                                                                    class="flex items-center gap-1.5 text-[10.5px] font-bold text-slate-700 dark:text-slate-300"
                                                                >
                                                                    <span
                                                                        class="h-1.5 w-1.5 rounded-full"
                                                                        :style="{
                                                                            backgroundColor:
                                                                                interpretationColor(
                                                                                    item.color_class,
                                                                                ),
                                                                        }"
                                                                    ></span>
                                                                    {{
                                                                        item.classification
                                                                    }}
                                                                </span>
                                                                <span
                                                                    class="text-[9.5px] font-bold text-slate-400"
                                                                >
                                                                    {{
                                                                        item.student_count
                                                                    }}
                                                                    Students ({{
                                                                        item.percentage
                                                                    }}%)
                                                                </span>
                                                            </div>
                                                            <p
                                                                class="mt-1 text-[9.5px] text-slate-500 dark:text-slate-400"
                                                            >
                                                                <strong
                                                                    >Interpretation:</strong
                                                                >
                                                                {{
                                                                    item.interpretation
                                                                }}
                                                            </p>
                                                            <p
                                                                v-if="
                                                                    item.suggested_intervention
                                                                "
                                                                class="mt-0.5 text-[9.5px] text-orange-600 dark:text-orange-400"
                                                            >
                                                                <strong
                                                                    >Intervention
                                                                    Plan:</strong
                                                                >
                                                                {{
                                                                    item.suggested_intervention
                                                                }}
                                                            </p>
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
                </div>
            </section>

            <section
                v-else
                class="report-card animate-fade-in rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
            >
                <div class="mb-4">
                    <h2
                        class="mb-1 text-sm font-bold text-slate-800 dark:text-white"
                    >
                        Component & Test Type Intelligence Profile
                    </h2>
                    <p class="text-[11px] text-slate-400">
                        Detailed metric performance distribution for each
                        physical fitness category. Expand each component and
                        category to view the test types and interpretation
                        rules.
                    </p>
                </div>

                <div class="flex flex-col gap-4">
                    <div
                        v-for="comp in apiData.components"
                        :key="comp.id"
                        class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50/20 dark:border-slate-800/80 dark:bg-slate-950/10"
                    >
                        <!-- Component Header Toggle -->
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-slate-50/50 p-4 text-left transition-colors hover:bg-slate-50 dark:bg-slate-900/50 dark:hover:bg-slate-900/80"
                            @click="toggleComponent(comp.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-600"
                                ></span>
                                <span
                                    class="text-xs font-extrabold tracking-wider text-slate-800 uppercase sm:text-sm dark:text-white"
                                    >{{ comp.name }}</span
                                >
                                <span
                                    class="text-[10px] font-semibold text-slate-400 sm:text-[11px] dark:text-slate-500"
                                >
                                    ({{ comp.total_results }} results ·
                                    {{ comp.total_students }} students)
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="hidden flex-wrap items-center gap-1.5 sm:flex"
                                >
                                    <span
                                        v-for="(
                                            val, label
                                        ) in comp.classifications"
                                        :key="label"
                                        class="rounded-md border px-2 py-0.5 text-[9px] font-bold"
                                        :style="{
                                            color: interpretationColor(
                                                getClassificationColor(label),
                                            ),
                                            borderColor:
                                                interpretationColor(
                                                    getClassificationColor(
                                                        label,
                                                    ),
                                                ) + '33',
                                            backgroundColor:
                                                interpretationColor(
                                                    getClassificationColor(
                                                        label,
                                                    ),
                                                ) + '14',
                                        }"
                                    >
                                        {{ label }}: {{ val }}
                                    </span>
                                </div>
                                <ChevronDown
                                    v-if="expandedComponents.includes(comp.id)"
                                    class="h-4 w-4 text-slate-500"
                                />
                                <ChevronRight
                                    v-else
                                    class="h-4 w-4 text-slate-500"
                                />
                            </div>
                        </button>

                        <!-- Collapsible Category Section -->
                        <div
                            v-if="expandedComponents.includes(comp.id)"
                            class="flex flex-col gap-3 border-t border-slate-100 p-4 dark:border-slate-800"
                        >
                            <div
                                v-for="cat in comp.categories"
                                :key="cat.id"
                                class="overflow-hidden rounded-lg border border-slate-100 bg-white dark:border-slate-800/50 dark:bg-slate-900/20"
                            >
                                <!-- Category Header Toggle -->
                                <button
                                    type="button"
                                    class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50/50"
                                    @click="toggleCategory(cat.id)"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-teal-500"
                                        ></span>
                                        <span
                                            class="text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                                            >{{ cat.name }}</span
                                        >
                                        <span
                                            class="text-[10px] text-slate-400"
                                        >
                                            ({{ cat.total_results }} results ·
                                            {{ cat.total_students }} students)
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="hidden flex-wrap items-center gap-1.5 md:flex"
                                        >
                                            <span
                                                v-for="(
                                                    val, label
                                                ) in cat.classifications"
                                                :key="label"
                                                class="rounded border px-1.5 py-0.5 text-[8px] font-medium"
                                                :style="{
                                                    color: interpretationColor(
                                                        getClassificationColor(
                                                            label,
                                                        ),
                                                    ),
                                                    borderColor:
                                                        interpretationColor(
                                                            getClassificationColor(
                                                                label,
                                                            ),
                                                        ) + '33',
                                                    backgroundColor:
                                                        interpretationColor(
                                                            getClassificationColor(
                                                                label,
                                                            ),
                                                        ) + '14',
                                                }"
                                            >
                                                {{ label }}: {{ val }}
                                            </span>
                                        </div>
                                        <ChevronDown
                                            v-if="
                                                expandedCategories.includes(
                                                    cat.id,
                                                )
                                            "
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                        <ChevronRight
                                            v-else
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                    </div>
                                </button>

                                <!-- Collapsible Test Types list (Component -> Category -> Test Type based on interpretation rule) -->
                                <div
                                    v-if="expandedCategories.includes(cat.id)"
                                    class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/20 p-3 dark:border-slate-800 dark:bg-slate-950/10"
                                >
                                    <div
                                        v-for="type in cat.test_types"
                                        :key="type.id"
                                        class="border-slate-150 overflow-hidden rounded-xl border bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <!-- Test Type Header Toggle -->
                                        <button
                                            type="button"
                                            class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50/50"
                                            @click="toggleTestType(type.id)"
                                        >
                                            <div
                                                class="flex items-center gap-2.5"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full bg-indigo-500"
                                                ></span>
                                                <span
                                                    class="text-xs font-extrabold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                                                    >{{ type.name }}</span
                                                >
                                                <span
                                                    class="text-[10px] text-slate-400"
                                                >
                                                    ({{ type.total_results }}
                                                    results)
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="hidden flex-wrap items-center gap-1.5 sm:flex"
                                                >
                                                    <span
                                                        v-for="(
                                                            val, label
                                                        ) in type.classifications"
                                                        :key="label"
                                                        class="rounded border px-1.5 py-0.5 text-[8px] font-bold"
                                                        :style="{
                                                            color: interpretationColor(
                                                                getClassificationColor(
                                                                    label,
                                                                ),
                                                            ),
                                                            borderColor:
                                                                interpretationColor(
                                                                    getClassificationColor(
                                                                        label,
                                                                    ),
                                                                ) + '33',
                                                            backgroundColor:
                                                                interpretationColor(
                                                                    getClassificationColor(
                                                                        label,
                                                                    ),
                                                                ) + '14',
                                                        }"
                                                    >
                                                        {{ label }}: {{ val }}
                                                    </span>
                                                </div>
                                                <ChevronDown
                                                    v-if="
                                                        expandedTestTypes.includes(
                                                            type.id,
                                                        )
                                                    "
                                                    class="h-3.5 w-3.5 text-slate-400"
                                                />
                                                <ChevronRight
                                                    v-else
                                                    class="h-3.5 w-3.5 text-slate-400"
                                                />
                                            </div>
                                        </button>

                                        <!-- Collapsible Content (Charts and Interpretations) -->
                                        <div
                                            v-if="
                                                expandedTestTypes.includes(
                                                    type.id,
                                                )
                                            "
                                            class="border-t border-slate-100 p-4 dark:border-slate-800"
                                        >
                                            <!-- Show details from classifications -->
                                            <div
                                                class="grid gap-4 sm:grid-cols-2"
                                            >
                                                <div>
                                                    <h5
                                                        class="mb-2 text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                                    >
                                                        Student Count
                                                    </h5>
                                                    <VueApexCharts
                                                        height="160"
                                                        type="bar"
                                                        :options="
                                                            getComponentTestTypeData(
                                                                comp.name,
                                                                type.name,
                                                            ).barOptions
                                                        "
                                                        :series="
                                                            getComponentTestTypeData(
                                                                comp.name,
                                                                type.name,
                                                            ).series
                                                        "
                                                    />
                                                </div>
                                                <div>
                                                    <h5
                                                        class="mb-2 text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                                    >
                                                        Percentage Share
                                                    </h5>
                                                    <VueApexCharts
                                                        height="160"
                                                        type="donut"
                                                        :options="
                                                            getComponentTestTypeData(
                                                                comp.name,
                                                                type.name,
                                                            ).donutOptions
                                                        "
                                                        :series="
                                                            getComponentTestTypeData(
                                                                comp.name,
                                                                type.name,
                                                            ).donutSeries
                                                        "
                                                    />
                                                </div>
                                            </div>

                                            <!-- Dynamic Interpretation details -->
                                            <div
                                                class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800"
                                            >
                                                <h5
                                                    class="mb-2 text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                                >
                                                    Dynamic Interpretations
                                                    (Based on Rules)
                                                </h5>
                                                <div
                                                    class="flex flex-col gap-2"
                                                >
                                                    <div
                                                        v-for="rule in apiData.classifications.filter(
                                                            (c) =>
                                                                c.test_type_id ===
                                                                    type.id &&
                                                                c.student_count >
                                                                    0,
                                                        )"
                                                        :key="rule.id"
                                                        class="cursor-pointer rounded-lg border border-slate-100 bg-slate-50/50 p-2.5 shadow-sm transition-all hover:border-emerald-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950/50"
                                                        @click="
                                                            openDrilldown({
                                                                classification:
                                                                    rule.classification,
                                                                testTypeId:
                                                                    String(
                                                                        type.id,
                                                                    ),
                                                                title: `${type.name} - ${rule.classification}`,
                                                            })
                                                        "
                                                    >
                                                        <div
                                                            class="flex items-center justify-between"
                                                        >
                                                            <span
                                                                class="flex items-center gap-1.5 text-[10.5px] font-bold text-slate-700 dark:text-slate-300"
                                                            >
                                                                <span
                                                                    class="h-1.5 w-1.5 rounded-full"
                                                                    :style="{
                                                                        backgroundColor:
                                                                            interpretationColor(
                                                                                rule.color_class,
                                                                            ),
                                                                    }"
                                                                ></span>
                                                                {{
                                                                    rule.classification
                                                                }}
                                                            </span>
                                                            <span
                                                                class="text-[9.5px] font-bold text-slate-400"
                                                            >
                                                                {{
                                                                    rule.student_count
                                                                }}
                                                                Students ({{
                                                                    rule.percentage
                                                                }}%)
                                                            </span>
                                                        </div>
                                                        <p
                                                            class="mt-1 text-[9.5px] text-slate-500 dark:text-slate-400"
                                                        >
                                                            <strong
                                                                >Interpretation:</strong
                                                            >
                                                            {{
                                                                rule.interpretation
                                                            }}
                                                        </p>
                                                        <p
                                                            v-if="
                                                                rule.suggested_intervention
                                                            "
                                                            class="text-orange-650 mt-0.5 text-[9.5px] dark:text-orange-400"
                                                        >
                                                            <strong
                                                                >Intervention
                                                                Plan:</strong
                                                            >
                                                            {{
                                                                rule.suggested_intervention
                                                            }}
                                                        </p>
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
    <div
        v-if="drilldownOpen"
        class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
        <div
            class="animate-slide-up flex max-h-[85vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900"
        >
            <!-- Modal Header -->
            <div
                class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-6 py-4 dark:border-slate-800 dark:bg-slate-800/50"
            >
                <div>
                    <h3
                        class="text-sm font-black tracking-wider text-slate-800 uppercase dark:text-white"
                    >
                        Student Intelligence Drilldown
                    </h3>
                    <p class="mt-0.5 text-[11px] font-medium text-slate-400">
                        Target Group: {{ drilldownCriteria.title }}
                    </p>
                </div>
                <button
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                    @click="drilldownOpen = false"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Modal Filters/Actions -->
            <div
                class="flex flex-col gap-3 border-b border-slate-100 bg-white px-6 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900"
            >
                <!-- Search bar -->
                <div class="relative w-full sm:max-w-xs">
                    <input
                        v-model="drilldownSearch"
                        type="text"
                        placeholder="Search student no or name..."
                        class="report-input border-slate-200 py-1.5 pl-8 text-xs focus:border-emerald-500"
                        @input="fetchDrilldown"
                    />
                    <Search
                        class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400"
                    />
                </div>
                <!-- Actions -->
                <div class="grid w-full gap-2 sm:max-w-3xl sm:grid-cols-3">
                    <button
                        class="flex flex-col items-start gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-emerald-300 hover:bg-emerald-50/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                        @click="exportDrilldownExcel"
                        :disabled="drilldownReportLoading"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-white"
                        >
                            <FileDown class="h-4 w-4 text-emerald-600" />
                            Export data
                        </span>
                        <span class="text-[11px] leading-tight text-slate-400">
                            Download campus, college, and student rows for
                            Excel.
                        </span>
                    </button>
                    <button
                        class="flex flex-col items-start gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition-colors hover:border-sky-300 hover:bg-sky-50/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                        @click="printDrilldown"
                        :disabled="drilldownReportLoading"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-white"
                        >
                            <Printer class="h-4 w-4 text-sky-600" />
                            Print list
                        </span>
                        <span class="text-[11px] leading-tight text-slate-400">
                            Print the full grouped drilldown for review or
                            filing.
                        </span>
                    </button>
                    <button
                        class="flex flex-col items-start gap-1 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-left shadow-sm transition-colors hover:border-orange-300 hover:bg-orange-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-orange-900/60 dark:bg-orange-950/30 dark:hover:bg-orange-900/40"
                        @click="printInterventionList"
                        :disabled="drilldownReportLoading"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-semibold text-orange-700 dark:text-orange-200"
                        >
                            <Layers class="h-4 w-4" />
                            Intervention plan
                        </span>
                        <span
                            class="text-[11px] leading-tight text-orange-700/70 dark:text-orange-200/70"
                        >
                            Generate grouped intervention notes by campus and
                            college.
                        </span>
                    </button>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Loading spinner -->
                <div
                    v-if="drilldownLoading"
                    class="flex flex-col items-center justify-center py-20"
                >
                    <Loader2
                        class="mb-2 h-8 w-8 animate-spin text-emerald-600"
                    />
                    <p class="text-xs font-semibold text-slate-500">
                        Loading student records...
                    </p>
                </div>
                <!-- Empty list -->
                <div
                    v-else-if="!drilldownCampuses.length"
                    class="py-20 text-center text-xs text-slate-400"
                >
                    No student records found matching this drilldown filter
                    query.
                </div>
                <!-- Tree Drilldown -->
                <div v-else class="flex flex-col gap-3">
                    <div class="text-[11px] text-slate-400">
                        Showing {{ drilldownRootCount }} matching results
                        grouped by campus. Expand each level to load only what
                        you need.
                    </div>
                    <div
                        v-for="campus in drilldownCampuses"
                        :key="campus.key"
                        class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50/20 dark:border-slate-800/80 dark:bg-slate-950/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-slate-50/60 p-4 text-left transition-colors hover:bg-slate-50 dark:bg-slate-900/50 dark:hover:bg-slate-900/80"
                            @click="toggleDrilldownCampus(campus)"
                        >
                            <div class="min-w-0">
                                <strong
                                    class="block truncate text-xs font-extrabold tracking-wider text-slate-800 uppercase dark:text-white"
                                >
                                    {{ campus.campus_name }}
                                </strong>
                                <small class="text-[10px] text-slate-400">
                                    {{ campus.total_students }} students
                                    &middot; {{ campus.total_results }} results
                                </small>
                            </div>
                            <ChevronDown
                                v-if="
                                    expandedDrilldownCampuses.includes(
                                        campus.key,
                                    )
                                "
                                class="h-4 w-4 text-slate-500"
                            />
                            <ChevronRight
                                v-else
                                class="h-4 w-4 text-slate-500"
                            />
                        </button>

                        <div
                            v-if="
                                expandedDrilldownCampuses.includes(campus.key)
                            "
                            class="flex flex-col gap-3 border-t border-slate-100 p-4 dark:border-slate-800"
                        >
                            <div
                                v-if="drilldownCollegeLoading[campus.key]"
                                class="py-3 text-[11px] text-slate-400"
                            >
                                Loading colleges...
                            </div>
                            <div
                                v-for="college in drilldownCollegesByCampus[
                                    campus.key
                                ] ?? []"
                                :key="college.key"
                                class="overflow-hidden rounded-lg border border-slate-100 bg-white dark:border-slate-800/50 dark:bg-slate-900/20"
                            >
                                <button
                                    type="button"
                                    class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50/50"
                                    @click="
                                        toggleDrilldownCollege(campus, college)
                                    "
                                >
                                    <div class="min-w-0">
                                        <strong
                                            class="block truncate text-xs font-bold tracking-wider text-slate-800 uppercase dark:text-white"
                                        >
                                            {{ college.college_name }}
                                        </strong>
                                        <small
                                            class="text-[10px] text-slate-400"
                                        >
                                            {{ college.total_students }}
                                            students &middot;
                                            {{ college.total_results }} results
                                        </small>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <ChevronDown
                                            v-if="
                                                expandedDrilldownColleges.includes(
                                                    college.key,
                                                )
                                            "
                                            class="h-4 w-4 text-slate-500"
                                        />
                                        <ChevronRight
                                            v-else
                                            class="h-4 w-4 text-slate-500"
                                        />
                                    </div>
                                </button>

                                <div
                                    v-if="
                                        expandedDrilldownColleges.includes(
                                            college.key,
                                        )
                                    "
                                    class="flex flex-col gap-3 border-t border-slate-100 p-3 dark:border-slate-800"
                                >
                                    <div
                                        v-if="
                                            drilldownStudentLoading[college.key]
                                        "
                                        class="py-3 text-[11px] text-slate-400"
                                    >
                                        Loading students...
                                    </div>
                                    <div
                                        v-for="student in drilldownStudentsByCollege[
                                            college.key
                                        ] ?? []"
                                        :key="student.key"
                                        class="overflow-hidden rounded-lg border border-slate-100 bg-slate-50/50 dark:border-slate-800/50 dark:bg-slate-900/30"
                                    >
                                        <button
                                            type="button"
                                            class="dark:hover:bg-slate-850/50 flex w-full items-center justify-between p-3 text-left transition-colors hover:bg-slate-50"
                                            @click="
                                                toggleDrilldownStudent(
                                                    campus,
                                                    college,
                                                    student,
                                                )
                                            "
                                        >
                                            <div class="min-w-0">
                                                <strong
                                                    class="block truncate text-xs font-semibold text-slate-800 dark:text-white"
                                                >
                                                    {{ student.student_name }}
                                                </strong>
                                                <small
                                                    class="block text-[10px] text-slate-400"
                                                >
                                                    {{
                                                        student.student_no ??
                                                        '-'
                                                    }}
                                                    &middot;
                                                    {{ student.section }}
                                                    &middot; Year
                                                    {{ student.year_level }}
                                                </small>
                                            </div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-[10px] font-semibold text-slate-400"
                                                >
                                                    {{ student.total_results }}
                                                    results
                                                </span>
                                                <ChevronDown
                                                    v-if="
                                                        expandedDrilldownStudents.includes(
                                                            student.key,
                                                        )
                                                    "
                                                    class="h-4 w-4 text-slate-500"
                                                />
                                                <ChevronRight
                                                    v-else
                                                    class="h-4 w-4 text-slate-500"
                                                />
                                            </div>
                                        </button>

                                        <div
                                            v-if="
                                                expandedDrilldownStudents.includes(
                                                    student.key,
                                                )
                                            "
                                            class="border-t border-slate-100 bg-white p-3 dark:border-slate-800 dark:bg-slate-950/20"
                                        >
                                            <div
                                                v-if="
                                                    drilldownRowsLoading[
                                                        student.key
                                                    ]
                                                "
                                                class="py-2 text-[11px] text-slate-400"
                                            >
                                                Loading results...
                                            </div>
                                            <div
                                                v-else-if="
                                                    !drilldownRowsByStudent[
                                                        student.key
                                                    ]?.length
                                                "
                                                class="py-2 text-[11px] text-slate-400"
                                            >
                                                No results loaded.
                                            </div>
                                            <div v-else class="overflow-x-auto">
                                                <table
                                                    class="w-full border-collapse text-left text-[11px]"
                                                >
                                                    <thead>
                                                        <tr
                                                            class="border-b border-slate-100 font-bold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400"
                                                        >
                                                            <th class="p-2">
                                                                Component
                                                            </th>
                                                            <th class="p-2">
                                                                Test Type
                                                            </th>
                                                            <th class="p-2">
                                                                Raw Result
                                                            </th>
                                                            <th class="p-2">
                                                                Classification
                                                            </th>
                                                            <th class="p-2">
                                                                Remarks
                                                            </th>
                                                            <th class="p-2">
                                                                Test Date
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-slate-100 dark:divide-slate-800"
                                                    >
                                                        <tr
                                                            v-for="row in drilldownRowsByStudent[
                                                                student.key
                                                            ]"
                                                            :key="`${student.key}-${row.test_type}-${row.test_date}`"
                                                            class="dark:hover:bg-slate-850/50 hover:bg-slate-50/50"
                                                        >
                                                            <td class="p-2">
                                                                {{
                                                                    row.component
                                                                }}
                                                            </td>
                                                            <td class="p-2">
                                                                {{
                                                                    row.test_type
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-2 font-mono text-slate-600 dark:text-slate-400"
                                                            >
                                                                {{
                                                                    row.raw_result
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-2 font-bold"
                                                            >
                                                                {{
                                                                    row.classification
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-2 text-slate-400"
                                                            >
                                                                {{
                                                                    row.remarks ??
                                                                    '-'
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-2 text-slate-400"
                                                            >
                                                                {{
                                                                    row.test_date ??
                                                                    '-'
                                                                }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer (Paging controls) -->
            <div
                class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4 text-xs dark:border-slate-800 dark:bg-slate-800/50"
            >
                <span class="text-slate-500">
                    {{ drilldownRootCount }} matching records loaded lazily.
                </span>
            </div>
        </div>
    </div>
</template>
