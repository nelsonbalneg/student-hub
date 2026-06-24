<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    Calendar,
    CheckCircle2,
    ClipboardCheck,
    Edit2,
    Eye,
    FileText,
    Loader2,
    MessageSquare,
    Plus,
    RefreshCcw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

type Period = {
    id: number;
    campus_id: number | null;
    office_id: number | null;
    title: string;
    description: string | null;
    academic_year: string;
    semester: string;
    start_date: string;
    end_date: string;
    status: string;
    creator: { name: string } | null;
    requests_count: number | null;
    campus: { id: number; name: string } | null;
    office: {
        id: number;
        name: string;
        code: string | null;
        category: string;
    } | null;
};

type Campus = {
    id: number;
    campus_name: string;
};

type Office = {
    id: number;
    campus_id: number;
    name: string;
    code: string | null;
    category: string;
};

type EvaluationRequest = {
    id: number;
    student_no: string | null;
    intent: string;
    status: string;
    registrar_feedback: string | null;
    created_at: string;
    period: Period | null;
    student: {
        name: string;
        email: string | null;
        student_no: string | null;
    } | null;
    evaluator: { name: string } | null;
};

type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
};

const props = defineProps<{
    periods: Page<Period>;
    requests: Page<EvaluationRequest>;
    filters: Record<string, string | undefined>;
    filterOptions: {
        periods: Period[];
        statuses: string[];
        periodStatuses: string[];
        semesters: string[];
    };
    reports: {
        periods_total: number;
        active_periods: number;
        requests_total: number;
        done_requests: number;
        by_status: Record<string, number>;
    };
    campuses: Campus[];
    offices: Office[];
    can: {
        createPeriod: boolean;
        editPeriod: boolean;
        deletePeriod: boolean;
        evaluate: boolean;
        feedback: boolean;
        markDone: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student Services', href: '#' },
            { title: 'Manage Evaluations', href: '/admin/evaluations' },
        ],
    },
});

const activeTab = ref(props.filters.tab ?? 'periods');
const periodSearch = ref(props.filters.period_search ?? '');
const requestSearch = ref(props.filters.request_search ?? '');
const filterOpen = ref(false);
const requestFilters = ref({
    period_id: props.filters.period_id ?? '',
    status: props.filters.status ?? '',
    semester: props.filters.semester ?? '',
});
const periodModal = ref(false);
const editingPeriod = ref<Period | null>(null);
const deletePeriodTarget = ref<Period | null>(null);
const statusTarget = ref<{ period: Period; status: string } | null>(null);
const requestStatusTarget = ref<{
    request: EvaluationRequest;
    status: string;
} | null>(null);
const feedbackTarget = ref<{
    request: EvaluationRequest;
    status: string | null;
} | null>(null);

const periodForm = useForm({
    campus_id: '' as number | string,
    office_id: '' as number | string,
    title: '',
    description: '',
    academic_year: '',
    semester: '',
    start_date: '',
    end_date: '',
    status: 'draft',
});

const filteredOffices = computed(() => {
    if (!periodForm.campus_id) {
        return [];
    }

    return props.offices.filter(
        (office) => office.campus_id === Number(periodForm.campus_id),
    );
});

watch(
    () => periodForm.campus_id,
    (campusId) => {
        const selectedOffice = props.offices.find(
            (office) => office.id === Number(periodForm.office_id),
        );

        if (selectedOffice && selectedOffice.campus_id !== Number(campusId)) {
            periodForm.office_id = '';
        }
    },
);

const actionForm = useForm({
    status: '',
});
const feedbackForm = useForm({
    message: '',
    visibility: 'student_visible',
    status: '',
});

const statusStyles: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    closed: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200',
    archived:
        'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300',
    submitted:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200',
    under_evaluation:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    needs_action:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200',
    resolved:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    done: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200',
    cancelled:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200',
};

const activeFilterCount = computed(
    () => Object.values(requestFilters.value).filter(Boolean).length,
);

const formatDate = (value: string | null) => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const openCreatePeriod = () => {
    editingPeriod.value = null;
    periodForm.reset();
    periodForm.status = 'draft';
    periodForm.campus_id = '';
    periodForm.office_id = '';
    periodModal.value = true;
};

