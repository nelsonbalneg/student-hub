<script setup lang="ts">
import { Head, useForm, setLayoutProps } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    Calendar,
    CheckCircle2,
    ChevronDown,
    Circle,
    Clock3,
    Download,
    Dumbbell,
    Edit,
    ExternalLink,
    FileText,
    GraduationCap,
    Home,
    IdCard,
    Info,
    Mail,
    Phone,
    Plus,
    School,
    Trash2,
    Trophy,
    User,
    Users,
    ChartColumnIncreasing,
} from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    nextTick,
    onMounted,
    ref,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as achievementsRoutes from '@/routes/achievements';
import * as pftRoutes from '@/routes/student-profile/physical-fitness';
import * as trainingsRoutes from '@/routes/trainings';
import { Heart } from 'lucide-vue-next';
import * as ccdCaresEvaluationRoutes from '@/routes/student-profile/ccd-cares/evaluation';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

setLayoutProps({
    breadcrumbs: [
        { title: 'Home', href: '/' },
        { title: 'Student Profile', href: '/student-profile' },
    ],
});

type ProfileData = {
    studentNo: string;
    lastName: string;
    firstName: string;
    middlename?: string;
    middleInitial?: string;
    extName?: string;
    dateOfBirth?: string;
    placeOfBirth?: string;
    gender?: string;
    mobileNo?: string;
    email?: string;
    resAddress?: string;
    resStreet?: string;
    resBarangay?: string;
    resTownCity?: string;
    resProvince?: string;
    permAddress?: string;
    father?: string;
    mother?: string;
    guardian?: string;
    elemSchool?: string;
    hsSchool?: string;
    shsSchool?: string;
    statusRemarks?: string;
    [key: string]: any;
};

type Achievement = {
    id: number;
    title: string;
    date_received: string;
    awarder: string | null;
    description: string | null;
};

type Training = {
    id: number;
    title: string;
    date_from: string;
    date_to: string | null;
    venue: string | null;
    organizer: string | null;
};

type CeeDocument = {
    key: string;
    field: string;
    label: string;
    name: string;
    path: string;
    url: string;
    exists: boolean;
    extension: string;
};

type PftConfiguration = {
    id: number;
    field_name: string;
    field_label: string;
    field_type: string;
    options: string[] | null;
    placeholder: string | null;
    help_text: string | null;
    is_required: boolean;
};

type PftInterpretationRule = {
    id: number;
    field_name: string;
    label: string;
    min_value: number | null;
    max_value: number | null;
    color: string | null;
    is_active: boolean;
};

type PftTestType = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    unit: string | null;
    configurations: PftConfiguration[];
    interpretation_rules: PftInterpretationRule[];
};

type PftCategory = {
    id: number;
    name: string;
    description: string | null;
    test_types: PftTestType[];
};

type PftComponent = {
    id: number;
    name: string;
    description: string | null;
    categories: PftCategory[];
};

type PftResult = {
    id: number;
    pft_test_type_id: number;
    term_id: string;
    status: 'draft' | 'completed' | string;
    results_json: Record<string, unknown>;
    remarks: string | null;
    tested_at: string | null;
    updated_at: string;
};

type PftTerm = {
    id: number;
    school_year: string;
    semester: string;
    term_id: string;
    status: string;
};

type PftRequirementRow = {
    key: string;
    term: PftTerm;
    component: PftComponent;
    category: PftCategory;
    testType: PftTestType;
    result: PftResult | null;
};

const props = defineProps<{
    profile: {
        data: ProfileData | null;
        error: string | null;
    };
    ceeDocuments: {
        data: CeeDocument[];
        error: string | null;
    };
    achievements: Achievement[];
    trainings: Training[];
    lookups?: {
        tribes?: Array<{ tribeId: number; tribeName: string }>;
        civilStatuses?: Array<{ statusId: number; civilDesc: string }>;
        religions?: Array<{ religionId: number; religion: string }>;
        nationalities?: Array<{ nationalityId: number; nationality: string }>;
    };
    physicalFitness: {
        components: PftComponent[];
        results: Record<string, PftResult>;
        terms: PftTerm[];
        canView: boolean;
        canSubmit: boolean;
        canFillUp: boolean;
    };
    ccdCares: {
        assessments: Array<{
            period: {
                id: number;
                title: string;
                description: string | null;
                start_date: string;
                end_date: string;
                status: string;
                is_open: boolean;
            };
            template: any;
            submission: {
                submitted_at: string;
                answers: Record<string, any>;
                interpretation_results: Array<{
                    category_id: number;
                    category_name: string;
                    score: number;
                    interpretation: string;
                    suggested_intervention: string | null;
                }>;
            } | null;
        }>;
    };
}>();

const PROFILE_TAB_KEY = 'student_profile_active_tab';
const activeTab = ref('personal');

const baseTabs = [
    { id: 'personal', label: 'Personal', icon: User },
    { id: 'academic', label: 'Academic', icon: GraduationCap },
    { id: 'family', label: 'Family', icon: Users },
    { id: 'education', label: 'Education', icon: BookOpen },
    { id: 'documents', label: 'Documents', icon: FileText },
    { id: 'achievements', label: 'Awards', icon: Trophy },
    { id: 'trainings', label: 'Trainings', icon: Calendar },
    { id: 'socio', label: 'Socio-Econ', icon: Info },
    {
        id: 'physical-fitness-test',
        label: 'Physical Fitness Test',
        icon: Dumbbell,
    },
    {
        id: 'ccd-cares',
        label: 'CCD Cares',
        icon: Heart,
    },
];

const tabs = computed(() =>
    baseTabs.filter(
        (tab) =>
            tab.id !== 'physical-fitness-test' || props.physicalFitness.canView,
    ),
);

const isValidTab = (tab: string | null) =>
    Boolean(tab && tabs.value.some((item) => item.id === tab));

const setActiveTab = (tab: string) => {
    if (!isValidTab(tab)) return;

    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(PROFILE_TAB_KEY, tab);
        window.history.replaceState(null, '', `#${tab}`);
    }
};

const fullName = computed(() => {
    if (!props.profile.data) return '';
    const { firstName, middleInitial, lastName, extName } = props.profile.data;
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

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
};

const displayValue = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '-';

    return String(value);
};

const genderLabel = computed(() => {
    const gender = props.profile.data?.gender?.trim().toUpperCase();

    if (gender === 'M') return 'Male';
    if (gender === 'F') return 'Female';

    return displayValue(props.profile.data?.gender);
});

const getInitials = (name: string) => {
    if (!name) return 'S';
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2);
};

const studentPictureUrl = computed(() => {
    const raw = String(props.profile.data?.studentPicture ?? '').trim();
    if (!raw) return '';
    if (raw.startsWith('data:image')) return raw;
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    if (/^[A-Za-z0-9+/=\r\n]+$/.test(raw)) {
        return `data:image/jpeg;base64,${raw.replace(/\s+/g, '')}`;
    }
    return '';
});

const personalFields = computed(() => [
    { label: 'Gender', value: genderLabel.value },
    { label: 'Birth Date', value: formatDate(props.profile.data?.dateOfBirth) },
    {
        label: 'Birth Place',
        value: displayValue(props.profile.data?.placeOfBirth),
    },
    {
        label: 'Civil Status',
        value: displayValue(props.profile.data?.civilStatus ?? 'Single'),
    },
]);

const academicFields = computed(() => [
    {
        label: 'Year Level',
        value: displayValue(props.profile.data?.yearLevelId),
    },
    {
        label: 'Max Units',
        value: displayValue(props.profile.data?.maxUnitsLoad),
    },
    {
        label: 'Date Admitted',
        value: formatDate(props.profile.data?.dateAdmitted),
    },
    { label: 'Status', value: displayValue(props.profile.data?.statusRemarks) },
]);

const familyFields = computed(() => [
    { label: 'Father', value: displayValue(props.profile.data?.father) },
    { label: 'Mother', value: displayValue(props.profile.data?.mother) },
    { label: 'Guardian', value: displayValue(props.profile.data?.guardian) },
]);

const educationFields = computed(() => [
    {
        label: 'Elementary',
        value: displayValue(props.profile.data?.elemSchool),
    },
    { label: 'High School', value: displayValue(props.profile.data?.hsSchool) },
    {
        label: 'Senior High School',
        value: displayValue(props.profile.data?.shsSchool),
    },
]);

const socioFields = computed(() => [
    {
        label: 'Household Income',
        value:
            typeof props.profile.data?.householdIncome === 'number'
                ? formatCurrency(props.profile.data.householdIncome)
                : displayValue(props.profile.data?.householdIncome),
    },
    {
        label: 'Scholarship',
        value: displayValue(props.profile.data?.scholarship),
    },
    {
        label: 'Student Type',
        value: displayValue(props.profile.data?.studentType),
    },
]);

const ceeDocumentCount = computed(() => props.ceeDocuments?.data?.length ?? 0);

const groupedCeeDocuments = computed(() => {
    const groups = new Map<
        string,
        { key: string; label: string; documents: CeeDocument[] }
    >();

    for (const document of props.ceeDocuments?.data ?? []) {
        const key = document.field;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label: document.label,
                documents: [],
            });
        }

        groups.get(key)?.documents.push(document);
    }

    return Array.from(groups.values());
});

const isImageDocument = (document: CeeDocument) =>
    ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(document.extension);

const isPdfDocument = (document: CeeDocument) => document.extension === 'pdf';

const activeTabDescription = computed(() =>
    activeTab.value === 'documents'
        ? 'Student profile information synced with the usmcee system.'
        : activeTab.value === 'physical-fitness-test'
          ? 'Physical fitness tests are loaded from the active campus configuration.'
          : activeTab.value === 'ccd-cares'
            ? 'Access your CCD Cares evaluation forms and view your result interpretations.'
            : 'Student profile information synced with the academic system.',
);

const selectedPftComponentId = ref(
    props.physicalFitness.components[0]?.id ?? null,
);
const selectedPftCategoryId = ref<number | null>(
    props.physicalFitness.components[0]?.categories[0]?.id ?? null,
);
const selectedPftTestTypeId = ref<number | null>(
    props.physicalFitness.components[0]?.categories[0]?.test_types[0]?.id ??
        null,
);
const selectedPftTermId = ref(props.physicalFitness.terms[0]?.term_id ?? null);
const pftDrawerOpen = ref(false);
const pftAnalyticsDrawerOpen = ref(false);
const pftAnalyticsChartsReady = ref(false);
const pftAnalyticsChartKey = ref(0);
const pftAnalyticsLoading = ref(false);
const pftAnalyticsError = ref<string | null>(null);
const pftAnalyticsData = ref<Record<string, any> | null>(null);
const pftAnalyticsFilterTermId = ref('');
const pftAnalyticsFilterComponentId = ref('');
const pftAnalyticsFilterCategoryId = ref('');
const pftAnalyticsFilterTestTypeId = ref('');
const pftAnalyticsFilterDateFrom = ref('');
const pftAnalyticsFilterDateTo = ref('');
const openPftAnalyticsTimelineGroups = ref<string[]>([]);
const openPftAnalyticsTimelineComponentGroups = ref<string[]>([]);

const selectedPftComponent = computed(() =>
    props.physicalFitness.components.find(
        (component) => component.id === selectedPftComponentId.value,
    ),
);

const selectedPftCategory = computed(() =>
    selectedPftComponent.value?.categories.find(
        (category) => category.id === selectedPftCategoryId.value,
    ),
);

const selectedPftTestType = computed(() =>
    selectedPftCategory.value?.test_types.find(
        (testType) => testType.id === selectedPftTestTypeId.value,
    ),
);
const selectedPftTerm = computed(() =>
    props.physicalFitness.terms.find(
        (term) => term.term_id === selectedPftTermId.value,
    ),
);
const activePftTerm = computed(
    () =>
        props.physicalFitness.terms.find(
            (term) => term.status?.toLowerCase() === 'active',
        ) ?? selectedPftTerm.value,
);

watch(selectedPftComponentId, () => {
    selectedPftCategoryId.value =
        selectedPftComponent.value?.categories[0]?.id ?? null;
    selectedPftTestTypeId.value =
        selectedPftCategory.value?.test_types[0]?.id ?? null;
});

watch(selectedPftCategoryId, () => {
    selectedPftTestTypeId.value =
        selectedPftCategory.value?.test_types[0]?.id ?? null;
});

const pftResultKey = (termId?: string | null, testTypeId?: number | null) =>
    termId && testTypeId ? `${termId}:${testTypeId}` : '';

const pftResultFor = (
    testTypeId?: number | null,
    termId: string | null = selectedPftTermId.value,
) => props.physicalFitness.results[pftResultKey(termId, testTypeId)] ?? null;

const pftRequirementRows = computed<PftRequirementRow[]>(() =>
    props.physicalFitness.terms.flatMap((term) =>
        props.physicalFitness.components.flatMap((component) =>
            component.categories.flatMap((category) =>
                category.test_types.map((testType) => ({
                    key: pftResultKey(term.term_id, testType.id),
                    term,
                    component,
                    category,
                    testType,
                    result: pftResultFor(testType.id, term.term_id),
                })),
            ),
        ),
    ),
);

const visiblePftRequirementRows = computed(() =>
    pftRequirementRows.value.filter(
        (row) =>
            row.term.term_id === selectedPftTermId.value &&
            (props.physicalFitness.canFillUp || pftRowIsCompleted(row)),
    ),
);

const pftRowIsDraft = (row?: PftRequirementRow | null) => {
    if (!row?.result) return false;
    if (row.result.status === 'draft') return true;
    if (row.result.status === 'completed') return false;
    if (row.result.results_json._is_draft === true) return true;

    return row.testType.configurations.some((field) => {
        if (!field.is_required) return false;

        const value = row.result?.results_json[field.field_name];

        return value === null || value === undefined || value === '';
    });
};

const pftRowIsCompleted = (row?: PftRequirementRow | null) =>
    Boolean(row?.result) && !pftRowIsDraft(row);

const totalPftPendingCount = computed(() =>
    props.physicalFitness.canFillUp
        ? pftRequirementRows.value.filter((row) => !pftRowIsCompleted(row))
              .length
        : 0,
);

const selectedPftTermCompletedCount = computed(
    () => visiblePftRequirementRows.value.filter(pftRowIsCompleted).length,
);

const selectedPftTermPendingCount = computed(
    () =>
        visiblePftRequirementRows.value.filter((row) => !pftRowIsCompleted(row))
            .length,
);

const selectedPftTermCompletionPercent = computed(() => {
    const total = visiblePftRequirementRows.value.length;

    return total > 0
        ? Math.round((selectedPftTermCompletedCount.value / total) * 100)
        : 0;
});

const pftRowsForTerm = (termId: string) =>
    pftRequirementRows.value.filter(
        (row) =>
            row.term.term_id === termId &&
            (props.physicalFitness.canFillUp || pftRowIsCompleted(row)),
    );

const pftRowForTest = (termId: string, testTypeId: number) =>
    pftRowsForTerm(termId).find((row) => row.testType.id === testTypeId);

const pftTermPendingCount = (termId: string) =>
    props.physicalFitness.canFillUp
        ? pftRowsForTerm(termId).filter((row) => !pftRowIsCompleted(row)).length
        : 0;

const pftTermSavedCount = (termId: string) =>
    pftRowsForTerm(termId).filter((row) => row.result).length;

const pftComponentTestCount = (component: PftComponent) =>
    component.categories.reduce(
        (total, category) => total + category.test_types.length,
        0,
    );

const pftComponentCompletedCount = (component: PftComponent) =>
    component.categories.reduce(
        (total, category) =>
            total +
            category.test_types.filter((testType) => {
                const row = selectedPftTermId.value
                    ? pftRowForTest(selectedPftTermId.value, testType.id)
                    : undefined;

                return pftRowIsCompleted(row);
            }).length,
        0,
    );

const hasPftDraftInput = computed(() => {
    return Object.values(pftForm.results).some((value) => {
        if (value === null || value === undefined) return false;
        if (typeof value === 'boolean') return value;
        return String(value).trim() !== '';
    });
});

const pftTestStatus = (row?: (typeof pftRequirementRows.value)[number]) => {
    if (pftRowIsCompleted(row)) return 'Completed';
    if (pftRowIsDraft(row)) return 'Draft';
    if (
        row &&
        selectedPftTermId.value === row.term.term_id &&
        selectedPftTestTypeId.value === row.testType.id &&
        hasPftDraftInput.value
    ) {
        return 'Draft';
    }

    return 'Not Started';
};

const pftResultValue = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) return value.length ? value.join(', ') : '-';
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
};

const selectPftRequirement = (
    row: (typeof pftRequirementRows.value)[number],
) => {
    selectedPftTermId.value = row.term.term_id;
    selectedPftComponentId.value = row.component.id;
    selectedPftCategoryId.value = row.category.id;
    selectedPftTestTypeId.value = row.testType.id;

    nextTick(() => {
        loadPftResult();
    });
};

const openPftTermDrawer = (term: PftTerm) => {
    if (!props.physicalFitness.canFillUp) {
        openPftTermSummary(term);

        return;
    }

    selectedPftTermId.value = term.term_id;
    const firstRow =
        pftRowsForTerm(term.term_id).find((row) => !pftRowIsCompleted(row)) ??
        pftRowsForTerm(term.term_id)[0];

    if (firstRow) {
        selectPftRequirement(firstRow);
    }

    nextTick(() => {
        pftDrawerOpen.value = true;
    });
};

const pftSummaryModalOpen = ref(false);
const pftTermSummaryModalOpen = ref(false);
const selectedPftSummaryRow = ref<PftRequirementRow | null>(null);
const selectedPftSummaryTermId = ref<string | null>(null);
const openPftSummaryComponentIds = ref<number[]>([]);

const selectedPftSummaryTerm = computed(() =>
    props.physicalFitness.terms.find(
        (term) => term.term_id === selectedPftSummaryTermId.value,
    ),
);

const pftTermSummaryRows = computed(() =>
    selectedPftSummaryTermId.value
        ? pftRowsForTerm(selectedPftSummaryTermId.value).filter(
              pftRowIsCompleted,
          )
        : [],
);

const pftTermSummaryGroups = computed(() => {
    const groups = new Map<
        number,
        { component: PftComponent; rows: PftRequirementRow[] }
    >();

    for (const row of pftTermSummaryRows.value) {
        if (!groups.has(row.component.id)) {
            groups.set(row.component.id, {
                component: row.component,
                rows: [],
            });
        }

        groups.get(row.component.id)?.rows.push(row);
    }

    return Array.from(groups.values());
});

const pftSummaryFields = computed(() => {
    const row = selectedPftSummaryRow.value;

    if (!row?.result) return [];

    return row.testType.configurations.map((field) => ({
        label: field.field_label,
        value: pftResultValue(row.result?.results_json[field.field_name]),
    }));
});

const pftPrimaryScore = (row: PftRequirementRow) => {
    const rawScore = row.result?.results_json.score;

    if (rawScore === null || rawScore === undefined || rawScore === '') {
        return null;
    }

    const score = Number(rawScore);

    return Number.isFinite(score) ? score : null;
};

const pftBmiInterpretation = (bmi: number) => {
    if (bmi < 18.5) return 'Underweight';
    if (bmi < 25) return 'Normal';
    if (bmi < 30) return 'Overweight';

    return 'Obese';
};

const pftResultInterpretation = (row?: PftRequirementRow | null) => {
    const result = row?.result;

    if (!result) return '-';

    const bmi = Number(result.results_json.bmi);

    if (row.testType.slug === 'bmi-test' && Number.isFinite(bmi)) {
        return `${pftBmiInterpretation(bmi)} BMI classification.`;
    }

    const remarks =
        String(result.remarks ?? '').trim() ||
        String(result.results_json.remarks ?? '').trim();

    if (remarks) return remarks;

    const score = pftPrimaryScore(row);

    if (score === null) {
        return 'Saved result is available for review.';
    }

    if (
        ['shuttle-run', '40-meter-sprint', 'stick-drop-test'].includes(
            row.testType.slug,
        )
    ) {
        return `Recorded score: ${score}. Lower time or distance is generally better for this test.`;
    }

    if (
        [
            'push-up-test',
            'curl-up-test',
            'standing-long-jump',
            'stork-balance-stand-test',
            'juggling-test',
            'zipper-test',
            'sit-and-reach-test',
            '3-minute-step-test',
        ].includes(row.testType.slug)
    ) {
        return `Recorded score: ${score}. Higher scores generally indicate stronger performance for this test.`;
    }

    return `Recorded score: ${score}.`;
};

const togglePftSummaryComponent = (componentId: number) => {
    openPftSummaryComponentIds.value =
        openPftSummaryComponentIds.value.includes(componentId)
            ? openPftSummaryComponentIds.value.filter(
                  (id) => id !== componentId,
              )
            : [...openPftSummaryComponentIds.value, componentId];
};

const pftAnalyticsRows = computed(() => pftRequirementRows.value);

const pftAnalyticsCompletedRows = computed(() =>
    pftAnalyticsRows.value.filter(pftRowIsCompleted),
);

const pftAnalyticsPendingRows = computed(() =>
    pftAnalyticsRows.value.filter((row) => !pftRowIsCompleted(row)),
);

const pftCompletionPercentForRows = (rows: PftRequirementRow[]) => {
    if (rows.length === 0) return 0;

    return Math.round(
        (rows.filter(pftRowIsCompleted).length / rows.length) * 100,
    );
};

