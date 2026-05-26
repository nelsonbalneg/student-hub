<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Edit,
    Fingerprint,
    History,
    Info,
    MapPin,
    Plus,
    Power,
    RefreshCcw,
    Search,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import * as termRoutes from '@/routes/site-settings/campuses/terms';
import { format } from 'date-fns';

interface AcademicTerm {
    id: number;
    school_year: string;
    semester: string;
    term_id: string;
    status: 'Active' | 'Inactive' | 'Archived';
    start_date: string;
    end_date: string;
}

interface Campus {
    id: number;
    campus_name: string;
    campus_address: string;
    campus_logo_path: string;
    real_campus_id: string;
    status: 'Active' | 'Inactive';
    created_at?: string | null;
    updated_at?: string | null;
    academic_terms: AcademicTerm[];
}

const props = defineProps<{
    campus: Campus;
}>();

const showTermModal = ref(false);
const showDeleteTermModal = ref(false);
const showEditModal = ref(false);
const selectedTerm = ref<AcademicTerm | null>(null);
const searchTerm = ref('');
const activeTab = ref<'info' | 'terms'>('info');

const form = useForm({
    campus_name: props.campus.campus_name,
    campus_address: props.campus.campus_address,
    real_campus_id: props.campus.real_campus_id,
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

const openEditModal = () => {
    form.campus_name = props.campus.campus_name;
    form.campus_address = props.campus.campus_address;
    form.real_campus_id = props.campus.real_campus_id;
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
    if (!selectedTerm.value) return;

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
                    @click="activeTab = 'info'"
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
                    @click="activeTab = 'terms'"
                >
                    <Calendar class="size-3.5" />
                    Academic Terms
                    <span
                        class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] dark:bg-white/10"
                        >{{ termStats.total }}</span
                    >
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
        </div>
    </SiteSettingsLayout>
</template>
