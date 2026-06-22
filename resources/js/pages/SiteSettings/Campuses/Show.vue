<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { format } from 'date-fns';
import {
    Archive,
    ArrowLeft,
    Building2,
    BriefcaseBusiness,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Edit,
    FileCheck2,
    Fingerprint,
    History,
    Info,
    Loader2,
    MapPin,
    Plus,
    Power,
    RefreshCcw,
    Search,
    Settings,
    Trash2,
    UserRound,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import * as campusRoutes from '@/routes/site-settings/campuses';
import * as clearanceTypeRoutes from '@/routes/site-settings/campuses/clearance-types';
import * as officeRoutes from '@/routes/site-settings/campuses/offices';
import * as termRoutes from '@/routes/site-settings/campuses/terms';

interface AcademicTerm {
    id: number;
    school_year: string;
    semester: string;
    term_id: string;
    status: 'Active' | 'Inactive' | 'Archived';
    start_date: string;
    end_date: string;
}

interface ClearanceType {
    id: number;
    name: string;
    description: string | null;
    audience: 'all' | 'individual';
    offices: Office[];
}

interface Campus {
    id: number;
    campus_name: string;
    campus_address: string;
    campus_logo_path: string;
    real_campus_id: string;
    campus_id: number | null;
    tenant_id: string | null;
    status: 'Active' | 'Inactive';
    created_at?: string | null;
    updated_at?: string | null;
    academic_terms: AcademicTerm[];
    offices: Office[];
    clearance_types: ClearanceType[];
}

interface Office {
    id: number;
    name: string;
    code: string | null;
    category: 'academic' | 'support' | 'administration';
    description: string | null;
}

type CampusTab = 'info' | 'terms' | 'offices' | 'clearance-types';

const props = defineProps<{
    campus: Campus;
}>();

const campusTabs: CampusTab[] = ['info', 'terms', 'offices', 'clearance-types'];

const tabFromUrl = (): CampusTab => {
    if (typeof window === 'undefined') {
        return 'info';
    }

    const tab = window.location.hash.slice(1);

    return campusTabs.includes(tab as CampusTab) ? (tab as CampusTab) : 'info';
};

const showTermModal = ref(false);
const showDeleteTermModal = ref(false);
const showEditModal = ref(false);
const showOfficeModal = ref(false);
const showDeleteOfficeModal = ref(false);
const showClearanceTypeModal = ref(false);
const showDeleteClearanceTypeModal = ref(false);
const showConfigureModal = ref(false);
const selectedTerm = ref<AcademicTerm | null>(null);
const selectedOffice = ref<Office | null>(null);
const selectedClearanceType = ref<ClearanceType | null>(null);
const searchTerm = ref('');
const officeSearch = ref('');
const officeSearchTerm = ref('');
const clearanceTypeSearch = ref('');
const activeTab = ref<CampusTab>(tabFromUrl());

const selectTab = (tab: CampusTab) => {
    activeTab.value = tab;

    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.hash = tab === 'info' ? '' : tab;
    window.history.replaceState(window.history.state, '', url);
};

const syncTabFromUrl = () => {
    activeTab.value = tabFromUrl();
};

onMounted(() => {
    window.addEventListener('hashchange', syncTabFromUrl);
});

onBeforeUnmount(() => {
    window.removeEventListener('hashchange', syncTabFromUrl);
});

const form = useForm({
    campus_name: props.campus.campus_name,
    campus_address: props.campus.campus_address,
    real_campus_id: props.campus.real_campus_id,
    campus_id: props.campus.campus_id ?? ('' as string | number),
    tenant_id: props.campus.tenant_id ?? '',
    status: props.campus.status,
    logo: null as File | null,
});

const termForm = useForm({
    school_year: '',
    semester: '',
    term_id: '',
    status: 'Inactive' as 'Active' | 'Inactive' | 'Archived',
    start_date: '',
    end_date: '',
});

const officeForm = useForm({
    name: '',
    code: '',
    category: 'support' as 'academic' | 'support' | 'administration',
    description: '',
});

const clearanceTypeForm = useForm({
    name: '',
    description: '',
    audience: 'all' as 'all' | 'individual',
});

const configForm = useForm({
    office_ids: [] as number[],
});

const fetchingColleges = ref(false);
const showImportModal = ref(false);
const apiColleges = ref<
    Array<{
        collegeId: number;
        collegeCode: string;
        collegeName: string;
        inactive: boolean;
    }>
>([]);
const selectedImportCollegeCodes = ref<string[]>([]);

const fetchCollegesFromApi = async () => {
    fetchingColleges.value = true;

    try {
        const response = await fetch(
            officeRoutes.fetchColleges.url(props.campus.id),
        );

        if (!response.ok) {
            const errData = await response.json();
            alert(
                errData.error || 'Failed to fetch colleges from Academic API.',
            );

            return;
        }

        const data = await response.json();
        apiColleges.value = data.colleges || [];
        selectedImportCollegeCodes.value = apiColleges.value.map(
            (c) => c.collegeCode,
        );
        showImportModal.value = true;
    } catch (error) {
        console.error(error);
        alert('An error occurred while fetching colleges.');
    } finally {
        fetchingColleges.value = false;
    }
};

const toggleAllImportColleges = () => {
    if (selectedImportCollegeCodes.value.length === apiColleges.value.length) {
        selectedImportCollegeCodes.value = [];
    } else {
        selectedImportCollegeCodes.value = apiColleges.value.map(
            (c) => c.collegeCode,
        );
    }
};

const importForm = useForm({
    colleges: [] as Array<{ name: string; code: string }>,
});

const importColleges = () => {
    const collegesToImport = apiColleges.value
        .filter((c) => selectedImportCollegeCodes.value.includes(c.collegeCode))
        .map((c) => ({
            name: c.collegeName,
            code: c.collegeCode,
        }));

    if (collegesToImport.length === 0) {
        alert('Please select at least one college to import.');

        return;
    }

    importForm.colleges = collegesToImport;
    importForm.post(officeRoutes.importColleges.url(props.campus.id), {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            apiColleges.value = [];
            selectedImportCollegeCodes.value = [];
        },
    });
};

