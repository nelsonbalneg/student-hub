<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    FileText,
    Users,
    Building2,
    AlertCircle,
    History,
    FileBarChart,
    ChevronLeft,
    ChevronRight,
    CheckCircle,
    StopCircle,
    RotateCcw,
    Trash2,
    CalendarClock,
    Search,
    Plus,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { index as accountabilitiesIndex } from '@/routes/clearance/accountabilities';
import * as updateRoutes from '@/routes/clearance/updates';

const props = defineProps<{
    update: any;
    logs: {
        data: any[];
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    semesters: any[];
    types: any[];
    allOffices: {
        id: number;
        name: string;
        code: string | null;
        category: 'academic' | 'support' | 'administration';
    }[];
    configuredOfficeIds: number[];
    students: {
        id: number;
        name: string;
        student_no: string | null;
        campus_id: number | null;
    }[];
    applications: {
        data: any[];
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    applicationFilters: {
        applications_search?: string;
    };
    accountabilitySummary: {
        total: number;
        pending: number;
        resolved: number;
        waived: number;
        affected_students: number;
        outstanding_amount: number;
        posted_offices: number;
        total_offices: number;
        offices: {
            id: number;
            name: string;
            posted: boolean;
            finalized_at: string | null;
        }[];
    };
    can: any;
}>();

type ClearanceUpdateTab =
    | 'overview'
    | 'offices'
    | 'applications'
    | 'accountabilities'
    | 'uploads'
    | 'reports'
    | 'logs';

const clearanceUpdateTabs: ClearanceUpdateTab[] = [
    'overview',
    'offices',
    'applications',
    'accountabilities',
    'uploads',
    'reports',
    'logs',
];

const tabFromUrl = (): ClearanceUpdateTab => {
    if (typeof window === 'undefined') {
        return 'overview';
    }

    const tab = window.location.hash.slice(1);

    return clearanceUpdateTabs.includes(tab as ClearanceUpdateTab)
        ? (tab as ClearanceUpdateTab)
        : 'overview';
};

const activeTab = ref<ClearanceUpdateTab>(tabFromUrl());

const selectTab = (tab: string) => {
    if (!clearanceUpdateTabs.includes(tab as ClearanceUpdateTab)) {
        return;
    }

    const selectedTab = tab as ClearanceUpdateTab;
    activeTab.value = selectedTab;

    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.hash = selectedTab === 'overview' ? '' : selectedTab;
    window.history.replaceState(window.history.state, '', url);
};

const syncTabFromUrl = () => {
    activeTab.value = tabFromUrl();
};

const navigateLogs = (url: string | null) => {
    if (!url) {
        return;
    }

    router.visit(`${url}#logs`, {
        only: ['logs'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            activeTab.value = 'logs';
        },
    });
};

const navigateApplications = (url: string | null) => {
    if (!url) {
        return;
    }

    router.visit(`${url}#applications`, {
        only: ['applications', 'applicationFilters'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            activeTab.value = 'applications';
        },
    });
};

onMounted(() => {
    window.addEventListener('hashchange', syncTabFromUrl);
});

onBeforeUnmount(() => {
    window.removeEventListener('hashchange', syncTabFromUrl);
});

const extendModalOpen = ref(false);
const reopenModalOpen = ref(false);

const extendForm = useForm({
    end_date: props.update.end_date,
    remarks: '',
});
const reopenForm = useForm({
    status: 'published',
    reason: '',
});

const editModalOpen = ref(false);
const editForm = useForm({
    semester_id: props.update.semester.id,
    clearance_type_id: props.update.type.id,
    title: props.update.title,
    description: props.update.description,
    purpose: props.update.purpose,
    start_date: props.update.start_date,
    end_date: props.update.end_date,
    selected_student_ids:
        props.update.targeted_students?.map((student: any) => student.id) ?? [],
});
const editStudentSearch = ref('');

const selectedEditSemester = computed(() =>
    props.semesters.find(
        (semester: any) => semester.id === Number(editForm.semester_id),
    ),
);

const filteredEditTypes = computed(() => {
    const siteCampusId = selectedEditSemester.value?.site_campus_id;

    if (!siteCampusId) {
        return props.types;
    }

    return props.types.filter(
        (type: any) => Number(type.campus_id) === Number(siteCampusId),
    );
});

const selectedEditType = computed(() =>
    props.types.find(
        (type: any) => type.id === Number(editForm.clearance_type_id),
    ),
);

watch(
    () => editForm.semester_id,
    () => {
        const siteCampusId = selectedEditSemester.value?.site_campus_id;

        if (
            siteCampusId &&
            selectedEditType.value &&
            Number(selectedEditType.value.campus_id) !== Number(siteCampusId)
        ) {
            editForm.clearance_type_id = '';
        }
    },
);
const filteredEditStudents = computed(() => {
    const query = editStudentSearch.value.trim().toLowerCase();
    const campusId = selectedEditSemester.value?.campus_id;

    return props.students
        .filter(
            (student) =>
                !campusId ||
                Number(student.campus_id) === Number(campusId),
        )
        .filter(
            (student) =>
                !query ||
                student.name.toLowerCase().includes(query) ||
                student.student_no?.toLowerCase().includes(query),
        )
        .slice(0, 50);
});
const toggleEditStudent = (studentId: number) => {
    editForm.selected_student_ids = editForm.selected_student_ids.includes(
        studentId,
    )
        ? editForm.selected_student_ids.filter((id: number) => id !== studentId)
        : [...editForm.selected_student_ids, studentId];
};
const confirmModal = ref({
    show: false,
    title: '',
    description: '',
    confirmText: '',
    variant: 'default' as 'default' | 'destructive',
    action: () => {},
    loading: false,
    compact: false,
});

const officeSearch = ref('');
const initialOfficeIds =
    props.update.offices?.length > 0
        ? props.update.offices.map((office: any) => office.office.id)
        : props.configuredOfficeIds;
const officeForm = useForm({
    office_ids: [...initialOfficeIds] as number[],
});

const filteredOffices = computed(() => {
    if (!officeSearch.value) {
        return props.allOffices;
    }

    return props.allOffices.filter((o) =>
        o.name.toLowerCase().includes(officeSearch.value.toLowerCase()),
    );
});

const isOfficeSelected = (id: number) => {
    return officeForm.office_ids.includes(id);
};

const isConfiguredOffice = (id: number) => {
    return props.configuredOfficeIds.includes(id);
};

const toggleOffice = (id: number) => {
    officeForm.office_ids = isOfficeSelected(id)
        ? officeForm.office_ids.filter((officeId) => officeId !== id)
        : [...officeForm.office_ids, id];
};

const loadConfiguredOffices = () => {
    officeForm.office_ids = [...props.configuredOfficeIds];
};

const saveOffices = () => {
    officeForm.post(updateRoutes.syncOffices.url(props.update.id), {
        preserveScroll: true,
        onSuccess: () => {
            officeForm.defaults('office_ids', [...officeForm.office_ids]);
            selectTab('offices');
        },
    });
};

const studentSearch = ref(props.applicationFilters.applications_search ?? '');
let applicationSearchTimer: ReturnType<typeof setTimeout> | undefined;

watch(studentSearch, (value) => {
    window.clearTimeout(applicationSearchTimer);
    applicationSearchTimer = window.setTimeout(() => {
        const search = value.trim();
        const url = updateRoutes.show.url(props.update.id, {
            query: search ? { applications_search: search } : {},
        });

        navigateApplications(url);
    }, 350);
});

onBeforeUnmount(() => {
    window.clearTimeout(applicationSearchTimer);
});

const statusColor = (status: string) => {
    switch (status) {
        case 'cleared':
            return 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'pending_review':
            return 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400';
        case 'with_accountability':
            return 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400';
        case 'completed':
            return 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400';
        default:
            return 'bg-slate-50 text-slate-600 dark:bg-white/5 dark:text-slate-400';
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Clearance Updates',
                href: '/student-services/clearance/updates',
            },
            { title: 'Update Details', href: '#' },
        ],
    },
});

