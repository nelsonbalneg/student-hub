<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FileClock,
    RefreshCw,
    Search,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
type AuditRow = {
    id: number;
    actor_name: string | null;
    actor_email: string | null;
    module: string | null;
    action: string;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    route_name: string | null;
    url: string | null;
    method: string | null;
    created_at: string | null;
};

const props = defineProps<{
    logs: Page<AuditRow>;
    filters: Record<string, string | undefined>;
    filterOptions: {
        users: { id: number; name: string; email: string }[];
        modules: string[];
        actions: string[];
    };
    stats: { total: number; today: number; actors: number; modules: number };
    pageSizeOptions: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Reporting', href: '/admin/reporting/overview' },
            { title: 'Audit Logs', href: '/admin/reporting/audit-logs' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const userId = ref(props.filters.user_id ?? '');
const module = ref(props.filters.module ?? '');
const action = ref(props.filters.action ?? '');
const ipAddress = ref(props.filters.ip_address ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const perPage = ref(Number(props.filters.per_page ?? props.logs.meta.per_page));
const selectedLog = ref<AuditRow | null>(null);
const loading = ref(false);

const activeFilters = computed(
    () =>
        [
            search.value,
            userId.value,
            module.value,
            action.value,
            ipAddress.value,
            dateFrom.value,
            dateTo.value,
        ].filter(Boolean).length,
);

const params = () =>
    Object.fromEntries(
        Object.entries({
            search: search.value,
            user_id: userId.value,
            module: module.value,
            action: action.value,
            ip_address: ipAddress.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            per_page: perPage.value,
        }).filter(([, value]) => value !== ''),
    );

const applyFilters = () => {
    router.get('/admin/reporting/audit-logs', params(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const resetFilters = () => {
    search.value = '';
    userId.value = '';
    module.value = '';
    action.value = '';
    ipAddress.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    perPage.value = 10;
    applyFilters();
};

const navigatePage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const pretty = (value: unknown) => JSON.stringify(value ?? {}, null, 2);
</script>

<template>
    <Head title="Audit Logs" />

    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950"
    >
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">
                    Audit Logs
                </h1>
                <p class="text-xs text-slate-500">
                    System activity across users, modules, routes, and IP
                    addresses.
                </p>
            </div>
            <span class="text-xs font-bold text-slate-400"
                >{{ activeFilters }} filters active</span
            >
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="stat-card">
                <FileClock class="stat-icon text-sky-600" /><span
                    >Total events</span
                ><strong>{{ stats.total }}</strong>
            </div>
            <div class="stat-card">
                <ShieldCheck class="stat-icon text-emerald-600" /><span
                    >Today</span
                ><strong>{{ stats.today }}</strong>
            </div>
            <div class="stat-card">
                <UserRound class="stat-icon text-violet-600" /><span
                    >Actors</span
                ><strong>{{ stats.actors }}</strong>
            </div>
            <div class="stat-card">
                <Search class="stat-icon text-amber-600" /><span>Modules</span
                ><strong>{{ stats.modules }}</strong>
            </div>
        </div>

        <div
            class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="grid gap-2 xl:grid-cols-[1fr_180px_160px_140px_130px_130px_120px_auto_auto]"
            >
                <input
                    v-model="search"
                    class="report-input"
                    placeholder="Search activity"
                    @keydown.enter="applyFilters"
                />
                <select v-model="userId" class="report-input">
                    <option value="">All users</option>
                    <option
                        v-for="user in filterOptions.users"
                        :key="user.id"
                        :value="user.id"
                    >
                        {{ user.name }}
                    </option>
                </select>
                <select v-model="module" class="report-input">
                    <option value="">All modules</option>
                    <option
                        v-for="item in filterOptions.modules"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
                <select v-model="action" class="report-input">
                    <option value="">All actions</option>
                    <option
                        v-for="item in filterOptions.actions"
                        :key="item"
                        :value="item"
                    >
                        {{ item }}
                    </option>
                </select>
                <input
                    v-model="ipAddress"
                    class="report-input"
                    placeholder="IP address"
                    @keydown.enter="applyFilters"
                />
                <input v-model="dateFrom" type="date" class="report-input" />
                <input v-model="dateTo" type="date" class="report-input" />
                <button class="report-btn-primary" @click="applyFilters">
                    <Search class="h-3.5 w-3.5" />Search
                </button>
                <button class="report-btn" @click="resetFilters">
                    <RefreshCw class="h-3.5 w-3.5" />Reset
                </button>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                v-if="loading"
                class="border-b border-slate-100 px-4 py-2 text-xs font-semibold text-sky-600 dark:border-white/10"
            >
                Loading audit logs...
            </div>
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                >
                    <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                        <tr>
                            <th class="report-th">Time</th>
                            <th class="report-th">Actor</th>
                            <th class="report-th">Module</th>
                            <th class="report-th">Action</th>
                            <th class="report-th">Description</th>
                            <th class="report-th">IP</th>
                            <th class="report-th text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                        >
                            <td class="report-td">{{ log.created_at }}</td>
                            <td
                                class="report-td font-semibold text-slate-900 dark:text-white"
                            >
                                {{ log.actor_name || 'System' }}
                                <div
                                    class="text-[11px] font-normal text-slate-500"
                                >
                                    {{ log.actor_email }}
                                </div>
                            </td>
                            <td class="report-td">{{ log.module }}</td>
                            <td class="report-td">
                                <span
                                    class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700 uppercase dark:bg-sky-500/10 dark:text-sky-300"
                                    >{{ log.action }}</span
                                >
                            </td>
                            <td class="report-td max-w-md truncate">
                                {{ log.description }}
                            </td>
                            <td class="report-td">{{ log.ip_address }}</td>
                            <td class="report-td text-right">
                                <button
                                    class="report-btn h-7"
                                    @click="selectedLog = log"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td
                                colspan="7"
                                class="py-12 text-center text-sm text-slate-400"
                            >
                                No audit logs found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-white/10"
            >
                <span
                    >Showing {{ logs.meta.from ?? 0 }}-{{
                        logs.meta.to ?? 0
                    }}
                    of {{ logs.meta.total }}</span
                >
                <select
                    v-model.number="perPage"
                    class="report-input w-24"
                    @change="applyFilters"
                >
                    <option
                        v-for="size in pageSizeOptions"
                        :key="size"
                        :value="size"
                    >
                        {{ size }}
                    </option>
                </select>
                <div class="flex gap-1">
                    <button
                        v-for="link in logs.links"
                        :key="link.label"
                        class="page-btn"
                        :disabled="!link.url"
                        :class="{ 'bg-sky-600 text-white': link.active }"
                        @click="navigatePage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="selectedLog"
            class="fixed inset-0 z-50 flex justify-end bg-black/40"
            @click.self="selectedLog = null"
        >
            <aside
                class="h-full w-full max-w-2xl overflow-y-auto bg-white p-5 shadow-xl dark:bg-slate-950"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        Audit Log Details
                    </h2>
                    <button class="report-btn h-8" @click="selectedLog = null">
                        <X class="h-3.5 w-3.5" />Close
                    </button>
                </div>
                <div
                    class="grid gap-3 text-xs text-slate-600 dark:text-slate-300"
                >
                    <p><strong>Route:</strong> {{ selectedLog.route_name }}</p>
                    <p><strong>URL:</strong> {{ selectedLog.url }}</p>
                    <p><strong>Method:</strong> {{ selectedLog.method }}</p>
                    <p>
                        <strong>User agent:</strong>
                        {{ selectedLog.user_agent }}
                    </p>
                    <div>
                        <strong>Payload</strong>
                        <pre
                            class="mt-2 overflow-auto rounded-lg bg-slate-950 p-3 text-[11px] text-slate-100"
                            >{{ pretty(selectedLog.new_values) }}</pre
                        >
                    </div>
                    <div>
                        <strong>Old values</strong>
                        <pre
                            class="mt-2 overflow-auto rounded-lg bg-slate-950 p-3 text-[11px] text-slate-100"
                            >{{ pretty(selectedLog.old_values) }}</pre
                        >
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.stat-card {
    @apply rounded-xl border border-slate-200 bg-white p-4 text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-950;
}
.stat-card strong {
    @apply mt-2 block text-2xl text-slate-900 dark:text-white;
}
.stat-icon {
    @apply mb-3 h-5 w-5;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
}
.report-btn {
    @apply inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
}
.report-btn-primary {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-3 text-xs font-bold text-white hover:bg-sky-700;
}
.report-th {
    @apply px-3 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}
.report-td {
    @apply px-3 py-2 text-xs text-slate-600 dark:text-slate-300;
}
.page-btn {
    @apply min-w-7 rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold disabled:opacity-40 dark:border-white/10;
}
</style>
