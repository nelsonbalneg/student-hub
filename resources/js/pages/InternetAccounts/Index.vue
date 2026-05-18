<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Ban,
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Clock3,
    Copy,
    Eye,
    EyeOff,
    Hash,
    IdCard,
    KeyRound,
    Loader2,
    MapPin,
    RefreshCcw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    Wifi,
    XCircle,
    X,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import * as internetAccountRoutes from '@/routes/internet-accounts';

type ActiveSemester = {
    semester: string | null;
    termId: string | null;
    campusId: number | null;
    error: string | null;
};

type Student = {
    name: string;
    student_no: string | null;
    campus_name: string | null;
    campus_id: number | null;
};

type InternetAccountRequest = {
    id: number;
    student_no: string;
    semester: string;
    term_id: string;
    campus_id: number | null;
    username: string;
    password: string | null;
    status: 'pending' | 'cancelled' | 'processing' | 'active' | 'failed';
    failure_reason: string | null;
    created_at: string | null;
    student_name: string | null;
    student_email: string | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type PageMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};
type Page<T> = { data: T[]; links: PageLink[]; meta: PageMeta };

const props = defineProps<{
    activeSemester: ActiveSemester;
    student: Student;
    currentTermRequest: InternetAccountRequest | null;
    requests: Page<InternetAccountRequest>;
    filters: Record<string, string | undefined>;
    filterOptions: {
        statuses: string[];
    };
    can: {
        manage: boolean;
        approve: boolean;
        cancel: boolean;
        delete: boolean;
    };
}>();

const form = useForm({});
const visiblePasswordIds = ref<number[]>([]);
const search = ref(props.filters.request_search ?? '');
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status ?? '',
    term_id: props.filters.term_id ?? '',
});

const activeFilterCount = computed(
    () => Object.values(filters.value).filter(Boolean).length,
);

const canSubmit = computed(
    () =>
        !props.activeSemester.error &&
        !!props.student.student_no &&
        !!props.activeSemester.semester &&
        !!props.activeSemester.termId &&
        !props.currentTermRequest,
);

const generatedUsername = computed(() => {
    if (!props.student.student_no || !props.activeSemester.termId) {
        return '-';
    }

    return `${props.student.student_no}-${props.activeSemester.termId}`;
});

const statusStyles: Record<InternetAccountRequest['status'], string> = {
    pending:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200',
    cancelled:
        'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300',
    processing:
        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200',
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200',
};

const statusIcons = {
    pending: Clock3,
    cancelled: Ban,
    processing: RefreshCcw,
    active: CheckCircle2,
    failed: XCircle,
};

const formatDate = (value: string | null) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
};

const submit = () => {
    form.post(internetAccountRoutes.store.url(), {
        preserveScroll: true,
    });
};

const approve = (request: InternetAccountRequest) => {
    form.patch(internetAccountRoutes.approve.url(request.id), {
        preserveScroll: true,
    });
};

const cancel = (request: InternetAccountRequest) => {
    form.patch(internetAccountRoutes.cancel.url(request.id), {
        preserveScroll: true,
    });
};

const destroy = (request: InternetAccountRequest) => {
    form.delete(internetAccountRoutes.destroy.url(request.id), {
        preserveScroll: true,
    });
};

const isPasswordVisible = (request: InternetAccountRequest) =>
    visiblePasswordIds.value.includes(request.id);

const togglePassword = (request: InternetAccountRequest) => {
    visiblePasswordIds.value = isPasswordVisible(request)
        ? visiblePasswordIds.value.filter((id) => id !== request.id)
        : [...visiblePasswordIds.value, request.id];
};

const maskedPassword = (password: string | null) =>
    password ? '•'.repeat(Math.min(Math.max(password.length, 8), 16)) : '-';

const copyPassword = async (request: InternetAccountRequest) => {
    if (!request.password) return;

    await navigator.clipboard.writeText(request.password);
};

const canApproveRequest = (request: InternetAccountRequest) =>
    props.can.approve && !['active', 'processing'].includes(request.status);

const canCancelRequest = (request: InternetAccountRequest) =>
    props.can.cancel && !['active', 'cancelled'].includes(request.status);

const canDeleteRequest = (request: InternetAccountRequest) =>
    props.can.delete && request.status !== 'processing';