const terms = computed(() => props.campus.academic_terms || []);

const activeTerm = computed(() =>
    terms.value.find((term) => term.status === 'Active'),
);

const termStats = computed(() => ({
    total: terms.value.length,
    active: terms.value.filter((term) => term.status === 'Active').length,
    archived: terms.value.filter((term) => term.status === 'Archived').length,
}));

const filteredTerms = computed(() => {
    const query = searchTerm.value.toLowerCase();

    return terms.value
        .filter(
            (term) =>
                term.school_year.toLowerCase().includes(query) ||
                term.semester.toLowerCase().includes(query) ||
                term.term_id?.toLowerCase().includes(query),
        )
        .sort((a, b) => b.id - a.id);
});

const filteredOffices = computed(() => {
    const query = officeSearch.value.trim().toLowerCase();

    return (props.campus.offices ?? []).filter(
        (office) =>
            office.name.toLowerCase().includes(query) ||
            office.code?.toLowerCase().includes(query) ||
            office.description?.toLowerCase().includes(query),
    );
});

const filteredClearanceTypes = computed(() => {
    const query = clearanceTypeSearch.value.trim().toLowerCase();

    return (props.campus.clearance_types ?? []).filter(
        (type) =>
            type.name.toLowerCase().includes(query) ||
            type.description?.toLowerCase().includes(query) ||
            type.audience.toLowerCase().includes(query),
    );
});

const openEditModal = () => {
    form.campus_name = props.campus.campus_name;
    form.campus_address = props.campus.campus_address;
    form.real_campus_id = props.campus.real_campus_id;
    form.campus_id = props.campus.campus_id ?? '';
    form.tenant_id = props.campus.tenant_id ?? '';
    form.status = props.campus.status;
    form.logo = null;
    showEditModal.value = true;
};

const updateCampus = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(campusRoutes.update.url(props.campus.id), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const openCreateTerm = () => {
    selectedTerm.value = null;
    termForm.reset();
    showTermModal.value = true;
};

const openEditTerm = (term: AcademicTerm) => {
    selectedTerm.value = term;
    termForm.school_year = term.school_year;
    termForm.semester = term.semester;
    termForm.term_id = term.term_id || '';
    termForm.status = term.status;
    termForm.start_date = term.start_date || '';
    termForm.end_date = term.end_date || '';
    showTermModal.value = true;
};

const saveTerm = () => {
    if (selectedTerm.value) {
        termForm.patch(
            termRoutes.update.url({
                campus: props.campus.id,
                term: selectedTerm.value.id,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => (showTermModal.value = false),
            },
        );

        return;
    }

    termForm.post(termRoutes.store.url(props.campus.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (showTermModal.value = false),
    });
};

const activateTerm = (term: AcademicTerm) => {
    termForm.patch(
        termRoutes.activate.url({
            campus: props.campus.id,
            term: term.id,
        }),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const confirmDeleteTerm = (term: AcademicTerm) => {
    selectedTerm.value = term;
    showDeleteTermModal.value = true;
};

const deleteTerm = () => {
    if (!selectedTerm.value) {
        return;
    }

    termForm.delete(
        termRoutes.destroy.url({
            campus: props.campus.id,
            term: selectedTerm.value.id,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (showDeleteTermModal.value = false),
        },
    );
};

const openCreateOffice = () => {
    selectedOffice.value = null;
    officeForm.reset();
    officeForm.clearErrors();
    showOfficeModal.value = true;
};

const openEditOffice = (office: Office) => {
    selectedOffice.value = office;
    officeForm.name = office.name;
    officeForm.code = office.code ?? '';
    officeForm.category = office.category;
    officeForm.description = office.description ?? '';
    officeForm.clearErrors();
    showOfficeModal.value = true;
};

const saveOffice = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showOfficeModal.value = false),
    };

    if (selectedOffice.value) {
        officeForm.patch(
            officeRoutes.update.url({
                campus: props.campus.id,
                office: selectedOffice.value.id,
            }),
            options,
        );

        return;
    }

    officeForm.post(officeRoutes.store.url(props.campus.id), options);
};

const confirmDeleteOffice = (office: Office) => {
    selectedOffice.value = office;
    showDeleteOfficeModal.value = true;
};

const deleteOffice = () => {
    if (!selectedOffice.value) {
        return;
    }

    officeForm.delete(
        officeRoutes.destroy.url({
            campus: props.campus.id,
            office: selectedOffice.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => (showDeleteOfficeModal.value = false),
        },
    );
};

const openCreateClearanceType = () => {
    selectedClearanceType.value = null;
    clearanceTypeForm.reset();
    clearanceTypeForm.clearErrors();
    showClearanceTypeModal.value = true;
};

const openEditClearanceType = (type: ClearanceType) => {
    selectedClearanceType.value = type;
    clearanceTypeForm.name = type.name;
    clearanceTypeForm.description = type.description ?? '';
    clearanceTypeForm.audience = type.audience;
    clearanceTypeForm.clearErrors();
    showClearanceTypeModal.value = true;
};

const saveClearanceType = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showClearanceTypeModal.value = false),
    };

    if (selectedClearanceType.value) {
        clearanceTypeForm.patch(
            clearanceTypeRoutes.update.url({
                campus: props.campus.id,
                clearanceType: selectedClearanceType.value.id,
            }),
            options,
        );

        return;
    }

    clearanceTypeForm.post(
        clearanceTypeRoutes.store.url(props.campus.id),
        options,
    );
};

