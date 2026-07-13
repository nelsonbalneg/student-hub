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
import { computed, defineComponent, h, onMounted, ref, watch, unref } from 'vue';
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
import AsyncSelect from '@/components/AsyncSelect.vue';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import { useAppearance } from '@/composables/useAppearance';

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
    cleared?: number;
    clearance_required?: number;
    clearance_uploaded?: number;
    average_bmi?: number;
    fitness_index?: number;
    total_programs?: number;
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
    bmi_analytics?: {
        distribution: Record<string, number>;
        by_campus: { campus: string; avg_bmi: number; overweight_prevalence: number }[];
        lifestyle_risk: Record<string, number>;
    };
    comparisons?: {
        year_level_progression: { year: string; score: number }[];
        male_female: { Male: number; Female: number };
        program_performance: { program: string; score: number }[];
        college_ranking: {
            college: string;
            students: number;
            fitness: number;
            bmi: number;
            participation: string;
            risk: { label: string; color: string };
        }[];
        heatmap: {
            unit: string;
            'Cardiovascular Endurance': { label: string; color_class: string; score: number };
            'Muscular Strength': { label: string; color_class: string; score: number };
            'Muscular Endurance': { label: string; color_class: string; score: number };
            'Flexibility': { label: string; color_class: string; score: number };
            'Body Composition': { label: string; color_class: string; score: number };
        }[];
    };
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
const { resolvedAppearance } = useAppearance();

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
const interventionPanelOpen = ref(false);
const filtersPanelOpen = ref(true);

const toggleInterventionPanel = () => {
    interventionPanelOpen.value = !interventionPanelOpen.value;
};

const toggleFiltersPanel = () => {
    filtersPanelOpen.value = !filtersPanelOpen.value;
};

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

const groupedInterventions = computed(() => {
    if (!apiData.value) return {};
    return apiData.value.interventions.reduce((acc, item) => {
        if (!acc[item.test_type]) acc[item.test_type] = [];
        acc[item.test_type].push(item);
        return acc;
    }, {} as Record<string, InterventionItem[]>);
});

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
    theme: { mode: resolvedAppearance.value },
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

// Year Level Progression (Line chart)
const yearLevelProgressionSeries = computed(() => [
    { name: 'Fitness Index', data: apiData.value?.comparisons?.year_level_progression.map((item) => item.score) ?? [] }
]);
const yearLevelProgressionOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'line' },
        colors: ['#3b82f6'],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 4 },
        xaxis: { categories: apiData.value?.comparisons?.year_level_progression.map((item) => item.year) ?? [] },
        yaxis: { min: 0, max: 100 },
    }),
);

// Program Performance Matrix (Horizontal Bar)
const programPerformanceSeries = computed(() => [
    { name: 'Fitness Score', data: apiData.value?.comparisons?.program_performance.map((item) => item.score) ?? [] }
]);
const programPerformanceOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'bar' },
        colors: ['#3b82f6', '#10b981', '#8b5cf6', '#06b6d4', '#f59e0b', '#f97316'],
        plotOptions: {
            bar: { horizontal: true, barHeight: '60%', borderRadius: 4, distributed: true },
        },
        xaxis: { min: 0, max: 100 },
        yaxis: { categories: apiData.value?.comparisons?.program_performance.map((item) => item.program) ?? [] },
        legend: { show: false }
    }),
);

// Male vs Female Performance (Grouped Bar)
const maleFemalePerformanceSeries = computed(() => [
    { name: 'Male', data: [apiData.value?.comparisons?.male_female.Male ?? 0] },
    { name: 'Female', data: [apiData.value?.comparisons?.male_female.Female ?? 0] },
]);
const maleFemalePerformanceOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'bar' },
        colors: ['#3b82f6', '#ec4899'],
        plotOptions: {
            bar: { horizontal: false, columnWidth: '60%', borderRadius: 4 },
        },
        xaxis: { categories: ['Overall Average'] },
        yaxis: { min: 0, max: 100 },
    }),
);