const applyFilters = () => {
    router.get(
        internetAccountRoutes.index.url(),
        {
            request_search: search.value,
            ...filters.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
watch(filters, applyFilters, { deep: true });

const clearFilters = () => {
    search.value = '';
    filters.value = {
        status: '',
        term_id: '',
    };
    applyFilters();
};

const navigatePage = (url?: string | null) => {
    if (!url) return;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Internet Account" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 lg:p-6">
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <div
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 uppercase shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <Wifi class="size-3.5 text-sky-600" />
                        Network Access
                    </div>
                    <h1
                        class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white"
                    >
                        Internet Account
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Request and manage MikroTik internet accounts for the
                        current active term.
                    </p>
                </div>

                <Button
                    class="h-10 rounded-md px-4"
                    :disabled="!canSubmit || form.processing"
                    @click="submit"
                >
                    <Loader2
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    <KeyRound v-else class="size-4" />
                    Request Account
                </Button>
            </div>
        </section>

        <div
            v-if="activeSemester.error"
            class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200"
        >
            <AlertCircle class="mt-0.5 size-4 shrink-0" />
            <span>{{ activeSemester.error }}</span>
        </div>

        <div
            v-if="currentTermRequest"
            class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100"
        >
            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
            <span>
                You already have an internet account request for this active
                term.
            </span>
        </div>

        <section class="grid gap-3 md:grid-cols-2" :class="can.manage ? 'xl:grid-cols-4' : 'xl:grid-cols-3'">
            <div
                v-if="can.manage"
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                >
                    <CalendarClock class="size-4 text-sky-600" />
                    Active Semester
                </div>
                <div
                    class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                >
                    {{ activeSemester.semester || '-' }}
                </div>
            </div>
            <div
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                >
                    <MapPin class="size-4 text-emerald-600" />
                    Campus
                </div>
                <div
                    class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                >
                    {{ student.campus_name || activeSemester.campusId || '-' }}
                </div>
            </div>
            <div
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                >
                    <Hash class="size-4 text-violet-600" />
                    Current Term ID
                </div>
                <div
                    class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                >
                    {{ activeSemester.termId || '-' }}
                </div>
            </div>
            <div
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                >
                    <IdCard class="size-4 text-amber-600" />
                    Generated Username
                </div>
                <div
                    class="mt-2 truncate text-lg font-bold text-slate-950 dark:text-white"
                >
                    {{ generatedUsername }}
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 dark:border-white/10"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Request History
                        </h2>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{
                                can.manage
                                    ? 'Review approvals, cancellations, and provisioning status across students.'
                                    : 'Accounts are generated per student number and active term.'
                            }}
                        </p>
                    </div>
                    <p class="text-xs font-medium text-slate-500">
                        Showing
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                        >
                            {{ requests.meta.from ?? 0 }}-{{
                                requests.meta.to ?? 0
                            }}
                        </span>
                        of
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                        >
                            {{ requests.meta.total }}
                        </span>
                    </p>
                </div>

                <div
                    v-if="can.manage"
                    class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search username, student, semester, or term..."
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute top-1/2 right-2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-white"
                            @click="search = ''"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-xs font-bold transition"
                        :class="
                            filterOpen || activeFilterCount > 0
                                ? 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'
                        "
                        @click="filterOpen = !filterOpen"
                    >
                        <SlidersHorizontal class="size-3.5" />
                        Filters
                        <span
                            v-if="activeFilterCount > 0"
                            class="inline-flex size-4 items-center justify-center rounded-full bg-sky-600 text-[9px] font-bold text-white"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                        @click="clearFilters"
                    >
                        <RefreshCcw class="size-3.5" />
                        Reset
                    </button>
                </div>

                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-2 opacity-0"
                >
                    <div
                        v-if="can.manage && filterOpen"
                        class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5"
                    >
                        <label
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Status
                            <select
                                v-model="filters.status"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All statuses</option>
                                <option
                                    v-for="status in filterOptions.statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </option>
                            </select>
                        </label>
                        <label
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Term ID
                            <input
                                v-model="filters.term_id"
                                type="text"
                                placeholder="Filter by term ID"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            />
                        </label>
                    </div>
                </Transition>
            </div>

            <div
                v-if="requests.data.length === 0"
                class="flex min-h-[220px] flex-col items-center justify-center gap-2 p-8 text-center"
            >
                <Wifi class="size-9 text-slate-300 dark:text-slate-600" />
                <p class="text-sm font-bold text-slate-900 dark:text-white">
                    No internet account requests yet
                </p>
                <p
                    class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                >
                    Your first request will be queued for MikroTik provisioning.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead
                        class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                    >
                        <tr>
                            <th v-if="can.manage" class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3">Password</th>
                            <th class="px-4 py-3">Semester</th>
                            <th v-if="can.manage" class="px-4 py-3">Term ID</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Requested</th>
                            <th
                                v-if="can.approve || can.cancel || can.delete"
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
                            v-for="request in requests.data"
                            :key="request.id"
                            class="hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <td v-if="can.manage" class="px-4 py-3">
                                <div
                                    class="font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        request.student_name ||
                                        request.student_no
                                    }}
                                </div>
                                <div
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        request.student_email ||
                                        request.student_no
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="font-bold text-slate-950 dark:text-white"
                                >
                                    {{ request.username }}
                                </div>
                                <div
                                    v-if="request.failure_reason"
                                    class="mt-1 text-xs font-medium text-red-600 dark:text-red-300"
                                >
                                    {{ request.failure_reason }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex max-w-[220px] items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 dark:border-white/10 dark:bg-white/5"
                                >
                                    <span
                                        class="min-w-0 flex-1 truncate font-mono text-xs font-bold text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            isPasswordVisible(request)
                                                ? request.password || '-'
                                                : maskedPassword(
                                                      request.password,
                                                  )
                                        }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-white hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                                        title="Show or hide password"
                                        :disabled="!request.password"
                                        @click="togglePassword(request)"
                                    >
                                        <EyeOff
                                            v-if="isPasswordVisible(request)"
                                            class="size-3.5"
                                        />
                                        <Eye v-else class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-white hover:text-slate-950 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                                        title="Copy password"
                                        :disabled="!request.password"
                                        @click="copyPassword(request)"
                                    >
                                        <Copy class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                {{ request.semester }}
                            </td>
                            <td
                                v-if="can.manage"
                                class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                {{ request.term_id }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-bold uppercase"
                                    :class="statusStyles[request.status]"
                                >
                                    <component
                                        :is="statusIcons[request.status]"
                                        class="size-3.5"
                                    />
                                    {{ request.status }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                            >
                                {{ formatDate(request.created_at) }}
                            </td>
                            <td
                                v-if="can.approve || can.cancel || can.delete"
                                class="px-4 py-3"
                            >
                                <div class="flex justify-end gap-1">
                                    <button
                                        v-if="canApproveRequest(request)"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-emerald-600 transition hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                        title="Approve request"
                                        :disabled="form.processing"
                                        @click="approve(request)"
                                    >
                                        <CheckCircle2 class="size-4" />
                                    </button>
                                    <button
                                        v-if="canCancelRequest(request)"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-amber-600 transition hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10"
                                        title="Cancel request"
                                        :disabled="form.processing"
                                        @click="cancel(request)"
                                    >
                                        <Ban class="size-4" />
                                    </button>
                                    <button
                                        v-if="canDeleteRequest(request)"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                                        title="Delete request"
                                        :disabled="form.processing"
                                        @click="destroy(request)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="requests.meta.last_page > 1"
                class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <p class="text-xs font-medium text-slate-500">
                    Page {{ requests.meta.current_page }} of
                    {{ requests.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="!requests.links[0]?.url"
                        @click="navigatePage(requests.links[0]?.url)"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="requests.meta.current_page === 1"
                        @click="
                            navigatePage(
                                requests.links[requests.meta.current_page - 1]
                                    ?.url,
                            )
                        "
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <template
                        v-for="link in requests.links.slice(1, -1)"
                        :key="link.label"
                    >
                        <button
                            v-if="link.label !== '...'"
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-md text-xs font-bold transition"
                            :class="
                                link.active
                                    ? 'bg-sky-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'
                            "
                            @click="navigatePage(link.url)"
                            v-html="link.label"
                        />
                        <span v-else class="px-1 text-slate-400">...</span>
                    </template>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            requests.meta.current_page ===
                            requests.meta.last_page
                        "
                        @click="
                            navigatePage(
                                requests.links[requests.meta.current_page + 1]
                                    ?.url,
                            )
                        "
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            !requests.links[requests.links.length - 1]?.url
                        "
                        @click="
                            navigatePage(
                                requests.links[requests.links.length - 1]?.url,
                            )
                        "
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