const publish = () => {
    confirmModal.value = {
        show: true,
        title: 'Publish Clearance Update',
        description:
            'Are you sure you want to publish this clearance update? Students will be able to apply once published.',
        confirmText: 'Publish Update',
        variant: 'default',
        action: () => {
            router.post(
                `/student-services/clearance/updates/${props.update.id}/publish`,
                {},
                {
                    onStart: () => (confirmModal.value.loading = true),
                    onFinish: () => {
                        confirmModal.value.loading = false;
                        confirmModal.value.show = false;
                    },
                },
            );
        },
        loading: false,
        compact: false,
    };
};

const closeUpdate = () => {
    confirmModal.value = {
        show: true,
        title: 'Close Clearance Update',
        description:
            'Are you sure you want to close this clearance update? Students will no longer be able to apply.',
        confirmText: 'Close Update',
        variant: 'default',
        action: () => {
            router.post(
                `/student-services/clearance/updates/${props.update.id}/close`,
                {},
                {
                    onStart: () => (confirmModal.value.loading = true),
                    onFinish: () => {
                        confirmModal.value.loading = false;
                        confirmModal.value.show = false;
                    },
                },
            );
        },
        loading: false,
        compact: false,
    };
};

const openReopenModal = () => {
    reopenForm.reset();
    reopenForm.status = 'published';
    reopenForm.clearErrors();
    reopenModalOpen.value = true;
};