// BMI Distribution (Donut)
const bmiDistributionSeries = computed(() => {
    const dist = apiData.value?.bmi_analytics?.distribution ?? {};
    return [
        dist['Underweight'] ?? 0,
        dist['Normal'] ?? 0,
        dist['Overweight'] ?? 0,
        dist['Obese I'] ?? 0,
        dist['Obese II'] ?? 0,
    ];
});
const bmiDistributionOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'donut' },
        labels: ['Underweight', 'Normal', 'Overweight', 'Obese I', 'Obese II'],
        colors: ['#3b82f6', '#10b981', '#f59e0b', '#f97316', '#8b5cf6'],
        legend: { position: 'bottom' },
    }),
);

// BMI by Campus (Grouped Bar)
const bmiByCampusSeries = computed(() => [
    { name: 'Avg. BMI', data: apiData.value?.bmi_analytics?.by_campus.map(c => c.avg_bmi) ?? [] },
    { name: 'Overweight/Obese %', data: apiData.value?.bmi_analytics?.by_campus.map(c => c.overweight_prevalence) ?? [] },
]);
const bmiByCampusOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'bar' },
        colors: ['#06b6d4', '#f97316'],
        plotOptions: {
            bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 },
        },
        xaxis: { categories: apiData.value?.bmi_analytics?.by_campus.map(c => c.campus) ?? [] },
        yaxis: { min: 0, max: 100 },
    }),
);

// Lifestyle Risk Profile (Polar Area)
const lifestyleRiskSeries = computed(() => {
    const risk = apiData.value?.bmi_analytics?.lifestyle_risk ?? {};
    return [
        risk['Medical Condition'] ?? 0,
        risk['Medication'] ?? 0,
        risk['Current Smoking'] ?? 0,
        risk['Former Smoking'] ?? 0,
        risk['Regular Alcohol'] ?? 0,
    ];
});
const lifestyleRiskOptions = computed<ApexOptions>(() =>
    makeOptions({
        chart: { type: 'polarArea' },
        labels: ['Medical Condition', 'Medication', 'Current Smoking', 'Former Smoking', 'Regular Alcohol'],
        colors: ['#ef4444', '#f97316', '#94a3b8', '#8b5cf6', '#f59e0b'],
        stroke: { colors: ['#fff'] },
        fill: { opacity: 0.8 },
        legend: { position: 'bottom' },
    }),
);