const openEditPeriod = (period: Period) => {
    editingPeriod.value = period;
    periodForm.campus_id = period.campus_id ?? '';
    periodForm.office_id = period.office_id ?? '';
    periodForm.title = period.title;
    periodForm.description = period.description ?? '';
    periodForm.academic_year = period.academic_year;
    periodForm.semester = period.semester;
    periodForm.start_date = String(period.start_date).slice(0, 10);
    periodForm.end_date = String(period.end_date).slice(0, 10);
    periodForm.status = period.status;
    periodModal.value = true;
};

const savePeriod = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            periodModal.value = false;
            editingPeriod.value = null;
            periodForm.reset();
        },
    };

    if (editingPeriod.value) {
        periodForm.patch(
            `/admin/evaluations/periods/${editingPeriod.value.id}`,
            options,
        );

        return;
    }

    periodForm.post('/admin/evaluations/periods', options);
};

const applyFilters = () => {
    router.get(
        '/admin/evaluations',
        {
            tab: activeTab.value,
            period_search: periodSearch.value,
            request_search: requestSearch.value,
            ...requestFilters.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

let periodSearchTimeout: ReturnType<typeof setTimeout>;
let requestSearchTimeout: ReturnType<typeof setTimeout>;
watch(periodSearch, () => {
    clearTimeout(periodSearchTimeout);
    periodSearchTimeout = setTimeout(applyFilters, 400);
});
watch(requestSearch, () => {
    clearTimeout(requestSearchTimeout);
    requestSearchTimeout = setTimeout(applyFilters, 400);
});
watch(requestFilters, applyFilters, { deep: true });
watch(activeTab, applyFilters);

const clearRequestFilters = () => {
    requestSearch.value = '';
    requestFilters.value = { period_id: '', status: '', semester: '' };
};

const updatePeriodStatus = () => {
    if (!statusTarget.value) {
        return;
    }

    actionForm.status = statusTarget.value.status;
    actionForm.patch(
        `/admin/evaluations/periods/${statusTarget.value.period.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => (statusTarget.value = null),
        },
    );
};

const destroyPeriod = () => {
    if (!deletePeriodTarget.value) {
        return;
    }

    actionForm.delete(
        `/admin/evaluations/periods/${deletePeriodTarget.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => (deletePeriodTarget.value = null),
        },
    );
};

const updateRequestStatus = () => {
    if (!requestStatusTarget.value) {
        return;
    }

    actionForm.status = requestStatusTarget.value.status;
    actionForm.patch(
        `/admin/evaluations/requests/${requestStatusTarget.value.request.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => (requestStatusTarget.value = null),
        },
    );
};

const openFeedback = (
    request: EvaluationRequest,
    status: string | null = null,
) => {
    feedbackTarget.value = { request, status };
    feedbackForm.reset();
    feedbackForm.visibility = 'student_visible';
    feedbackForm.status = status ?? '';
};

const saveFeedback = () => {
    if (!feedbackTarget.value) {
        return;
    }

    feedbackForm.post(
        `/admin/evaluations/requests/${feedbackTarget.value.request.id}/feedback`,
        {
            preserveScroll: true,
            onSuccess: () => {
                feedbackTarget.value = null;
                feedbackForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head title="Evaluation Management" />

    <div class="flex h-full flex-1 flex-col gap-3 p-3">
        <section>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1
                        class="text-base font-bold text-slate-800 dark:text-white"
                    >
                        Manage Evaluations
                    </h1>
                    <p class="text-xs text-slate-400">
                        Manage evaluation periods and process student requests.
                    </p>
                </div>

                <Button
                    v-if="can.createPeriod"
                    class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700"
                    @click="openCreatePeriod"
                >
                    <Plus class="size-3.5" />
                    New Period
                </Button>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div
                v-for="item in [
                    { label: 'Periods', value: reports.periods_total },
                    { label: 'Active', value: reports.active_periods },
                    { label: 'Requests', value: reports.requests_total },
                    { label: 'Completed', value: reports.done_requests },
                ]"
                :key="item.label"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-slate-950"
            >
                <p class="text-[10px] font-medium text-slate-400 uppercase">
                    {{ item.label }}
                </p>
                <p
                    class="mt-0.5 text-lg font-semibold text-slate-800 dark:text-white"
                >
                    {{ item.value }}
                </p>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-3">
            <aside
                class="flex items-center gap-1 overflow-x-auto border-b border-slate-200 dark:border-white/10"
            >
                <button
                    v-for="tab in [
                        {
                            key: 'periods',
                            label: 'Evaluation Periods',
                            icon: Calendar,
                        },
                        {
                            key: 'requests',
                            label: 'Student Requests',
                            icon: FileText,
                        },
                        { key: 'reports', label: 'Reports', icon: ShieldCheck },
                    ]"
                    :key="tab.key"
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-xs font-semibold transition"
                    :class="
                        activeTab === tab.key
                            ? 'border-emerald-500 text-emerald-700 dark:text-emerald-300'
                            : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                </button>
            </aside>

            <section
                v-if="activeTab === 'periods'"
                class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-white/10"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="periodSearch"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs font-medium dark:border-white/10 dark:bg-slate-900 dark:text-white"
                            placeholder="Search periods..."
                        />
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead
                            class="bg-slate-50/80 text-left text-[11px] font-semibold text-slate-500 uppercase dark:bg-white/[0.03]"
                        >
                            <tr>
                                <th class="px-4 py-3">Title</th>
                                <th class="px-4 py-3">Campus</th>
                                <th class="px-4 py-3">College / Office</th>
                                <th class="px-4 py-3">Academic Year</th>
                                <th class="px-4 py-3">Semester</th>
                                <th class="px-4 py-3">Start Date</th>
                                <th class="px-4 py-3">End Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Created By</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="period in periods.data"
                                :key="period.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td class="px-4 py-3">
                                    <p
                                        class="font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ period.title }}
                                    </p>
                                    <p
                                        class="text-[10px] font-medium text-slate-400"
                                    >
                                        {{ period.requests_count ?? 0 }}
                                        requests
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    {{ period.campus?.name ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <p
                                        class="text-xs font-medium text-slate-700 dark:text-slate-200"
                                    >
                                        {{ period.office?.name ?? '-' }}
                                    </p>
                                    <p
                                        v-if="period.office"
                                        class="mt-0.5 text-[10px] text-slate-400"
                                    >
                                        {{
                                            period.office.category ===
                                            'academic'
                                                ? 'College'
                                                : 'Office'
                                        }}
                                        <template v-if="period.office.code">
                                            · {{ period.office.code }}
                                        </template>
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ period.academic_year }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ period.semester }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-500"
                                >
                                    {{ formatDate(period.start_date) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-500"
                                >
                                    {{ formatDate(period.end_date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize',
                                            statusStyles[period.status],
                                        ]"
                                        >{{ period.status }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ period.creator?.name ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            v-if="can.editPeriod"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs"
                                            @click="openEditPeriod(period)"
                                            ><Edit2
                                                class="mr-1 size-3.5"
                                            />Edit</Button
                                        >
                                        <Button
                                            v-if="
                                                can.editPeriod &&
                                                period.status !== 'active'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-emerald-600"
                                            @click="
                                                statusTarget = {
                                                    period,
                                                    status: 'active',
                                                }
                                            "
                                            >Activate</Button
                                        >
                                        <Button
                                            v-if="
                                                can.editPeriod &&
                                                period.status === 'active'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs"
                                            @click="
                                                statusTarget = {
                                                    period,
                                                    status: 'closed',
                                                }
                                            "
                                            >Close</Button
                                        >
                                        <Button
                                            v-if="
                                                can.editPeriod &&
                                                period.status !== 'archived'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs"
                                            @click="
                                                statusTarget = {
                                                    period,
                                                    status: 'archived',
                                                }
                                            "
                                            ><Archive
                                                class="mr-1 size-3.5"
                                            />Archive</Button
                                        >
                                        <Button
                                            v-if="can.deletePeriod"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-red-600"
                                            @click="deletePeriodTarget = period"
                                            ><Trash2
                                                class="mr-1 size-3.5"
                                            />Delete</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="periods.data.length === 0">
                                <td colspan="10" class="p-10 text-center">
                                    <ClipboardCheck
                                        class="mx-auto size-10 text-slate-300 dark:text-slate-600"
                                    />
                                    <p
                                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No evaluation periods found
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-else-if="activeTab === 'requests'"
                class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="border-b border-slate-100 px-4 py-3 dark:border-white/10"
                >
                    <div
                        class="flex flex-col gap-2 lg:flex-row lg:items-center"
                    >
                        <div class="relative flex-1">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="requestSearch"
                                class="h-9 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs font-medium dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                placeholder="Search student no or name..."
                            />
                        </div>
                        <Button
                            variant="outline"
                            class="h-9 rounded-md text-xs font-bold"
                            @click="filterOpen = !filterOpen"
                        >
                            <SlidersHorizontal class="mr-2 size-4" />
                            Filters
                            <span
                                v-if="activeFilterCount"
                                class="ml-1 rounded-full bg-emerald-600 px-1.5 text-[10px] text-white"
                                >{{ activeFilterCount }}</span
                            >
                        </Button>
                        <Button
                            variant="outline"
                            class="h-9 rounded-md text-xs font-bold"
                            @click="clearRequestFilters"
                        >
                            <RefreshCcw class="mr-2 size-4" />
                            Reset
                        </Button>
                    </div>
                    <div
                        v-if="filterOpen"
                        class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <select
                            v-model="requestFilters.period_id"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">All Periods</option>
                            <option
                                v-for="period in filterOptions.periods"
                                :key="period.id"
                                :value="period.id"
                            >
                                {{ period.title }}
                            </option>
                        </select>
                        <select
                            v-model="requestFilters.status"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">All Statuses</option>
                            <option
                                v-for="status in filterOptions.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status.replace('_', ' ') }}
                            </option>
                        </select>
                        <select
                            v-model="requestFilters.semester"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">All Semesters</option>
                            <option
                                v-for="semester in filterOptions.semesters"
                                :key="semester"
                                :value="semester"
                            >
                                {{ semester }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-[1180px] text-sm">
                        <thead
                            class="bg-slate-50/80 text-left text-[11px] font-semibold text-slate-500 uppercase dark:bg-white/[0.03]"
                        >
                            <tr>
                                <th class="px-4 py-3">Student No</th>
                                <th class="px-4 py-3">Student Name</th>
                                <th class="px-4 py-3">Evaluation Period</th>
                                <th class="px-4 py-3">Intent</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3">Evaluated By</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="request in requests.data"
                                :key="request.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td
                                    class="px-4 py-3 text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{ request.student_no ?? '-' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ request.student?.name ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <p
                                        class="text-xs font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ request.period?.title }}
                                    </p>
                                    <p
                                        class="text-[10px] font-bold text-slate-400 uppercase"
                                    >
                                        {{ request.period?.semester }}
                                    </p>
                                </td>
                                <td
                                    class="max-w-[260px] truncate px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ request.intent }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize',
                                            statusStyles[request.status],
                                        ]"
                                        >{{
                                            request.status.replace('_', ' ')
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-500"
                                >
                                    {{ formatDate(request.created_at) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                                >
                                    {{ request.evaluator?.name ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Link
                                            :href="`/admin/evaluations/requests/${request.id}`"
                                            ><Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 text-xs"
                                                ><Eye
                                                    class="mr-1 size-3.5"
                                                />View</Button
                                            ></Link
                                        >
                                        <Button
                                            v-if="
                                                can.evaluate &&
                                                request.status === 'submitted'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-emerald-600"
                                            @click="
                                                requestStatusTarget = {
                                                    request,
                                                    status: 'under_evaluation',
                                                }
                                            "
                                            >Start</Button
                                        >
                                        <Button
                                            v-if="can.feedback"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs"
                                            @click="openFeedback(request, null)"
                                            ><MessageSquare
                                                class="mr-1 size-3.5"
                                            />Feedback</Button
                                        >
                                        <Button
                                            v-if="can.feedback"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-amber-600"
                                            @click="
                                                openFeedback(
                                                    request,
                                                    'needs_action',
                                                )
                                            "
                                            >Needs Action</Button
                                        >
                                        <Button
                                            v-if="can.feedback"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-emerald-600"
                                            @click="
                                                openFeedback(
                                                    request,
                                                    'resolved',
                                                )
                                            "
                                            >Resolved</Button
                                        >
                                        <Button
                                            v-if="
                                                can.markDone &&
                                                request.status !== 'done'
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs"
                                            @click="
                                                requestStatusTarget = {
                                                    request,
                                                    status: 'done',
                                                }
                                            "
                                            ><CheckCircle2
                                                class="mr-1 size-3.5"
                                            />Done</Button
                                        >
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="requests.data.length === 0">
                                <td colspan="8" class="p-10 text-center">
                                    <FileText
                                        class="mx-auto size-10 text-slate-300 dark:text-slate-600"
                                    />
                                    <p
                                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No student requests found
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-else
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                    Reports
                </h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="status in filterOptions.statuses"
                        :key="status"
                        class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-[10px] font-bold text-slate-400 uppercase"
                        >
                            {{ status.replace('_', ' ') }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                        >
                            {{ reports.by_status[status] ?? 0 }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div
        v-if="periodModal"
        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
        @click.self="periodModal = false"
    >
        <div
            class="flex h-full w-full max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <div>
                    <p
                        class="text-[10px] font-semibold text-emerald-600 uppercase"
                    >
                        Evaluation period
                    </p>
                    <h2
                        class="mt-1 text-base font-semibold text-slate-900 dark:text-white"
                    >
                        {{
                            editingPeriod ? 'Edit period' : 'Create new period'
                        }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Set the academic term, availability, and publishing
                        status.
                    </p>
                </div>
                <button
                    class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10"
                    @click="periodModal = false"
                >
                    <X class="size-4" />
                </button>
            </div>
            <div
                class="grid flex-1 content-start gap-4 overflow-y-auto p-5 md:grid-cols-2"
            >
                <label class="grid gap-2 md:col-span-2">
                    <span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                    >
                        Campus
                    </span>
                    <select
                        v-model="periodForm.campus_id"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                    >
                        <option value="" disabled>Select campus first</option>
                        <option
                            v-for="campus in campuses"
                            :key="campus.id"
                            :value="campus.id"
                        >
                            {{ campus.campus_name }}
                        </option>
                    </select>
                    <InputError :message="periodForm.errors.campus_id" />
                </label>
                <label class="grid gap-2 md:col-span-2">
                    <span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                    >
                        College or Office
                    </span>
                    <select
                        v-model="periodForm.office_id"
                        :disabled="!periodForm.campus_id"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-900/50"
                    >
                        <option value="" disabled>
                            {{
                                periodForm.campus_id
                                    ? 'Select college or office'
                                    : 'Select a campus first'
                            }}
                        </option>
                        <optgroup
                            v-if="
                                filteredOffices.some(
                                    (office) => office.category === 'academic',
                                )
                            "
                            label="Colleges"
                        >
                            <option
                                v-for="office in filteredOffices.filter(
                                    (item) => item.category === 'academic',
                                )"
                                :key="office.id"
                                :value="office.id"
                            >
                                {{ office.name }}
                                {{ office.code ? `(${office.code})` : '' }}
                            </option>
                        </optgroup>
                        <optgroup
                            v-if="
                                filteredOffices.some(
                                    (office) => office.category !== 'academic',
                                )
                            "
                            label="Offices"
                        >
                            <option
                                v-for="office in filteredOffices.filter(
                                    (item) => item.category !== 'academic',
                                )"
                                :key="office.id"
                                :value="office.id"
                            >
                                {{ office.name }}
                                {{ office.code ? `(${office.code})` : '' }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="text-[10px] text-slate-400">
                        Options are loaded from the selected campus.
                    </p>
                    <InputError :message="periodForm.errors.office_id" />
                </label>
                <label class="grid gap-2 md:col-span-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Title</span
                    ><input
                        v-model="periodForm.title"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white" /><InputError
                        :message="periodForm.errors.title"
                /></label>
                <label class="grid gap-2 md:col-span-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Description</span
                    ><textarea
                        v-model="periodForm.description"
                        class="min-h-24 rounded-lg border border-slate-200 p-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                    ></textarea
                    ><InputError :message="periodForm.errors.description"
                /></label>
                <label class="grid gap-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Academic Year</span
                    ><input
                        v-model="periodForm.academic_year"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                        placeholder="2026-2027" /><InputError
                        :message="periodForm.errors.academic_year"
                /></label>
                <label class="grid gap-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Semester</span
                    ><input
                        v-model="periodForm.semester"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                        placeholder="1st Semester" /><InputError
                        :message="periodForm.errors.semester"
                /></label>
                <label class="grid gap-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Start Date</span
                    ><input
                        v-model="periodForm.start_date"
                        type="date"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white" /><InputError
                        :message="periodForm.errors.start_date"
                /></label>
                <label class="grid gap-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >End Date</span
                    ><input
                        v-model="periodForm.end_date"
                        type="date"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white" /><InputError
                        :message="periodForm.errors.end_date"
                /></label>
                <label class="grid gap-2"
                    ><span
                        class="text-[11px] font-semibold text-slate-500 uppercase"
                        >Status</span
                    ><select
                        v-model="periodForm.status"
                        class="h-9 rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                    >
                        <option
                            v-for="status in filterOptions.periodStatuses"
                            :key="status"
                            :value="status"
                        >
                            {{ status }}
                        </option></select
                    ><InputError :message="periodForm.errors.status"
                /></label>
            </div>
            <div
                class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <Button variant="outline" @click="periodModal = false"
                    >Cancel</Button
                >
                <Button
                    class="bg-emerald-600 text-white hover:bg-emerald-700"
                    :disabled="periodForm.processing"
                    @click="savePeriod"
                    ><Loader2
                        v-if="periodForm.processing"
                        class="mr-2 size-4 animate-spin"
                    />{{
                        editingPeriod ? 'Save changes' : 'Create draft'
                    }}</Button
                >
            </div>
        </div>
    </div>

    <div
        v-if="feedbackTarget"
        class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
        @click.self="feedbackTarget = null"
    >
        <div
            class="flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <div>
                    <p
                        class="text-[10px] font-semibold text-emerald-600 uppercase"
                    >
                        Student request
                    </p>
                    <h2
                        class="mt-1 text-base font-semibold text-slate-900 dark:text-white"
                    >
                        Add feedback
                    </h2>
                </div>
                <button
                    class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"
                    @click="feedbackTarget = null"
                >
                    <X class="size-4" />
                </button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto p-5">
                <textarea
                    v-model="feedbackForm.message"
                    class="min-h-40 w-full rounded-lg border border-slate-200 p-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                    placeholder="Feedback is required for needs action or resolved updates."
                ></textarea>
                <InputError :message="feedbackForm.errors.message" />
                <select
                    v-model="feedbackForm.visibility"
                    class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-white"
                >
                    <option value="student_visible">Student visible</option>
                    <option value="internal">Internal only</option>
                </select>
            </div>
            <div
                class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <Button variant="outline" @click="feedbackTarget = null"
                    >Cancel</Button
                >
                <Button
                    class="bg-emerald-600 text-white hover:bg-emerald-700"
                    :disabled="feedbackForm.processing"
                    @click="saveFeedback"
                    ><Loader2
                        v-if="feedbackForm.processing"
                        class="mr-2 size-4 animate-spin"
                    />Save Feedback</Button
                >
            </div>
        </div>
    </div>

    <div
        v-if="deletePeriodTarget || statusTarget || requestStatusTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md rounded-xl border bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950"
        >
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                Confirm Action
            </h2>
            <p class="mt-2 text-sm text-slate-500">
                Please confirm this important evaluation workflow change.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <Button
                    variant="outline"
                    @click="
                        deletePeriodTarget = null;
                        statusTarget = null;
                        requestStatusTarget = null;
                    "
                    >Cancel</Button
                >
                <Button
                    v-if="deletePeriodTarget"
                    variant="destructive"
                    :disabled="actionForm.processing"
                    @click="destroyPeriod"
                    >Delete</Button
                >
                <Button
                    v-else-if="statusTarget"
                    :disabled="actionForm.processing"
                    @click="updatePeriodStatus"
                    >Confirm</Button
                >
                <Button
                    v-else
                    :disabled="actionForm.processing"
                    @click="updateRequestStatus"
                    >Confirm</Button
                >
            </div>
        </div>
    </div>
</template>