const pftAnalyticsSummary = computed(() => {
    const total = pftAnalyticsRows.value.length;
    const completed = pftAnalyticsCompletedRows.value.length;
    const pending = pftAnalyticsPendingRows.value.length;
    const latestDate = pftAnalyticsCompletedRows.value
        .map(
            (row) =>
                row.result?.tested_at ??
                row.result?.results_json.date_tested ??
                row.result?.updated_at,
        )
        .filter(Boolean)
        .map((date) => String(date))
        .sort()
        .at(-1);
    const rowsForComponent = (componentName: string) =>
        pftAnalyticsRows.value.filter(
            (row) =>
                row.component.name.toLowerCase() ===
                componentName.toLowerCase(),
        );

    return {
        total,
        completed,
        pending,
        completionPercent:
            total > 0 ? Math.round((completed / total) * 100) : 0,
        healthCompletion: pftCompletionPercentForRows(
            rowsForComponent('Health Related'),
        ),
        skillCompletion: pftCompletionPercentForRows(
            rowsForComponent('Skill Related'),
        ),
        latestDate: latestDate ? formatDate(latestDate) : '-',
        overallStatus:
            total === 0
                ? 'No Tests'
                : pending === 0
                  ? 'Complete'
                  : `${pending} Pending`,
    };
});

const pftAnalyticsOverview = computed(() => {
    const overview = pftAnalyticsData.value?.overview;

    if (!overview) {
        return {
            academicYear: activePftTerm.value?.school_year ?? '-',
            semester: activePftTerm.value?.semester ?? '-',
            totalRequiredTests: pftAnalyticsSummary.value.total,
            completedTests: pftAnalyticsSummary.value.completed,
            pendingTests: pftAnalyticsSummary.value.pending,
            completionPercentage: pftAnalyticsSummary.value.completionPercent,
            healthRelatedCompletion: pftAnalyticsSummary.value.healthCompletion,
            skillRelatedCompletion: pftAnalyticsSummary.value.skillCompletion,
            latestTestDate: pftAnalyticsSummary.value.latestDate,
            overallFitnessStatus: pftAnalyticsSummary.value.overallStatus,
        };
    }

    return overview;
});

const pftAnalyticsSummaryCards = computed(() => [
    {
        label: 'Academic Year',
        value: pftAnalyticsOverview.value.academicYear,
        tone: 'text-slate-950 dark:text-white',
    },
    {
        label: 'Semester',
        value: pftAnalyticsOverview.value.semester,
        tone: 'text-slate-950 dark:text-white',
    },
    {
        label: 'Completed Tests',
        value: `${pftAnalyticsOverview.value.completedTests} / ${pftAnalyticsOverview.value.totalRequiredTests}`,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Pending Tests',
        value: pftAnalyticsOverview.value.pendingTests,
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        label: 'Completion %',
        value: `${pftAnalyticsOverview.value.completionPercentage}%`,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: 'Health Related Completion',
        value: `${pftAnalyticsOverview.value.healthRelatedCompletion}%`,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: 'Skill Related Completion',
        value: `${pftAnalyticsOverview.value.skillRelatedCompletion}%`,
        tone: 'text-violet-700 dark:text-violet-300',
    },
    {
        label: 'Latest Test Date',
        value: pftAnalyticsOverview.value.latestTestDate,
        tone: 'text-slate-950 dark:text-white',
    },
    {
        label: 'Overall Fitness Status',
        value: pftAnalyticsOverview.value.overallFitnessStatus,
        tone:
            pftAnalyticsOverview.value.pendingTests === 0
                ? 'text-emerald-700 dark:text-emerald-300'
                : 'text-amber-700 dark:text-amber-300',
    },
]);

const pftAnalyticsComponentStats = computed(() =>
    props.physicalFitness.components.map((component) => {
        const rows = pftAnalyticsRows.value.filter(
            (row) => row.component.id === component.id,
        );
        const completed = rows.filter(pftRowIsCompleted).length;

        return {
            name: component.name,
            total: rows.length,
            completed,
            percent: pftCompletionPercentForRows(rows),
        };
    }),
);

const pftAnalyticsCategoryStats = computed(() =>
    props.physicalFitness.components.flatMap((component) =>
        component.categories.map((category) => {
            const rows = pftAnalyticsRows.value.filter(
                (row) => row.category.id === category.id,
            );
            const completed = rows.filter(pftRowIsCompleted).length;

            return {
                name: `${component.name} - ${category.name}`,
                total: rows.length,
                completed,
                percent: pftCompletionPercentForRows(rows),
            };
        }),
    ),
);

const pftAnalyticsResultValue = (row: PftRequirementRow) => {
    if (!row.result) return '-';

    const preferredField =
        row.testType.configurations.find((field) =>
            ['score', 'bmi'].includes(field.field_name),
        ) ?? row.testType.configurations[0];

    if (!preferredField) return '-';

    return pftResultValue(row.result.results_json[preferredField.field_name]);
};

const pftAnalyticsRecentRows = computed(() =>
    pftAnalyticsRows.value
        .filter((row) => row.result)
        .map((row) => ({
            row,
            sortDate: String(
                row.result?.tested_at ??
                    row.result?.results_json.date_tested ??
                    row.result?.updated_at ??
                    '',
            ),
        }))
        .sort((a, b) => b.sortDate.localeCompare(a.sortDate))
        .slice(0, 12)
        .map(({ row }) => row),
);

const pftAnalyticsCompletionSeries = computed(() => [
    pftAnalyticsOverview.value.completedTests,
    pftAnalyticsOverview.value.pendingTests,
]);

const pftAnalyticsCompletionOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    labels: ['Completed', 'Pending'],
    colors: ['#059669', '#f59e0b'],
    legend: { position: 'bottom' as const },
    dataLabels: { enabled: true },
}));

const pftAnalyticsComponentSeries = computed(() => [
    {
        name: 'Completion %',
        data: pftAnalyticsComponentStats.value.map((item) => item.percent),
    },
]);

const pftAnalyticsComponentOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    colors: ['#059669'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: pftAnalyticsComponentStats.value.map((item) => item.name),
    },
    yaxis: { max: 100, labels: { formatter: (value: number) => `${value}%` } },
}));

const pftAnalyticsCategorySeries = computed(() => [
    {
        name: 'Completion %',
        data: pftAnalyticsCategoryStats.value.map((item) => item.percent),
    },
]);

const pftAnalyticsCategoryOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    colors: ['#0ea5e9'],
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: pftAnalyticsCategoryStats.value.map((item) => item.name),
        max: 100,
        labels: { formatter: (value: number) => `${value}%` },
    },
    yaxis: {
        labels: {
            minWidth: 140,
            maxWidth: 220,
        },
    },
}));

const pftAnalyticsComparisonItems = computed(
    () => pftAnalyticsData.value?.semesterComparison ?? [],
);

const selectedPftAnalyticsComparison = computed(
    () => pftAnalyticsComparisonItems.value[0] ?? null,
);

const pftAnalyticsComparisonSeries = computed(() => [
    {
        name: selectedPftAnalyticsComparison.value?.testType ?? 'Trend',
        data:
            selectedPftAnalyticsComparison.value?.series?.map(
                (item: Record<string, any>) => item.value,
            ) ?? [],
    },
]);

const pftAnalyticsComparisonOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    colors: ['#059669'],
    stroke: { curve: 'smooth' as const, width: 3 },
    markers: { size: 4 },
    dataLabels: { enabled: false },
    xaxis: {
        categories:
            selectedPftAnalyticsComparison.value?.series?.map(
                (item: Record<string, any>) => item.label,
            ) ?? [],
    },
}));

const pftAnalyticsRadarSeries = computed(() => [
    {
        name:
            pftAnalyticsData.value?.radarData?.currentLabel ??
            'Current Semester',
        data: pftAnalyticsData.value?.radarData?.current ?? [],
    },
    {
        name:
            pftAnalyticsData.value?.radarData?.previousLabel ??
            'Previous Semester',
        data: pftAnalyticsData.value?.radarData?.previous ?? [],
    },
]);

const pftAnalyticsRadarOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    colors: ['#059669', '#94a3b8'],
    dataLabels: { enabled: false },
    xaxis: { categories: pftAnalyticsData.value?.radarData?.labels ?? [] },
    yaxis: { min: 0, max: 100 },
}));

const pftAnalyticsTimeline = computed(
    () => pftAnalyticsData.value?.timeline ?? [],
);