const reopenUpdate = () => {
    reopenForm.post(updateRoutes.reopen.url(props.update.id), {
        preserveScroll: true,
        onSuccess: () => {
            reopenModalOpen.value = false;
            reopenForm.reset();
        },
    });
};

const deleteApplication = (application: any) => {
    confirmModal.value = {
        show: true,
        title: 'Remove Student Application',
        description: `Are you sure you want to remove ${application.student?.name}'s application? This will permanently delete their clearance progress for this period.`,
        confirmText: 'Remove Application',
        variant: 'destructive',
        compact: true,
        action: () => {
            router.delete(
                `/student-services/clearance/updates/${props.update.id}/applications/${application.id}`,
                {
                    preserveScroll: true,
                    onStart: () => (confirmModal.value.loading = true),
                    onFinish: () => {
                        confirmModal.value.loading = false;
                        confirmModal.value.show = false;
                    },
                },
            );
        },
        loading: false,
    };
};

const deleteUpdate = () => {
    confirmModal.value = {
        show: true,
        title: 'Delete Clearance Update',
        description:
            'Are you sure you want to delete this clearance update? This action cannot be undone and all associated records will be lost.',
        confirmText: 'Delete Update',
        variant: 'destructive',
        action: () => {
            router.delete(
                `/student-services/clearance/updates/${props.update.id}`,
                {
                    onStart: () => (confirmModal.value.loading = true),
                    onFinish: () => {
                        confirmModal.value.loading = false;
                        confirmModal.value.show = false;
                    },
                },
            );
        },
        loading: false,
        compact: false,
    };
};

const extendPeriod = () => {
    extendForm.patch(
        `/student-services/clearance/updates/${props.update.id}/extend`,
        {
            onSuccess: () => {
                extendModalOpen.value = false;
                extendForm.reset('remarks');
            },
        },
    );
};

const openEdit = () => {
    editForm.semester_id = props.update.semester.id;
    editForm.clearance_type_id = props.update.type.id;
    editForm.title = props.update.title;
    editForm.description = props.update.description;
    editForm.purpose = props.update.purpose;
    editForm.start_date = props.update.start_date;
    editForm.end_date = props.update.end_date;
    editForm.selected_student_ids =
        props.update.targeted_students?.map((student: any) => student.id) ?? [];
    editStudentSearch.value = '';
    editModalOpen.value = true;
};

const updateDetails = () => {
    editForm.patch(updateRoutes.update.url(props.update.id), {
        onSuccess: () => (editModalOpen.value = false),
    });
};

const tabs = computed(() => [
    { id: 'overview', name: 'Overview', icon: FileText },
    {
        id: 'offices',
        name: `Participating Offices (${officeForm.office_ids.length})`,
        icon: Building2,
    },
    {
        id: 'applications',
        name: `Student Applications (${props.applications.meta.total})`,
        icon: Users,
    },
    { id: 'accountabilities', name: 'Accountabilities', icon: AlertCircle },
    { id: 'uploads', name: 'Upload History', icon: History },
    { id: 'reports', name: 'Reports', icon: FileBarChart },
    {
        id: 'logs',
        name: `Audit Logs (${props.logs.meta.total})`,
        icon: History,
    },
]);

const statusClass = (status: string) => {
    switch (status) {
        case 'published':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'closed':
            return 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400';
        default:
            return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }
};
const actionColor = (action: string) => {
    switch (action) {
        case 'PUBLISHED':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'CLOSED':
            return 'text-slate-600 bg-slate-50 dark:bg-white/5 dark:text-slate-400';
        case 'REOPENED':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'EXTEND_PERIOD':
            return 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400';
        case 'OFFICE_ADDED':
            return 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400';
        case 'OFFICE_REMOVED':
            return 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400';
        case 'OFFICES_UPDATED':
            return 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400';
        case 'APPLICATION_REMOVED':
            return 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 dark:text-rose-400';
        default:
            return 'text-slate-600 bg-slate-50 dark:bg-white/5 dark:text-slate-400';
    }
};
</script>