const heatmapClass = (colorClass?: string) => {
    switch (colorClass) {
        case 'emerald': return 'bg-emerald-50 font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400';
        case 'blue': return 'bg-blue-50 font-bold text-blue-700 dark:bg-blue-950/30 dark:text-blue-400';
        case 'amber': return 'bg-amber-50 font-bold text-amber-700 dark:bg-amber-950/30 dark:text-amber-400';
        case 'orange': return 'bg-orange-50 font-bold text-orange-700 dark:bg-orange-950/30 dark:text-orange-400';
        case 'red': return 'bg-red-50 font-bold text-red-700 dark:bg-red-950/30 dark:text-red-400';
        default: return 'text-slate-500';
    }
};

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

    <div class="pft-analytics-page min-h-screen font-sans bg-slate-50 text-slate-800 lg:flex dark:bg-slate-950 dark:text-slate-100">
        <FitnessIntelligenceSidebar
            active="executive"
            :campus-id="campusId"
            :term-id="termId"
        />

        <!-- Main Content -->
        <main class="min-w-0 flex-1 relative flex flex-col h-screen overflow-y-auto">
            <!-- Header -->
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <button class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 lg:hidden">☰</button>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Physical Fitness Intelligence Dashboard</h2>
                            <p class="hidden text-xs text-slate-500 sm:block">University-wide decision support and analytics</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <AppearanceToggle />
                        <a
                            v-if="apiData && canExport"
                            class="hidden items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 md:flex"
                            :href="`/admin/reporting/pft-result/export/analytics-pdf?campus_id=${campusId}&term_id=${termId}&college_id=${collegeId}&section_id=${sectionId}`"
                            target="_blank"
                        >
                            <FileDown class="h-4 w-4" /> Export Report
                        </a>
                    </div>
                </div>
            </header>

            <!-- Global Analytics Filters -->
            <section class="sticky top-16 z-20 border-b border-slate-200 bg-slate-50/95 px-4 py-4 backdrop-blur sm:px-6">
                <div class="rounded-xl border border-slate-200 bg-white shadow-soft">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between cursor-pointer" @click="toggleFiltersPanel">
                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-700">Dashboard Queries & Filters</h3>
                            <p class="mt-1 text-xs text-slate-500">All charts, KPIs, tables, insights, and reports use the selected filters.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700" @click.stop="fetchAnalyticsData">
                                Apply Filters
                            </button>
                            <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click.stop="resetFilters">
                                ↻ Reset
                            </button>
                            <button type="button" class="ml-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                <ChevronDown v-if="filtersPanelOpen" class="h-5 w-5" />
                                <ChevronRight v-else class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div v-show="filtersPanelOpen">
                        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Campus</span>
                                <AsyncSelect
                                    v-model="campusId"
                                    :selected="selectedCampus"
                                    :endpoint="filterEndpoints.campuses"
                                    placeholder="Search campus"
                                    @select="onCampusChange"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Academic Term</span>
                                <AsyncSelect
                                    v-model="termId"
                                    :selected="selectedTerm"
                                    :endpoint="filterEndpoints.terms"
                                    :params="{ campus_id: campusId }"
                                    :disabled="!campusId"
                                    placeholder="Search academic term"
                                    @select="onTermChange"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">College</span>
                                <AsyncSelect
                                    v-model="collegeId"
                                    :selected="selectedCollege"
                                    :endpoint="filterEndpoints.colleges"
                                    :params="{ campus_id: campusId }"
                                    :disabled="!campusId"
                                    placeholder="Search college"
                                    @select="onCollegeChange"
                                />
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Section</span>
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
                            </label>
                        </div>

                        <div v-if="campusId && termId" class="grid gap-4 p-4 pt-0 md:grid-cols-2 xl:grid-cols-4">
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Year Level</span>
                                <select v-model="yearLevelId" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">All Years</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                    <option value="5">5th Year</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Sex</span>
                                <select v-model="sex" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">All Sexes</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Component</span>
                                <select v-model="componentId" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">All Components</option>
                                    <option value="1">Health-Related</option>
                                    <option value="2">Skill-Related</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Test Type</span>
                                <AsyncSelect
                                    v-model="testTypeId"
                                    :selected="selectedTestType"
                                    :endpoint="filterEndpoints.testTypes"
                                    placeholder="Search test type"
                                />
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <div class="space-y-6 p-4 sm:p-6">
                <div v-if="loading" class="flex h-64 items-center justify-center">
                    <Loader2 class="h-8 w-8 animate-spin text-slate-400" />
                </div>

                <div v-else-if="!apiData" class="flex h-64 flex-col items-center justify-center text-slate-500">
                    <Activity class="mb-2 h-12 w-12 opacity-50" />
                    <p class="font-medium">No analytics data available</p>
                    <p class="text-sm">Please select a campus and academic term to load data.</p>
                </div>

                <div v-else class="grid gap-4 animate-fade-in">
                    <!-- Old dashboard content will render here -->
            <!-- Executive Statistics Cards -->
            <section id="executive" class="space-y-4">
                <div>
                    <h3 class="text-xl font-extrabold text-slate-900">Executive Overview</h3>
                    <p class="text-sm text-slate-500">Participation, risk, readiness, health, and fitness performance summary</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                    <!-- Students Assessed -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Students Assessed</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.total_students }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-600">↑ 8.6%</p>
                    </article>

                    <!-- Cleared -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Cleared</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.cleared ?? 0 }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-600">{{ apiData.executive_stats.total_students ? Math.round(((apiData.executive_stats.cleared ?? 0) / apiData.executive_stats.total_students) * 100) : 0 }}%</p>
                    </article>

                    <!-- Clearance Required -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Clearance Required</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.clearance_required ?? 0 }}</p>
                        <p class="mt-1 text-xs font-semibold text-orange-600">{{ apiData.executive_stats.total_students ? Math.round(((apiData.executive_stats.clearance_required ?? 0) / apiData.executive_stats.total_students) * 100) : 0 }}%</p>
                    </article>

                    <!-- Clearance Uploaded -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Clearance Uploaded</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.clearance_uploaded ?? 0 }}</p>
                        <p class="mt-1 text-xs font-semibold text-violet-600">{{ apiData.executive_stats.clearance_required ? Math.round(((apiData.executive_stats.clearance_uploaded ?? 0) / apiData.executive_stats.clearance_required) * 100) : 0 }}% of required</p>
                    </article>

                    <!-- Average BMI -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Average BMI</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.average_bmi?.toFixed(1) ?? 'N/A' }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-600">Overall</p>
                    </article>

                    <!-- Fitness Index -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Fitness Index</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.fitness_index?.toFixed(1) ?? 'N/A' }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-600">Overall Score</p>
                    </article>

                    <!-- Campuses -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Campuses</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.total_campuses }}</p>
                        <p class="mt-1 text-xs text-slate-500">Participating</p>
                    </article>

                    <!-- Programs -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-1">
                        <p class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400">Programs</p>
                        <p class="mt-2 text-2xl font-black text-slate-800 dark:text-white">{{ apiData.executive_stats.total_programs ?? apiData.executive_stats.total_colleges }}</p>
                        <p class="mt-1 text-xs text-slate-500">With records</p>
                    </article>
                </div>
            </section>

            <!-- Executive Graphs -->
            <div class="grid gap-4 xl:grid-cols-12">
                <!-- Campus Physical Fitness Profile (Overall Classification Distribution) -->
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-5">
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-slate-900">Campus Physical Fitness Profile</h4>
                        <p class="text-xs text-slate-500">Distribution of students across classification rules.</p>
                    </div>
                    <div class="h-[300px]">
                        <VueApexCharts
                            height="100%"
                            type="bar"
                            :options="overallDistributionOptions"
                            :series="overallDistributionSeries"
                        />
                    </div>
                </article>

                <!-- Component Performance Distribution (Stacked Classification per Component) -->
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-4">
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-slate-900">Component Performance Distribution</h4>
                        <p class="text-xs text-slate-500">Identify strong and weak campus components.</p>
                    </div>
                    <div class="h-[300px]">
                        <VueApexCharts
                            height="100%"
                            type="bar"
                            :options="componentPerformanceOptions"
                            :series="componentPerformanceSeries"
                        />
                    </div>
                </article>

                <!-- Priority Alerts -->
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-3">
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-slate-900">Priority Alerts</h4>
                        <p class="text-xs text-slate-500">Items needing immediate action</p>
                    </div>

                    <div class="flex flex-col justify-between space-y-3 h-[300px] overflow-y-auto pr-1">
                        <div class="rounded-xl border border-red-100 bg-red-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">High Risk</p>
                            <p class="mt-1 text-xs font-semibold text-slate-900">174 students have unresolved PAR-Q clearance</p>
                        </div>
                        <div class="rounded-xl border border-orange-100 bg-orange-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Performance Concern</p>
                            <p class="mt-1 text-xs font-semibold text-slate-900">Reaction time is the weakest university component</p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Participation Gap</p>
                            <p class="mt-1 text-xs font-semibold text-slate-900">M’lang Campus is 13% below target</p>
                        </div>
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Data Quality</p>
                            <p class="mt-1 text-xs font-semibold text-slate-900">93 student records have incomplete assessments</p>
                        </div>
                    </div>
                </article>
            </div>

            <!-- COMPARISONS -->
            <section id="comparisons" class="space-y-4">
                <div>
                    <h3 class="text-xl font-extrabold text-slate-900">Comparative Analytics</h3>
                    <p class="text-sm text-slate-500">Campus, college, section, and term trend comparisons</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-12">
                    <!-- Campus Comparison -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Campus Comparison</h4>
                            <p class="text-xs text-slate-500">Compare PFT results counts across regional campuses.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="campusComparisonOptions"
                                :series="campusComparisonSeries"
                            />
                        </div>
                    </article>

                    <!-- Term Trend Analysis -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Academic Term Trend Analysis</h4>
                            <p class="text-xs text-slate-500">Historical changes of fitness scores over terms.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="line"
                                :options="termTrendOptions"
                                :series="termTrendSeries"
                            />
                        </div>
                    </article>

                    <!-- Radar Component Ranking -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Component Strength Radar</h4>
                            <p class="text-xs text-slate-500">Overall visual campus strengths and weaknesses ranking.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="radar"
                                :options="componentRadarOptions"
                                :series="componentRadarSeries"
                            />
                        </div>
                    </article>

                    <!-- College Comparison -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900">College Comparison</h4>
                            <p class="text-xs text-slate-500">Average performance score across campus colleges.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="collegeComparisonOptions"
                                :series="collegeComparisonSeries"
                            />
                        </div>
                    </article>

                    <!-- Section Comparison -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900">Section Comparison</h4>
                            <p class="text-xs text-slate-500">Average performance scores for top 15 sections.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="sectionComparisonOptions"
                                :series="sectionComparisonSeries"
                            />
                        </div>
                    </article>

                    <!-- Year Level Progression -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Year Level Progression</h4>
                            <p class="text-xs text-slate-500">How fitness changes from first to fourth year.</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="line"
                                :options="yearLevelProgressionOptions"
                                :series="yearLevelProgressionSeries"
                            />
                        </div>
                    </article>

                    <!-- College Ranking Table -->
                    <article class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-7 overflow-hidden">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white">College Ranking</h4>
                                <p class="text-xs text-slate-500">Overall performance, participation, and risk indicators</p>
                            </div>
                            <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Export</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                                    <tr>
                                        <th class="px-4 py-3">Rank</th>
                                        <th class="px-4 py-3">College</th>
                                        <th class="px-4 py-3">Students</th>
                                        <th class="px-4 py-3">Fitness</th>
                                        <th class="px-4 py-3">BMI</th>
                                        <th class="px-4 py-3">Participation</th>
                                        <th class="px-4 py-3">Risk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="(rank, index) in apiData?.comparisons?.college_ranking ?? []" :key="rank.college">
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ index + 1 }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ rank.college }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ rank.students.toLocaleString() }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ rank.fitness.toFixed(1) }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ rank.bmi }}</td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ rank.participation }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wider"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400': rank.risk.color === 'emerald',
                                                    'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400': rank.risk.color === 'amber',
                                                    'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400': rank.risk.color === 'orange',
                                                    'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400': rank.risk.color === 'red',
                                                }">
                                                {{ rank.risk.label }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!(apiData?.comparisons?.college_ranking?.length)">
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No college ranking data available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <!-- Program Performance Matrix -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-5">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Program Performance Matrix</h4>
                            <p class="text-xs text-slate-500">Top degree programs by fitness performance</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="programPerformanceOptions"
                                :series="programPerformanceSeries"
                            />
                        </div>
                    </article>

                    <!-- Male vs Female Performance -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Male vs Female Performance</h4>
                            <p class="text-xs text-slate-500">Average normalized scores across components</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="maleFemalePerformanceOptions"
                                :series="maleFemalePerformanceSeries"
                            />
                        </div>
                    </article>

                    <!-- Campus-College Heatmap -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-6 overflow-hidden">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Campus-College Heatmap</h4>
                            <p class="text-xs text-slate-500">Quick identification of strengths and weaknesses</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-slate-500 border-b border-slate-100 dark:border-slate-800">
                                        <th class="px-3 py-2 text-left">Unit</th>
                                        <th class="px-3 py-2">BMI</th>
                                        <th class="px-3 py-2">Cardio</th>
                                        <th class="px-3 py-2">Strength</th>
                                        <th class="px-3 py-2">Flexibility</th>
                                        <th class="px-3 py-2">Speed</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-center">
                                    <tr v-for="unit in apiData?.comparisons?.heatmap ?? []" :key="unit.unit">
                                        <td class="px-3 py-3 text-left font-semibold text-slate-900 dark:text-white">{{ unit.unit }}</td>
                                        <td :class="heatmapClass(unit['Body Composition']?.color_class)">{{ unit['Body Composition']?.label ?? '-' }}</td>
                                        <td :class="heatmapClass(unit['Cardiovascular Endurance']?.color_class)">{{ unit['Cardiovascular Endurance']?.label ?? '-' }}</td>
                                        <td :class="heatmapClass(unit['Muscular Strength']?.color_class)">{{ unit['Muscular Strength']?.label ?? '-' }}</td>
                                        <td :class="heatmapClass(unit['Flexibility']?.color_class)">{{ unit['Flexibility']?.label ?? '-' }}</td>
                                        <td :class="heatmapClass(unit['Muscular Endurance']?.color_class)">{{ unit['Muscular Endurance']?.label ?? '-' }}</td>
                                    </tr>
                                    <tr v-if="!(apiData?.comparisons?.heatmap?.length)">
                                        <td colspan="6" class="px-3 py-8 text-center text-slate-500">No heatmap data available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>

            <!-- HEALTH -->
            <section id="health" class="space-y-4">
                <div>
                    <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Health and BMI Analytics</h3>
                    <p class="text-sm text-slate-500">Body composition, lifestyle, and health risk patterns</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-12">
                    <!-- BMI Distribution -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">BMI Distribution</h4>
                            <p class="text-xs text-slate-500">WHO classification of assessed students</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="donut"
                                :options="bmiDistributionOptions"
                                :series="bmiDistributionSeries"
                            />
                        </div>
                    </article>

                    <!-- BMI by Campus -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">BMI by Campus</h4>
                            <p class="text-xs text-slate-500">Average BMI and overweight prevalence</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="bar"
                                :options="bmiByCampusOptions"
                                :series="bmiByCampusSeries"
                            />
                        </div>
                    </article>

                    <!-- Lifestyle Risk Profile -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900 xl:col-span-4">
                        <div class="mb-4">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Lifestyle Risk Profile</h4>
                            <p class="text-xs text-slate-500">Smoking, alcohol, medication, and medical conditions</p>
                        </div>
                        <div class="h-[300px]">
                            <VueApexCharts
                                height="100%"
                                type="polarArea"
                                :options="lifestyleRiskOptions"
                                :series="lifestyleRiskSeries"
                            />
                        </div>
                    </article>
                </div>
            </section>
            <section
                class="report-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/5 dark:bg-slate-900"
            >
                <div class="flex cursor-pointer items-start justify-between" @click="toggleInterventionPanel">
                    <div>
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
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <ChevronDown v-if="interventionPanelOpen" class="h-5 w-5" />
                        <ChevronRight v-else class="h-5 w-5" />
                    </button>
                </div>
                <div v-if="interventionPanelOpen" class="mt-4 flex flex-col gap-6">
                    <div v-for="(items, testType) in groupedInterventions" :key="testType" class="flex flex-col gap-3">
                        <div class="border-b border-slate-100 pb-2 dark:border-slate-800">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ testType }}</h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="item in items"
                                :key="`${item.component}-${item.classification}`"
                                class="group flex cursor-pointer items-start gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-all hover:border-slate-300 hover:shadow-md dark:border-white/5 dark:bg-slate-800/50 dark:hover:border-white/10"
                                @click="
                                    openDrilldown({
                                        classification: item.classification,
                                        title: `Intervention: ${item.classification}`,
                                    })
                                "
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="{
                                        'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400': item.priority === 'High',
                                        'bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400': item.priority === 'Medium',
                                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400': item.priority === 'Low',
                                    }">
                                    <Activity class="h-5 w-5" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ item.classification }}</h4>
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                            :class="{
                                                'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400': item.priority === 'High',
                                                'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400': item.priority === 'Medium',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400': item.priority === 'Low',
                                            }">
                                            {{ item.priority }} Priority
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ item.suggested_intervention }}</p>
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                                            <Users class="h-3.5 w-3.5" />
                                            <span>{{ item.student_count }} Students ({{ item.percentage }}%)</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                            <span class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                            <span>{{ item.component }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!apiData.interventions.length"
                        class="py-8 text-center text-xs text-slate-400"
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
        </main>
    </div>
</template>

<style>
@reference "tailwindcss";
.report-card {
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
    color-scheme: light;
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-color: #e2e8f0 !important;
}
.report-input::placeholder {
    color: #94a3b8 !important;
}
.report-input option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}
.report-btn {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
    background-color: #ffffff !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.report-btn-primary {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700;
}
.report-th {
    @apply px-3 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}