const pftAnalyticsTimelineGroups = computed(() => {
    const groups = new Map<
        string,
        {
            key: string;
            academicYear: string;
            semester: string;
            rows: Record<string, any>[];
            components: {
                key: string;
                name: string;
                rows: Record<string, any>[];
            }[];
        }
    >();

    for (const row of pftAnalyticsTimeline.value) {
        const academicYear = row.academicYear ?? '-';
        const semester = row.semester ?? '-';
        const key = `${academicYear}:${semester}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                academicYear,
                semester,
                rows: [],
                components: [],
            });
        }

        const group = groups.get(key);
        group?.rows.push(row);

        if (group) {
            const componentName = row.component ?? '-';
            const componentKey = `${key}:${componentName}`;
            let componentGroup = group.components.find(
                (component) => component.key === componentKey,
            );

            if (!componentGroup) {
                componentGroup = {
                    key: componentKey,
                    name: componentName,
                    rows: [],
                };
                group.components.push(componentGroup);
            }

            componentGroup.rows.push(row);
        }
    }

    return Array.from(groups.values());
});

const togglePftAnalyticsTimelineGroup = (key: string) => {
    openPftAnalyticsTimelineGroups.value =
        openPftAnalyticsTimelineGroups.value.includes(key)
            ? openPftAnalyticsTimelineGroups.value.filter(
                  (item) => item !== key,
              )
            : [...openPftAnalyticsTimelineGroups.value, key];
};

const togglePftAnalyticsTimelineComponentGroup = (key: string) => {
    openPftAnalyticsTimelineComponentGroups.value =
        openPftAnalyticsTimelineComponentGroups.value.includes(key)
            ? openPftAnalyticsTimelineComponentGroups.value.filter(
                  (item) => item !== key,
              )
            : [...openPftAnalyticsTimelineComponentGroups.value, key];
};

const pftAnalyticsInsights = computed(
    () => pftAnalyticsData.value?.insights ?? [],
);

const pftAnalyticsFitnessIndex = computed(
    () =>
        pftAnalyticsData.value?.fitnessIndex ?? {
            score: 0,
            rating: 'Needs Improvement',
            weights: { healthRelated: 60, skillRelated: 40 },
        },
);

const loadPftAnalytics = async () => {
    pftAnalyticsLoading.value = true;
    pftAnalyticsError.value = null;

    try {
        const endpoint = new URL(
            pftRoutes.analytics.url(),
            window.location.origin,
        );
        const filters = {
            term_id: pftAnalyticsFilterTermId.value,
            component_id: pftAnalyticsFilterComponentId.value,
            category_id: pftAnalyticsFilterCategoryId.value,
            test_type_id: pftAnalyticsFilterTestTypeId.value,
            date_from: pftAnalyticsFilterDateFrom.value,
            date_to: pftAnalyticsFilterDateTo.value,
        };

        Object.entries(filters).forEach(([key, value]) => {
            if (value) endpoint.searchParams.set(key, value);
        });

        const response = await fetch(endpoint.toString(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Unable to load physical fitness analytics.');
        }

        pftAnalyticsData.value = await response.json();
        openPftAnalyticsTimelineGroups.value =
            pftAnalyticsTimelineGroups.value.map((group) => group.key);
        openPftAnalyticsTimelineComponentGroups.value =
            pftAnalyticsTimelineGroups.value.flatMap((group) =>
                group.components.map((component) => component.key),
            );
    } catch (error) {
        pftAnalyticsError.value =
            error instanceof Error
                ? error.message
                : 'Unable to load physical fitness analytics.';
    } finally {
        pftAnalyticsLoading.value = false;
    }
};

const openPftAnalyticsDrawer = async () => {
    pftAnalyticsDrawerOpen.value = true;
    pftAnalyticsChartsReady.value = false;
    pftAnalyticsChartKey.value += 1;
    await loadPftAnalytics();

    nextTick(() => {
        pftAnalyticsChartsReady.value = true;
    });
};

const closePftAnalyticsDrawer = () => {
    pftAnalyticsChartsReady.value = false;
    pftAnalyticsDrawerOpen.value = false;
};

const openPftSummary = (row?: PftRequirementRow) => {
    if (!row?.result) return;

    selectPftRequirement(row);
    selectedPftSummaryRow.value = row;
    pftSummaryModalOpen.value = true;
};

const openPftTermSummary = (term: PftTerm) => {
    selectedPftSummaryTermId.value = term.term_id;
    openPftSummaryComponentIds.value = Array.from(
        new Set(pftRowsForTerm(term.term_id).map((row) => row.component.id)),
    );
    pftTermSummaryModalOpen.value = true;
};

const pftForm = useForm<{
    term_id: string;
    results: Record<string, any>;
    is_draft: boolean;
}>({
    term_id: '',
    results: {},
    is_draft: false,
});

const loadPftResult = () => {
    pftForm.term_id = selectedPftTermId.value ?? '';
    const result = pftResultFor(selectedPftTestTypeId.value);
    pftForm.results = { ...(result?.results_json ?? {}) };
    pftForm.is_draft = false;
};

watch(selectedPftTestTypeId, loadPftResult, { immediate: true });
watch(selectedPftTermId, loadPftResult);

const interpretPftResults = (
    testType: PftTestType | null | undefined,
    results: Record<string, any>,
) => {
    for (const rule of testType?.interpretation_rules ?? []) {
        const rawValue = results[rule.field_name];
        const value = Number(rawValue);

        if (!Number.isFinite(value)) {
            continue;
        }

        if (rule.min_value !== null && value < Number(rule.min_value)) {
            continue;
        }

        if (rule.max_value !== null && value > Number(rule.max_value)) {
            continue;
        }

        return rule;
    }

    return null;
};

watch(
    () => [pftForm.results.height, pftForm.results.weight],
    ([newHeight, newWeight]) => {
        if (selectedPftTestType.value?.slug === 'bmi-test') {
            const h = parseFloat(String(newHeight || ''));
            const w = parseFloat(String(newWeight || ''));
            if (h > 0 && w > 0) {
                const heightInMeters = h / 100;
                const bmiVal = parseFloat(
                    (w / (heightInMeters * heightInMeters)).toFixed(2),
                );
                pftForm.results.bmi = bmiVal;
                pftForm.results.remarks =
                    interpretPftResults(selectedPftTestType.value, {
                        ...pftForm.results,
                        bmi: bmiVal,
                    })?.label ?? '';
            } else {
                pftForm.results.bmi = '';
                pftForm.results.remarks = '';
            }
        }
    },
    { deep: true },
);

const submitPftResult = (isDraft = false) => {
    if (
        !props.physicalFitness.canFillUp ||
        !selectedPftTestType.value ||
        !selectedPftTermId.value
    ) {
        return;
    }

    pftForm.term_id = selectedPftTermId.value;
    pftForm.is_draft = isDraft;
    pftForm.post(pftRoutes.store.url(selectedPftTestType.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pftForm.is_draft = false;
        },
        onError: (errors) => {
            pftForm.is_draft = false;
            const firstError = Object.values(errors)[0];
            toast.error(
                typeof firstError === 'string'
                    ? firstError
                    : 'Unable to save the physical fitness result.',
            );
        },
    });
};

const achievementModalOpen = ref(false);
const editingAchievement = ref<Achievement | null>(null);
const achievementForm = useForm({
    title: '',
    date_received: '',
    awarder: '',
    description: '',
});

const openAchievementModal = (achievement: Achievement | null = null) => {
    editingAchievement.value = achievement;
    if (achievement) {
        achievementForm.title = achievement.title;
        achievementForm.date_received = achievement.date_received;
        achievementForm.awarder = achievement.awarder || '';
        achievementForm.description = achievement.description || '';
    } else {
        achievementForm.reset();
    }
    achievementModalOpen.value = true;
};

const submitAchievement = () => {
    if (editingAchievement.value) {
        achievementForm.patch(
            achievementsRoutes.update.url(editingAchievement.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    achievementModalOpen.value = false;
                    achievementForm.reset();
                },
            },
        );
    } else {
        achievementForm.post(achievementsRoutes.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                achievementModalOpen.value = false;
                achievementForm.reset();
            },
        });
    }
};

const trainingModalOpen = ref(false);
const editingTraining = ref<Training | null>(null);
const trainingForm = useForm({
    title: '',
    date_from: '',
    date_to: '',
    venue: '',
    organizer: '',
});

const openTrainingModal = (training: Training | null = null) => {
    editingTraining.value = training;
    if (training) {
        trainingForm.title = training.title;
        trainingForm.date_from = training.date_from;
        trainingForm.date_to = training.date_to || '';
        trainingForm.venue = training.venue || '';
        trainingForm.organizer = training.organizer || '';
    } else {
        trainingForm.reset();
    }
    trainingModalOpen.value = true;
};

const submitTraining = () => {
    if (editingTraining.value) {
        trainingForm.patch(
            trainingsRoutes.update.url(editingTraining.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    trainingModalOpen.value = false;
                    trainingForm.reset();
                },
            },
        );
    } else {
        trainingForm.post(trainingsRoutes.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                trainingModalOpen.value = false;
                trainingForm.reset();
            },
        });
    }
};

const deleteConfirmOpen = ref(false);
const itemToDelete = ref<{
    id: number;
    type: 'achievement' | 'training';
    title: string;
} | null>(null);

const categoryDetailsModalOpen = ref(false);
const selectedPftCategoryDetails = ref<PftCategory | null>(null);

const openCategoryDetailsModal = (category: PftCategory) => {
    selectedPftCategoryDetails.value = category;
    categoryDetailsModalOpen.value = true;
};

const confirmDelete = (
    id: number,
    type: 'achievement' | 'training',
    title: string,
) => {
    itemToDelete.value = { id, type, title };
    deleteConfirmOpen.value = true;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;

    if (itemToDelete.value.type === 'achievement') {
        achievementForm.delete(
            achievementsRoutes.deleteMethod.url(itemToDelete.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    deleteConfirmOpen.value = false;
                    itemToDelete.value = null;
                },
            },
        );
    } else {
        trainingForm.delete(
            trainingsRoutes.deleteMethod.url(itemToDelete.value.id),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    deleteConfirmOpen.value = false;
                    itemToDelete.value = null;
                },
            },
        );
    }
};

const editMode = ref(false);
const wizardSteps = [
    'basic',
    'address',
    'guardian',
    'emergency',
    'family',
    'education',
    'ched',
] as const;
const currentStep = ref(0);
const lastSavedAt = ref('');
const provinces = ref<string[]>([]);
const cities = ref<string[]>([]);
const barangays = ref<string[]>([]);
const resCities = ref<string[]>([]);
const resBarangays = ref<string[]>([]);
const incomeRanges = [
    { value: '0-9520', label: '0-9,520 (Poor)' },
    { value: '9520-19040', label: '9,520-19,040 (Low Income)' },
    { value: '19040-38080', label: '19,040-38,080 (Lower Middle Income)' },
    { value: '38080-66640', label: '38,080-66,640 (Middle Income)' },
    { value: '66640-114240', label: '66,640-114,240 (Upper Middle Income)' },
    { value: '114240-190400', label: '114,240-190,400 (Upper Income)' },
    { value: '190400-9999999', label: 'At least PHP 190,400 (Rich)' },
];
const fatherIncomeRange = computed({
    get: () =>
        `${profileForm.fatherIncomeFrom ?? ''}-${profileForm.fatherIncomeTo ?? ''}`,
    set: (v: string) => {
        const [from, to] = String(v || '').split('-');
        profileForm.fatherIncomeFrom = from ?? '';
        profileForm.fatherIncomeTo = to ?? '';
    },
});
const motherIncomeRange = computed({
    get: () =>
        `${profileForm.motherIncomeFrom ?? ''}-${profileForm.motherIncomeTo ?? ''}`,
    set: (v: string) => {
        const [from, to] = String(v || '').split('-');
        profileForm.motherIncomeFrom = from ?? '';
        profileForm.motherIncomeTo = to ?? '';
    },
});

const profileForm = useForm({
    gender: String(props.profile.data?.gender ?? '')
        .trim()
        .toUpperCase(),
    height: props.profile.data?.height ?? '',
    weight: props.profile.data?.weight ?? '',
    bloodType: props.profile.data?.bloodType ?? '',
    placeOfBirth: props.profile.data?.placeOfBirth ?? '',
    mobileNo: props.profile.data?.mobileNo ?? '',
    telNo: props.profile.data?.telNo ?? '',
    permProvince: props.profile.data?.permProvince ?? '',
    permTownCity: props.profile.data?.permTownCity ?? '',
    permBarangay: props.profile.data?.permBarangay ?? '',
    permAddress: props.profile.data?.permAddress ?? '',
    permStreet: props.profile.data?.permStreet ?? '',
    resProvince: props.profile.data?.resProvince ?? '',
    resTownCity: props.profile.data?.resTownCity ?? '',
    resBarangay: props.profile.data?.resBarangay ?? '',
    resAddress: props.profile.data?.resAddress ?? '',
    resStreet: props.profile.data?.resStreet ?? '',
    civilStatusId: props.profile.data?.civilStatusId ?? '',
    religionId: props.profile.data?.religionId ?? '',
    nationalityId: props.profile.data?.nationalityId ?? '',
    tribeId: props.profile.data?.tribeId ?? '',
    permZipCode: props.profile.data?.permZipCode ?? '',
    resZipCode: props.profile.data?.resZipCode ?? '',
    guardian: props.profile.data?.guardian ?? '',
    guardianAddress: props.profile.data?.guardianAddress ?? '',
    guardianTelNo: props.profile.data?.guardianTelNo ?? '',
    guardianEmail: props.profile.data?.guardianEmail ?? '',
    emergencyContact: props.profile.data?.emergencyContact ?? '',
    emergencyAddress: props.profile.data?.emergencyAddress ?? '',
    emergencyMobileNo: props.profile.data?.emergencyMobileNo ?? '',
    emergencyTelNo: props.profile.data?.emergencyTelNo ?? '',
    father: props.profile.data?.father ?? '',
    fatherOccupation: props.profile.data?.fatherOccupation ?? '',
    fatherCompany: props.profile.data?.fatherCompany ?? '',
    fatherCompanyAddress: props.profile.data?.fatherCompanyAddress ?? '',
    fatherTelNo: props.profile.data?.fatherTelNo ?? '',
    fatherEmail: props.profile.data?.fatherEmail ?? '',
    fatherBirthDate: props.profile.data?.fatherBirthDate ?? '',
    fatherEducAttain: props.profile.data?.fatherEducAttain ?? '',
    fatherIncomeFrom: props.profile.data?.fatherIncomeFrom ?? '',
    fatherIncomeTo: props.profile.data?.fatherIncomeTo ?? '',
    fatherCitizenship: props.profile.data?.fatherCitizenship ?? '',
    fatherNatureOfWork: props.profile.data?.fatherNatureOfWork ?? '',
    fatherEducationalAttainment:
        props.profile.data?.fatherEducationalAttainment ?? '',
    fatherEmploymentStatus: props.profile.data?.fatherEmploymentStatus ?? '',
    mother: props.profile.data?.mother ?? '',
    motherOccupation: props.profile.data?.motherOccupation ?? '',
    motherCompany: props.profile.data?.motherCompany ?? '',
    motherCompanyAddress: props.profile.data?.motherCompanyAddress ?? '',
    motherTelNo: props.profile.data?.motherTelNo ?? '',
    motherEmail: props.profile.data?.motherEmail ?? '',
    motherBirthDate: props.profile.data?.motherBirthDate ?? '',
    motherEducAttain: props.profile.data?.motherEducAttain ?? '',
    motherIncomeFrom: props.profile.data?.motherIncomeFrom ?? '',
    motherIncomeTo: props.profile.data?.motherIncomeTo ?? '',
    motherCitizenship: props.profile.data?.motherCitizenship ?? '',
    motherNatureOfWork: props.profile.data?.motherNatureOfWork ?? '',
    motherEducationalAttainment:
        props.profile.data?.motherEducationalAttainment ?? '',
    motherEmploymentStatus: props.profile.data?.motherEmploymentStatus ?? '',
    noofBrothers: props.profile.data?.noofBrothers ?? '',
    noofSisters: props.profile.data?.noofSisters ?? '',
    isIllegitimate: props.profile.data?.isIllegitimate ?? false,
    elemSchool: props.profile.data?.elemSchool ?? '',
    elemAddress: props.profile.data?.elemAddress ?? '',
    elemInclDates: props.profile.data?.elemInclDates ?? '',
    elemAwardHonor: props.profile.data?.elemAwardHonor ?? '',
    hsSchool: props.profile.data?.hsSchool ?? '',
    hsAddress: props.profile.data?.hsAddress ?? '',
    hsInclDates: props.profile.data?.hsInclDates ?? '',
    hsAwardHonor: props.profile.data?.hsAwardHonor ?? '',
    vocational: props.profile.data?.vocational ?? '',
    vocationalAddress: props.profile.data?.vocationalAddress ?? '',
    vocationalInclDates: props.profile.data?.vocationalInclDates ?? '',
    collegeSchool: props.profile.data?.collegeSchool ?? '',
    collegeDegree: props.profile.data?.collegeDegree ?? '',
    collegeAddress: props.profile.data?.collegeAddress ?? '',
    collegeInclDates: props.profile.data?.collegeInclDates ?? '',
    shsTrack: props.profile.data?.shsTrack ?? '',
    shsSchool: props.profile.data?.shsSchool ?? '',
    shsIncldates: props.profile.data?.shsIncldates ?? '',
    shsAwardsHonors: props.profile.data?.shsAwardsHonors ?? '',
    lastSchoolAttendedType: props.profile.data?.lastSchoolAttendedType ?? '',
    studentType: props.profile.data?.studentType ?? '',
    studentCategory: props.profile.data?.studentCategory ?? '',
    firstGenerationStudent: props.profile.data?.firstGenerationStudent ?? '',
    isGida: props.profile.data?.isGida ?? '',
    descGida: props.profile.data?.descGida ?? '',
    isBelongToFarmer: props.profile.data?.isBelongToFarmer ?? '',
    isRebelReturnee: props.profile.data?.isRebelReturnee ?? '',
    familySize: props.profile.data?.familySize ?? '',
    ipMember: props.profile.data?.ipMember ?? false,
    ipMemberTribe: props.profile.data?.ipMemberTribe ?? '',
    pwdMember: props.profile.data?.pwdMember ?? false,
    pwdMemberId: props.profile.data?.pwdMemberId ?? '',
    pwdCategory: props.profile.data?.pwdCategory ?? '',
    soloParent: props.profile.data?.soloParent ?? false,
    soloParentId: props.profile.data?.soloParentId ?? '',
    raisedBySoloParent: props.profile.data?.raisedBySoloParent ?? '',
    isAdm: props.profile.data?.isAdm ?? '',
    admSchool: props.profile.data?.admSchool ?? '',
    admSchoolYear: props.profile.data?.admSchoolYear ?? '',
    isAls: props.profile.data?.isAls ?? '',
    alsSchool: props.profile.data?.alsSchool ?? '',
    alsSchoolYear: props.profile.data?.alsSchoolYear ?? '',
});

// --- Auto-save Draft Logic ---
const DRAFT_KEY = `student_profile_draft_${props.profile.data?.studentNo}`;
const saveDraft = () => {
    localStorage.setItem(DRAFT_KEY, JSON.stringify(profileForm.data()));
};
const loadDraft = () => {
    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
        const data = JSON.parse(draft);
        Object.assign(profileForm, data);
        toast.info('Restored your unsaved changes.');
    }
};
const clearDraft = () => localStorage.removeItem(DRAFT_KEY);

watch(profileForm, saveDraft, { deep: true });
// -----------------------------

const loadRefs = async () => {
    const [prov, city, brgy] = await Promise.all([
        fetch('/assets/refprovince.json').then((r) => r.json()),
        fetch('/assets/refcitymun.json').then((r) => r.json()),
        fetch('/assets/refbrgy.json').then((r) => r.json()),
    ]);

    provinces.value = (prov.RECORDS ?? []).map((x: any) => x.provDesc);
    const cityRows = city.RECORDS ?? [];
    const brgyRows = brgy.RECORDS ?? [];

    const allCities = cityRows.map((x: any) => x.citymunDesc);
    const bindPerm = () => {
        // Legacy behavior: municipality list comes from all city/mun records,
        // then barangay is filtered by selected municipality.
        cities.value = allCities;
        barangays.value = brgyRows
            .filter((x: any) => x.citymunDesc === profileForm.permTownCity)
            .map((x: any) => x.brgyDesc);
    };
    const bindRes = () => {
        resCities.value = allCities;
        resBarangays.value = brgyRows
            .filter((x: any) => x.citymunDesc === profileForm.resTownCity)
            .map((x: any) => x.brgyDesc);
    };

    bindPerm();
    bindRes();

    watch(
        () => profileForm.permProvince,
        () => {
            profileForm.permTownCity = '';
            profileForm.permBarangay = '';
            bindPerm();
        },
    );
    watch(
        () => profileForm.permTownCity,
        () => {
            profileForm.permBarangay = '';
            bindPerm();
        },
    );
    watch(
        () => profileForm.resProvince,
        () => {
            if (sameAsPermanentAddress.value) {
                bindRes();
                return;
            }
            profileForm.resTownCity = '';
            profileForm.resBarangay = '';
            bindRes();
        },
    );
    watch(
        () => profileForm.resTownCity,
        () => {
            if (sameAsPermanentAddress.value) {
                bindRes();
                return;
            }
            profileForm.resBarangay = '';
            bindRes();
        },
    );
};

const formatMobile = (
    field:
        | 'mobileNo'
        | 'telNo'
        | 'guardianTelNo'
        | 'emergencyMobileNo'
        | 'emergencyTelNo'
        | 'fatherTelNo'
        | 'motherTelNo',
) => {
    let val = profileForm[field].replace(/\D/g, '');
    if (val.length > 11) val = val.substring(0, 11);

    if (val.startsWith('09') && val.length > 2) {
        if (val.length <= 6) val = val.replace(/(\d{4})(\d+)/, '$1-$2');
        else val = val.replace(/(\d{4})(\d{3})(\d+)/, '$1-$2-$3');
    }
    profileForm[field] = val;
};

const sameAsPermanentAddress = ref(false);
const syncAddresses = () => {
    if (!sameAsPermanentAddress.value) return;
    profileForm.resAddress = profileForm.permAddress;
    profileForm.resStreet = profileForm.permStreet;
    profileForm.resZipCode = profileForm.permZipCode;
    profileForm.resProvince = profileForm.permProvince;
    profileForm.resTownCity = profileForm.permTownCity;
    profileForm.resBarangay = profileForm.permBarangay;
};

watch(sameAsPermanentAddress, (val) => {
    if (val) {
        syncAddresses();
    }
});
watch(() => profileForm.permAddress, syncAddresses);
watch(() => profileForm.permStreet, syncAddresses);
watch(() => profileForm.permZipCode, syncAddresses);
watch(() => profileForm.permProvince, syncAddresses);
watch(() => profileForm.permTownCity, syncAddresses);
watch(() => profileForm.permBarangay, syncAddresses);

const saveProfile = () => {
    profileForm.gender = String(profileForm.gender ?? '')
        .trim()
        .toUpperCase();
    profileForm.patch('/student-profile', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editMode.value = false;
            clearDraft();
            lastSavedAt.value = new Date().toLocaleString();
        },
        onError: (errors) => {
            toast.error((errors as any)?.gender ?? 'Unable to save profile.');
        },
    });
};

const requiredFields = [
    'gender',
    'height',
    'weight',
    'bloodType',
    'placeOfBirth',
    'mobileNo',
    'permAddress',
    'permStreet',
    'permZipCode',
    'permProvince',
    'permTownCity',
    'permBarangay',
    'resAddress',
    'resStreet',
    'resZipCode',
    'resProvince',
    'resTownCity',
    'resBarangay',
    'guardian',
    'guardianAddress',
    'guardianTelNo',
    'emergencyContact',
    'emergencyAddress',
    'emergencyMobileNo',
] as const;

const completeness = computed(() => {
    const total = requiredFields.length;
    const done = requiredFields.filter((k) => {
        const v = (profileForm as any)[k];
        return v !== null && v !== undefined && String(v).trim() !== '';
    }).length;
    return Math.round((done / total) * 100);
});

const stepTitle = computed(() => {
    const titles = [
        'Basic Details',
        'Address',
        'Guardian',
        'Emergency Contact',
        'Family Background',
        'Educational Background',
        'Additional Details for CHED',
    ];
    return titles[currentStep.value] ?? 'Profile Wizard';
});

const nextStep = () => {
    if (currentStep.value < wizardSteps.length - 1) {
        currentStep.value += 1;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
const previousStep = () => {
    if (currentStep.value > 0) {
        currentStep.value -= 1;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
// CCD Cares States & Methods
const activeEvaluationPeriod = ref<any>(null);
const evaluationDrawerOpen = ref(false);
const resultsModalOpen = ref(false);
const selectedAssessment = ref<any>(null);

const evaluationForm = useForm({
    period_id: null as number | null,
    answers: {} as Record<number, any>,
});

const startEvaluation = (assessment: any) => {
    activeEvaluationPeriod.value = assessment;
    evaluationForm.period_id = assessment.period.id;
    
    // Initialize answers: arrays for checkboxes, nulls for everything else
    const initialAnswers: Record<number, any> = {};
    assessment.template.categories.forEach((cat: any) => {
        cat.statements.forEach((stmt: any) => {
            if (stmt.statement_type === 'checkbox') {
                initialAnswers[stmt.id] = [];
            } else {
                initialAnswers[stmt.id] = null;
            }
        });
    });
    evaluationForm.answers = initialAnswers;
    evaluationForm.clearErrors();
    evaluationDrawerOpen.value = true;
};

const submitEvaluation = () => {
    evaluationForm.post(ccdCaresEvaluationRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            evaluationDrawerOpen.value = false;
            toast.success('Your evaluation has been submitted successfully.');
        },
        onError: (errors) => {
            const firstError = Object.keys(errors)[0];
            if (firstError) {
                toast.error(errors[firstError] || 'Please complete all required fields.');
            } else {
                toast.error('Unable to submit evaluation. Please check your inputs.');
            }
        },
    });
};

const viewResults = (assessment: any) => {
    selectedAssessment.value = assessment;
    resultsModalOpen.value = true;
};

const ccdCaresChartSeries = computed(() => {
    if (!selectedAssessment.value?.submission?.interpretation_results) return [];
    return [
        {
            name: 'Your Score',
            data: selectedAssessment.value.submission.interpretation_results.map(
                (res: any) => res.score
            ),
        },
    ];
});

const ccdCaresChartOptions = computed(() => {
    const categories = selectedAssessment.value?.submission?.interpretation_results?.map(
        (res: any) => res.category_name
    ) ?? [];
    
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '50%',
                distributed: true,
            },
        },
        colors: selectedAssessment.value?.submission?.interpretation_results?.map((res: any) => {
            const val = String(res.interpretation || '').toLowerCase().trim();
            if (val.includes('extremely severe') || val.includes('extremely_severe')) return '#dc2626';
            if (val.includes('severe')) return '#ea580c';
            if (val.includes('moderate')) return '#d97706';
            if (val.includes('mild')) return '#0284c7';
            return '#059669';
        }) ?? ['#059669', '#0284c7', '#d97706'],
        xaxis: {
            categories: categories,
            labels: {
                style: {
                    fontSize: '11px',
                    fontWeight: 600,
                },
            },
        },
        yaxis: {
            max: 42,
            tickAmount: 6,
            labels: {
                formatter: (val: number) => Math.round(val),
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val: number) => Math.round(val),
            style: {
                fontSize: '11px',
                fontWeight: 'bold',
            },
        },
        legend: {
            show: false,
        },
        grid: {
            borderColor: 'rgba(156, 163, 175, 0.1)',
        },
    };
});

const getInterpretationColorClass = (interpretation: string) => {
    const val = String(interpretation || '').toLowerCase().trim();
    if (val.includes('extremely severe')) return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20';
    if (val.includes('extremely_severe')) return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20';
    if (val.includes('severe')) return 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:border-orange-500/20';
    if (val.includes('moderate')) return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20';
    if (val.includes('mild')) return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/20';
    return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20';
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const hasCcdParam = urlParams.get('ccd') === '1';

    const hashTab = window.location.hash.replace('#', '');
    const savedTab = window.localStorage.getItem(PROFILE_TAB_KEY);

    if (hasCcdParam && isValidTab('ccd-cares')) {
        activeTab.value = 'ccd-cares';
        window.history.replaceState(null, '', `#ccd-cares`);
    } else if (isValidTab(hashTab)) {
        activeTab.value = hashTab;
    } else if (isValidTab(savedTab)) {
        activeTab.value = savedTab as string;
        window.history.replaceState(null, '', `#${savedTab}`);
    }

    loadRefs();
    if (editMode.value) loadDraft();
});
watch(tabs, () => {
    if (!isValidTab(activeTab.value)) {
        setActiveTab('personal');
    }
});
watch(activeTab, (tab) => {
    if (typeof window !== 'undefined' && isValidTab(tab)) {
        window.localStorage.setItem(PROFILE_TAB_KEY, tab);
        window.history.replaceState(null, '', `#${tab}`);
    }
});
watch(editMode, (val) => {
    if (val) loadDraft();
});
</script>

