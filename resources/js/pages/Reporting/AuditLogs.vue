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
type UserOption = { id: number; name: string; email: string | null };

const props = defineProps<{
    logs: Page<AuditRow>;
    filters: Record<string, string | undefined>;
    filterOptions: {
        users: UserOption[];
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
const userOptions = ref<UserOption[]>(props.filterOptions.users);
const userSearch = ref('');
const userOptionsLoading = ref(false);
const userPickerOpen = ref(false);

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
const selectedUser = computed(
    () =>
        userOptions.value.find((user) => String(user.id) === userId.value) ??
        null,
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
    userSearch.value = '';
    userOptions.value = props.filterOptions.users;
    userPickerOpen.value = false;
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

const selectUser = (id = '') => {
    userId.value = id;
    userPickerOpen.value = false;
};

const searchUsers = async () => {
    userOptionsLoading.value = true;

    try {
        const params = new URLSearchParams();

        if (userSearch.value) {
            params.set('search', userSearch.value);
        }

        if (userId.value) {
            params.set('selected_user_id', userId.value);
        }

        const response = await fetch(
            `/admin/reporting/audit-logs/users?${params.toString()}`,
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (response.ok) {
            const payload = (await response.json()) as { users: UserOption[] };
            userOptions.value = payload.users;
        }
    } finally {
        userOptionsLoading.value = false;
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
                class="grid gap-2 xl:grid-cols-[1fr_260px_160px_140px_130px_130px_120px_auto_auto]"
            >
                <input
                    v-model="search"
                    class="report-input"
                    placeholder="Search activity"
                    @keydown.enter="applyFilters"
                />
                <div class="relative">
                    <button
                        class="report-user-trigger"
                        type="button"
                        @click="userPickerOpen = !userPickerOpen"
                    >
                        <span class="truncate">
                            {{ selectedUser?.name || 'All users' }}
                        </span>
                        <Search class="h-3.5 w-3.5 shrink-0" />
                    </button>
                    <div v-if="userPickerOpen" class="user-picker">
                        <div class="flex gap-1.5">
                            <input
                                v-model="userSearch"
                                class="report-input min-w-0"
                                placeholder="Search users"
                                @keydown.enter="searchUsers"
                            />
                            <button
                                class="report-icon-btn"
                                type="button"
                                :disabled="userOptionsLoading"
                                @click="searchUsers"
                            >
                                <Search class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div class="mt-2 max-h-56 overflow-y-auto">
                            <button
                                class="user-option"
                                type="button"
                                @click="selectUser()"
                            >
                                <span>All users</span>
                            </button>
                            <button
                                v-for="user in userOptions"
                                :key="user.id"
                                class="user-option"
                                type="button"
                                @click="selectUser(String(user.id))"
                            >
                                <span class="truncate">{{ user.name }}</span>
                                <small class="truncate">{{
                                    user.email || 'No email'
                                }}</small>
                            </button>
                        </div>
                    </div>
                </div>
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
                        :class="{ 'page-btn-active': link.active }"
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
                        <pre class="audit-payload">{{
                            pretty(selectedLog.new_values)
                        }}</pre>
                    </div>
                    <div>
                        <strong>Old values</strong>
                        <pre class="audit-payload">{{
                            pretty(selectedLog.old_values)
                        }}</pre>
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
    background-color: #ffffff;
    color: #64748b;
}
.stat-card strong {
    @apply mt-2 block text-2xl text-slate-900 dark:text-white;
    color: #0f172a;
}
.stat-icon {
    @apply mb-3 h-5 w-5;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
    color-scheme: light;
    background-color: #ffffff;
    color: #0f172a;
}
.report-input option {
    background-color: #ffffff;
    color: #0f172a;
}
.report-btn {
    @apply inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
    background-color: #ffffff;
    color: #475569;
}
.report-icon-btn {
    @apply inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
    background-color: #ffffff;
    color: #475569;
}
.report-user-trigger {
    @apply flex h-9 w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 text-left text-xs text-slate-900 hover:bg-slate-50 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
    color-scheme: light;
    background-color: #ffffff;
    color: #0f172a;
}
.user-picker {
    @apply absolute z-30 mt-2 w-80 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-white/10 dark:bg-slate-950;
    background-color: #ffffff;
    color: #0f172a;
}
.user-option {
    @apply flex w-full flex-col rounded-lg px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-white/[0.04];
    background-color: transparent;
    color: #334155;
}
.user-option small {
    @apply mt-0.5 text-[11px] text-slate-400;
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
    @apply min-w-7 rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600 disabled:opacity-40 dark:border-white/10;
    color-scheme: light;
    background-color: #ffffff;
    color: #475569;
}
.page-btn-active {
    @apply border-sky-600 bg-sky-600 text-white;
    background-color: #0284c7;
    color: #ffffff;
}
.audit-payload {
    @apply mt-2 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-[11px] text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
    color-scheme: light;
}
.dark .report-input {
    color-scheme: dark;
    background-color: #0f172a;
    color: #f1f5f9;
}
.dark .report-input option {
    background-color: #0f172a;
    color: #f1f5f9;
}
.dark .report-btn {
    background-color: #0f172a;
    color: #e2e8f0;
}
.dark .report-icon-btn {
    background-color: #0f172a;
    color: #e2e8f0;
}
.dark .report-user-trigger {
    color-scheme: dark;
    background-color: #0f172a;
    color: #f1f5f9;
}
.dark .user-picker {
    background-color: #020617;
    color: #f1f5f9;
}
.dark .user-option {
    color: #e2e8f0;
}
.dark .stat-card {
    background-color: #020617;
    color: #94a3b8;
}
.dark .stat-card strong {
    color: #ffffff;
}
.dark .page-btn {
    color-scheme: dark;
    background-color: #0f172a;
    color: #cbd5e1;
}
.dark .page-btn-active {
    background-color: #0284c7;
    color: #ffffff;
}
.dark .audit-payload {
    color-scheme: dark;
}
</style>
