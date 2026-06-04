<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock,
    Copy,
    Download,
    FileDown,
    FileText,
    Filter,
    RotateCcw,
    Search,
    ShieldAlert,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    clear as clearLogs,
    download as downloadLog,
    exportMethod,
    index as logsIndex,
} from '@/routes/system/logs';

type Summary = {
    total_log_files: number;
    errors_today: number;
    critical_errors: number;
    warning_logs: number;
    latest_error: string | null;
};

type LogFile = {
    name: string;
    size: number;
    size_display: string;
    modified_at: string | null;
};

type LogEntry = {
    id: string;
    timestamp: string;
    environment: string;
    level: string;
    message: string;
    raw: string;
    exception_type: string | null;
    file: string | null;
    line: string | null;
    url: string | null;
    method: string | null;
    ip_address: string | null;
    user_agent: string | null;
};

type FilePaginator = {
    data: LogFile[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type EntryPaginator = {
    data: LogEntry[];
    total: number;
    current_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    has_more: boolean;
};

type Filters = {
    level: string;
    range: string;
    from: string;
    to: string;
    search: string;
    environment: string;
    page: number;
    per_page: number;
    file_search: string;
    file_sort: string;
};

const props = defineProps<{
    summary: Summary;
    files: FilePaginator;
    entries: EntryPaginator;
    selectedFile: string | null;
    filters: Filters;
    can: {
        download: boolean;
        clear: boolean;
    };
    levels: string[];
    environments: string[];
}>();

const fileSearch = ref(props.filters.file_search || '');
const fileSort = ref(props.filters.file_sort || 'modified_desc');
const level = ref(props.filters.level || 'all');
const range = ref(props.filters.range || 'today');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const search = ref(props.filters.search || '');
const environment = ref(props.filters.environment || 'all');
const perPage = ref(props.filters.per_page || 25);
const selectedEntry = ref<LogEntry | null>(null);
const clearCandidate = ref<string | null>(null);

const selectedFile = computed(() => props.selectedFile);

const query = (overrides: Record<string, unknown> = {}) => ({
    file: selectedFile.value,
    file_search: fileSearch.value,
    file_sort: fileSort.value,
    level: level.value,
    range: range.value,
    from: from.value,
    to: to.value,
    search: search.value,
    environment: environment.value,
    per_page: perPage.value,
    ...overrides,
});

const visit = (overrides: Record<string, unknown> = {}) => {
    router.get(
        logsIndex.url(query({ page: 1, ...overrides })),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

let searchTimer: number | undefined;
const debouncedVisit = () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => visit(), 300);
};

const resetFilters = () => {
    level.value = 'all';
    range.value = 'today';
    from.value = '';
    to.value = '';
    search.value = '';
    environment.value = 'all';
    perPage.value = 25;
    visit();
};

const levelBadgeClass = (value: string) => {
    const levelName = value.toLowerCase();

    if (['emergency', 'alert', 'critical'].includes(levelName)) {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300';
    }

    if (levelName === 'error') {
        return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300';
    }

    if (levelName === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (levelName === 'notice') {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300';
    }

    if (levelName === 'info') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
};

const summaryCards = computed(() => [
    {
        label: 'Total Log Files',
        value: props.summary.total_log_files,
        icon: FileText,
    },
    {
        label: 'Errors Today',
        value: props.summary.errors_today,
        icon: AlertTriangle,
    },
    {
        label: 'Critical Errors',
        value: props.summary.critical_errors,
        icon: ShieldAlert,
    },
    {
        label: 'Warning Logs',
        value: props.summary.warning_logs,
        icon: Clock,
    },
]);

const copyText = async (value: string | null | undefined) => {
    if (!value) {
        return;
    }

    await navigator.clipboard.writeText(value);
};

const downloadDetails = () => {
    if (!selectedEntry.value) {
        return;
    }

    const payload = [
        `Timestamp: ${selectedEntry.value.timestamp}`,
        `Environment: ${selectedEntry.value.environment}`,
        `Level: ${selectedEntry.value.level}`,
        `Message: ${selectedEntry.value.message}`,
        '',
        selectedEntry.value.raw,
    ].join('\n');

    const blob = new Blob([payload], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `error-${selectedEntry.value.id}.txt`;
    link.click();
    URL.revokeObjectURL(url);
};

const confirmClear = () => {
    if (!clearCandidate.value) {
        return;
    }

    router.delete(clearLogs.url(), {
        data: { file: clearCandidate.value },
        preserveScroll: true,
        onSuccess: () => {
            clearCandidate.value = null;
        },
    });
};
</script>

<template>
    <Head title="System Logs" />

   
        <div class="space-y-5 p-4 sm:p-6">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 pb-4 lg:flex-row lg:items-end lg:justify-between dark:border-white/10"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-emerald-600 uppercase dark:text-emerald-300"
                    >
                        Production safe
                    </p>
                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                    >
                        System Logs
                    </h1>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        View and export application logs without enabling debug
                        mode.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="can.download && selectedFile"
                        :href="downloadLog.url({ file: selectedFile })"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-emerald-500/10"
                    >
                        <Download class="size-4" />
                        Download File
                    </a>
                    <a
                        v-if="can.download && selectedFile"
                        :href="exportMethod.url(query({ format: 'csv' }))"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <FileDown class="size-4" />
                        Export CSV
                    </a>
                    <button
                        v-if="can.clear && selectedFile"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300"
                        @click="clearCandidate = selectedFile"
                    >
                        <Trash2 class="size-4" />
                        Clear Logs
                    </button>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ card.label }}
                        </p>
                        <component
                            :is="card.icon"
                            class="size-4 text-emerald-600 dark:text-emerald-300"
                        />
                    </div>
                    <p
                        class="mt-3 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ card.value }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <p
                            class="text-xs font-semibold text-slate-500 uppercase dark:text-slate-400"
                        >
                            Latest Error
                        </p>
                        <Clock
                            class="size-4 text-emerald-600 dark:text-emerald-300"
                        />
                    </div>
                    <p
                        class="mt-3 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ summary.latest_error || 'No recent error' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-white/10"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                Log Files
                            </h2>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ files.total }} available
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            @click="visit()"
                        >
                            <RotateCcw class="size-4" />
                        </button>
                    </div>

                    <div class="space-y-3 p-4">
                        <label
                            class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            <Search class="size-4" />
                            <input
                                v-model="fileSearch"
                                type="search"
                                class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
                                placeholder="Search files"
                                @input="debouncedVisit"
                            />
                        </label>
                        <select
                            v-model="fileSort"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                            @change="visit()"
                        >
                            <option value="modified_desc">
                                Newest modified
                            </option>
                            <option value="modified_asc">
                                Oldest modified
                            </option>
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="size_desc">Largest size</option>
                            <option value="size_asc">Smallest size</option>
                        </select>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <Link
                            v-for="file in files.data"
                            :key="file.name"
                            :href="
                                logsIndex.url(
                                    query({ file: file.name, page: 1 }),
                                )
                            "
                            class="block p-4 transition hover:bg-emerald-50/70 dark:hover:bg-emerald-500/10"
                            :class="
                                file.name === selectedFile
                                    ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                    : ''
                            "
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ file.name }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ file.size_display }} ·
                                        {{ file.modified_at || 'No timestamp' }}
                                    </p>
                                </div>
                                <FileText
                                    class="mt-0.5 size-4 shrink-0 text-emerald-600"
                                />
                            </div>
                        </Link>

                        <div
                            v-if="files.data.length === 0"
                            class="p-8 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            No log files found.
                        </div>
                    </div>

                    <div
                        v-if="files.links.length > 1"
                        class="flex flex-wrap gap-1 border-t border-slate-200 p-3 dark:border-white/10"
                    >
                        <Link
                            v-for="link in files.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="rounded-md px-2.5 py-1 text-xs font-semibold"
                            :class="
                                link.active
                                    ? 'bg-emerald-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5'
                            "
                            v-html="link.label"
                        />
                    </div>
                </section>

                <section
                    class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-white/10"
                    >
                        <div
                            class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Log Entries
                                </h2>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ entries.from || 0 }}-{{
                                        entries.to || 0
                                    }}
                                    of {{ entries.total }}
                                    entries
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-if="can.download && selectedFile"
                                    :href="
                                        exportMethod.url(
                                            query({ format: 'txt' }),
                                        )
                                    "
                                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:text-slate-200 dark:hover:bg-emerald-500/10"
                                >
                                    <FileDown class="size-4" />
                                    Export TXT
                                </a>
                            </div>
                        </div>

                        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-6">
                            <label
                                class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm text-slate-600 xl:col-span-2 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                            >
                                <Search class="size-4" />
                                <input
                                    v-model="search"
                                    type="search"
                                    class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
                                    placeholder="Message, exception, URL"
                                    @input="debouncedVisit"
                                />
                            </label>
                            <select
                                v-model="level"
                                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            >
                                <option
                                    v-for="item in levels"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ item === 'all' ? 'All Levels' : item }}
                                </option>
                            </select>
                            <select
                                v-model="environment"
                                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            >
                                <option
                                    v-for="item in environments"
                                    :key="item"
                                    :value="item"
                                >
                                    {{
                                        item === 'all'
                                            ? 'All Environments'
                                            : item
                                    }}
                                </option>
                            </select>
                            <select
                                v-model="range"
                                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            >
                                <option value="today">Today</option>
                                <option value="7d">Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                                <option value="custom">Custom</option>
                                <option value="all">All Dates</option>
                            </select>
                            <select
                                v-model="perPage"
                                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            >
                                <option :value="10">10 rows</option>
                                <option :value="25">25 rows</option>
                                <option :value="50">50 rows</option>
                                <option :value="100">100 rows</option>
                            </select>
                        </div>

                        <div
                            v-if="range === 'custom'"
                            class="grid gap-2 sm:grid-cols-3"
                        >
                            <input
                                v-model="from"
                                type="date"
                                class="h-9 rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            />
                            <input
                                v-model="to"
                                type="date"
                                class="h-9 rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200"
                                @change="visit()"
                            />
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="resetFilters"
                            >
                                <Filter class="size-4" />
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 dark:divide-white/10"
                        >
                            <thead class="bg-slate-50 dark:bg-white/5">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Date & Time
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Environment
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Level
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Message
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <tr
                                    v-for="entry in entries.data"
                                    :key="entry.id"
                                    class="transition hover:bg-emerald-50/60 dark:hover:bg-emerald-500/10"
                                >
                                    <td
                                        class="px-4 py-3 text-xs font-medium whitespace-nowrap text-slate-700 dark:text-slate-200"
                                    >
                                        {{ entry.timestamp }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        {{ entry.environment }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase"
                                            :class="
                                                levelBadgeClass(entry.level)
                                            "
                                        >
                                            {{ entry.level }}
                                        </span>
                                    </td>
                                    <td class="max-w-[520px] px-4 py-3">
                                        <p
                                            class="line-clamp-2 text-xs leading-5 text-slate-700 dark:text-slate-300"
                                        >
                                            {{ entry.message }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 dark:bg-white dark:text-slate-950"
                                            @click="selectedEntry = entry"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="entries.data.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-12 text-center"
                                    >
                                        <p
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                                        >
                                            No log entries found.
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Try widening the date range or
                                            clearing filters.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-col gap-2 border-t border-slate-200 p-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:text-slate-400"
                    >
                        <span>
                            Showing {{ entries.from || 0 }} to
                            {{ entries.to || 0 }} of
                            {{ entries.total }}
                        </span>
                        <div class="flex gap-2">
                            <Link
                                :href="
                                    logsIndex.url(
                                        query({
                                            page: Math.max(
                                                entries.current_page - 1,
                                                1,
                                            ),
                                        }),
                                    )
                                "
                                class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                :class="
                                    entries.current_page <= 1
                                        ? 'pointer-events-none opacity-40'
                                        : ''
                                "
                            >
                                Previous
                            </Link>
                            <Link
                                :href="
                                    logsIndex.url(
                                        query({
                                            page: entries.current_page + 1,
                                        }),
                                    )
                                "
                                class="rounded-lg border border-slate-200 px-3 py-1.5 font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                :class="
                                    !entries.has_more
                                        ? 'pointer-events-none opacity-40'
                                        : ''
                                "
                            >
                                Next
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="selectedEntry"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-slate-950"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-white/10"
                >
                    <div class="min-w-0">
                        <span
                            class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase"
                            :class="levelBadgeClass(selectedEntry.level)"
                        >
                            {{ selectedEntry.level }}
                        </span>
                        <h3
                            class="mt-3 text-lg font-bold text-slate-950 dark:text-white"
                        >
                            Error Details
                        </h3>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ selectedEntry.timestamp }} ·
                            {{ selectedEntry.environment }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                        @click="selectedEntry = null"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div
                    class="max-h-[calc(90vh-88px)] space-y-5 overflow-y-auto p-5"
                >
                    <div
                        class="rounded-lg border border-slate-200 p-4 dark:border-white/10"
                    >
                        <p
                            class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                        >
                            Message
                        </p>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-800 dark:text-slate-200"
                        >
                            {{ selectedEntry.message }}
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Exception
                            </p>
                            <p
                                class="mt-1 truncate text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{
                                    selectedEntry.exception_type ||
                                    'Not detected'
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[11px] font-bold text-slate-500 uppercase"
                            >
                                File
                            </p>
                            <p
                                class="mt-1 truncate text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ selectedEntry.file || 'Not detected' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Line
                            </p>
                            <p
                                class="mt-1 text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ selectedEntry.line || 'N/A' }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[11px] font-bold text-slate-500 uppercase"
                            >
                                IP Address
                            </p>
                            <p
                                class="mt-1 text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ selectedEntry.ip_address || 'Not captured' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 p-4 dark:border-white/10"
                    >
                        <p
                            class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                        >
                            Request Information
                        </p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <p
                                class="truncate text-xs text-slate-600 dark:text-slate-300"
                            >
                                <span class="font-bold">URL:</span>
                                {{ selectedEntry.url || 'Not captured' }}
                            </p>
                            <p
                                class="text-xs text-slate-600 dark:text-slate-300"
                            >
                                <span class="font-bold">Method:</span>
                                {{ selectedEntry.method || 'Not captured' }}
                            </p>
                            <p
                                class="truncate text-xs text-slate-600 md:col-span-2 dark:text-slate-300"
                            >
                                <span class="font-bold">User Agent:</span>
                                {{ selectedEntry.user_agent || 'Not captured' }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <div
                            class="mb-2 flex items-center justify-between gap-3"
                        >
                            <p
                                class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                Stack Trace
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200"
                                    @click="copyText(selectedEntry.message)"
                                >
                                    <Copy class="size-3.5" />
                                    Copy Message
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200"
                                    @click="copyText(selectedEntry.raw)"
                                >
                                    <Copy class="size-3.5" />
                                    Copy Trace
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-2 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white"
                                    @click="downloadDetails"
                                >
                                    <Download class="size-3.5" />
                                    Download
                                </button>
                            </div>
                        </div>
                        <pre
                            class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-4 text-xs leading-5 text-slate-100"
                            >{{ selectedEntry.raw }}</pre
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="clearCandidate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl dark:bg-slate-950"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"
                    >
                        <ShieldAlert class="size-5" />
                    </div>
                    <div>
                        <h3
                            class="text-base font-bold text-slate-950 dark:text-white"
                        >
                            Clear this log file?
                        </h3>
                        <p
                            class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Are you sure you want to clear this log file? This
                            action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200"
                        @click="clearCandidate = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        @click="confirmClear"
                    >
                        Clear Logs
                    </button>
                </div>
            </div>
        </div>

</template>