.report-td {
    @apply px-3 py-2 text-xs text-slate-600 dark:text-slate-300;
    color: #334155 !important;
}
.page-btn {
    @apply min-w-7 rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold disabled:opacity-40 dark:border-white/10;
    background-color: #ffffff !important;
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.stat-card:is(.dark *) {
    background-color: #020617 !important;
    color: #94a3b8 !important;
}
.stat-card:is(.dark *) strong {
    color: #ffffff !important;
}
.report-card:is(.dark *) {
    background-color: #020617 !important;
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.report-input:is(.dark *) {
    color-scheme: dark;
    background-color: #0f172a !important;
    color: #f1f5f9 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.report-input:is(.dark *)::placeholder {
    color: #64748b !important;
}
.report-input:is(.dark *) option {
    background-color: #0f172a !important;
    color: #f1f5f9 !important;
}
.report-btn:is(.dark *),
.page-btn:is(.dark *) {
    background-color: #0f172a !important;
    color: #e2e8f0 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.report-th:is(.dark *) {
    color: #94a3b8 !important;
}
.report-td:is(.dark *) {
    color: #cbd5e1 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.dark .pft-analytics-page {
    background-color: #020617 !important;
    color: #cbd5e1 !important;
}

.dark .pft-analytics-page [class*='bg-white'],
.dark .pft-analytics-page [class*='bg-slate-50'] {
    background-color: #0f172a !important;
}

.dark .pft-analytics-page [class*='bg-white/'],
.dark .pft-analytics-page [class*='bg-slate-50/'] {
    background-color: rgba(15, 23, 42, 0.84) !important;
}

.dark .pft-analytics-page [class*='border-slate-100'],
.dark .pft-analytics-page [class*='border-slate-200'] {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.dark .pft-analytics-page [class*='text-slate-900'],
.dark .pft-analytics-page [class*='text-slate-800'],
.dark .pft-analytics-page [class*='text-slate-700'] {
    color: #f8fafc !important;
}

.dark .pft-analytics-page [class*='text-slate-600'],
.dark .pft-analytics-page [class*='text-slate-500'] {
    color: #94a3b8 !important;
}

.dark .pft-analytics-page [class*='hover:bg-slate-50']:hover {
    background-color: #1e293b !important;
}

.dark .pft-analytics-page [class*='bg-red-50'],
.dark .pft-analytics-page [class*='bg-orange-50'],
.dark .pft-analytics-page [class*='bg-yellow-50'],
.dark .pft-analytics-page [class*='bg-blue-50'] {
    background-color: #111827 !important;
}
</style>