const confirmDeleteClearanceType = (type: ClearanceType) => {
    selectedClearanceType.value = type;
    showDeleteClearanceTypeModal.value = true;
};

const deleteClearanceType = () => {
    if (!selectedClearanceType.value) {
        return;
    }

    clearanceTypeForm.delete(
        clearanceTypeRoutes.destroy.url({
            campus: props.campus.id,
            clearanceType: selectedClearanceType.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => (showDeleteClearanceTypeModal.value = false),
        },
    );
};

const openConfigureClearanceType = (type: ClearanceType) => {
    selectedClearanceType.value = type;
    configForm.office_ids = (type.offices || []).map((o) => o.id);
    officeSearchTerm.value = '';
    configForm.clearErrors();
    showConfigureModal.value = true;
};

const saveConfiguration = () => {
    if (!selectedClearanceType.value) {
        return;
    }

    configForm.post(
        clearanceTypeRoutes.syncOffices.url({
            campus: props.campus.id,
            clearanceType: selectedClearanceType.value.id,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showConfigureModal.value = false;
            },
        },
    );
};

const selectAllOffices = () => {
    configForm.office_ids = (props.campus.offices || []).map((o) => o.id);
};

const deselectAllOffices = () => {
    configForm.office_ids = [];
};

const filteredConfigOffices = computed(() => {
    const query = officeSearchTerm.value.trim().toLowerCase();

    if (!query) {
        return props.campus.offices || [];
    }

    return (props.campus.offices || []).filter(
        (office) =>
            office.name.toLowerCase().includes(query) ||
            (office.code && office.code.toLowerCase().includes(query)),
    );
});

const formatDate = (date?: string | null, pattern = 'MMM d, yyyy') =>
    date ? format(new Date(date), pattern) : 'N/A';

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'Active':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'Archived':
            return 'border-slate-200 bg-slate-100 text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-slate-300';
        default:
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    }
};
</script>