<template>
    <Head title="My Profile" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <div
            v-if="profile.error"
            class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200"
        >
            <AlertCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ profile.error }}</span>
        </div>

        <Transition name="fade-transform" mode="out-in">
            <!-- View Mode -->
            <div
                v-if="profile.data && !editMode"
                key="view-mode"
                class="flex flex-col gap-4"
            >
                <section class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-center md:justify-between dark:border-white/10">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-sm font-bold text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <img
                                v-if="studentPictureUrl"
                                :src="studentPictureUrl"
                                alt="Student Picture"
                                class="size-full object-cover"
                            />
                            <span v-else>{{
                                getInitials(profile.data.firstName)
                            }}</span>
                        </div>
                        <div class="min-w-0">
                            <h1
                                class="truncate text-lg font-bold text-slate-950 dark:text-white"
                            >
                                {{ fullName }}
                            </h1>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-3 text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                <span
                                    class="inline-flex items-center gap-1"
                                >
                                    <IdCard class="size-3.5" />
                                    {{ profile.data.studentNo }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1"
                                >
                                    <School class="size-3.5" />
                                    {{ profile.data.statusRemarks || '-' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                        @click="editMode = true"
                    >
                        <Edit class="size-4" />
                        Edit Profile
                    </button>
                </section>

                <div class="grid gap-6 xl:grid-cols-[200px_1fr]">
                    <aside class="flex flex-col gap-0.5 xl:border-r xl:border-slate-100 xl:pr-4 xl:dark:border-white/10">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-bold transition"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20 dark:bg-emerald-500 dark:text-emerald-950'
                                    : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300'
                            "
                            @click="setActiveTab(tab.id)"
                        >
                            <component :is="tab.icon" class="size-4" />
                            {{ tab.label }}
                        </button>
                    </aside>

                    <section class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <div
                            class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                        >
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        tabs.find((tab) => tab.id === activeTab)
                                            ?.label
                                    }}
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ activeTabDescription }}
                                </p>
                                <p
                                    v-if="activeTab === 'documents'"
                                    class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                >
                                    The viewing of documents is available only
                                    to students enrolled from AY 2024-2025
                                    onwards.
                                </p>
                            </div>

                            <button
                                v-if="activeTab === 'achievements'"
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="openAchievementModal()"
                            >
                                <Plus class="size-4" />
                                Add
                            </button>
                            <button
                                v-if="activeTab === 'trainings'"
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="openTrainingModal()"
                            >
                                <Plus class="size-4" />
                                Add
                            </button>
                        </div>

                        <div class="p-4">
                            <!-- Personal Tab -->
                            <div
                                v-if="activeTab === 'personal'"
                                class="space-y-6"
                            >
                                <!-- Basic Info -->
                                <div>
                                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Basic Information</div>
                                    <dl class="divide-y divide-slate-100 dark:divide-white/10">
                                        <div
                                            v-for="field in personalFields"
                                            :key="field.label"
                                            class="grid grid-cols-[160px_1fr] gap-3 py-2.5 text-xs"
                                        >
                                            <dt class="font-medium text-slate-500 dark:text-slate-400">
                                                {{ field.label }}
                                            </dt>
                                            <dd class="font-semibold text-slate-900 dark:text-white">
                                                {{ field.value }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Contact -->
                                <div>
                                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Contact</div>
                                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                                        <div class="flex items-center gap-3 py-2.5 text-xs">
                                            <Mail class="size-4 shrink-0 text-slate-400" />
                                            <span class="font-medium text-slate-500 dark:text-slate-400 w-28">Email</span>
                                            <span class="truncate font-semibold text-slate-900 dark:text-white">{{ profile.data.email || '-' }}</span>
                                        </div>
                                        <div class="flex items-center gap-3 py-2.5 text-xs">
                                            <Phone class="size-4 shrink-0 text-slate-400" />
                                            <span class="font-medium text-slate-500 dark:text-slate-400 w-28">Mobile</span>
                                            <span class="truncate font-semibold text-slate-900 dark:text-white">{{ profile.data.mobileNo || '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div>
                                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Address</div>
                                    <div class="grid gap-4 text-xs sm:grid-cols-2">
                                        <div>
                                            <div class="mb-1 flex items-center gap-1.5 font-medium text-slate-400 dark:text-slate-500">
                                                <Home class="size-3.5" />
                                                Residential
                                            </div>
                                            <p class="font-semibold text-slate-900 dark:text-white leading-relaxed">
                                                {{ profile.data.resAddress }}
                                                {{ profile.data.resStreet }}
                                                {{ profile.data.resBarangay }}
                                            </p>
                                            <p class="mt-0.5 text-slate-500 dark:text-slate-400">
                                                {{ profile.data.resTownCity }}
                                                {{ profile.data.resProvince }}
                                            </p>
                                        </div>
                                        <div>
                                            <div class="mb-1 flex items-center gap-1.5 font-medium text-slate-400 dark:text-slate-500">
                                                <Home class="size-3.5" />
                                                Permanent
                                            </div>
                                            <p class="font-semibold text-slate-900 dark:text-white leading-relaxed">
                                                {{ profile.data.permAddress || '-' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Tab -->
                            <div
                                v-if="activeTab === 'academic'"
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <div
                                    v-for="field in academicFields"
                                    :key="field.label"
                                    class="grid grid-cols-[160px_1fr] gap-3 py-2.5 text-xs"
                                >
                                    <dt class="font-medium text-slate-500 dark:text-slate-400">
                                        {{ field.label }}
                                    </dt>
                                    <dd class="font-semibold text-slate-900 dark:text-white">
                                        {{ field.value }}
                                    </dd>
                                </div>
                            </div>

                            <!-- Family Tab -->
                            <div
                                v-if="activeTab === 'family'"
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <div
                                    v-for="field in familyFields"
                                    :key="field.label"
                                    class="grid grid-cols-[160px_1fr] gap-3 py-2.5 text-xs"
                                >
                                    <dt class="font-medium text-slate-500 dark:text-slate-400">
                                        {{ field.label }}
                                    </dt>
                                    <dd class="font-semibold text-slate-900 dark:text-white">
                                        {{ field.value }}
                                    </dd>
                                </div>
                            </div>

                            <!-- Education Tab -->
                            <div
                                v-if="activeTab === 'education'"
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <div
                                    v-for="field in educationFields"
                                    :key="field.label"
                                    class="flex items-start gap-3 py-3 text-xs"
                                >
                                    <BookOpen
                                        class="mt-0.5 size-4 shrink-0 text-slate-400"
                                    />
                                    <div class="min-w-0">
                                        <div
                                            class="font-medium text-slate-400 uppercase dark:text-slate-500 text-[11px] tracking-wider"
                                        >
                                            {{ field.label }}
                                        </div>
                                        <div
                                            class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ field.value }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'documents'">
                                <div
                                    v-if="ceeDocuments.error"
                                    class="mb-3 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100"
                                >
                                    <AlertCircle
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    <span>{{ ceeDocuments.error }}</span>
                                </div>

                                <div
                                    v-if="ceeDocumentCount === 0"
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <FileText class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No CEE uploaded files found
                                    </p>
                                    <p
                                        class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Uploaded admission requirements from the
                                        CEE portal will appear here once matched
                                        by student number and campus.
                                    </p>
                                </div>

                                <div v-else class="space-y-6">
                                    <section
                                        v-for="group in groupedCeeDocuments"
                                        :key="group.key"
                                    >
                                        <div class="mb-3 flex min-w-0 items-center gap-2">
                                            <FileText class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            <div class="min-w-0">
                                                <h3
                                                    class="truncate text-sm font-bold text-slate-900 dark:text-white"
                                                >
                                                    {{ group.label }}
                                                </h3>
                                                <p
                                                    class="text-xs font-medium text-slate-400 dark:text-slate-500"
                                                >
                                                    {{
                                                        group.documents.length
                                                    }}
                                                    file{{
                                                        group.documents.length === 1
                                                            ? ''
                                                            : 's'
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                        >
                                            <div
                                                v-for="document in group.documents"
                                                :key="document.key"
                                                class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                                            >
                                                <div
                                                    class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-slate-50 dark:bg-white/5"
                                                >
                                                    <img
                                                        v-if="
                                                            document.exists &&
                                                            isImageDocument(
                                                                document,
                                                            )
                                                        "
                                                        :src="document.url"
                                                        :alt="document.label"
                                                        class="size-full object-contain"
                                                        loading="lazy"
                                                    />
                                                    <iframe
                                                        v-else-if="
                                                            document.exists &&
                                                            isPdfDocument(
                                                                document,
                                                            )
                                                        "
                                                        :src="document.url"
                                                        :title="document.label"
                                                        class="size-full border-0"
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex flex-col items-center gap-2 p-6 text-center"
                                                    >
                                                        <FileText
                                                            class="size-10 text-slate-300"
                                                        />
                                                        <p
                                                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                document.exists
                                                                    ? 'Preview is not available for this file type.'
                                                                    : 'File not found in linked storage.'
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div
                                                    class="grid grid-cols-2 gap-2 p-3"
                                                >
                                                    <a
                                                        :href="document.url"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-50 px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                                    >
                                                        <ExternalLink
                                                            class="size-4"
                                                        />
                                                        View
                                                    </a>
                                                    <a
                                                        :href="document.url"
                                                        :download="
                                                            document.name
                                                        "
                                                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-sky-50 px-3 text-xs font-bold text-sky-700 transition hover:bg-sky-100 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                                                    >
                                                        <Download
                                                            class="size-4"
                                                        />
                                                        Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>

                            <div v-if="activeTab === 'achievements'">
                                <div
                                    v-if="achievements.length === 0"
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <Trophy class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No awards added yet
                                    </p>
                                </div>

                                <div v-else class="space-y-3 sm:space-y-0">
                                    <div class="grid gap-3 sm:hidden">
                                        <div
                                            v-for="item in achievements"
                                            :key="item.id"
                                            class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-slate-950"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-sm font-bold break-words text-slate-900 dark:text-white"
                                                    >
                                                        {{ item.title }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            item.awarder ||
                                                            'No awarder set'
                                                        }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex shrink-0 items-center gap-1"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                        @click="
                                                            openAchievementModal(
                                                                item,
                                                            )
                                                        "
                                                    >
                                                        <Edit class="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                        @click="
                                                            confirmDelete(
                                                                item.id,
                                                                'achievement',
                                                                item.title,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                    </button>
                                                </div>
                                            </div>
                                            <div
                                                class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-xs dark:border-white/10"
                                            >
                                                <div>
                                                    <p
                                                        class="font-bold text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Date
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatDate(
                                                                item.date_received,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="font-bold text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Notes
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold break-words text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            item.description ||
                                                            '-'
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="hidden overflow-x-auto rounded-lg border border-slate-200 sm:block dark:border-white/10"
                                    >
                                        <table
                                            class="w-full min-w-[720px] text-sm"
                                        >
                                            <thead
                                                class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                            >
                                                <tr>
                                                    <th class="px-4 py-3">
                                                        Title
                                                    </th>
                                                    <th class="px-4 py-3">
                                                        Awarder
                                                    </th>
                                                    <th class="px-4 py-3">
                                                        Date
                                                    </th>
                                                    <th
                                                        class="px-4 py-3 text-right"
                                                    >
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-slate-100 dark:divide-white/10"
                                            >
                                                <tr
                                                    v-for="item in achievements"
                                                    :key="item.id"
                                                    class="hover:bg-slate-50 dark:hover:bg-white/5"
                                                >
                                                    <td class="px-4 py-3">
                                                        <div
                                                            class="font-bold text-slate-900 dark:text-white"
                                                        >
                                                            {{ item.title }}
                                                        </div>
                                                        <div
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                        >
                                                            {{
                                                                item.description ||
                                                                '-'
                                                            }}
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{
                                                            item.awarder || '-'
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{
                                                            formatDate(
                                                                item.date_received,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-right"
                                                    >
                                                        <div
                                                            class="flex justify-end gap-1"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                                @click="
                                                                    openAchievementModal(
                                                                        item,
                                                                    )
                                                                "
                                                            >
                                                                <Edit
                                                                    class="size-4"
                                                                />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                                @click="
                                                                    confirmDelete(
                                                                        item.id,
                                                                        'achievement',
                                                                        item.title,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="size-4"
                                                                />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeTab === 'trainings'">
                                <div
                                    v-if="trainings.length === 0"
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <Calendar class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No training records found
                                    </p>
                                </div>

                                <div v-else class="space-y-3 sm:space-y-0">
                                    <div class="grid gap-3 sm:hidden">
                                        <div
                                            v-for="item in trainings"
                                            :key="item.id"
                                            class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-slate-950"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-sm font-bold break-words text-slate-900 dark:text-white"
                                                    >
                                                        {{ item.title }}
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            item.organizer ||
                                                            'No organizer set'
                                                        }}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex shrink-0 items-center gap-1"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                        @click="
                                                            openTrainingModal(
                                                                item,
                                                            )
                                                        "
                                                    >
                                                        <Edit class="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                        @click="
                                                            confirmDelete(
                                                                item.id,
                                                                'training',
                                                                item.title,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-4"
                                                        />
                                                    </button>
                                                </div>
                                            </div>
                                            <div
                                                class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-xs dark:border-white/10"
                                            >
                                                <div>
                                                    <p
                                                        class="font-bold text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Date
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatDate(
                                                                item.date_from,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p
                                                        class="font-bold text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        Venue
                                                    </p>
                                                    <p
                                                        class="mt-1 font-semibold break-words text-slate-900 dark:text-white"
                                                    >
                                                        {{ item.venue || '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="hidden overflow-x-auto rounded-lg border border-slate-200 sm:block dark:border-white/10"
                                    >
                                        <table
                                            class="w-full min-w-[720px] text-sm"
                                        >
                                            <thead
                                                class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                            >
                                                <tr>
                                                    <th class="px-4 py-3">
                                                        Title
                                                    </th>
                                                    <th class="px-4 py-3">
                                                        Organizer
                                                    </th>
                                                    <th class="px-4 py-3">
                                                        Date
                                                    </th>
                                                    <th
                                                        class="px-4 py-3 text-right"
                                                    >
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-slate-100 dark:divide-white/10"
                                            >
                                                <tr
                                                    v-for="item in trainings"
                                                    :key="item.id"
                                                    class="hover:bg-slate-50 dark:hover:bg-white/5"
                                                >
                                                    <td
                                                        class="px-4 py-3 font-bold text-slate-900 dark:text-white"
                                                    >
                                                        {{ item.title }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{
                                                            item.organizer ||
                                                            '-'
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{
                                                            formatDate(
                                                                item.date_from,
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-3 text-right"
                                                    >
                                                        <div
                                                            class="flex justify-end gap-1"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                                                @click="
                                                                    openTrainingModal(
                                                                        item,
                                                                    )
                                                                "
                                                            >
                                                                <Edit
                                                                    class="size-4"
                                                                />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="inline-flex size-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                                @click="
                                                                    confirmDelete(
                                                                        item.id,
                                                                        'training',
                                                                        item.title,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="size-4"
                                                                />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Socio Tab -->
                            <div
                                v-if="activeTab === 'socio'"
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <div
                                    v-for="field in socioFields"
                                    :key="field.label"
                                    class="grid grid-cols-[160px_1fr] gap-3 py-2.5 text-xs"
                                >
                                    <dt class="font-medium text-slate-500 dark:text-slate-400">
                                        {{ field.label }}
                                    </dt>
                                    <dd class="font-semibold text-slate-900 dark:text-white">
                                        {{ field.value }}
                                    </dd>
                                </div>
                            </div>

                            <div
                                v-if="activeTab === 'physical-fitness-test'"
                                class="space-y-4 font-light"
                            >
                                <div
                                    v-if="!physicalFitness.canFillUp"
                                    class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-light text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                                >
                                    Physical Fitness Test submissions are
                                    available only to students currently
                                    enrolled in PE/PATHFIT subjects.
                                    <span class="block pt-1">
                                        You may still view your completed
                                        Physical Fitness Test records below.
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        physicalFitness.components.length ===
                                            0 ||
                                        physicalFitness.terms.length === 0
                                    "
                                    class="flex min-h-[240px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center dark:border-white/10"
                                >
                                    <Dumbbell class="size-9 text-slate-300" />
                                    <p
                                        class="text-sm font-light text-slate-900 dark:text-white"
                                    >
                                        {{
                                            physicalFitness.canFillUp
                                                ? 'No physical fitness tests configured'
                                                : 'No completed physical fitness records found'
                                        }}
                                    </p>
                                    <p
                                        class="max-w-md text-xs font-light text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            physicalFitness.canFillUp
                                                ? 'Active PFT components and active campus academic terms must be configured before students can submit results.'
                                                : 'Only completed Physical Fitness Test records are visible for your account.'
                                        }}
                                    </p>
                                </div>

                                <template v-else>
                                    <div
                                        class="flex flex-col gap-3 rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-500/20 dark:bg-emerald-500/10"
                                    >
                                        <div>
                                            <p
                                                class="text-[11px] font-light tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                            >
                                                Physical Fitness Test
                                            </p>
                                            <h3
                                                class="mt-1 text-lg font-light text-slate-950 dark:text-white"
                                            >
                                                AY
                                                {{
                                                    activePftTerm?.school_year ??
                                                    '-'
                                                }}
                                                ·
                                                {{
                                                    activePftTerm?.semester ??
                                                    '-'
                                                }}
                                            </h3>
                                            <p
                                                class="text-xs font-light text-slate-500 dark:text-slate-400"
                                            >
                                                Active academic year and
                                                semester.
                                                {{
                                                    physicalFitness.canFillUp
                                                        ? 'Open a term to fill up the component, category, and test type requirements.'
                                                        : 'Completed Physical Fitness Test records are listed below.'
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="flex flex-col gap-2 sm:items-end"
                                        >
                                            <div
                                                class="flex flex-wrap items-center gap-2 text-xs font-light text-slate-600 dark:text-slate-300"
                                            >
                                                <span
                                                    class="rounded-full bg-white px-3 py-1 text-emerald-700 ring-1 ring-emerald-100 dark:bg-white/10 dark:text-emerald-300 dark:ring-white/10"
                                                >
                                                    {{
                                                        physicalFitness.terms
                                                            .length
                                                    }}
                                                    term{{
                                                        physicalFitness.terms
                                                            .length === 1
                                                            ? ''
                                                            : 's'
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        physicalFitness.canFillUp
                                                    "
                                                    class="rounded-full bg-white px-3 py-1 text-amber-700 ring-1 ring-amber-100 dark:bg-white/10 dark:text-amber-300 dark:ring-white/10"
                                                >
                                                    {{ totalPftPendingCount }}
                                                    total pending
                                                </span>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="h-9 w-full border-emerald-200 bg-white px-4 text-xs font-light text-emerald-700 hover:bg-emerald-50 sm:w-auto dark:border-emerald-500/30 dark:bg-white/10 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                                @click="openPftAnalyticsDrawer"
                                            >
                                                <ChartColumnIncreasing
                                                    class="mr-2 h-4 w-4"
                                                />
                                                View My Analytics
                                            </Button>
                                        </div>
                                    </div>

                                    <div
                                        class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                                    >
                                        <div
                                            class="divide-y divide-slate-100 md:hidden dark:divide-white/10"
                                        >
                                            <article
                                                v-for="term in physicalFitness.terms"
                                                :key="term.term_id"
                                                class="space-y-3 p-3"
                                                :class="
                                                    selectedPftTermId ===
                                                    term.term_id
                                                        ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                                        : ''
                                                "
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-2"
                                                >
                                                    <div class="min-w-0">
                                                        <p
                                                            class="text-sm font-medium text-slate-900 dark:text-white"
                                                        >
                                                            AY
                                                            {{
                                                                term.school_year
                                                            }}
                                                            ·
                                                            {{ term.semester }}
                                                        </p>
                                                        <p
                                                            class="mt-0.5 truncate font-mono text-[10px] text-slate-500"
                                                        >
                                                            Term
                                                            {{ term.term_id }}
                                                        </p>
                                                    </div>
                                                    <span
                                                        class="inline-flex shrink-0 rounded-full px-2 py-1 text-[9px] font-light"
                                                        :class="
                                                            pftTermPendingCount(
                                                                term.term_id,
                                                            ) === 0
                                                                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
                                                                : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
                                                        "
                                                    >
                                                        {{
                                                            pftTermPendingCount(
                                                                term.term_id,
                                                            ) === 0
                                                                ? 'Complete'
                                                                : physicalFitness.canFillUp
                                                                  ? 'Needs fill up'
                                                                  : 'Completed'
                                                        }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="grid grid-cols-2 gap-2"
                                                >
                                                    <div
                                                        class="rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5"
                                                    >
                                                        <p
                                                            class="text-[9px] tracking-wide text-slate-400 uppercase"
                                                        >
                                                            Tests
                                                        </p>
                                                        <p
                                                            class="mt-0.5 text-sm text-slate-800 dark:text-slate-100"
                                                        >
                                                            {{
                                                                pftRowsForTerm(
                                                                    term.term_id,
                                                                ).length
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div
                                                        class="rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5"
                                                    >
                                                        <p
                                                            class="text-[9px] tracking-wide text-slate-400 uppercase"
                                                        >
                                                            Pending
                                                        </p>
                                                        <p
                                                            class="mt-0.5 text-sm text-slate-800 dark:text-slate-100"
                                                        >
                                                            {{
                                                                pftTermPendingCount(
                                                                    term.term_id,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    class="h-8 w-full bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                                                    @click="
                                                        !physicalFitness.canFillUp ||
                                                        pftTermPendingCount(
                                                            term.term_id,
                                                        ) === 0
                                                            ? openPftTermSummary(
                                                                  term,
                                                              )
                                                            : openPftTermDrawer(
                                                                  term,
                                                              )
                                                    "
                                                >
                                                    {{
                                                        pftTermPendingCount(
                                                            term.term_id,
                                                        ) === 0 ||
                                                        !physicalFitness.canFillUp
                                                            ? 'View Summary'
                                                            : pftTermSavedCount(
                                                                    term.term_id,
                                                                ) > 0
                                                              ? 'Open'
                                                              : 'Fill Up'
                                                    }}
                                                </Button>
                                            </article>
                                        </div>

                                        <div
                                            class="hidden overflow-x-auto md:block"
                                        >
                                            <table class="min-w-full text-left">
                                                <thead
                                                    class="border-b border-slate-100 bg-slate-50 text-[11px] font-light tracking-wide text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                                                >
                                                    <tr>
                                                        <th class="px-3 py-3">
                                                            AY
                                                        </th>
                                                        <th class="px-3 py-3">
                                                            Semester
                                                        </th>
                                                        <th class="px-3 py-3">
                                                            Term ID
                                                        </th>
                                                        <th class="px-3 py-3">
                                                            Tests
                                                        </th>
                                                        <th
                                                            v-if="
                                                                physicalFitness.canFillUp
                                                            "
                                                            class="px-3 py-3"
                                                        >
                                                            Pending
                                                        </th>
                                                        <th class="px-3 py-3">
                                                            Status
                                                        </th>
                                                        <th
                                                            class="px-3 py-3 text-right"
                                                        >
                                                            Action
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody
                                                    class="divide-y divide-slate-100 text-sm dark:divide-white/10"
                                                >
                                                    <tr
                                                        v-for="term in physicalFitness.terms"
                                                        :key="term.term_id"
                                                        class="transition hover:bg-emerald-50/50 dark:hover:bg-emerald-500/10"
                                                        :class="
                                                            selectedPftTermId ===
                                                            term.term_id
                                                                ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                                                : ''
                                                        "
                                                    >
                                                        <td
                                                            class="px-3 py-3 font-light text-slate-900 dark:text-white"
                                                        >
                                                            {{
                                                                term.school_year
                                                            }}
                                                        </td>
                                                        <td
                                                            v-if="
                                                                physicalFitness.canFillUp
                                                            "
                                                            class="px-3 py-3 text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{ term.semester }}
                                                        </td>
                                                        <td
                                                            class="px-3 py-3 font-mono text-xs text-slate-500"
                                                        >
                                                            {{ term.term_id }}
                                                        </td>
                                                        <td
                                                            class="px-3 py-3 text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                pftRowsForTerm(
                                                                    term.term_id,
                                                                ).length
                                                            }}
                                                        </td>
                                                        <td
                                                            class="px-3 py-3 text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                pftTermPendingCount(
                                                                    term.term_id,
                                                                )
                                                            }}
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span
                                                                class="inline-flex rounded-full px-2 py-1 text-[11px] font-light"
                                                                :class="
                                                                    pftTermPendingCount(
                                                                        term.term_id,
                                                                    ) === 0
                                                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
                                                                        : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
                                                                "
                                                            >
                                                                {{
                                                                    pftTermPendingCount(
                                                                        term.term_id,
                                                                    ) === 0
                                                                        ? 'Complete'
                                                                        : physicalFitness.canFillUp
                                                                          ? 'Needs fill up'
                                                                          : 'Completed records'
                                                                }}
                                                            </span>
                                                        </td>
                                                        <td
                                                            class="px-3 py-3 text-right"
                                                        >
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                class="h-8 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                                                                @click="
                                                                    !physicalFitness.canFillUp ||
                                                                    pftTermPendingCount(
                                                                        term.term_id,
                                                                    ) === 0
                                                                        ? openPftTermSummary(
                                                                              term,
                                                                          )
                                                                        : openPftTermDrawer(
                                                                              term,
                                                                          )
                                                                "
                                                            >
                                                                {{
                                                                    pftTermPendingCount(
                                                                        term.term_id,
                                                                    ) === 0 ||
                                                                    !physicalFitness.canFillUp
                                                                        ? 'View Summary'
                                                                        : pftTermSavedCount(
                                                                                term.term_id,
                                                                            ) >
                                                                            0
                                                                          ? 'Open'
                                                                          : 'Fill Up'
                                                                }}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div
                                        v-if="pftAnalyticsDrawerOpen"
                                        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
                                        @click.self="closePftAnalyticsDrawer"
                                    >
                                        <aside
                                            class="flex h-full w-full flex-col bg-white shadow-2xl sm:max-w-[95vw] lg:max-w-[85vw] dark:bg-slate-950"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-white/10"
                                            >
                                                <div class="min-w-0 font-light">
                                                    <p
                                                        class="text-[11px] tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                                    >
                                                        Physical Fitness Test
                                                    </p>
                                                    <h3
                                                        class="mt-1 text-lg text-slate-950 dark:text-white"
                                                    >
                                                        My Physical Fitness
                                                        Analytics
                                                    </h3>
                                                    <p
                                                        class="text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        Dynamic results from
                                                        your submitted fitness
                                                        test records.
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-9 font-light"
                                                    @click="
                                                        closePftAnalyticsDrawer
                                                    "
                                                >
                                                    Close
                                                </Button>
                                            </div>

                                            <div
                                                class="min-h-0 flex-1 overflow-y-auto px-5 py-4 font-light"
                                            >
                                                <div
                                                    v-if="pftAnalyticsError"
                                                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                                                >
                                                    {{ pftAnalyticsError }}
                                                </div>
                                                <div
                                                    class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-6 dark:border-white/10 dark:bg-white/5"
                                                >
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Academic Year /
                                                            Semester</Label
                                                        >
                                                        <select
                                                            v-model="
                                                                pftAnalyticsFilterTermId
                                                            "
                                                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                                            @change="
                                                                loadPftAnalytics
                                                            "
                                                        >
                                                            <option value="">
                                                                All terms
                                                            </option>
                                                            <option
                                                                v-for="term in pftAnalyticsData
                                                                    ?.filters
                                                                    ?.terms ??
                                                                []"
                                                                :key="
                                                                    term.termId
                                                                "
                                                                :value="
                                                                    term.termId
                                                                "
                                                            >
                                                                {{ term.label }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Component</Label
                                                        >
                                                        <select
                                                            v-model="
                                                                pftAnalyticsFilterComponentId
                                                            "
                                                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                                            @change="
                                                                pftAnalyticsFilterCategoryId =
                                                                    '';
                                                                pftAnalyticsFilterTestTypeId =
                                                                    '';
                                                                loadPftAnalytics();
                                                            "
                                                        >
                                                            <option value="">
                                                                All components
                                                            </option>
                                                            <option
                                                                v-for="component in pftAnalyticsData
                                                                    ?.filters
                                                                    ?.components ??
                                                                []"
                                                                :key="
                                                                    component.id
                                                                "
                                                                :value="
                                                                    component.id
                                                                "
                                                            >
                                                                {{
                                                                    component.name
                                                                }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Category</Label
                                                        >
                                                        <select
                                                            v-model="
                                                                pftAnalyticsFilterCategoryId
                                                            "
                                                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                                            @change="
                                                                pftAnalyticsFilterTestTypeId =
                                                                    '';
                                                                loadPftAnalytics();
                                                            "
                                                        >
                                                            <option value="">
                                                                All categories
                                                            </option>
                                                            <template
                                                                v-for="component in pftAnalyticsData
                                                                    ?.filters
                                                                    ?.components ??
                                                                []"
                                                                :key="
                                                                    component.id
                                                                "
                                                            >
                                                                <option
                                                                    v-for="category in component.categories"
                                                                    :key="
                                                                        category.id
                                                                    "
                                                                    :value="
                                                                        category.id
                                                                    "
                                                                >
                                                                    {{
                                                                        category.name
                                                                    }}
                                                                </option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Test Type</Label
                                                        >
                                                        <select
                                                            v-model="
                                                                pftAnalyticsFilterTestTypeId
                                                            "
                                                            class="mt-1 h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-xs text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                                            @change="
                                                                loadPftAnalytics
                                                            "
                                                        >
                                                            <option value="">
                                                                All tests
                                                            </option>
                                                            <template
                                                                v-for="component in pftAnalyticsData
                                                                    ?.filters
                                                                    ?.components ??
                                                                []"
                                                                :key="
                                                                    component.id
                                                                "
                                                            >
                                                                <template
                                                                    v-for="category in component.categories"
                                                                    :key="
                                                                        category.id
                                                                    "
                                                                >
                                                                    <option
                                                                        v-for="testType in category.testTypes"
                                                                        :key="
                                                                            testType.id
                                                                        "
                                                                        :value="
                                                                            testType.id
                                                                        "
                                                                    >
                                                                        {{
                                                                            testType.name
                                                                        }}
                                                                    </option>
                                                                </template>
                                                            </template>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Date From</Label
                                                        >
                                                        <Input
                                                            v-model="
                                                                pftAnalyticsFilterDateFrom
                                                            "
                                                            type="date"
                                                            class="mt-1 h-9 text-xs"
                                                            @change="
                                                                loadPftAnalytics
                                                            "
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                            >Date To</Label
                                                        >
                                                        <Input
                                                            v-model="
                                                                pftAnalyticsFilterDateTo
                                                            "
                                                            type="date"
                                                            class="mt-1 h-9 text-xs"
                                                            @change="
                                                                loadPftAnalytics
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="pftAnalyticsLoading"
                                                    class="mb-4 rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
                                                >
                                                    Loading analytics...
                                                </div>
                                                <section class="space-y-3">
                                                    <div
                                                        class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"
                                                    >
                                                        <div>
                                                            <p
                                                                class="text-sm text-slate-950 dark:text-white"
                                                            >
                                                                Summary Cards
                                                            </p>
                                                            <p
                                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                            >
                                                                Based on all
                                                                visible required
                                                                tests for your
                                                                configured
                                                                terms.
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                                                    >
                                                        <div
                                                            v-for="card in pftAnalyticsSummaryCards"
                                                            :key="card.label"
                                                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                            >
                                                                {{ card.label }}
                                                            </p>
                                                            <p
                                                                class="mt-2 text-2xl"
                                                                :class="
                                                                    card.tone
                                                                "
                                                            >
                                                                {{ card.value }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </section>

                                                <section class="mt-6 space-y-3">
                                                    <div>
                                                        <p
                                                            class="text-sm text-slate-950 dark:text-white"
                                                        >
                                                            Charts
                                                        </p>
                                                        <p
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                        >
                                                            Completion and
                                                            progress by
                                                            component and
                                                            category.
                                                        </p>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            pftAnalyticsChartsReady
                                                        "
                                                        class="grid gap-4 xl:grid-cols-2"
                                                    >
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="mb-3 text-sm text-slate-950 dark:text-white"
                                                            >
                                                                Completion Donut
                                                                Chart
                                                            </p>
                                                            <VueApexCharts
                                                                :key="`completion-${pftAnalyticsChartKey}`"
                                                                type="donut"
                                                                height="300"
                                                                :options="
                                                                    pftAnalyticsCompletionOptions
                                                                "
                                                                :series="
                                                                    pftAnalyticsCompletionSeries
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="mb-3 text-sm text-slate-950 dark:text-white"
                                                            >
                                                                Component
                                                                Completion Bar
                                                                Chart
                                                            </p>
                                                            <VueApexCharts
                                                                :key="`component-${pftAnalyticsChartKey}`"
                                                                type="bar"
                                                                height="300"
                                                                :options="
                                                                    pftAnalyticsComponentOptions
                                                                "
                                                                :series="
                                                                    pftAnalyticsComponentSeries
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="mb-3 text-sm text-slate-950 dark:text-white"
                                                            >
                                                                Category
                                                                Progress Chart
                                                            </p>
                                                            <VueApexCharts
                                                                :key="`category-${pftAnalyticsChartKey}`"
                                                                type="bar"
                                                                height="360"
                                                                :options="
                                                                    pftAnalyticsCategoryOptions
                                                                "
                                                                :series="
                                                                    pftAnalyticsCategorySeries
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <div
                                                                class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                                            >
                                                                <p
                                                                    class="text-sm text-slate-950 dark:text-white"
                                                                >
                                                                    Semester
                                                                    Comparison
                                                                    Line Chart
                                                                </p>
                                                                <p
                                                                    v-if="
                                                                        selectedPftAnalyticsComparison
                                                                    "
                                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                                >
                                                                    {{
                                                                        selectedPftAnalyticsComparison.testType
                                                                    }}
                                                                    · Change
                                                                    {{
                                                                        selectedPftAnalyticsComparison.percentageChange ??
                                                                        0
                                                                    }}%
                                                                </p>
                                                            </div>
                                                            <VueApexCharts
                                                                :key="`comparison-${pftAnalyticsChartKey}`"
                                                                type="line"
                                                                height="320"
                                                                :options="
                                                                    pftAnalyticsComparisonOptions
                                                                "
                                                                :series="
                                                                    pftAnalyticsComparisonSeries
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 xl:col-span-2 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="mb-3 text-sm text-slate-950 dark:text-white"
                                                            >
                                                                Student Fitness
                                                                Profile Radar
                                                                Chart
                                                            </p>
                                                            <VueApexCharts
                                                                :key="`radar-${pftAnalyticsChartKey}`"
                                                                type="radar"
                                                                height="360"
                                                                :options="
                                                                    pftAnalyticsRadarOptions
                                                                "
                                                                :series="
                                                                    pftAnalyticsRadarSeries
                                                                "
                                                            />
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-else
                                                        class="rounded-lg border border-dashed border-slate-200 p-6 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
                                                    >
                                                        Preparing charts...
                                                    </div>
                                                </section>

                                                <section
                                                    class="mt-6 space-y-3 pb-2"
                                                >
                                                    <div
                                                        class="grid gap-4 lg:grid-cols-[280px_1fr]"
                                                    >
                                                        <div
                                                            class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                                                        >
                                                            <p
                                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                            >
                                                                Overall Fitness
                                                                Index
                                                            </p>
                                                            <p
                                                                class="mt-2 text-4xl text-emerald-700 dark:text-emerald-300"
                                                            >
                                                                {{
                                                                    pftAnalyticsFitnessIndex.score
                                                                }}
                                                                <span
                                                                    class="text-lg text-slate-500 dark:text-slate-400"
                                                                    >/ 100</span
                                                                >
                                                            </p>
                                                            <p
                                                                class="mt-1 text-sm text-slate-700 dark:text-slate-200"
                                                            >
                                                                {{
                                                                    pftAnalyticsFitnessIndex.rating
                                                                }}
                                                            </p>
                                                        </div>
                                                        <div
                                                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                                                        >
                                                            <p
                                                                class="text-sm text-slate-950 dark:text-white"
                                                            >
                                                                AI Insights
                                                            </p>
                                                            <p
                                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                            >
                                                                Rule-based
                                                                interpretations
                                                                from completion,
                                                                trends, and
                                                                historical
                                                                records.
                                                            </p>
                                                            <ul
                                                                class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300"
                                                            >
                                                                <li
                                                                    v-for="insight in pftAnalyticsInsights"
                                                                    :key="
                                                                        insight
                                                                    "
                                                                    class="flex gap-2"
                                                                >
                                                                    <span
                                                                        class="mt-1 size-1.5 rounded-full bg-emerald-500"
                                                                    ></span>
                                                                    <span>{{
                                                                        insight
                                                                    }}</span>
                                                                </li>
                                                                <li
                                                                    v-if="
                                                                        pftAnalyticsInsights.length ===
                                                                        0
                                                                    "
                                                                    class="text-slate-500 dark:text-slate-400"
                                                                >
                                                                    No insights
                                                                    available
                                                                    yet.
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </section>

                                                <section
                                                    class="mt-6 space-y-3 pb-2"
                                                >
                                                    <div>
                                                        <p
                                                            class="text-sm text-slate-950 dark:text-white"
                                                        >
                                                            Test History
                                                            Timeline
                                                        </p>
                                                        <p
                                                            class="text-xs text-slate-500 dark:text-slate-400"
                                                        >
                                                            Newest saved records
                                                            across your fitness
                                                            test requirements.
                                                        </p>
                                                    </div>
                                                    <div class="space-y-3">
                                                        <section
                                                            v-for="group in pftAnalyticsTimelineGroups"
                                                            :key="group.key"
                                                            class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="flex w-full items-center justify-between gap-3 bg-slate-50 px-4 py-3 text-left transition hover:bg-emerald-50 focus:ring-2 focus:ring-emerald-500 focus:outline-none dark:bg-white/5 dark:hover:bg-emerald-500/10"
                                                                :aria-expanded="
                                                                    openPftAnalyticsTimelineGroups.includes(
                                                                        group.key,
                                                                    )
                                                                "
                                                                @click="
                                                                    togglePftAnalyticsTimelineGroup(
                                                                        group.key,
                                                                    )
                                                                "
                                                            >
                                                                <div>
                                                                    <p
                                                                        class="text-sm text-slate-950 dark:text-white"
                                                                    >
                                                                        AY
                                                                        {{
                                                                            group.academicYear
                                                                        }}
                                                                        ·
                                                                        {{
                                                                            group.semester
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="text-xs text-slate-500 dark:text-slate-400"
                                                                    >
                                                                        {{
                                                                            group
                                                                                .rows
                                                                                .length
                                                                        }}
                                                                        saved
                                                                        result{{
                                                                            group
                                                                                .rows
                                                                                .length ===
                                                                            1
                                                                                ? ''
                                                                                : 's'
                                                                        }}
                                                                    </p>
                                                                </div>
                                                                <ChevronDown
                                                                    class="size-4 shrink-0 text-slate-400 transition"
                                                                    :class="
                                                                        openPftAnalyticsTimelineGroups.includes(
                                                                            group.key,
                                                                        )
                                                                            ? 'rotate-180'
                                                                            : ''
                                                                    "
                                                                />
                                                            </button>
                                                            <div
                                                                v-if="
                                                                    openPftAnalyticsTimelineGroups.includes(
                                                                        group.key,
                                                                    )
                                                                "
                                                                class="space-y-3 border-t border-slate-100 p-3 dark:border-white/10"
                                                            >
                                                                <section
                                                                    v-for="componentGroup in group.components"
                                                                    :key="
                                                                        componentGroup.key
                                                                    "
                                                                    class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="flex w-full items-center justify-between gap-3 bg-white px-4 py-3 text-left transition hover:bg-emerald-50 focus:ring-2 focus:ring-emerald-500 focus:outline-none dark:bg-slate-950 dark:hover:bg-emerald-500/10"
                                                                        :aria-expanded="
                                                                            openPftAnalyticsTimelineComponentGroups.includes(
                                                                                componentGroup.key,
                                                                            )
                                                                        "
                                                                        @click="
                                                                            togglePftAnalyticsTimelineComponentGroup(
                                                                                componentGroup.key,
                                                                            )
                                                                        "
                                                                    >
                                                                        <div>
                                                                            <p
                                                                                class="text-sm text-slate-950 dark:text-white"
                                                                            >
                                                                                {{
                                                                                    componentGroup.name
                                                                                }}
                                                                            </p>
                                                                            <p
                                                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                                            >
                                                                                {{
                                                                                    componentGroup
                                                                                        .rows
                                                                                        .length
                                                                                }}
                                                                                result{{
                                                                                    componentGroup
                                                                                        .rows
                                                                                        .length ===
                                                                                    1
                                                                                        ? ''
                                                                                        : 's'
                                                                                }}
                                                                            </p>
                                                                        </div>
                                                                        <ChevronDown
                                                                            class="size-4 shrink-0 text-slate-400 transition"
                                                                            :class="
                                                                                openPftAnalyticsTimelineComponentGroups.includes(
                                                                                    componentGroup.key,
                                                                                )
                                                                                    ? 'rotate-180'
                                                                                    : ''
                                                                            "
                                                                        />
                                                                    </button>
                                                                    <div
                                                                        v-if="
                                                                            openPftAnalyticsTimelineComponentGroups.includes(
                                                                                componentGroup.key,
                                                                            )
                                                                        "
                                                                        class="overflow-x-auto border-t border-slate-100 dark:border-white/10"
                                                                    >
                                                                        <table
                                                                            class="min-w-[720px] text-left text-sm"
                                                                        >
                                                                            <thead
                                                                                class="border-b border-slate-100 bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                                                                            >
                                                                                <tr>
                                                                                    <th
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        Category
                                                                                    </th>
                                                                                    <th
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        Result
                                                                                        /
                                                                                        Score
                                                                                    </th>
                                                                                    <th
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        Date
                                                                                        Tested
                                                                                    </th>
                                                                                    <th
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        Status
                                                                                    </th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody
                                                                                class="divide-y divide-slate-100 dark:divide-white/10"
                                                                            >
                                                                                <tr
                                                                                    v-for="row in componentGroup.rows"
                                                                                    :key="
                                                                                        row.id
                                                                                    "
                                                                                    class="text-slate-600 dark:text-slate-300"
                                                                                >
                                                                                    <td
                                                                                        class="px-3 py-3 text-slate-900 dark:text-white"
                                                                                    >
                                                                                        <div>
                                                                                            <p>
                                                                                                {{
                                                                                                    row.category
                                                                                                }}
                                                                                            </p>
                                                                                            <p
                                                                                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                                                                            >
                                                                                                {{
                                                                                                    row.testType
                                                                                                }}
                                                                                            </p>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        {{
                                                                                            row.result
                                                                                        }}
                                                                                        <span
                                                                                            v-if="
                                                                                                row.rating &&
                                                                                                row.rating !==
                                                                                                    '-'
                                                                                            "
                                                                                            class="text-slate-500 dark:text-slate-400"
                                                                                        >
                                                                                            ({{
                                                                                                row.rating
                                                                                            }})
                                                                                        </span>
                                                                                    </td>
                                                                                    <td
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        {{
                                                                                            row.dateTested
                                                                                        }}
                                                                                    </td>
                                                                                    <td
                                                                                        class="px-3 py-3"
                                                                                    >
                                                                                        <span
                                                                                            class="inline-flex rounded-full px-2 py-1 text-[11px]"
                                                                                            :class="
                                                                                                row.status ===
                                                                                                'completed'
                                                                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
                                                                                                    : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20'
                                                                                            "
                                                                                        >
                                                                                            {{
                                                                                                row.status ===
                                                                                                'completed'
                                                                                                    ? 'Completed'
                                                                                                    : 'Draft'
                                                                                            }}
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </section>
                                                            </div>
                                                        </section>
                                                        <div
                                                            v-if="
                                                                pftAnalyticsTimelineGroups.length ===
                                                                0
                                                            "
                                                            class="rounded-lg border border-dashed border-slate-200 px-3 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400"
                                                        >
                                                            No saved fitness
                                                            test results yet.
                                                        </div>
                                                    </div>
                                                </section>
                                            </div>
                                        </aside>
                                    </div>

                                    <div
                                        v-if="
                                            pftDrawerOpen &&
                                            physicalFitness.canFillUp &&
                                            selectedPftTestType &&
                                            selectedPftTerm
                                        "
                                        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
                                        @click.self="pftDrawerOpen = false"
                                    >
                                        <aside
                                            class="flex h-full w-full max-w-[96rem] flex-col bg-white shadow-2xl dark:bg-slate-950"
                                        >
                                            <div
                                                class="flex min-h-0 flex-1 flex-col"
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-white/10"
                                                >
                                                    <div class="min-w-0">
                                                        <p
                                                            class="text-[11px] font-light tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                                        >
                                                            Physical Fitness
                                                            Test
                                                        </p>
                                                        <h3
                                                            class="mt-1 text-lg font-light text-slate-950 dark:text-white"
                                                        >
                                                            <span
                                                                >Physical
                                                                Fitness Test
                                                                Requirements</span
                                                            >
                                                        </h3>
                                                        <p
                                                            class="text-xs font-light text-slate-500 dark:text-slate-400"
                                                        >
                                                            AY
                                                            {{
                                                                selectedPftTerm.school_year
                                                            }}
                                                            ·
                                                            {{
                                                                selectedPftTerm.semester
                                                            }}
                                                            · Term ID
                                                            {{
                                                                selectedPftTerm.term_id
                                                            }}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-9"
                                                        @click="
                                                            pftDrawerOpen = false
                                                        "
                                                    >
                                                        Close
                                                    </Button>
                                                </div>

                                                <div
                                                    class="min-h-0 flex-1 overflow-y-auto px-5 py-4"
                                                >
                                                    <div
                                                        class="mb-5 rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                                                    >
                                                        <div
                                                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                                                        >
                                                            <div>
                                                                <p
                                                                    class="text-sm font-light text-slate-950 dark:text-white"
                                                                >
                                                                    Physical
                                                                    Fitness
                                                                    Progress
                                                                </p>
                                                                <p
                                                                    class="mt-1 text-xs font-light text-slate-500 dark:text-slate-400"
                                                                >
                                                                    {{
                                                                        selectedPftTermCompletedCount
                                                                    }}
                                                                    Completed ·
                                                                    {{
                                                                        selectedPftTermPendingCount
                                                                    }}
                                                                    Pending ·
                                                                    {{
                                                                        visiblePftRequirementRows.length
                                                                    }}
                                                                    Total Tests
                                                                </p>
                                                            </div>
                                                            <div
                                                                class="text-left sm:text-right"
                                                            >
                                                                <p
                                                                    class="text-2xl font-light text-emerald-700 dark:text-emerald-300"
                                                                >
                                                                    {{
                                                                        selectedPftTermCompletionPercent
                                                                    }}%
                                                                </p>
                                                                <p
                                                                    class="text-[11px] font-light tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                                >
                                                                    Complete
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="mt-4 h-2 overflow-hidden rounded-full bg-white ring-1 ring-emerald-100 dark:bg-white/10 dark:ring-white/10"
                                                            aria-label="Physical fitness completion progress"
                                                        >
                                                            <div
                                                                class="h-full rounded-full bg-emerald-600 transition-all"
                                                                :style="{
                                                                    width: `${selectedPftTermCompletionPercent}%`,
                                                                }"
                                                            ></div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="mb-5 grid min-h-[640px] overflow-hidden rounded-lg border border-slate-200 bg-white xl:grid-cols-2 2xl:grid-cols-[240px_240px_260px_minmax(0,1fr)] dark:border-white/10 dark:bg-slate-950"
                                                    >
                                                        <!-- Column 1: Components -->
                                                        <section
                                                            class="overflow-y-auto border-b border-slate-100 p-4 2xl:border-r 2xl:border-b-0 dark:border-white/10"
                                                            aria-label="Physical fitness components"
                                                        >
                                                            <div
                                                                class="mb-3 flex items-center justify-between"
                                                            >
                                                                <h4
                                                                    class="text-xs font-light tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                                >
                                                                    Components
                                                                </h4>
                                                                <span
                                                                    class="text-xs font-light text-slate-400"
                                                                >
                                                                    {{
                                                                        physicalFitness
                                                                            .components
                                                                            .length
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible lg:pb-0"
                                                                role="tablist"
                                                                aria-label="Select component"
                                                            >
                                                                <button
                                                                    v-for="component in physicalFitness.components"
                                                                    :key="
                                                                        component.id
                                                                    "
                                                                    type="button"
                                                                    class="min-w-48 rounded-lg px-3 py-3 text-left transition focus:ring-2 focus:ring-emerald-500 focus:outline-none lg:w-full lg:min-w-0"
                                                                    :class="
                                                                        selectedPftComponentId ===
                                                                        component.id
                                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                                            : 'bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200'
                                                                    "
                                                                    role="tab"
                                                                    :aria-selected="
                                                                        selectedPftComponentId ===
                                                                        component.id
                                                                    "
                                                                    :aria-label="`Select ${component.name} component`"
                                                                    @click="
                                                                        selectedPftComponentId =
                                                                            component.id
                                                                    "
                                                                >
                                                                    <span
                                                                        class="block text-sm font-light"
                                                                    >
                                                                        {{
                                                                            component.name
                                                                        }}
                                                                    </span>
                                                                    <span
                                                                        class="mt-1 block text-xs font-light opacity-80"
                                                                    >
                                                                        {{
                                                                            pftComponentCompletedCount(
                                                                                component,
                                                                            )
                                                                        }}
                                                                        /
                                                                        {{
                                                                            pftComponentTestCount(
                                                                                component,
                                                                            )
                                                                        }}
                                                                        complete
                                                                    </span>
                                                                </button>
                                                            </div>
                                                        </section>

                                                        <!-- Column 2: Categories -->
                                                        <section
                                                            class="overflow-y-auto border-b border-slate-100 p-4 2xl:border-r 2xl:border-b-0 dark:border-white/10"
                                                            aria-label="Physical fitness categories"
                                                        >
                                                            <div
                                                                class="mb-3 flex items-center justify-between"
                                                            >
                                                                <h4
                                                                    class="text-xs font-light tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                                >
                                                                    Categories
                                                                </h4>
                                                                <span
                                                                    class="text-xs font-light text-slate-400"
                                                                >
                                                                    {{
                                                                        selectedPftComponent
                                                                            ?.categories
                                                                            ?.length ??
                                                                        0
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible lg:pb-0"
                                                            >
                                                                <div
                                                                    v-for="category in selectedPftComponent?.categories ??
                                                                    []"
                                                                    :key="
                                                                        category.id
                                                                    "
                                                                    class="flex items-center justify-between rounded-lg p-1 transition lg:mb-2"
                                                                    :class="
                                                                        selectedPftCategoryId ===
                                                                        category.id
                                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                                            : 'bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200'
                                                                    "
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="flex-1 px-2 py-2 text-left text-sm font-light focus:outline-none"
                                                                        @click="
                                                                            selectedPftCategoryId =
                                                                                category.id
                                                                        "
                                                                    >
                                                                        {{
                                                                            category.name
                                                                        }}
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        class="px-2 opacity-60 hover:opacity-100 focus:outline-none"
                                                                        @click="
                                                                            openCategoryDetailsModal(
                                                                                category,
                                                                            )
                                                                        "
                                                                    >
                                                                        <Info
                                                                            class="size-3.5"
                                                                        />
                                                                    </button>
                                                                </div>
                                                                <div
                                                                    v-if="
                                                                        (
                                                                            selectedPftComponent?.categories ??
                                                                            []
                                                                        )
                                                                            .length ===
                                                                        0
                                                                    "
                                                                    class="p-2 text-xs text-slate-400 dark:text-slate-500"
                                                                >
                                                                    No
                                                                    categories
                                                                    configured.
                                                                </div>
                                                            </div>
                                                        </section>

                                                        <!-- Column 3: Test Types -->
                                                        <section
                                                            class="overflow-y-auto border-b border-slate-100 p-4 2xl:border-r 2xl:border-b-0 dark:border-white/10"
                                                            aria-label="Physical fitness test types"
                                                        >
                                                            <div
                                                                class="mb-3 flex items-center justify-between"
                                                            >
                                                                <h4
                                                                    class="text-xs font-light tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                                >
                                                                    Test Types
                                                                </h4>
                                                                <span
                                                                    class="text-xs font-light text-slate-400"
                                                                >
                                                                    {{
                                                                        selectedPftCategory
                                                                            ?.test_types
                                                                            ?.length ??
                                                                        0
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible lg:pb-0"
                                                            >
                                                                <button
                                                                    v-for="testType in selectedPftCategory?.test_types ??
                                                                    []"
                                                                    :key="
                                                                        testType.id
                                                                    "
                                                                    type="button"
                                                                    class="w-full min-w-48 rounded-lg px-3 py-3 text-left transition focus:ring-2 focus:ring-emerald-500 focus:outline-none lg:min-w-0"
                                                                    :class="
                                                                        selectedPftTestTypeId ===
                                                                        testType.id
                                                                            ? 'bg-emerald-600 text-white shadow-sm'
                                                                            : 'bg-slate-50 text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200'
                                                                    "
                                                                    @click="
                                                                        selectedPftTestTypeId =
                                                                            testType.id
                                                                    "
                                                                >
                                                                    <div
                                                                        class="flex items-center gap-2"
                                                                    >
                                                                        <div
                                                                            class="flex size-6 shrink-0 items-center justify-center rounded-full"
                                                                            :class="
                                                                                selectedPftTestTypeId ===
                                                                                testType.id
                                                                                    ? 'bg-white/20 text-white'
                                                                                    : pftTestStatus(
                                                                                            pftRowForTest(
                                                                                                selectedPftTerm.term_id,
                                                                                                testType.id,
                                                                                            ),
                                                                                        ) ===
                                                                                        'Completed'
                                                                                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                                                      : pftTestStatus(
                                                                                              pftRowForTest(
                                                                                                  selectedPftTerm.term_id,
                                                                                                  testType.id,
                                                                                              ),
                                                                                          ) ===
                                                                                          'Draft'
                                                                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                                                                                        : 'bg-slate-100 text-slate-400 dark:bg-white/10 dark:text-slate-500'
                                                                            "
                                                                        >
                                                                            <CheckCircle2
                                                                                v-if="
                                                                                    pftTestStatus(
                                                                                        pftRowForTest(
                                                                                            selectedPftTerm.term_id,
                                                                                            testType.id,
                                                                                        ),
                                                                                    ) ===
                                                                                    'Completed'
                                                                                "
                                                                                class="size-3.5"
                                                                            />
                                                                            <Clock3
                                                                                v-else-if="
                                                                                    pftTestStatus(
                                                                                        pftRowForTest(
                                                                                            selectedPftTerm.term_id,
                                                                                            testType.id,
                                                                                        ),
                                                                                    ) ===
                                                                                    'Draft'
                                                                                "
                                                                                class="size-3.5"
                                                                            />
                                                                            <Circle
                                                                                v-else
                                                                                class="size-3.5"
                                                                            />
                                                                        </div>
                                                                        <div
                                                                            class="min-w-0 flex-1"
                                                                        >
                                                                            <span
                                                                                class="block truncate text-sm font-light"
                                                                            >
                                                                                {{
                                                                                    testType.name
                                                                                }}
                                                                            </span>
                                                                            <span
                                                                                class="block text-[10px] opacity-80"
                                                                                :class="
                                                                                    selectedPftTestTypeId ===
                                                                                    testType.id
                                                                                        ? 'text-white'
                                                                                        : 'text-slate-500 dark:text-slate-400'
                                                                                "
                                                                            >
                                                                                {{
                                                                                    pftTestStatus(
                                                                                        pftRowForTest(
                                                                                            selectedPftTerm.term_id,
                                                                                            testType.id,
                                                                                        ),
                                                                                    )
                                                                                }}
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </button>
                                                                <div
                                                                    v-if="
                                                                        (
                                                                            selectedPftCategory?.test_types ??
                                                                            []
                                                                        )
                                                                            .length ===
                                                                        0
                                                                    "
                                                                    class="p-2 text-xs text-slate-400 dark:text-slate-500"
                                                                >
                                                                    No test
                                                                    types
                                                                    configured.
                                                                </div>
                                                            </div>
                                                        </section>

                                                        <!-- Column 4: Configurations (Form) -->
                                                        <section
                                                            class="overflow-y-auto p-4"
                                                            aria-label="Physical fitness configurations"
                                                        >
                                                            <div
                                                                v-if="
                                                                    selectedPftTestType
                                                                "
                                                                class="flex h-full flex-col"
                                                            >
                                                                <div
                                                                    class="mb-4"
                                                                >
                                                                    <h4
                                                                        class="text-xs font-light tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                                                    >
                                                                        Configurations
                                                                        ({{
                                                                            selectedPftTestType.name
                                                                        }})
                                                                    </h4>
                                                                    <p
                                                                        class="mt-1 text-sm font-light text-slate-900 dark:text-white"
                                                                    >
                                                                        Fill up
                                                                        the
                                                                        scores
                                                                        and
                                                                        remarks.
                                                                    </p>
                                                                </div>

                                                                <form
                                                                    @submit.prevent="
                                                                        submitPftResult(
                                                                            false,
                                                                        )
                                                                    "
                                                                    class="flex flex-1 flex-col justify-between"
                                                                >
                                                                    <input
                                                                        v-model="
                                                                            pftForm.term_id
                                                                        "
                                                                        type="hidden"
                                                                    />

                                                                    <div
                                                                        class="space-y-4"
                                                                    >
                                                                        <div
                                                                            class="grid gap-3 sm:grid-cols-2"
                                                                        >
                                                                            <label
                                                                                v-for="field in selectedPftTestType?.configurations"
                                                                                :key="
                                                                                    field.id
                                                                                "
                                                                                class="grid gap-1.5 text-xs font-light text-slate-600 dark:text-slate-300"
                                                                                :class="
                                                                                    field.field_type ===
                                                                                    'textarea'
                                                                                        ? 'sm:col-span-2'
                                                                                        : ''
                                                                                "
                                                                            >
                                                                                {{
                                                                                    field.field_label
                                                                                }}
                                                                                <span
                                                                                    v-if="
                                                                                        field.is_required
                                                                                    "
                                                                                    class="text-red-500"
                                                                                    >*</span
                                                                                >

                                                                                <textarea
                                                                                    v-if="
                                                                                        field.field_type ===
                                                                                        'textarea'
                                                                                    "
                                                                                    v-model="
                                                                                        pftForm
                                                                                            .results[
                                                                                            field
                                                                                                .field_name
                                                                                        ]
                                                                                    "
                                                                                    class="pft-profile-input min-h-20"
                                                                                    :placeholder="
                                                                                        field.placeholder ??
                                                                                        ''
                                                                                    "
                                                                                />

                                                                                <select
                                                                                    v-else-if="
                                                                                        [
                                                                                            'select',
                                                                                            'radio',
                                                                                        ].includes(
                                                                                            field.field_type,
                                                                                        )
                                                                                    "
                                                                                    v-model="
                                                                                        pftForm
                                                                                            .results[
                                                                                            field
                                                                                                .field_name
                                                                                        ]
                                                                                    "
                                                                                    class="pft-profile-input"
                                                                                >
                                                                                    <option
                                                                                        value=""
                                                                                    >
                                                                                        Select
                                                                                    </option>
                                                                                    <option
                                                                                        v-for="option in field.options ??
                                                                                        []"
                                                                                        :key="
                                                                                            option
                                                                                        "
                                                                                        :value="
                                                                                            option
                                                                                        "
                                                                                    >
                                                                                        {{
                                                                                            option
                                                                                        }}
                                                                                    </option>
                                                                                </select>

                                                                                <label
                                                                                    v-else-if="
                                                                                        field.field_type ===
                                                                                        'checkbox'
                                                                                    "
                                                                                    class="flex h-10 items-center gap-2 rounded-md border border-slate-200 px-3 dark:border-white/10"
                                                                                >
                                                                                    <input
                                                                                        v-model="
                                                                                            pftForm
                                                                                                .results[
                                                                                                field
                                                                                                    .field_name
                                                                                            ]
                                                                                        "
                                                                                        type="checkbox"
                                                                                        class="accent-emerald-600"
                                                                                    />
                                                                                    Checked
                                                                                </label>

                                                                                <input
                                                                                    v-else
                                                                                    v-model="
                                                                                        pftForm
                                                                                            .results[
                                                                                            field
                                                                                                .field_name
                                                                                        ]
                                                                                    "
                                                                                    class="pft-profile-input"
                                                                                    :type="
                                                                                        field.field_type ===
                                                                                        'decimal'
                                                                                            ? 'number'
                                                                                            : field.field_type
                                                                                    "
                                                                                    :step="
                                                                                        field.field_type ===
                                                                                        'decimal'
                                                                                            ? '0.01'
                                                                                            : undefined
                                                                                    "
                                                                                    :placeholder="
                                                                                        field.placeholder ??
                                                                                        ''
                                                                                    "
                                                                                    :readonly="
                                                                                        selectedPftTestType?.slug ===
                                                                                            'bmi-test' &&
                                                                                        field.field_name ===
                                                                                            'bmi'
                                                                                    "
                                                                                    :class="
                                                                                        selectedPftTestType?.slug ===
                                                                                            'bmi-test' &&
                                                                                        field.field_name ===
                                                                                            'bmi'
                                                                                            ? 'cursor-not-allowed bg-slate-100 opacity-80 dark:bg-slate-900'
                                                                                            : ''
                                                                                    "
                                                                                />
                                                                                <span
                                                                                    v-if="
                                                                                        field.help_text
                                                                                    "
                                                                                    class="text-[10px] font-light text-slate-400"
                                                                                    >{{
                                                                                        field.help_text
                                                                                    }}</span
                                                                                >
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <div
                                                                        class="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4 dark:border-white/10"
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="outline"
                                                                            :disabled="
                                                                                pftForm.processing ||
                                                                                !physicalFitness.canSubmit ||
                                                                                !physicalFitness.canFillUp
                                                                            "
                                                                            @click="
                                                                                submitPftResult(
                                                                                    true,
                                                                                )
                                                                            "
                                                                        >
                                                                            Save
                                                                            Draft
                                                                        </Button>
                                                                        <Button
                                                                            type="submit"
                                                                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                                                                            :disabled="
                                                                                pftForm.processing ||
                                                                                !physicalFitness.canSubmit ||
                                                                                !physicalFitness.canFillUp
                                                                            "
                                                                        >
                                                                            {{
                                                                                pftForm.processing
                                                                                    ? 'Saving...'
                                                                                    : 'Save Result'
                                                                            }}
                                                                        </Button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            <div
                                                                v-else
                                                                class="flex h-full flex-col items-center justify-center py-12 text-slate-400 dark:text-slate-500"
                                                            >
                                                                <Info
                                                                    class="mb-2 size-8"
                                                                />
                                                                <p
                                                                    class="text-sm font-light"
                                                                >
                                                                    Select a
                                                                    test type to
                                                                    fill up
                                                                    results.
                                                                </p>
                                                            </div>
                                                        </section>
                                                    </div>
                                                </div>
                                            </div>
                                        </aside>
                                    </div>
                                </template>
                            </div>

                            <!-- CCD Cares Evaluation Tab -->
                            <div
                                v-if="activeTab === 'ccd-cares'"
                                class="space-y-4 font-light"
                            >
                                <div
                                    v-if="ccdCares.assessments.length"
                                    class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 md:hidden dark:divide-white/10 dark:border-white/10"
                                >
                                    <article
                                        v-for="assessment in ccdCares.assessments"
                                        :key="assessment.period.id"
                                        class="space-y-3 p-3"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        assessment.period.title
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-1 line-clamp-2 text-[11px] leading-4 text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        assessment.period
                                                            .description ||
                                                        'No description provided'
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                v-if="assessment.submission"
                                                class="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200/50 bg-emerald-50 px-2 py-1 text-[9px] font-semibold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                                            >
                                                <CheckCircle2 class="size-3" />
                                                Submitted
                                            </span>
                                            <span
                                                v-else-if="
                                                    assessment.period.is_open
                                                "
                                                class="inline-flex shrink-0 items-center gap-1 rounded-full border border-blue-200/50 bg-blue-50 px-2 py-1 text-[9px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300"
                                            >
                                                <Clock3 class="size-3" />
                                                Open
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex shrink-0 items-center gap-1 rounded-full border border-slate-200/50 bg-slate-50 px-2 py-1 text-[9px] font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-500/10 dark:text-slate-400"
                                            >
                                                <AlertCircle class="size-3" />
                                                Closed
                                            </span>
                                        </div>

                                        <div
                                            class="rounded-md bg-slate-50 px-3 py-2 text-[10px] font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300"
                                        >
                                            {{
                                                formatDate(
                                                    assessment.period
                                                        .start_date,
                                                )
                                            }}
                                            <span class="mx-1 text-slate-400"
                                                >to</span
                                            >
                                            {{
                                                formatDate(
                                                    assessment.period.end_date,
                                                )
                                            }}
                                        </div>

                                        <Button
                                            v-if="assessment.submission"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 w-full text-xs font-semibold"
                                            @click="viewResults(assessment)"
                                        >
                                            View Results
                                        </Button>
                                        <Button
                                            v-else-if="
                                                assessment.period.is_open
                                            "
                                            type="button"
                                            size="sm"
                                            class="h-8 w-full bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400"
                                            @click="startEvaluation(assessment)"
                                        >
                                            Evaluate
                                        </Button>
                                        <Button
                                            v-else
                                            type="button"
                                            size="sm"
                                            class="h-8 w-full text-xs font-semibold"
                                            variant="ghost"
                                            disabled
                                        >
                                            Closed
                                        </Button>
                                    </article>
                                </div>

                                <div
                                    v-if="ccdCares.assessments.length === 0"
                                    class="flex min-h-[180px] flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-slate-200 p-6 text-center md:hidden dark:border-white/10"
                                >
                                    <FileText
                                        class="size-8 text-slate-300 dark:text-slate-700"
                                    />
                                    <span
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        No evaluation periods scheduled at this
                                        time.
                                    </span>
                                </div>

                                <div class="hidden overflow-x-auto rounded-lg border border-slate-200 md:block dark:border-white/10">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5 text-xs font-bold text-slate-500 uppercase">
                                                <th class="px-4 py-3">Evaluation Period</th>
                                                <th class="px-4 py-3">Duration</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                            <tr 
                                                v-for="assessment in ccdCares.assessments" 
                                                :key="assessment.period.id" 
                                                class="text-xs hover:bg-slate-50/50 dark:hover:bg-white/5 transition"
                                            >
                                                <td class="px-4 py-4">
                                                    <div class="font-bold text-slate-900 dark:text-white text-sm">
                                                        {{ assessment.period.title }}
                                                    </div>
                                                    <div class="text-slate-500 dark:text-slate-400 mt-1 max-w-md font-light leading-relaxed">
                                                        {{ assessment.period.description || 'No description provided' }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                                    <span class="font-medium">{{ formatDate(assessment.period.start_date) }}</span>
                                                    <span class="mx-1 text-slate-400">to</span>
                                                    <span class="font-medium">{{ formatDate(assessment.period.end_date) }}</span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <span 
                                                        v-if="assessment.submission" 
                                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 border border-emerald-200/50 dark:border-emerald-500/20"
                                                    >
                                                        <CheckCircle2 class="size-3" /> Submitted
                                                    </span>
                                                    <span 
                                                        v-else-if="assessment.period.is_open" 
                                                        class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300 border border-blue-200/50 dark:border-blue-500/20"
                                                    >
                                                        <Clock3 class="size-3" /> Open
                                                    </span>
                                                    <span 
                                                        v-else 
                                                        class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-500/10 dark:text-slate-400 border border-slate-200/50 dark:border-white/10"
                                                    >
                                                        <AlertCircle class="size-3" /> Closed
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    <Button 
                                                        v-if="assessment.submission" 
                                                        type="button" 
                                                        variant="outline" 
                                                        size="sm" 
                                                        class="h-8 font-semibold text-xs transition" 
                                                        @click="viewResults(assessment)"
                                                    >
                                                        View Results
                                                    </Button>
                                                    <Button 
                                                        v-else-if="assessment.period.is_open" 
                                                        type="button" 
                                                        size="sm" 
                                                        class="h-8 bg-emerald-600 hover:bg-emerald-700 text-white dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 font-semibold text-xs transition" 
                                                        @click="startEvaluation(assessment)"
                                                    >
                                                        Evaluate
                                                    </Button>
                                                    <Button 
                                                        v-else 
                                                        type="button" 
                                                        size="sm" 
                                                        class="h-8 font-semibold text-xs" 
                                                        variant="ghost" 
                                                        disabled
                                                    >
                                                        Closed
                                                    </Button>
                                                </td>
                                            </tr>
                                            <tr v-if="ccdCares.assessments.length === 0">
                                                <td colspan="4" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                                    <div class="flex flex-col items-center justify-center gap-2">
                                                        <FileText class="size-8 text-slate-300 dark:text-slate-700" />
                                                        <span class="font-medium">No evaluation periods scheduled at this time.</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Edit Mode (Wizard) -->
            <section
                v-else
                key="edit-mode"
                class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <!-- Sticky Header for Editor -->
                <div
                    class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-5 py-5 backdrop-blur-md sm:px-8 sm:py-6 dark:border-white/10 dark:bg-slate-950/95"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-12 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                            >
                                <Edit class="size-6" />
                            </div>
                            <div>
                                <h3
                                    class="text-lg font-extrabold text-slate-900 dark:text-white"
                                >
                                    Profile Editor
                                </h3>
                                <p
                                    class="text-[10px] font-bold tracking-widest text-slate-500 uppercase"
                                >
                                    Update your records
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col items-end text-right">
                            <span
                                class="text-sm font-bold text-slate-700 dark:text-slate-300"
                                >{{ stepTitle }}</span
                            >
                            <span class="text-xs font-semibold text-slate-400"
                                >Step {{ currentStep + 1 }} of
                                {{ wizardSteps.length }}</span
                            >
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="px-2">
                        <div class="flex items-center justify-between">
                            <div
                                v-for="(step, index) in wizardSteps"
                                :key="step"
                                class="flex flex-1 items-center"
                            >
                                <div
                                    class="flex size-8 cursor-pointer items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300"
                                    :class="[
                                        index <= currentStep
                                            ? 'border-sky-600 bg-sky-600 text-white shadow-[0_0_10px_rgba(2,132,199,0.3)]'
                                            : 'border-slate-200 bg-white text-slate-400 hover:border-slate-300 dark:border-white/10 dark:bg-slate-900',
                                    ]"
                                    @click="currentStep = index"
                                >
                                    <span v-if="index < currentStep">✓</span>
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <div
                                    v-if="index < wizardSteps.length - 1"
                                    class="h-1 flex-1 transition-all duration-500"
                                    :class="[
                                        index < currentStep
                                            ? 'bg-sky-600'
                                            : 'bg-slate-100 dark:bg-white/5',
                                    ]"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wizard Body -->
                <div class="min-h-[500px] flex-1 px-5 py-6 sm:px-8 sm:py-8">
                    <div
                        class="mb-8 flex items-center justify-between rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-300"
                    >
                        <div class="flex items-center gap-2">
                            <Info class="size-5" />
                            <span
                                >Profile Completeness: {{ completeness }}%</span
                            >
                        </div>
                        <span
                            v-if="lastSavedAt"
                            class="text-xs text-slate-500 italic opacity-70"
                            >Auto-saved at {{ lastSavedAt }}</span
                        >
                    </div>

                    <!-- Step 0: Basic Details -->
                    <div v-if="currentStep === 0">
                        <h3
                            class="mb-6 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            Basic Details
                        </h3>
                        <div class="grid gap-5 md:grid-cols-4">
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Gender
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.gender"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">---</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Date of Birth</Label
                                ><Input
                                    :model-value="
                                        profile.data?.dateOfBirth
                                            ? new Date(profile.data.dateOfBirth)
                                                  .toISOString()
                                                  .slice(0, 10)
                                            : ''
                                    "
                                    readonly
                                    class="bg-slate-50 dark:bg-white/5"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Height (cm)
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.height"
                                    type="number"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Weight (kg)
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.weight"
                                    type="number"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Blood Type
                                    <span class="text-red-500">*</span></Label
                                ><Input v-model="profileForm.bloodType" />
                            </div>
                            <div class="md:col-span-3">
                                <Label class="mb-1.5 block text-xs"
                                    >Place of Birth
                                    <span class="text-red-500">*</span></Label
                                ><Input v-model="profileForm.placeOfBirth" />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Tribe:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.tribeId"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option :value="''">---</option>
                                    <option
                                        v-for="t in props.lookups?.tribes ?? []"
                                        :key="t.tribeId"
                                        :value="t.tribeId"
                                    >
                                        {{ t.tribeName }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Civil Status:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.civilStatusId"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option :value="''">---</option>
                                    <option
                                        v-for="c in props.lookups
                                            ?.civilStatuses ?? []"
                                        :key="c.statusId"
                                        :value="c.statusId"
                                    >
                                        {{ c.civilDesc }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Religion:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.religionId"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option :value="''">---</option>
                                    <option
                                        v-for="r in props.lookups?.religions ??
                                        []"
                                        :key="r.religionId"
                                        :value="r.religionId"
                                    >
                                        {{ r.religion }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Nationality:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.nationalityId"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option :value="''">---</option>
                                    <option
                                        v-for="n in props.lookups
                                            ?.nationalities ?? []"
                                        :key="n.nationalityId"
                                        :value="n.nationalityId"
                                    >
                                        {{ n.nationality }}
                                    </option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <Label class="mb-1.5 block text-xs"
                                    >Mobile No.
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.mobileNo"
                                    placeholder="09XX-XXX-XXXX"
                                    @input="formatMobile('mobileNo')"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <Label class="mb-1.5 block text-xs"
                                    >Telephone No.</Label
                                ><Input
                                    v-model="profileForm.telNo"
                                    @input="formatMobile('telNo')"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Address -->
                    <div v-if="currentStep === 1">
                        <h3
                            class="mb-2 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            Address
                        </h3>
                        <p class="mb-8 text-sm text-slate-500">
                            Please provide accurate address information for
                            university correspondence.
                        </p>

                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 text-xs font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400"
                            >
                                Permanent Address
                            </h4>
                            <div class="grid gap-5 md:grid-cols-4">
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >House/Block/Lot No.
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.permAddress" />
                                </div>
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >Street
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.permStreet" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Province
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.permProvince"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="p in provinces"
                                            :key="p"
                                            :value="p"
                                        >
                                            {{ p }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Municipality/City
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.permTownCity"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="c in cities"
                                            :key="c"
                                            :value="c"
                                        >
                                            {{ c }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Barangay
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.permBarangay"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="b in barangays"
                                            :key="b"
                                            :value="b"
                                        >
                                            {{ b }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Zip Code
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.permZipCode"
                                        type="number"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="my-6 flex items-center gap-3 rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm transition-all hover:bg-sky-100/50 dark:border-sky-500/20 dark:bg-sky-500/5 dark:hover:bg-sky-500/10"
                        >
                            <input
                                id="same-as-perm"
                                v-model="sameAsPermanentAddress"
                                type="checkbox"
                                class="size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                            />
                            <Label
                                for="same-as-perm"
                                class="cursor-pointer text-sm font-bold text-sky-800 dark:text-sky-300"
                                >Residential address is the same as permanent
                                address</Label
                            >
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 transition-opacity dark:border-white/10 dark:bg-white/5"
                            :class="{
                                'pointer-events-none opacity-50':
                                    sameAsPermanentAddress,
                            }"
                        >
                            <h4
                                class="mb-5 text-xs font-bold tracking-widest text-slate-500 uppercase dark:text-slate-400"
                            >
                                Current / Residential Address
                            </h4>
                            <div class="grid gap-5 md:grid-cols-4">
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >House/Block/Lot No.
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.resAddress"
                                        :disabled="sameAsPermanentAddress"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >Street
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.resStreet"
                                        :disabled="sameAsPermanentAddress"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Province
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.resProvince"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950"
                                        :disabled="sameAsPermanentAddress"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="p in provinces"
                                            :key="`r-${p}`"
                                            :value="p"
                                        >
                                            {{ p }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Municipality/City
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.resTownCity"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950"
                                        :disabled="sameAsPermanentAddress"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="c in resCities"
                                            :key="`r-${c}`"
                                            :value="c"
                                        >
                                            {{ c }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Barangay
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><select
                                        v-model="profileForm.resBarangay"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:opacity-50 dark:border-white/10 dark:bg-slate-950"
                                        :disabled="sameAsPermanentAddress"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="b in resBarangays"
                                            :key="`r-${b}`"
                                            :value="b"
                                        >
                                            {{ b }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Zip Code
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.resZipCode"
                                        type="number"
                                        :disabled="sameAsPermanentAddress"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Guardian -->
                    <div v-if="currentStep === 2">
                        <h3
                            class="mb-6 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            GUARDIAN
                        </h3>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Guardian Name:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><Input v-model="profileForm.guardian" />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Guardian Address:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><Input v-model="profileForm.guardianAddress" />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Guardian Mobile No:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><Input
                                    v-model="profileForm.guardianTelNo"
                                    placeholder="09XX-XXX-XXXX"
                                    @input="formatMobile('guardianTelNo')"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Guardian Email:</Label
                                ><Input
                                    v-model="profileForm.guardianEmail"
                                    type="email"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Emergency -->
                    <div v-if="currentStep === 3">
                        <h3
                            class="mb-6 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            EMERGENCY CONTACT
                        </h3>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Emergency Contact Person:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><Input
                                    v-model="profileForm.emergencyContact"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Emergency Address
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.emergencyAddress"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Mobile Number
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.emergencyMobileNo"
                                    placeholder="09XX-XXX-XXXX"
                                    @input="formatMobile('emergencyMobileNo')"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Telephone Number</Label
                                ><Input
                                    v-model="profileForm.emergencyTelNo"
                                    @input="formatMobile('emergencyTelNo')"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Family Background -->
                    <div v-if="currentStep === 4">
                        <h3
                            class="mb-8 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            FAMILY BACKGROUND
                        </h3>

                        <div
                            class="mb-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                <User class="size-4 text-sky-600" /> Father's
                                Information
                            </h4>
                            <div class="grid gap-5 md:grid-cols-3">
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Father's Name
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.father" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Date of Birth
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherBirthDate"
                                        type="date"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Educational Attainment
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherEducAttain"
                                    />
                                </div>

                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Occupation
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherOccupation"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Company
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherCompany"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Company Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="
                                            profileForm.fatherCompanyAddress
                                        "
                                    />
                                </div>

                                <div class="md:col-span-1">
                                    <Label class="mb-1.5 block text-xs"
                                        >Contact Number
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherTelNo"
                                        placeholder="09XX-XXX-XXXX"
                                        @input="formatMobile('fatherTelNo')"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >Email Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherEmail"
                                        type="email"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Father Income:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="fatherIncomeRange"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="x in incomeRanges"
                                            :key="`f-${x.value}`"
                                            :value="x.value"
                                        >
                                            {{ x.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Father Citizenship:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.fatherCitizenship"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Fathers Nature of Work:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="profileForm.fatherNatureOfWork"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option :value="1">Armed Forces</option>
                                        <option :value="2">Managers</option>
                                        <option :value="3">
                                            Professionals
                                        </option>
                                        <option :value="4">
                                            Technicians and Associate
                                            Professionals
                                        </option>
                                        <option :value="5">
                                            Clerical Support Workers
                                        </option>
                                        <option :value="6">
                                            Service and Sales Workers
                                        </option>
                                        <option :value="7">
                                            Skilled Agricultural, Forestry and
                                            Fishery Workers
                                        </option>
                                        <option :value="8">
                                            Craft and Related Trades Workers
                                        </option>
                                        <option :value="9">
                                            Plant and Machine Operators and
                                            Assemblers
                                        </option>
                                        <option :value="10">
                                            Elementary Occupations
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >What is your Fathers Highest
                                        Educational Attainment?:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="
                                            profileForm.fatherEducationalAttainment
                                        "
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option>No formal Education</option>
                                        <option>Elementary Level</option>
                                        <option>Elementary Graduate</option>
                                        <option>
                                            Junior High School Level
                                        </option>
                                        <option>
                                            Junior High School Graduate
                                        </option>
                                        <option>
                                            Senior High School Level
                                        </option>
                                        <option>
                                            Senior High School Graduate
                                        </option>
                                        <option>
                                            Vocational/Technical Course
                                        </option>
                                        <option>College Level</option>
                                        <option>College Graduate</option>
                                        <option>Master's Degree</option>
                                        <option>Doctorate Degree</option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >What is your Fathers Employment
                                        Status?:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="
                                            profileForm.fatherEmploymentStatus
                                        "
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option :value="1">
                                            Unemployed, but is not seeking for
                                            employment
                                        </option>
                                        <option :value="2">
                                            Unemployed, but is actively seeking
                                            for employment
                                        </option>
                                        <option :value="3">
                                            Self-Employed
                                        </option>
                                        <option :value="4">
                                            Employed-Government
                                        </option>
                                        <option :value="5">
                                            Employed-Private
                                        </option>
                                        <option :value="6">
                                            Not Applicable
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                <User class="size-4 text-pink-600" /> Mother's
                                Information
                            </h4>
                            <div class="grid gap-5 md:grid-cols-3">
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Mother's Name
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.mother" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Date of Birth
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherBirthDate"
                                        type="date"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Educational Attainment
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherEducAttain"
                                    />
                                </div>

                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Occupation
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherOccupation"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Company
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherCompany"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Company Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="
                                            profileForm.motherCompanyAddress
                                        "
                                    />
                                </div>

                                <div class="md:col-span-1">
                                    <Label class="mb-1.5 block text-xs"
                                        >Contact Number
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherTelNo"
                                        placeholder="09XX-XXX-XXXX"
                                        @input="formatMobile('motherTelNo')"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <Label class="mb-1.5 block text-xs"
                                        >Email Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherEmail"
                                        type="email"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Mother Income:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="motherIncomeRange"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">---</option>
                                        <option
                                            v-for="x in incomeRanges"
                                            :key="`m-${x.value}`"
                                            :value="x.value"
                                        >
                                            {{ x.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Mother Citizenship:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.motherCitizenship"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Mothers Nature of Work:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="profileForm.motherNatureOfWork"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option :value="1">Armed Forces</option>
                                        <option :value="2">Managers</option>
                                        <option :value="3">
                                            Professionals
                                        </option>
                                        <option :value="4">
                                            Technicians and Associate
                                            Professionals
                                        </option>
                                        <option :value="5">
                                            Clerical Support Workers
                                        </option>
                                        <option :value="6">
                                            Service and Sales Workers
                                        </option>
                                        <option :value="7">
                                            Skilled Agricultural, Forestry and
                                            Fishery Workers
                                        </option>
                                        <option :value="8">
                                            Craft and Related Trades Workers
                                        </option>
                                        <option :value="9">
                                            Plant and Machine Operators and
                                            Assemblers
                                        </option>
                                        <option :value="10">
                                            Elementary Occupations
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >What is your Mother Highest Educational
                                        Attainment?:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="
                                            profileForm.motherEducationalAttainment
                                        "
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option>No formal Education</option>
                                        <option>Elementary Level</option>
                                        <option>Elementary Graduate</option>
                                        <option>
                                            Junior High School Level
                                        </option>
                                        <option>
                                            Junior High School Graduate
                                        </option>
                                        <option>
                                            Senior High School Level
                                        </option>
                                        <option>
                                            Senior High School Graduate
                                        </option>
                                        <option>
                                            Vocational/Technical Course
                                        </option>
                                        <option>College Level</option>
                                        <option>College Graduate</option>
                                        <option>Master's Degree</option>
                                        <option>Doctorate Degree</option>
                                    </select>
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >What is your Mother Employment Status?:
                                        <span class="text-red-500"
                                            ><sup>* required</sup></span
                                        ></Label
                                    ><select
                                        v-model="
                                            profileForm.motherEmploymentStatus
                                        "
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                    >
                                        <option value="">Please Select</option>
                                        <option :value="1">
                                            Unemployed, but is not seeking for
                                            employment
                                        </option>
                                        <option :value="2">
                                            Unemployed, but is actively seeking
                                            for employment
                                        </option>
                                        <option :value="3">
                                            Self-Employed
                                        </option>
                                        <option :value="4">
                                            Employed-Government
                                        </option>
                                        <option :value="5">
                                            Employed-Private
                                        </option>
                                        <option :value="6">
                                            Not Applicable
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-5 md:grid-cols-3">
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Number of Brothers
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.noofBrothers"
                                    type="number"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Number of Sisters
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.noofSisters"
                                    type="number"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Legitimate Child?
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.isIllegitimate"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Education -->
                    <div v-if="currentStep === 5">
                        <h3
                            class="mb-8 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            EDUCATIONAL BACKGROUND
                        </h3>

                        <div
                            class="mb-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                Elementary School
                            </h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >School Name
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.elemSchool" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.elemAddress" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Inclusive Dates
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.elemInclDates"
                                        placeholder="e.g. 2010 - 2016"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Awards & Honors (Optional)</Label
                                    ><Input
                                        v-model="profileForm.elemAwardHonor"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                High School
                            </h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >School Name
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.hsSchool" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Address
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input v-model="profileForm.hsAddress" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Inclusive Dates
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    ><Input
                                        v-model="profileForm.hsInclDates"
                                        placeholder="e.g. 2016 - 2020"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Awards & Honors (Optional)</Label
                                    ><Input
                                        v-model="profileForm.hsAwardHonor"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-8 rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <h4
                                class="mb-5 text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                Vocational / College
                            </h4>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Vocational/Trade School</Label
                                    ><Input v-model="profileForm.vocational" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Address</Label
                                    ><Input
                                        v-model="profileForm.vocationalAddress"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Inclusive Dates</Label
                                    ><Input
                                        v-model="
                                            profileForm.vocationalInclDates
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >College School</Label
                                    ><Input
                                        v-model="profileForm.collegeSchool"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Degree/Course</Label
                                    ><Input
                                        v-model="profileForm.collegeDegree"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Address</Label
                                    ><Input
                                        v-model="profileForm.collegeAddress"
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs"
                                        >Inclusive Dates</Label
                                    ><Input
                                        v-model="profileForm.collegeInclDates"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: CHED Details -->
                    <div v-if="currentStep === 6">
                        <h3
                            class="mb-6 text-lg font-extrabold tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            Additional Details for CHED
                        </h3>
                        <div class="grid gap-6 md:grid-cols-3">
                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                            >
                                <Label
                                    class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >Indigenous Peoples (IP) Member?
                                    <span class="text-red-500">*</span></Label
                                >
                                <select
                                    v-model="profileForm.ipMember"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div v-if="profileForm.ipMember" class="mt-4">
                                    <Label class="mb-1.5 block text-xs"
                                        >Specify Tribe
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="profileForm.ipMemberTribe"
                                    />
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                            >
                                <Label
                                    class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >PWD Member?
                                    <span class="text-red-500">*</span></Label
                                >
                                <select
                                    v-model="profileForm.pwdMember"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div
                                    v-if="profileForm.pwdMember"
                                    class="mt-4 space-y-4"
                                >
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >PWD ID Number
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.pwdMemberId"
                                        />
                                    </div>
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >PWD Category
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.pwdCategory"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/5"
                            >
                                <Label
                                    class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >Solo Parent?
                                    <span class="text-red-500">*</span></Label
                                >
                                <select
                                    v-model="profileForm.soloParent"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option :value="true">Yes</option>
                                    <option :value="false">No</option>
                                </select>
                                <div v-if="profileForm.soloParent" class="mt-4">
                                    <Label class="mb-1.5 block text-xs"
                                        >Solo Parent ID
                                        <span class="text-red-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input v-model="profileForm.soloParentId" />
                                </div>
                            </div>

                            <div
                                class="col-span-full my-2 border-t border-slate-100 dark:border-white/5"
                            ></div>

                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Are you raised by a solo parent? :
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.raisedBySoloParent"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Are you the first in your immediate family
                                    to attend college?:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.firstGenerationStudent"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Student Category
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.studentCategory"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Old">Old</option>
                                    <option value="New">New</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Family Size
                                    <span class="text-red-500">*</span></Label
                                ><Input
                                    v-model="profileForm.familySize"
                                    type="number"
                                />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Living in GIDA?
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.isGida"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Please specify (GIDA)
                                    <span class="text-red-500">*</span></Label
                                ><Input v-model="profileForm.descGida" />
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Belong to farmer/fisherfolk family?
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.isBelongToFarmer"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Belong to rebel returnees family?
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.isRebelReturnee"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <Label class="mb-1.5 block text-xs"
                                    >Last school attended type
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.lastSchoolAttendedType"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Please Select</option>
                                    <option :value="1">Private</option>
                                    <option :value="2">Public</option>
                                </select>
                            </div>

                            <div class="md:col-span-3">
                                <Label class="mb-1.5 block text-xs"
                                    >Student Type
                                    <span class="text-red-500">*</span></Label
                                ><select
                                    v-model="profileForm.studentType"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Senior HS Graduate">
                                        Senior HS Graduate
                                    </option>
                                    <option
                                        value="High School Graduate (Old Curriculum)"
                                    >
                                        High School Graduate (Old Curriculum)
                                    </option>
                                    <option
                                        value="Alternative Delivery Mode (Home School, IMPACT, MISOSA, Night High School, Open High School)"
                                    >
                                        Alternative Delivery Mode (Home School,
                                        IMPACT, MISOSA, Night High School, Open
                                        High School)
                                    </option>
                                    <option
                                        value="Alternative Learning System (ALS) Passer"
                                    >
                                        Alternative Learning System (ALS) Passer
                                    </option>
                                    <option value="Transferee">
                                        Transferee
                                    </option>
                                    <option
                                        value="Second Courser (Completed Degree in other school)"
                                    >
                                        Second Courser (Completed Degree in
                                        other school)
                                    </option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <Label class="mb-1.5 block text-xs"
                                    >Senior High School Strand and Track:
                                    <span class="text-red-500"
                                        ><sup>* required</sup></span
                                    ></Label
                                ><select
                                    v-model="profileForm.shsTrack"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="N/A">N/A</option>
                                    <option
                                        value="Academic - STEM (Science, Technology, Engineering, and Mathematics)"
                                    >
                                        Academic - STEM (Science, Technology,
                                        Engineering, and Mathematics)
                                    </option>
                                    <option
                                        value="Academic - ABM (Accountancy, Business, and Management)"
                                    >
                                        Academic - ABM (Accountancy, Business,
                                        and Management)
                                    </option>
                                    <option
                                        value="Academic - HUMSS (Humanities and Social Sciences)"
                                    >
                                        Academic - HUMSS (Humanities and Social
                                        Sciences)
                                    </option>
                                    <option
                                        value="Academic - GAS (General Academic Strand)"
                                    >
                                        Academic - GAS (General Academic Strand)
                                    </option>
                                    <option
                                        value="TVL - ICT (Information and Communication Technology)"
                                    >
                                        TVL - ICT (Information and Communication
                                        Technology)
                                    </option>
                                    <option value="TVL - HE (Home Economics)">
                                        TVL - HE (Home Economics)
                                    </option>
                                    <option value="TVL - IA (Industrial Arts)">
                                        TVL - IA (Industrial Arts)
                                    </option>
                                    <option value="TVL - Agri-Fishery Arts">
                                        TVL - Agri-Fishery Arts
                                    </option>
                                    <option value="Arts and Design">
                                        Arts and Design
                                    </option>
                                    <option value="Sports Track">
                                        Sports Track
                                    </option>
                                </select>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 md:col-span-1 dark:border-white/10 dark:bg-white/5"
                            >
                                <Label class="mb-1.5 block text-xs"
                                    >ADM Student?
                                    <span class="text-red-500">*</span></Label
                                >
                                <select
                                    v-model="profileForm.isAdm"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                                <div
                                    v-if="profileForm.isAdm === 'Yes'"
                                    class="mt-4 space-y-4"
                                >
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >School Name
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.admSchool"
                                        />
                                    </div>
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >SY Attended
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.admSchoolYear"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 md:col-span-1 dark:border-white/10 dark:bg-white/5"
                            >
                                <Label class="mb-1.5 block text-xs"
                                    >ALS Student?
                                    <span class="text-red-500">*</span></Label
                                >
                                <select
                                    v-model="profileForm.isAls"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-white/10 dark:bg-slate-950"
                                >
                                    <option value="">Please Select</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                                <div
                                    v-if="profileForm.isAls === 'Yes'"
                                    class="mt-4 space-y-4"
                                >
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >School Name
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.alsSchool"
                                        />
                                    </div>
                                    <div>
                                        <Label class="mb-1.5 block text-xs"
                                            >SY Attended
                                            <span class="text-red-500"
                                                >*</span
                                            ></Label
                                        ><Input
                                            v-model="profileForm.alsSchoolYear"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer for Actions -->
                <div
                    class="sticky bottom-0 z-20 border-t border-slate-200 bg-white/95 px-5 py-4 backdrop-blur-md sm:px-8 sm:py-5 dark:border-white/10 dark:bg-slate-950/95"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="relative flex size-12 items-center justify-center rounded-full bg-slate-50 dark:bg-white/5"
                            >
                                <svg
                                    class="size-10 -rotate-90 transform"
                                    viewBox="0 0 36 36"
                                >
                                    <path
                                        class="text-slate-200 dark:text-slate-800"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                    />
                                    <path
                                        class="text-sky-500 transition-all duration-500"
                                        :stroke-dasharray="`${completeness}, 100`"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="3"
                                    />
                                </svg>
                                <span
                                    class="absolute text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                    >{{ completeness }}%</span
                                >
                            </div>
                            <div class="flex flex-col">
                                <span
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >Progress</span
                                >
                                <span
                                    class="text-sm font-semibold text-slate-900 dark:text-white"
                                    >{{
                                        completeness === 100
                                            ? 'Ready to save!'
                                            : 'Keep going'
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <Button
                                variant="outline"
                                @click="editMode = false"
                                class="h-10 px-5 font-bold"
                                >Exit</Button
                            >
                            <Button
                                variant="outline"
                                :disabled="currentStep === 0"
                                @click="previousStep"
                                class="h-10 px-5 font-bold"
                                >Back</Button
                            >
                            <Button
                                v-if="currentStep < wizardSteps.length - 1"
                                @click="nextStep"
                                class="h-10 bg-slate-900 px-6 font-bold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                                >Next Step</Button
                            >
                            <Button
                                v-else
                                :disabled="profileForm.processing"
                                @click="saveProfile"
                                class="h-10 bg-sky-600 px-8 font-bold text-white shadow-lg shadow-sky-600/20 hover:bg-sky-700"
                            >
                                {{
                                    profileForm.processing
                                        ? 'Saving...'
                                        : 'Save Profile'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </Transition>
    </div>

    <!-- Modals for Achievements & Trainings -->
    <Dialog
        :open="achievementModalOpen"
        @update:open="achievementModalOpen = $event"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{
                    editingAchievement ? 'Edit Award' : 'Add New Award'
                }}</DialogTitle>
                <DialogDescription
                    >Add details about your achievements and academic
                    awards.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label>Award Title</Label
                    ><Input v-model="achievementForm.title" />
                </div>
                <div class="grid gap-2">
                    <Label>Awarding Body / Organization</Label
                    ><Input v-model="achievementForm.awarder" />
                </div>
                <div class="grid gap-2">
                    <Label>Date Received</Label
                    ><Input
                        v-model="achievementForm.date_received"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Description (Optional)</Label
                    ><Input v-model="achievementForm.description" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="achievementModalOpen = false"
                    >Cancel</Button
                >
                <Button
                    :disabled="achievementForm.processing"
                    @click="submitAchievement"
                    >{{ editingAchievement ? 'Update' : 'Save' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="trainingModalOpen" @update:open="trainingModalOpen = $event">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{
                    editingTraining ? 'Edit Training' : 'Add New Training'
                }}</DialogTitle>
                <DialogDescription
                    >Record seminars, workshops, or training sessions you've
                    attended.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label>Title / Seminar Name</Label
                    ><Input v-model="trainingForm.title" />
                </div>
                <div class="grid gap-2">
                    <Label>Organizer</Label
                    ><Input v-model="trainingForm.organizer" />
                </div>
                <div class="grid gap-2">
                    <Label>Start Date</Label
                    ><Input v-model="trainingForm.date_from" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label>Venue</Label><Input v-model="trainingForm.venue" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="trainingModalOpen = false"
                    >Cancel</Button
                >
                <Button
                    :disabled="trainingForm.processing"
                    @click="submitTraining"
                    >{{ editingTraining ? 'Update' : 'Save' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleteConfirmOpen" @update:open="deleteConfirmOpen = $event">
        <DialogContent class="sm:max-w-[400px]">
            <DialogHeader>
                <DialogTitle>Delete Confirmation</DialogTitle>
                <DialogDescription
                    >Are you sure you want to delete "{{
                        itemToDelete?.title
                    }}"? This action cannot be undone.</DialogDescription
                >
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteConfirmOpen = false"
                    >Cancel</Button
                >
                <Button
                    variant="destructive"
                    :disabled="
                        achievementForm.processing || trainingForm.processing
                    "
                    @click="executeDelete"
                    >Delete</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="categoryDetailsModalOpen"
        @update:open="categoryDetailsModalOpen = $event"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{
                    selectedPftCategoryDetails?.name
                }}</DialogTitle>
                <DialogDescription
                    >Physical Fitness Test Category Details</DialogDescription
                >
            </DialogHeader>
            <div class="py-4">
                <p
                    class="text-sm leading-relaxed whitespace-pre-line text-slate-600 dark:text-slate-300"
                >
                    {{
                        selectedPftCategoryDetails?.description ||
                        'No description available for this category.'
                    }}
                </p>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    @click="categoryDetailsModalOpen = false"
                    >Close</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div
        v-if="pftTermSummaryModalOpen"
        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
        @click.self="pftTermSummaryModalOpen = false"
    >
        <aside
            class="flex h-full w-full max-w-4xl flex-col bg-white shadow-2xl dark:bg-slate-950"
        >
            <div
                class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-white/10"
            >
                <div class="min-w-0 font-light">
                    <p
                        class="text-[11px] tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                    >
                        Physical Fitness Test
                    </p>
                    <h3 class="mt-1 text-lg text-slate-950 dark:text-white">
                        Summary
                    </h3>
                    <p
                        v-if="selectedPftSummaryTerm"
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        AY {{ selectedPftSummaryTerm.school_year }} ·
                        {{ selectedPftSummaryTerm.semester }} · Term ID
                        {{ selectedPftSummaryTerm.term_id }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 font-light"
                    @click="pftTermSummaryModalOpen = false"
                >
                    Close
                </Button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 font-light">
                <div
                    class="mb-4 rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                >
                    <div
                        class="grid gap-3 text-sm sm:grid-cols-3 dark:text-slate-200"
                    >
                        <div>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Completed Tests
                            </p>
                            <p
                                class="mt-1 text-xl text-emerald-700 dark:text-emerald-300"
                            >
                                {{ pftTermSummaryRows.length }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Components
                            </p>
                            <p
                                class="mt-1 text-xl text-emerald-700 dark:text-emerald-300"
                            >
                                {{ pftTermSummaryGroups.length }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Status
                            </p>
                            <p
                                class="mt-1 text-xl text-emerald-700 dark:text-emerald-300"
                            >
                                Complete
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <section
                        v-for="group in pftTermSummaryGroups"
                        :key="group.component.id"
                        class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 bg-slate-50 px-4 py-3 text-left transition hover:bg-emerald-50 focus:ring-2 focus:ring-emerald-500 focus:outline-none dark:bg-white/5 dark:hover:bg-emerald-500/10"
                            :aria-expanded="
                                openPftSummaryComponentIds.includes(
                                    group.component.id,
                                )
                            "
                            @click="
                                togglePftSummaryComponent(group.component.id)
                            "
                        >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm text-slate-950 dark:text-white"
                                >
                                    {{ group.component.name }}
                                </span>
                                <span
                                    class="mt-1 block text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ group.rows.length }} saved result{{
                                        group.rows.length === 1 ? '' : 's'
                                    }}
                                </span>
                            </span>
                            <ChevronDown
                                class="size-4 shrink-0 text-slate-400 transition-transform"
                                :class="
                                    openPftSummaryComponentIds.includes(
                                        group.component.id,
                                    )
                                        ? 'rotate-180'
                                        : ''
                                "
                            />
                        </button>

                        <div
                            v-if="
                                openPftSummaryComponentIds.includes(
                                    group.component.id,
                                )
                            "
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <article
                                v-for="row in group.rows"
                                :key="row.key"
                                class="p-4"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm text-slate-950 dark:text-white"
                                        >
                                            {{ row.testType.name }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ row.category.name }}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 shrink-0 text-xs font-light"
                                        @click="openPftSummary(row)"
                                    >
                                        View Details
                                    </Button>
                                </div>

                                <dl
                                    class="mt-3 grid gap-3 text-xs sm:grid-cols-2"
                                >
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            Tested at
                                        </dt>
                                        <dd
                                            class="text-slate-900 dark:text-white"
                                        >
                                            {{
                                                formatDate(
                                                    row.result?.tested_at ??
                                                        undefined,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            Interpretation
                                        </dt>
                                        <dd
                                            class="text-slate-900 dark:text-white"
                                        >
                                            {{ pftResultInterpretation(row) }}
                                        </dd>
                                    </div>
                                    <div
                                        v-for="field in row.testType
                                            .configurations"
                                        :key="`${row.key}:${field.id}`"
                                    >
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ field.field_label }}
                                        </dt>
                                        <dd
                                            class="text-slate-900 dark:text-white"
                                        >
                                            {{
                                                pftResultValue(
                                                    row.result?.results_json[
                                                        field.field_name
                                                    ],
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        </div>
                    </section>
                </div>
            </div>

        </aside>
    </div>

    <!-- Drawer Questionnaire -->
    <div
        v-if="evaluationDrawerOpen && activeEvaluationPeriod"
        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
        @click.self="evaluationDrawerOpen = false"
    >
        <aside class="flex h-full w-full max-w-[50rem] flex-col bg-white shadow-2xl dark:bg-slate-950 transition-all duration-300">
            <div class="flex min-h-0 flex-1 flex-col">
                <!-- Header -->
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">
                            CCD Cares Evaluation
                        </p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950 dark:text-white truncate">
                            {{ activeEvaluationPeriod.period.title }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Please answer all statements below honestly.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9"
                        @click="evaluationDrawerOpen = false"
                    >
                        Close
                    </Button>
                </div>

                <!-- Scrollable content -->
                <div class="flex-1 overflow-y-auto p-5 space-y-6">
                    <div v-if="activeEvaluationPeriod.period.description" class="text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-white/5 rounded-lg p-3 leading-relaxed">
                        {{ activeEvaluationPeriod.period.description }}
                    </div>

                    <div v-for="category in activeEvaluationPeriod.template.categories" :key="category.id" class="space-y-4">
                        <div class="border-b border-slate-100 pb-2 dark:border-white/10">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ category.name }}</h4>
                            <p v-if="category.description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-light">{{ category.description }}</p>
                        </div>

                        <div class="space-y-5">
                            <div v-for="(stmt, index) in category.statements" :key="stmt.id" class="space-y-2">
                                <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ index + 1 }}. {{ stmt.statement }}
                                    <span v-if="stmt.is_required" class="text-red-500">*</span>
                                </label>
                                <p v-if="stmt.help_text" class="text-[11px] text-slate-500 dark:text-slate-400 font-light">{{ stmt.help_text }}</p>
                                
                                <!-- Input types -->
                                <div class="mt-2">
                                    <!-- Likert / Rating Scale -->
                                    <div v-if="stmt.statement_type === 'likert' || stmt.statement_type === 'rating_scale'" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        <label
                                            v-for="opt in stmt.scale_options"
                                            :key="opt.id"
                                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border p-2 text-center transition hover:bg-slate-50 dark:hover:bg-white/5"
                                            :class="evaluationForm.answers[stmt.id] === opt.value
                                                ? 'border-emerald-600 bg-emerald-50/50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300'"
                                        >
                                            <input
                                                type="radio"
                                                :name="`answers-${stmt.id}`"
                                                :value="opt.value"
                                                v-model="evaluationForm.answers[stmt.id]"
                                                class="sr-only"
                                            />
                                            <span class="text-xs font-bold">{{ opt.value }}</span>
                                            <span class="mt-0.5 text-[9px] leading-tight font-medium opacity-80">{{ opt.label }}</span>
                                        </label>
                                    </div>

                                    <!-- Multiple Choice / Yes No -->
                                    <div v-else-if="stmt.statement_type === 'multiple_choice' || stmt.statement_type === 'yes_no'" class="grid grid-cols-2 gap-2">
                                        <label
                                            v-for="opt in stmt.choices"
                                            :key="opt.id"
                                            class="flex cursor-pointer items-center justify-center rounded-lg border p-2 text-center transition hover:bg-slate-50 dark:hover:bg-white/5"
                                            :class="evaluationForm.answers[stmt.id] === opt.value
                                                ? 'border-emerald-600 bg-emerald-50/50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300'"
                                        >
                                            <input
                                                type="radio"
                                                :name="`answers-${stmt.id}`"
                                                :value="opt.value"
                                                v-model="evaluationForm.answers[stmt.id]"
                                                class="sr-only"
                                            />
                                            <span class="text-xs font-semibold">{{ opt.label }}</span>
                                        </label>
                                    </div>

                                    <!-- Checkbox -->
                                    <div v-else-if="stmt.statement_type === 'checkbox'" class="grid grid-cols-2 gap-2">
                                        <label
                                            v-for="opt in stmt.choices"
                                            :key="opt.id"
                                            class="flex cursor-pointer items-center justify-center rounded-lg border p-2 text-center transition hover:bg-slate-50 dark:hover:bg-white/5"
                                            :class="(evaluationForm.answers[stmt.id] || []).includes(opt.value)
                                                ? 'border-emerald-600 bg-emerald-50/50 text-emerald-800 dark:border-emerald-500 dark:bg-emerald-950/30 dark:text-emerald-300'
                                                : 'border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300'"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="opt.value"
                                                v-model="evaluationForm.answers[stmt.id]"
                                                class="sr-only"
                                            />
                                            <span class="text-xs font-semibold">{{ opt.label }}</span>
                                        </label>
                                    </div>

                                    <!-- Numeric -->
                                    <div v-else-if="stmt.statement_type === 'numeric_score'">
                                        <Input
                                            type="number"
                                            v-model.number="evaluationForm.answers[stmt.id]"
                                            class="w-full"
                                            placeholder="Enter numeric response"
                                        />
                                    </div>

                                    <!-- Default Text -->
                                    <div v-else>
                                        <Input
                                            type="text"
                                            v-model="evaluationForm.answers[stmt.id]"
                                            class="w-full"
                                            placeholder="Type your answer here..."
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="evaluationForm.errors[`answers.${stmt.id}`]"
                                    class="text-xs text-red-600 dark:text-red-400 mt-1 font-semibold"
                                >
                                    {{ evaluationForm.errors[`answers.${stmt.id}`] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-slate-100 px-5 py-4 flex items-center justify-end gap-3 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9"
                        @click="evaluationDrawerOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="h-9 bg-emerald-600 hover:bg-emerald-700 text-white dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 font-semibold"
                        :disabled="evaluationForm.processing"
                        @click="submitEvaluation"
                    >
                        {{ evaluationForm.processing ? 'Submitting...' : 'Submit Evaluation' }}
                    </Button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Results Interpretation Dialog -->
    <Dialog
        :open="resultsModalOpen"
        @update:open="resultsModalOpen = $event"
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[640px]">
            <DialogHeader>
                <DialogTitle>CCD Cares Evaluation Results</DialogTitle>
                <DialogDescription>
                    Interpretation of your submitted responses.
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedAssessment && selectedAssessment.submission" class="py-4 space-y-6">
                <!-- Summary Info -->
                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-xs border-b border-slate-100 dark:border-white/10 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400">Evaluation:</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">{{ selectedAssessment.period.title }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400">Submitted at:</span>
                        <span class="font-medium text-slate-800 dark:text-slate-200">
                            {{ formatDate(selectedAssessment.submission.submitted_at) }}
                        </span>
                    </div>
                </div>

                <!-- Subscale Score Chart -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                        Subscale Score Chart (DASS-42 Equivalent)
                    </h3>
                    <div class="h-[240px]">
                        <VueApexCharts
                            type="bar"
                            height="240"
                            :options="ccdCaresChartOptions"
                            :series="ccdCaresChartSeries"
                        />
                    </div>
                </div>

                <!-- Subscale Results Table -->
                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Subscale Results</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/10 text-slate-400 font-medium">
                                    <th class="py-2 font-semibold">Subscale</th>
                                    <th class="py-2 text-center font-semibold w-24">Score</th>
                                    <th class="py-2 text-right font-semibold w-40">Classification</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr 
                                    v-for="res in selectedAssessment.submission.interpretation_results"
                                    :key="res.category_id"
                                    class="text-slate-700 dark:text-slate-200"
                                >
                                    <td class="py-3 font-semibold uppercase tracking-wider text-slate-900 dark:text-white">
                                        {{ res.category_name }}
                                    </td>
                                    <td class="py-3 text-center font-bold text-sm">
                                        {{ res.score }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <span 
                                            class="inline-block px-2.5 py-1 text-[10px] font-bold rounded-md uppercase tracking-wider border"
                                            :class="getInterpretationColorClass(res.interpretation)"
                                        >
                                            {{ res.interpretation }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action Plans / Interventions -->
                <div 
                    v-if="selectedAssessment.submission.interpretation_results.some(r => r.suggested_intervention)"
                    class="space-y-3 pt-4 border-t border-slate-100 dark:border-white/10"
                >
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                        Suggested Interventions & Action Plans
                    </h3>
                    <div class="divide-y divide-slate-100 dark:divide-white/10 text-xs">
                        <div 
                            v-for="res in selectedAssessment.submission.interpretation_results"
                            :key="'intervention-' + res.category_id"
                            v-show="res.suggested_intervention"
                            class="py-3 first:pt-0 last:pb-0"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                                    {{ res.category_name }}
                                </span>
                                <span 
                                    class="text-[9px] px-1.5 py-0.5 rounded font-bold uppercase tracking-wider border scale-90 origin-left"
                                    :class="getInterpretationColorClass(res.interpretation)"
                                >
                                    {{ res.interpretation }}
                                </span>
                            </div>
                            <p class="text-slate-600 dark:text-slate-400 leading-relaxed font-light whitespace-pre-line">
                                {{ res.suggested_intervention }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="resultsModalOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="pftSummaryModalOpen"
        @update:open="pftSummaryModalOpen = $event"
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[560px]">
            <DialogHeader>
                <DialogTitle>{{
                    selectedPftSummaryRow?.testType.name
                }}</DialogTitle>
                <DialogDescription>
                    Summary of the saved physical fitness test result.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="selectedPftSummaryRow?.result"
                class="space-y-4 py-4 font-light"
            >
                <div
                    class="grid gap-3 rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 text-xs dark:border-emerald-500/20 dark:bg-emerald-500/10"
                >
                    <div class="grid gap-1 sm:grid-cols-[120px_1fr]">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Term</span
                        >
                        <span class="text-slate-900 dark:text-white">
                            AY
                            {{ selectedPftSummaryRow.term.school_year }} ·
                            {{ selectedPftSummaryRow.term.semester }} · Term ID
                            {{ selectedPftSummaryRow.term.term_id }}
                        </span>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[120px_1fr]">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Component</span
                        >
                        <span class="text-slate-900 dark:text-white">
                            {{ selectedPftSummaryRow.component.name }}
                        </span>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[120px_1fr]">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Category</span
                        >
                        <span class="text-slate-900 dark:text-white">
                            {{ selectedPftSummaryRow.category.name }}
                        </span>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[120px_1fr]">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Tested at</span
                        >
                        <span class="text-slate-900 dark:text-white">
                            {{
                                formatDate(
                                    selectedPftSummaryRow.result.tested_at ??
                                        undefined,
                                )
                            }}
                        </span>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[120px_1fr]">
                        <span class="text-slate-500 dark:text-slate-400">
                            Interpretation
                        </span>
                        <span class="text-slate-900 dark:text-white">
                            {{ pftResultInterpretation(selectedPftSummaryRow) }}
                        </span>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                >
                    <div
                        v-for="field in pftSummaryFields"
                        :key="field.label"
                        class="grid gap-1 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0 sm:grid-cols-[160px_1fr] dark:border-white/10"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ field.label }}
                        </span>
                        <span class="text-slate-900 dark:text-white">
                            {{ field.value }}
                        </span>
                    </div>
                    <div
                        class="grid gap-1 border-t border-slate-100 px-4 py-3 text-sm sm:grid-cols-[160px_1fr] dark:border-white/10"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            Remarks
                        </span>
                        <span class="text-slate-900 dark:text-white">
                            {{
                                pftResultValue(
                                    selectedPftSummaryRow.result.remarks,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="font-light"
                    @click="pftSummaryModalOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style>
.fade-transform-enter-active,
.fade-transform-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-transform-enter-from {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
}

.fade-transform-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(1.02);
}

.dark .flex-1:has(.shadow-2xl) {
    background-color: rgba(2, 6, 23, 0.4);
    transition: background-color 0.5s ease;
}

.pft-profile-input {
    min-height: 2.5rem;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: rgb(15 23 42);
    outline: none;
}

.pft-profile-input:focus {
    border-color: rgb(16 185 129);
    box-shadow: 0 0 0 1px rgb(16 185 129);
}

.dark .pft-profile-input {
    border-color: rgb(255 255 255 / 0.1);
    background: rgb(2 6 23);
    color: rgb(241 245 249);
}
</style>