<template>
    <Head :title="update.title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3">
                <Link
                    href="/student-services/clearance/updates"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-white/10 dark:bg-slate-950 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-lg font-bold text-slate-900 dark:text-white"
                        >
                            {{ update.title }}
                        </h1>
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                statusClass(update.status),
                            ]"
                        >
                            {{ update.status }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        <span
                            v-if="update.reference_code"
                            class="font-mono font-semibold"
                        >
                            {{ update.reference_code }} ·
                        </span>
                        {{ update.semester.academic_year }} -
                        {{ update.semester.term }} -
                        {{ update.semester.campus_name }} |
                        {{ update.type.name }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="
                        update.status === 'draft' &&
                        update.offices.length > 0 &&
                        can.publish
                    "
                    variant="outline"
                    class="h-8 gap-1.5 border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                    @click="publish"
                >
                    <Play class="h-3.5 w-3.5" />
                    Publish Update
                </Button>
                <Button
                    v-if="update.status === 'published' && can.extend"
                    variant="outline"
                    class="h-8 gap-1.5 border-indigo-200 bg-indigo-50 text-xs font-bold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20"
                    @click="extendModalOpen = true"
                >
                    <CalendarClock class="h-3.5 w-3.5" />
                    Extend Period
                </Button>
                <Button
                    v-if="update.status === 'published' && can.close"
                    variant="outline"
                    class="h-8 gap-1.5 border-amber-200 bg-amber-50 text-xs font-bold text-amber-700 hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
                    @click="closeUpdate"
                >
                    <StopCircle class="h-3.5 w-3.5" />
                    Close Update
                </Button>
                <Button
                    v-if="update.status === 'closed' && can.reopen"
                    variant="outline"
                    class="h-8 gap-1.5 border-emerald-200 bg-emerald-50 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                    @click="openReopenModal"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    Reopen Update
                </Button>
                <Button
                    v-if="update.status === 'draft' && can.edit"
                    variant="outline"
                    class="h-8 text-xs font-bold"
                    @click="openEdit"
                >
                    Edit Details
                </Button>
                <Button
                    v-if="update.status === 'draft' && can.delete"
                    variant="outline"
                    class="h-8 gap-1.5 border-red-200 bg-red-50 text-xs font-bold text-red-700 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                    @click="deleteUpdate"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    Delete
                </Button>
            </div>
        </div>

        <div
            class="flex items-center gap-1 overflow-x-auto border-b border-slate-100 dark:border-white/10"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="selectTab(tab.id)"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2 text-xs font-medium transition-colors',
                    activeTab === tab.id
                        ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                ]"
            >
                <component :is="tab.icon" class="h-3.5 w-3.5" />
                {{ tab.name }}
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div
                v-if="activeTab === 'overview'"
                class="grid gap-4 md:grid-cols-3"
            >
                <div class="col-span-2 space-y-4">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950"
                    >
                        <h3
                            class="mb-4 text-[11px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Update Information
                        </h3>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <p
                                    class="text-[10px] font-bold text-slate-400 uppercase"
                                >
                                    Description
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        update.description ||
                                        'No description provided.'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[10px] font-bold text-slate-400 uppercase"
                                >
                                    Purpose
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        update.purpose ||
                                        'No purpose specified.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950"
                    >
                        <h3
                            class="mb-4 text-[11px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Application Period
                        </h3>
                        <div class="flex items-center gap-8">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                                >
                                    <Calendar class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400 uppercase"
                                    >
                                        Start Date
                                    </p>
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ update.start_date }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                                >
                                    <Calendar class="h-5 w-5" />
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold text-slate-400 uppercase"
                                    >
                                        End Date
                                    </p>
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ update.end_date }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950"
                    >
                        <h3
                            class="mb-4 text-[11px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Statistics
                        </h3>
                        <div class="grid gap-4">
                            <div
                                class="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-white/5"
                            >
                                <div class="flex items-center gap-2">
                                    <Users class="h-4 w-4 text-slate-400" />
                                    <span
                                        class="text-xs text-slate-600 dark:text-slate-300"
                                        >Total Applied</span
                                    >
                                </div>
                                <span class="text-sm font-bold">0</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-lg bg-emerald-50/50 p-3 dark:bg-emerald-500/5"
                            >
                                <div class="flex items-center gap-2">
                                    <CheckCircle
                                        class="h-4 w-4 text-emerald-500"
                                    />
                                    <span
                                        class="text-xs text-emerald-700 dark:text-emerald-300"
                                        >Cleared</span
                                    >
                                </div>
                                <span
                                    class="text-sm font-bold text-emerald-700 dark:text-emerald-300"
                                    >0</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between rounded-lg bg-red-50/50 p-3 dark:bg-red-500/5"
                            >
                                <div class="flex items-center gap-2">
                                    <AlertCircle class="h-4 w-4 text-red-500" />
                                    <span
                                        class="text-xs text-red-700 dark:text-red-300"
                                        >With Accountability</span
                                    >
                                </div>
                                <span
                                    class="text-sm font-bold text-red-700 dark:text-red-300"
                                    >0</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="activeTab === 'offices'"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                >
                    <div>
                        <h3
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Participating Offices
                        </h3>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                            Offices tagged on the
                            <span class="font-semibold">{{
                                update.type.name
                            }}</span>
                            clearance type are selected by default.
                        </p>
                    </div>
                    <div
                        v-if="update.status === 'draft'"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-[10px] font-bold"
                            :disabled="configuredOfficeIds.length === 0"
                            @click="loadConfiguredOffices"
                        >
                            Load Tagged Offices
                        </Button>
                        <Button
                            size="sm"
                            class="h-7 bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700"
                            :disabled="
                                officeForm.processing ||
                                officeForm.office_ids.length === 0
                            "
                            @click="saveOffices"
                        >
                            {{
                                officeForm.processing
                                    ? 'Saving...'
                                    : 'Save Offices'
                            }}
                        </Button>
                    </div>
                </div>

                <div class="border-b border-slate-100 p-4 dark:border-white/10">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <div class="relative flex-1">
                            <Search
                                class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="officeSearch"
                                type="search"
                                placeholder="Search offices..."
                                class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-slate-100"
                            />
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-2 text-[10px] font-semibold text-slate-500"
                        >
                            <span
                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                {{ officeForm.office_ids.length }} selected
                            </span>
                            <span
                                class="rounded-full bg-blue-50 px-2.5 py-1 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                            >
                                {{ configuredOfficeIds.length }} tagged
                            </span>
                        </div>
                    </div>
                    <InputError :message="officeForm.errors.office_ids" />
                </div>

                <div class="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="office in filteredOffices"
                        :key="office.id"
                        :class="[
                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                            isOfficeSelected(office.id)
                                ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                                : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5',
                            update.status !== 'draft'
                                ? 'pointer-events-none'
                                : '',
                        ]"
                    >
                        <input
                            type="checkbox"
                            :checked="isOfficeSelected(office.id)"
                            :disabled="update.status !== 'draft'"
                            class="mt-0.5 size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900"
                            @change="toggleOffice(office.id)"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="truncate text-xs font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ office.name }}
                                </span>
                                <span
                                    v-if="isConfiguredOffice(office.id)"
                                    class="shrink-0 rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold text-blue-700 uppercase dark:bg-blue-500/15 dark:text-blue-300"
                                >
                                    Tagged
                                </span>
                            </span>
                            <span
                                v-if="office.code"
                                class="mt-0.5 block font-mono text-[10px] text-slate-400"
                            >
                                {{ office.code }}
                            </span>
                        </span>
                    </label>

                    <p
                        v-if="filteredOffices.length === 0"
                        class="col-span-full py-8 text-center text-xs text-slate-400"
                    >
                        No offices match your search.
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'applications'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Student Applications
                        </h3>
                        <p class="text-[10px] text-slate-500">
                            List of students who applied for this clearance
                            period.
                        </p>
                    </div>
                    <div class="relative w-64">
                        <Search
                            class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="studentSearch"
                            type="text"
                            placeholder="Search name or ID..."
                            class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                >
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:border-white/10 dark:bg-white/5"
                            >
                                <th class="px-4 py-3">Student</th>
                                <th class="px-4 py-3">Ref No.</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">
                                    Applied Date
                                </th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-50 dark:divide-white/5"
                        >
                            <tr
                                v-for="app in applications.data"
                                :key="app.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-white/5 dark:text-slate-400"
                                        >
                                            {{
                                                app.student?.name?.charAt(0) ||
                                                '?'
                                            }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-xs font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    app.student?.name ||
                                                    'Unknown Student'
                                                }}</span
                                            >
                                            <span
                                                class="text-[10px] text-slate-500"
                                                >{{
                                                    app.student?.student_id ||
                                                    'N/A'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-[10px] text-slate-500"
                                >
                                    {{ app.reference_no }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                            statusColor(app.status),
                                        ]"
                                    >
                                        {{
                                            app.status?.replace('_', ' ') ||
                                            'Unknown'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-center text-[10px] text-slate-500"
                                >
                                    {{ app.applied_at }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-[10px] font-bold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                            @click="
                                                router.visit(
                                                    `/student-services/clearance/my-clearance/${app.id}`,
                                                )
                                            "
                                        >
                                            View Details
                                        </Button>
                                        <Button
                                            v-if="update.status === 'draft'"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 p-0 text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                            @click="deleteApplication(app)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="applications.data.length === 0">
                                <td
                                    colspan="5"
                                    class="p-8 text-center text-xs text-slate-400"
                                >
                                    No applications found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="applications.meta.last_page > 1"
                        class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                    >
                        <p class="text-[10px] text-slate-500">
                            Showing {{ applications.meta.from }}–{{
                                applications.meta.to
                            }}
                            of {{ applications.meta.total }} applications
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                                :disabled="applications.meta.current_page === 1"
                                @click="
                                    navigateApplications(
                                        applications.links[0]?.url,
                                    )
                                "
                            >
                                <ChevronLeft class="h-3.5 w-3.5" />
                                Previous
                            </button>
                            <span class="text-[10px] text-slate-500">
                                {{ applications.meta.current_page }} /
                                {{ applications.meta.last_page }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                                :disabled="
                                    applications.meta.current_page ===
                                    applications.meta.last_page
                                "
                                @click="
                                    navigateApplications(
                                        applications.links[
                                            applications.links.length - 1
                                        ]?.url,
                                    )
                                "
                            >
                                Next
                                <ChevronRight class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'accountabilities'" class="space-y-4">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h3
                            class="text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            Accountability Summary
                        </h3>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                            Current encoding, resolution, and office posting
                            progress.
                        </p>
                    </div>
                    <Link
                        :href="accountabilitiesIndex.url(update.id)"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-xs font-medium text-white hover:bg-emerald-700"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Manage Accountabilities
                    </Link>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
                    >
                        <p
                            class="text-[9px] font-medium text-slate-400 uppercase"
                        >
                            Total Accountabilities
                        </p>
                        <p
                            class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"
                        >
                            {{ accountabilitySummary.total }}
                        </p>
                        <p class="mt-1 text-[10px] text-slate-500">
                            {{ accountabilitySummary.affected_students }}
                            affected student(s)
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-red-100 bg-red-50/50 p-4 dark:border-red-500/20 dark:bg-red-500/5"
                    >
                        <p
                            class="text-[9px] font-medium text-red-500 uppercase"
                        >
                            Pending
                        </p>
                        <p
                            class="mt-2 text-2xl font-semibold text-red-700 dark:text-red-400"
                        >
                            {{ accountabilitySummary.pending }}
                        </p>
                        <p class="mt-1 text-[10px] text-red-600/80">
                            ₱{{
                                Number(
                                    accountabilitySummary.outstanding_amount,
                                ).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                })
                            }}
                            outstanding
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/5"
                    >
                        <p
                            class="text-[9px] font-medium text-emerald-600 uppercase"
                        >
                            Resolved
                        </p>
                        <p
                            class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-400"
                        >
                            {{ accountabilitySummary.resolved }}
                        </p>
                        <p class="mt-1 text-[10px] text-emerald-600/80">
                            {{ accountabilitySummary.waived }} waived
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5"
                    >
                        <p
                            class="text-[9px] font-medium text-amber-600 uppercase"
                        >
                            Offices Posted
                        </p>
                        <p
                            class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-400"
                        >
                            {{ accountabilitySummary.posted_offices }}/{{
                                accountabilitySummary.total_offices
                            }}
                        </p>
                        <p class="mt-1 text-[10px] text-amber-600/80">
                            Encoding completion
                        </p>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <div>
                            <h4
                                class="text-xs font-semibold text-slate-800 dark:text-white"
                            >
                                Office Posting Progress
                            </h4>
                            <p class="text-[10px] text-slate-500">
                                Students receive final results after all
                                relevant offices post.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                        >
                            {{ accountabilitySummary.posted_offices }} posted
                        </span>
                    </div>
                    <div
                        v-if="accountabilitySummary.offices.length > 0"
                        class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="office in accountabilitySummary.offices"
                            :key="office.id"
                            class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2.5 dark:border-white/5 dark:bg-white/[0.03]"
                        >
                            <span
                                class="truncate text-[11px] font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ office.name }}
                            </span>
                            <span
                                :class="[
                                    'shrink-0 rounded-full px-2 py-0.5 text-[8px] font-medium',
                                    office.posted
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                                ]"
                            >
                                {{ office.posted ? 'Posted' : 'Pending' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="p-8 text-center text-xs text-slate-400">
                        No participating offices configured.
                    </p>
                </div>
            </div>

            <div
                v-if="activeTab === 'logs'"
                class="rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
            >
                <div class="border-b border-slate-100 p-4 dark:border-white/10">
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        Audit Trail
                    </h3>
                </div>
                <div class="p-4" v-if="logs.data.length === 0">
                    <p class="text-center text-xs text-slate-400">
                        No activity logged yet.
                    </p>
                </div>
                <div
                    v-else
                    class="relative space-y-4 p-4 before:absolute before:top-4 before:bottom-4 before:left-[21px] before:w-0.5 before:bg-slate-100 dark:before:bg-white/5"
                >
                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="relative pl-10"
                    >
                        <div
                            :class="[
                                'absolute left-0 flex h-[42px] w-[42px] items-center justify-center rounded-full border-4 border-white shadow-sm dark:border-slate-950',
                                actionColor(log.action),
                            ]"
                        >
                            <History class="h-4 w-4" />
                        </div>
                        <div
                            class="rounded-lg border border-slate-100 bg-white p-3 shadow-sm dark:border-white/5 dark:bg-slate-900/50"
                        >
                            <div
                                class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between"
                            >
                                <div
                                    :class="[
                                        'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold tracking-tight uppercase',
                                        actionColor(log.action),
                                    ]"
                                >
                                    {{ log.action?.replace('_', ' ') }}
                                </div>
                                <span class="text-[10px] text-slate-400">{{
                                    new Date(log.created_at).toLocaleString()
                                }}</span>
                            </div>
                            <p
                                class="mt-1 text-[11px] text-slate-600 dark:text-slate-400"
                            >
                                {{ log.remarks }}
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <div
                                    class="flex h-4 w-4 items-center justify-center rounded-full bg-emerald-100 text-[8px] font-bold text-emerald-700"
                                >
                                    {{ log.performer?.name?.charAt(0) }}
                                </div>
                                <p class="text-[10px] text-slate-500">
                                    Performed by
                                    <span
                                        class="font-medium text-slate-700 dark:text-slate-300"
                                        >{{ log.performer?.name }}</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="logs.meta.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/10"
                >
                    <p class="text-[10px] text-slate-500">
                        Showing {{ logs.meta.from }}–{{ logs.meta.to }} of
                        {{ logs.meta.total }} entries
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            :disabled="logs.meta.current_page === 1"
                            @click="navigateLogs(logs.links[0]?.url)"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                            Previous
                        </button>
                        <span class="text-[10px] text-slate-500">
                            {{ logs.meta.current_page }} /
                            {{ logs.meta.last_page }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-3 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            :disabled="
                                logs.meta.current_page === logs.meta.last_page
                            "
                            @click="
                                navigateLogs(
                                    logs.links[logs.links.length - 1]?.url,
                                )
                            "
                        >
                            Next
                            <ChevronRight class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="reopenModalOpen"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="reopenModalOpen = false"
    >
        <div
            class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
        >
            <div class="mb-5 flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                >
                    <RotateCcw class="size-5" />
                </div>
                <div>
                    <h2
                        class="text-base font-semibold text-slate-900 dark:text-white"
                    >
                        Reopen Clearance Update
                    </h2>
                    <p class="text-xs text-slate-500">
                        Select the new status and provide a reason.
                    </p>
                </div>
            </div>

            <form class="grid gap-4" @submit.prevent="reopenUpdate">
                <div class="grid gap-1.5">
                    <label
                        for="reopen-status"
                        class="text-[11px] font-medium text-slate-600 dark:text-slate-300"
                    >
                        Status after reopening
                    </label>
                    <select
                        id="reopen-status"
                        v-model="reopenForm.status"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                    <InputError :message="reopenForm.errors.status" />
                </div>

                <div class="grid gap-1.5">
                    <label
                        for="reopen-reason"
                        class="text-[11px] font-medium text-slate-600 dark:text-slate-300"
                    >
                        Reason for reopening
                    </label>
                    <textarea
                        id="reopen-reason"
                        v-model="reopenForm.reason"
                        rows="4"
                        required
                        placeholder="Explain why this clearance update needs to be reopened..."
                        class="resize-none rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                    ></textarea>
                    <InputError :message="reopenForm.errors.reason" />
                </div>

                <div class="mt-1 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 px-4 text-xs font-medium"
                        :disabled="reopenForm.processing"
                        @click="reopenModalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="h-9 bg-emerald-600 px-4 text-xs font-medium text-white hover:bg-emerald-700"
                        :disabled="reopenForm.processing"
                    >
                        {{
                            reopenForm.processing
                                ? 'Reopening...'
                                : 'Reopen Update'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Extend Period Modal -->
    <div
        v-if="extendModalOpen"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="extendModalOpen = false"
    >
        <div
            class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-slate-900 shadow-xl dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
        >
            <div class="mb-4 flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
                >
                    <CalendarClock class="h-5 w-5" />
                </div>
                <div>
                    <h2
                        class="text-lg font-bold text-slate-900 dark:text-white"
                    >
                        Extend Application Period
                    </h2>
                    <p class="text-xs text-slate-500">
                        Update the clearance end date.
                    </p>
                </div>
            </div>
            <form class="grid gap-4" @submit.prevent="extendPeriod">
                <div class="grid gap-1.5">
                    <label
                        class="text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                        >New End Date</label
                    >
                    <input
                        v-model="extendForm.end_date"
                        type="date"
                        class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                    />
                    <p
                        v-if="extendForm.errors.end_date"
                        class="text-[10px] font-medium text-red-500"
                    >
                        {{ extendForm.errors.end_date }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <label
                        class="text-[11px] font-bold tracking-wider text-slate-500 uppercase"
                        >Remarks (Internal Note)</label
                    >
                    <textarea
                        v-model="extendForm.remarks"
                        class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
                        rows="3"
                        placeholder="Reason for extension..."
                    ></textarea>
                    <p
                        v-if="extendForm.errors.remarks"
                        class="text-[10px] font-medium text-red-500"
                    >
                        {{ extendForm.errors.remarks }}
                    </p>
                </div>
                <div class="mt-2 flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 px-4 text-xs font-bold"
                        @click="extendModalOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        class="h-9 bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
                        :disabled="extendForm.processing"
                    >
                        Update End Date
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <ConfirmationModal
        :show="confirmModal.show"
        :title="confirmModal.title"
        :description="confirmModal.description"
        :confirm-text="confirmModal.confirmText"
        :variant="confirmModal.variant"
        :loading="confirmModal.loading"
        :compact="confirmModal.compact"
        @close="confirmModal.show = false"
        @confirm="confirmModal.action"
    />

    <!-- Edit Modal -->
    <div
        v-if="editModalOpen"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="editModalOpen = false"
    >
        <div
            class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-slate-950"
        >
            <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">
                Edit Clearance Update
            </h2>
            <form class="grid gap-3" @submit.prevent="updateDetails">
                <div class="grid gap-3">
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Semester
                        <select
                            v-model="editForm.semester_id"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option
                                v-for="sem in semesters"
                                :key="sem.id"
                                :value="sem.id"
                            >
                                {{ sem.academic_year }} - {{ sem.term }} -
                                {{ sem.campus_name }}
                            </option>
                        </select>
                        <InputError :message="editForm.errors.semester_id" />
                    </label>
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Type
                        <select
                            v-model="editForm.clearance_type_id"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option
                                v-for="type in filteredEditTypes"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                                {{
                                    type.audience === 'individual'
                                        ? ' (Individual)'
                                        : ' (All students)'
                                }}
                            </option>
                        </select>
                        <InputError
                            :message="editForm.errors.clearance_type_id"
                        />
                    </label>
                    <div
                        v-if="selectedEditType?.audience === 'individual'"
                        class="rounded-xl border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-500/20 dark:bg-violet-500/5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-bold text-violet-800 uppercase dark:text-violet-300"
                                >
                                    Selected Students
                                </p>
                                <p
                                    class="text-[10px] text-violet-600 dark:text-violet-400"
                                >
                                    Only these students can access this
                                    individual clearance.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                            >
                                {{ editForm.selected_student_ids.length }}
                                selected
                            </span>
                        </div>
                        <div class="relative mt-3">
                            <Search
                                class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="editStudentSearch"
                                type="search"
                                placeholder="Search student name or ID"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:border-violet-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                            />
                        </div>
                        <div
                            class="mt-2 max-h-44 overflow-y-auto rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                        >
                            <label
                                v-for="student in filteredEditStudents"
                                :key="student.id"
                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        editForm.selected_student_ids.includes(
                                            student.id,
                                        )
                                    "
                                    class="size-4 rounded border-slate-300 text-violet-600"
                                    @change="toggleEditStudent(student.id)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-xs font-semibold text-slate-900 dark:text-white"
                                        >{{ student.name }}</span
                                    >
                                    <span
                                        class="block text-[10px] text-slate-500"
                                        >{{
                                            student.student_no ||
                                            'No student number'
                                        }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <InputError
                            :message="editForm.errors.selected_student_ids"
                        />
                    </div>
                </div>
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    Title
                    <input
                        v-model="editForm.title"
                        type="text"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                    />
                    <InputError :message="editForm.errors.title" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Start Date
                        <input
                            v-model="editForm.start_date"
                            type="date"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <InputError :message="editForm.errors.start_date" />
                    </label>
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        End Date
                        <input
                            v-model="editForm.end_date"
                            type="date"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <InputError :message="editForm.errors.end_date" />
                    </label>
                </div>
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    Purpose
                    <textarea
                        v-model="editForm.purpose"
                        class="w-full rounded-lg border border-slate-200 bg-white p-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        rows="2"
                    ></textarea>
                    <InputError :message="editForm.errors.purpose" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="editModalOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        class="bg-emerald-600 text-white"
                        :disabled="editForm.processing"
                        >Update Details</Button
                    >
                </div>
            </form>
        </div>
    </div>
</template>