<template>
    <Head :title="`${props.campus.campus_name} - Details`" />

    <SiteSettingsLayout>
        <div class="space-y-4 p-4 lg:p-6">
            <div
                class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/10"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5"
                        >
                            <img
                                v-if="props.campus.campus_logo_path"
                                :src="`/storage/${props.campus.campus_logo_path}`"
                                :alt="props.campus.campus_name"
                                class="size-full object-cover"
                            />
                            <Building2
                                v-else
                                class="size-7 text-slate-300 dark:text-slate-600"
                            />
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1
                                    class="truncate text-xl font-semibold text-slate-950 dark:text-white"
                                >
                                    {{ props.campus.campus_name }}
                                </h1>
                                <Badge
                                    :class="
                                        props.campus.status === 'Active'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'border-slate-200 bg-slate-100 text-slate-600 dark:border-white/10 dark:bg-white/10 dark:text-slate-300'
                                    "
                                    variant="outline"
                                >
                                    <component
                                        :is="
                                            props.campus.status === 'Active'
                                                ? CheckCircle2
                                                : XCircle
                                        "
                                        class="mr-1 size-3"
                                    />
                                    {{ props.campus.status }}
                                </Badge>
                            </div>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <MapPin class="size-3.5 shrink-0" />
                                    <span class="truncate">{{
                                        props.campus.campus_address ||
                                        'No address set'
                                    }}</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <Fingerprint class="size-3.5" />
                                    External ID:
                                    <span
                                        class="font-mono font-semibold text-slate-700 dark:text-slate-200"
                                        >{{
                                            props.campus.real_campus_id ||
                                            'Not set'
                                        }}</span
                                    >
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                            class="h-8 rounded-md px-3 text-xs"
                        >
                            <Link :href="campusRoutes.index.url()">
                                <ArrowLeft class="mr-1.5 size-3.5" />
                                Back
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            class="h-8 rounded-md bg-sky-600 px-3 text-xs hover:bg-sky-700"
                            @click="openEditModal"
                        >
                            <Edit class="mr-1.5 size-3.5" />
                            Edit
                        </Button>
                    </div>
                </div>

                <div
                    class="grid divide-y divide-slate-200 text-xs sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4 dark:divide-white/10"
                >
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span class="font-medium text-slate-500"
                            >Active term</span
                        >
                        <span
                            class="truncate text-right font-semibold text-slate-900 dark:text-white"
                        >
                            {{
                                activeTerm
                                    ? `${activeTerm.school_year} ${activeTerm.semester}`
                                    : 'None'
                            }}
                        </span>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span class="font-medium text-slate-500">Terms</span>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                        >
                            {{ termStats.total }} total /
                            {{ termStats.archived }}
                            archived
                        </span>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span class="font-medium text-slate-500">Created</span>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                        >
                            {{ formatDate(props.campus.created_at) }}
                        </span>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span class="font-medium text-slate-500">Updated</span>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                        >
                            {{ formatDate(props.campus.updated_at) }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                class="flex w-full flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-white/10 dark:bg-white/5"
            >
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                    :class="
                        activeTab === 'info'
                            ? 'bg-white text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="selectTab('info')"
                >
                    <Info class="size-3.5" />
                    Overview
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                    :class="
                        activeTab === 'terms'
                            ? 'bg-white text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="selectTab('terms')"
                >
                    <Calendar class="size-3.5" />
                    Academic Terms
                    <span
                        class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] dark:bg-white/10"
                        >{{ termStats.total }}</span
                    >
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                    :class="
                        activeTab === 'offices'
                            ? 'bg-white text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="selectTab('offices')"
                >
                    <BriefcaseBusiness class="size-3.5" />
                    Offices
                    <span
                        class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] dark:bg-white/10"
                    >
                        {{ props.campus.offices?.length ?? 0 }}
                    </span>
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-2 rounded-md px-3 text-xs font-semibold transition"
                    :class="
                        activeTab === 'clearance-types'
                            ? 'bg-white text-sky-700 shadow-sm dark:bg-slate-900 dark:text-sky-300'
                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="selectTab('clearance-types')"
                >
                    <FileCheck2 class="size-3.5" />
                    Clearance Types
                    <span
                        class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] dark:bg-white/10"
                    >
                        {{ props.campus.clearance_types?.length ?? 0 }}
                    </span>
                </button>
            </div>

            <div
                v-if="activeTab === 'info'"
                class="grid gap-4 lg:grid-cols-[1fr_320px]"
            >
                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <Building2 class="size-4 text-slate-400" />
                        <h2
                            class="text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            Campus Record
                        </h2>
                    </div>
                    <dl
                        class="divide-y divide-slate-100 text-sm dark:divide-white/10"
                    >
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                Official name
                            </dt>
                            <dd
                                class="font-medium text-slate-900 dark:text-white"
                            >
                                {{ props.campus.campus_name }}
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                Address
                            </dt>
                            <dd class="text-slate-700 dark:text-slate-300">
                                {{
                                    props.campus.campus_address ||
                                    'No physical address provided.'
                                }}
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                External campus ID
                            </dt>
                            <dd>
                                <Badge
                                    variant="outline"
                                    class="font-mono text-[11px] font-semibold"
                                >
                                    {{
                                        props.campus.real_campus_id || 'Not set'
                                    }}
                                </Badge>
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                Campus ID
                            </dt>
                            <dd>
                                <Badge
                                    variant="outline"
                                    class="font-mono text-[11px] font-semibold"
                                >
                                    {{ props.campus.campus_id || 'Not set' }}
                                </Badge>
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                Tenant ID
                            </dt>
                            <dd>
                                <Badge
                                    variant="outline"
                                    class="font-mono text-[11px] font-semibold"
                                >
                                    {{ props.campus.tenant_id || 'Not set' }}
                                </Badge>
                            </dd>
                        </div>
                        <div
                            class="grid gap-1 px-4 py-3 sm:grid-cols-[180px_1fr]"
                        >
                            <dt class="text-xs font-semibold text-slate-500">
                                Visibility
                            </dt>
                            <dd>
                                <Badge
                                    :class="
                                        getStatusBadgeClass(props.campus.status)
                                    "
                                    variant="outline"
                                >
                                    {{ props.campus.status }}
                                </Badge>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <History class="size-4 text-slate-400" />
                        <h2
                            class="text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            System
                        </h2>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <div class="flex items-start gap-3 px-4 py-3">
                            <Clock class="mt-0.5 size-4 text-slate-400" />
                            <div>
                                <p class="text-xs font-semibold text-slate-500">
                                    Created
                                </p>
                                <p
                                    class="text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ formatDate(props.campus.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 px-4 py-3">
                            <RefreshCcw class="mt-0.5 size-4 text-slate-400" />
                            <div>
                                <p class="text-xs font-semibold text-slate-500">
                                    Last updated
                                </p>
                                <p
                                    class="text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ formatDate(props.campus.updated_at) }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <span
                                class="font-semibold text-slate-700 dark:text-slate-200"
                            >
                                {{ termStats.active }}
                            </span>
                            active term configured for this campus.
                        </div>
                    </div>
                </section>
            </div>

            <div v-if="activeTab === 'terms'" class="space-y-3">
                <div
                    class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="searchTerm"
                            placeholder="Search school year, semester, or API ID"
                            class="h-8 pl-8 text-xs"
                        />
                    </div>
                    <Button
                        size="sm"
                        class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                        @click="openCreateTerm"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Add Term
                    </Button>
                </div>

                <div
                    class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-4 py-2.5">Academic period</th>
                                <th class="px-4 py-2.5">API term ID</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Date range</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="term in filteredTerms"
                                :key="term.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td class="px-4 py-3">
                                    <div
                                        class="font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ term.school_year }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ term.semester }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs font-semibold text-sky-700 dark:text-sky-300"
                                >
                                    {{ term.term_id || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :class="
                                            getStatusBadgeClass(term.status)
                                        "
                                        variant="outline"
                                    >
                                        <component
                                            :is="
                                                term.status === 'Active'
                                                    ? CheckCircle2
                                                    : term.status === 'Archived'
                                                      ? Archive
                                                      : Clock
                                            "
                                            class="mr-1 size-3"
                                        />
                                        {{ term.status }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400"
                                >
                                    {{ formatDate(term.start_date) }}
                                    <span class="px-1 text-slate-300">to</span>
                                    {{ formatDate(term.end_date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            v-if="term.status !== 'Active'"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10"
                                            title="Set as active"
                                            @click="activateTerm(term)"
                                        >
                                            <Power class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                            title="Edit term"
                                            @click="openEditTerm(term)"
                                        >
                                            <Edit class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10"
                                            title="Delete term"
                                            @click="confirmDeleteTerm(term)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredTerms.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    <Calendar
                                        class="mx-auto mb-2 size-7 text-slate-300"
                                    />
                                    No academic terms found for this campus.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'offices'" class="space-y-3">
                <div
                    class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="officeSearch"
                            placeholder="Search office name, code, or description"
                            class="h-8 pl-8 text-xs"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 border-slate-200 text-xs dark:border-white/10"
                            :disabled="fetchingColleges"
                            @click="fetchCollegesFromApi"
                        >
                            <Loader2
                                v-if="fetchingColleges"
                                class="mr-1.5 size-3.5 animate-spin"
                            />
                            <Download v-else class="mr-1.5 size-3.5" />
                            Import Colleges
                        </Button>
                        <Button
                            size="sm"
                            class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                            @click="openCreateOffice"
                        >
                            <Plus class="mr-1.5 size-3.5" />
                            Add Office
                        </Button>
                    </div>
                </div>

                <div
                    class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-4 py-2.5">Office</th>
                                <th class="px-4 py-2.5">Code</th>
                                <th class="px-4 py-2.5">Category</th>
                                <th class="px-4 py-2.5">Description</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="office in filteredOffices"
                                :key="office.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td
                                    class="px-4 py-3 font-semibold text-slate-900 dark:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <BriefcaseBusiness
                                            class="size-4 text-sky-500"
                                        />
                                        {{ office.name }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs text-sky-700 dark:text-sky-300"
                                >
                                    {{ office.code || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold capitalize',
                                            office.category === 'academic'
                                                ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'
                                                : office.category ===
                                                    'administration'
                                                  ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                                  : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                        ]"
                                    >
                                        {{
                                            office.category === 'support'
                                                ? 'Support Unit'
                                                : office.category
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="max-w-md px-4 py-3 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ office.description || 'No description' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            @click="openEditOffice(office)"
                                        >
                                            <Edit class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            @click="confirmDeleteOffice(office)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredOffices.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No offices found for this campus.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'clearance-types'" class="space-y-3">
                <div
                    class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="clearanceTypeSearch"
                            placeholder="Search clearance type name or description"
                            class="h-8 pl-8 text-xs"
                        />
                    </div>
                    <Button
                        size="sm"
                        class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                        @click="openCreateClearanceType"
                    >
                        <Plus class="mr-1.5 size-3.5" />
                        Add Clearance Type
                    </Button>
                </div>

                <div
                    class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                        >
                            <tr>
                                <th class="px-4 py-2.5">Clearance Type</th>
                                <th class="px-4 py-2.5">Availability</th>
                                <th class="px-4 py-2.5">Description</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="type in filteredClearanceTypes"
                                :key="type.id"
                                class="hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <td
                                    class="px-4 py-3 font-semibold text-slate-900 dark:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <FileCheck2
                                            class="size-4 text-emerald-500"
                                        />
                                        {{ type.name }}
                                    </span>
                                    <div
                                        v-if="
                                            type.offices &&
                                            type.offices.length > 0
                                        "
                                        class="mt-1 flex flex-wrap gap-1"
                                    >
                                        <Badge
                                            v-for="office in type.offices"
                                            :key="office.id"
                                            variant="secondary"
                                            class="px-1 py-0 font-mono text-[9px] font-semibold"
                                            :title="office.name"
                                        >
                                            {{ office.code || office.name }}
                                        </Badge>
                                    </div>
                                    <div
                                        v-else
                                        class="mt-0.5 text-[10px] font-normal text-slate-400"
                                    >
                                        All campus offices
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-bold',
                                            type.audience === 'individual'
                                                ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'
                                                : 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                        ]"
                                    >
                                        <UserRound
                                            v-if="
                                                type.audience === 'individual'
                                            "
                                            class="size-3"
                                        />
                                        <Users v-else class="size-3" />
                                        {{
                                            type.audience === 'individual'
                                                ? 'Selected students'
                                                : 'All students'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="max-w-md px-4 py-3 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ type.description || 'No description' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            title="Configure Participating Offices"
                                            @click="
                                                openConfigureClearanceType(type)
                                            "
                                        >
                                            <Settings class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            @click="openEditClearanceType(type)"
                                        >
                                            <Edit class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            @click="
                                                confirmDeleteClearanceType(type)
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredClearanceTypes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-sm text-slate-500"
                                >
                                    No clearance types found for this campus.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog v-model:open="showTermModal">
                <DialogContent class="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                selectedTerm
                                    ? 'Edit Academic Term'
                                    : 'New Academic Term'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            Configure period metadata and API synchronization
                            ID.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-3 py-2" @submit.prevent="saveTerm">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="sy">School Year</Label>
                                <Input
                                    id="sy"
                                    v-model="termForm.school_year"
                                    class="h-8 text-sm"
                                    placeholder="2025-2026"
                                    required
                                />
                                <div
                                    v-if="termForm.errors.school_year"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.school_year }}
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="semester">Semester</Label>
                                <Select v-model="termForm.semester">
                                    <SelectTrigger id="semester" class="h-8">
                                        <SelectValue
                                            placeholder="Select semester"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1st Semester">
                                            1st Semester
                                        </SelectItem>
                                        <SelectItem value="2nd Semester">
                                            2nd Semester
                                        </SelectItem>
                                        <SelectItem value="Summer"
                                            >Summer</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <div
                                    v-if="termForm.errors.semester"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.semester }}
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="term_id">Term ID (API)</Label>
                                <Input
                                    id="term_id"
                                    v-model="termForm.term_id"
                                    class="h-8 text-sm"
                                    placeholder="102"
                                />
                                <div
                                    v-if="termForm.errors.term_id"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.term_id }}
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="term_status">Status</Label>
                                <Select v-model="termForm.status">
                                    <SelectTrigger id="term_status" class="h-8">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Active"
                                            >Active</SelectItem
                                        >
                                        <SelectItem value="Inactive"
                                            >Inactive</SelectItem
                                        >
                                        <SelectItem value="Archived"
                                            >Archived</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <div
                                    v-if="termForm.errors.status"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.status }}
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    v-model="termForm.start_date"
                                    class="h-8 text-sm"
                                    type="date"
                                />
                                <div
                                    v-if="termForm.errors.start_date"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.start_date }}
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="end_date">End Date</Label>
                                <Input
                                    id="end_date"
                                    v-model="termForm.end_date"
                                    class="h-8 text-sm"
                                    type="date"
                                />
                                <div
                                    v-if="termForm.errors.end_date"
                                    class="text-xs text-red-500"
                                >
                                    {{ termForm.errors.end_date }}
                                </div>
                            </div>
                        </div>

                        <DialogFooter class="pt-2">
                            <Button
                                type="submit"
                                :disabled="termForm.processing"
                                class="h-8 bg-sky-600 px-3 text-xs hover:bg-sky-700"
                            >
                                {{
                                    selectedTerm ? 'Update Term' : 'Create Term'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showOfficeModal">
                <DialogContent class="sm:max-w-[460px]">
                    <DialogHeader>
                        <DialogTitle>
                            {{ selectedOffice ? 'Edit Office' : 'New Office' }}
                        </DialogTitle>
                        <DialogDescription>
                            This office will belong to
                            {{ props.campus.campus_name }}.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-3 py-2" @submit.prevent="saveOffice">
                        <div class="space-y-1.5">
                            <Label for="office-name">Office Name</Label>
                            <Input
                                id="office-name"
                                v-model="officeForm.name"
                                class="h-8"
                                required
                            />
                            <div
                                v-if="officeForm.errors.name"
                                class="text-xs text-red-500"
                            >
                                {{ officeForm.errors.name }}
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="office-code">Office Code</Label>
                            <Input
                                id="office-code"
                                v-model="officeForm.code"
                                class="h-8"
                            />
                            <div
                                v-if="officeForm.errors.code"
                                class="text-xs text-red-500"
                            >
                                {{ officeForm.errors.code }}
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="office-category">Office Category</Label>
                            <select
                                id="office-category"
                                v-model="officeForm.category"
                                class="h-8 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-sky-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                required
                            >
                                <option value="academic">Academic</option>
                                <option value="support">Support Unit</option>
                                <option value="administration">
                                    Administration
                                </option>
                            </select>
                            <div
                                v-if="officeForm.errors.category"
                                class="text-xs text-red-500"
                            >
                                {{ officeForm.errors.category }}
                            </div>
                            <p class="text-[10px] text-slate-500">
                                Academic offices are shown only to students who
                                belong to that office.
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="office-description">Description</Label>
                            <textarea
                                id="office-description"
                                v-model="officeForm.description"
                                rows="3"
                                class="w-full rounded-md border border-slate-200 bg-white p-3 text-sm text-slate-900 focus:border-sky-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                            />
                            <div
                                v-if="officeForm.errors.description"
                                class="text-xs text-red-500"
                            >
                                {{ officeForm.errors.description }}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                                :disabled="officeForm.processing"
                            >
                                {{
                                    selectedOffice
                                        ? 'Update Office'
                                        : 'Create Office'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="showEditModal">
                <DialogContent class="sm:max-w-[460px]">
                    <DialogHeader>
                        <DialogTitle>Edit Campus</DialogTitle>
                        <DialogDescription>
                            Update identity, status, and logo settings.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-3 py-2" @submit.prevent="updateCampus">
                        <div class="space-y-1.5">
                            <Label for="edit_name">Campus Name</Label>
                            <Input
                                id="edit_name"
                                v-model="form.campus_name"
                                class="h-8 text-sm"
                                required
                            />
                            <div
                                v-if="form.errors.campus_name"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.campus_name }}
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit_address">Address</Label>
                            <Input
                                id="edit_address"
                                v-model="form.campus_address"
                                class="h-8 text-sm"
                            />
                            <div
                                v-if="form.errors.campus_address"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.campus_address }}
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="edit_real_id">Real Campus ID</Label>
                                <Input
                                    id="edit_real_id"
                                    v-model="form.real_campus_id"
                                    class="h-8 text-sm"
                                />
                                <div
                                    v-if="form.errors.real_campus_id"
                                    class="text-xs text-red-500"
                                >
                                    {{ form.errors.real_campus_id }}
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="edit_status">Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="edit_status" class="h-8">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Active"
                                            >Active</SelectItem
                                        >
                                        <SelectItem value="Inactive"
                                            >Inactive</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <div
                                    v-if="form.errors.status"
                                    class="text-xs text-red-500"
                                >
                                    {{ form.errors.status }}
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="edit_campus_id">Campus ID</Label>
                                <Input
                                    id="edit_campus_id"
                                    v-model="form.campus_id"
                                    class="h-8 text-sm"
                                    type="number"
                                />
                                <div
                                    v-if="form.errors.campus_id"
                                    class="text-xs text-red-500"
                                >
                                    {{ form.errors.campus_id }}
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="edit_tenant_id">Tenant ID</Label>
                                <Input
                                    id="edit_tenant_id"
                                    v-model="form.tenant_id"
                                    class="h-8 text-sm"
                                />
                                <div
                                    v-if="form.errors.tenant_id"
                                    class="text-xs text-red-500"
                                >
                                    {{ form.errors.tenant_id }}
                                </div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit_logo">Campus Logo</Label>
                            <Input
                                id="edit_logo"
                                type="file"
                                accept="image/*"
                                class="h-8 text-sm"
                                @input="form.logo = $event.target.files[0]"
                            />
                            <div
                                v-if="form.errors.logo"
                                class="text-xs font-medium text-red-500"
                            >
                                {{ form.errors.logo }}
                            </div>
                        </div>
                        <DialogFooter class="pt-2">
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-8 bg-sky-600 px-3 text-xs hover:bg-sky-700"
                            >
                                Update Campus
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationModal
                v-if="selectedTerm"
                :show="showDeleteTermModal"
                title="Delete Academic Term"
                :description="`Are you sure you want to delete ${selectedTerm.school_year} ${selectedTerm.semester}? This action cannot be undone.`"
                confirm-text="Delete Term"
                variant="destructive"
                @confirm="deleteTerm"
                @close="showDeleteTermModal = false"
            />
            <ConfirmationModal
                :show="showDeleteOfficeModal"
                title="Delete Office"
                :description="`Delete ${selectedOffice?.name ?? 'this office'} from ${props.campus.campus_name}?`"
                confirm-text="Delete Office"
                variant="destructive"
                :loading="officeForm.processing"
                @confirm="deleteOffice"
                @close="showDeleteOfficeModal = false"
            />

            <!-- Clearance Type Dialog -->
            <Dialog v-model:open="showClearanceTypeModal">
                <DialogContent class="sm:max-w-[460px]">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                selectedClearanceType
                                    ? 'Edit Clearance Type'
                                    : 'New Clearance Type'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            Configure clearance type audience rules. This will
                            belong to
                            {{ props.campus.campus_name }}.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        class="space-y-3 py-2"
                        @submit.prevent="saveClearanceType"
                    >
                        <div class="space-y-1.5">
                            <Label for="clearance-type-name">Name</Label>
                            <Input
                                id="clearance-type-name"
                                v-model="clearanceTypeForm.name"
                                class="h-8"
                                required
                            />
                            <div
                                v-if="clearanceTypeForm.errors.name"
                                class="text-xs text-red-500"
                            >
                                {{ clearanceTypeForm.errors.name }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="clearance-type-audience"
                                >Who is this clearance for?</Label
                            >
                            <Select v-model="clearanceTypeForm.audience">
                                <SelectTrigger
                                    id="clearance-type-audience"
                                    class="h-8"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All students
                                    </SelectItem>
                                    <SelectItem value="individual">
                                        Selected students only
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-[11px] text-slate-500">
                                Use selected students for credential release and
                                other individual clearances.
                            </p>
                            <div
                                v-if="clearanceTypeForm.errors.audience"
                                class="text-xs text-red-500"
                            >
                                {{ clearanceTypeForm.errors.audience }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="clearance-type-description"
                                >Description</Label
                            >
                            <textarea
                                id="clearance-type-description"
                                v-model="clearanceTypeForm.description"
                                rows="3"
                                class="w-full rounded-md border border-slate-200 bg-white p-3 text-sm text-slate-900 focus:border-sky-500 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                            />
                            <div
                                v-if="clearanceTypeForm.errors.description"
                                class="text-xs text-red-500"
                            >
                                {{ clearanceTypeForm.errors.description }}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="submit"
                                class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                                :disabled="clearanceTypeForm.processing"
                            >
                                {{
                                    selectedClearanceType
                                        ? 'Update Clearance Type'
                                        : 'Create Clearance Type'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Clearance Type Delete Confirmation -->
            <ConfirmationModal
                :show="showDeleteClearanceTypeModal"
                title="Delete Clearance Type"
                :description="`Delete ${selectedClearanceType?.name ?? 'this clearance type'} from ${props.campus.campus_name}?`"
                confirm-text="Delete Clearance Type"
                variant="destructive"
                :loading="clearanceTypeForm.processing"
                @confirm="deleteClearanceType"
                @close="showDeleteClearanceTypeModal = false"
            />

            <!-- Clearance Type Configure Participating Offices Dialog -->
            <Dialog v-model:open="showConfigureModal">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle
                            >Configure Participating Offices</DialogTitle
                        >
                        <DialogDescription>
                            Select which offices are required/participating
                            under
                            <span
                                class="font-semibold text-slate-900 dark:text-white"
                                >{{ selectedClearanceType?.name }}</span
                            >.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-2">
                        <!-- Search & Quick Select Action Buttons -->
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="relative flex-1">
                                <Search
                                    class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                                />
                                <Input
                                    v-model="officeSearchTerm"
                                    placeholder="Filter offices..."
                                    class="h-8 pl-8 text-xs"
                                />
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2 text-[10px]"
                                    @click="selectAllOffices"
                                >
                                    Select All
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2 text-[10px]"
                                    @click="deselectAllOffices"
                                >
                                    Clear All
                                </Button>
                            </div>
                        </div>

                        <!-- Offices List (Scrollable Area) -->
                        <div
                            class="max-h-[300px] space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3 dark:border-white/10 dark:bg-slate-900"
                        >
                            <div
                                v-for="office in filteredConfigOffices"
                                :key="office.id"
                                class="flex items-start gap-2.5 rounded-md p-1.5 hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <input
                                    :id="`config-office-${office.id}`"
                                    v-model="configForm.office_ids"
                                    type="checkbox"
                                    :value="office.id"
                                    class="mt-1 size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-800"
                                />
                                <label
                                    :for="`config-office-${office.id}`"
                                    class="flex flex-1 cursor-pointer flex-col text-xs select-none"
                                >
                                    <span
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ office.name }}
                                    </span>
                                    <span
                                        v-if="office.code"
                                        class="font-mono text-[10px] text-sky-600 dark:text-sky-400"
                                    >
                                        {{ office.code }}
                                    </span>
                                </label>
                            </div>
                            <div
                                v-if="filteredConfigOffices.length === 0"
                                class="py-6 text-center text-xs text-slate-500"
                            >
                                No offices match search query or exist for this
                                campus.
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500">
                            * Selected offices will automatically participate
                            when setting up clearance updates. Leaving all
                            offices unchecked defaults to assigning all offices.
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs"
                            @click="showConfigureModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                            :disabled="configForm.processing"
                            @click="saveConfiguration"
                        >
                            Save Settings
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Import Colleges Dialog -->
            <Dialog v-model:open="showImportModal">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Import Colleges as Offices</DialogTitle>
                        <DialogDescription>
                            We found {{ apiColleges.length }} colleges for
                            Tenant ID
                            <span
                                class="font-semibold text-slate-900 dark:text-white"
                                >{{ props.campus.tenant_id || '1' }}</span
                            >. Select which ones to import as offices for this
                            campus.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-2">
                        <!-- Select All toggle link/button -->
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto p-0 text-xs text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                                @click="toggleAllImportColleges"
                            >
                                {{
                                    selectedImportCollegeCodes.length ===
                                    apiColleges.length
                                        ? 'Deselect All'
                                        : 'Select All'
                                }}
                            </Button>
                        </div>

                        <!-- Colleges list scrollable container -->
                        <div
                            class="max-h-[300px] space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3 dark:border-white/10 dark:bg-slate-900"
                        >
                            <div
                                v-for="college in apiColleges"
                                :key="college.collegeId"
                                class="flex items-start gap-2.5 rounded-md p-1.5 hover:bg-slate-50 dark:hover:bg-white/5"
                            >
                                <input
                                    :id="`import-college-${college.collegeId}`"
                                    v-model="selectedImportCollegeCodes"
                                    type="checkbox"
                                    :value="college.collegeCode"
                                    class="mt-1 size-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-700 dark:bg-slate-800"
                                />
                                <label
                                    :for="`import-college-${college.collegeId}`"
                                    class="flex flex-1 cursor-pointer flex-col text-xs select-none"
                                >
                                    <span
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ college.collegeName }}
                                    </span>
                                    <span
                                        class="font-mono text-[10px] text-sky-600 dark:text-sky-400"
                                    >
                                        Code: {{ college.collegeCode }}
                                    </span>
                                </label>
                            </div>
                            <div
                                v-if="apiColleges.length === 0"
                                class="py-6 text-center text-xs text-slate-500"
                            >
                                No colleges returned from API.
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500">
                            * Colleges with duplicate names or codes already
                            registered in this campus will be automatically
                            skipped during import.
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs"
                            @click="showImportModal = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="h-8 bg-sky-600 text-xs hover:bg-sky-700"
                            :disabled="
                                importForm.processing ||
                                selectedImportCollegeCodes.length === 0
                            "
                            @click="importColleges"
                        >
                            Import Selected ({{
                                selectedImportCollegeCodes.length
                            }})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </SiteSettingsLayout>
</template>
