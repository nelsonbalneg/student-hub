<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    Clock,
    MonitorSmartphone,
    RefreshCw,
    Search,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

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
type SessionRow = {
    id: number;
    name: string | null;
    student_no: string | null;
    employee_no: string | null;
    email: string | null;
    roles: string[];
    current_module: string | null;
    current_url: string | null;
    browser: string | null;
    ip_address: string | null;
    device_type: string | null;
    last_activity_at: string | null;
    session_duration: string;
    status: 'online' | 'idle';
};

const props = defineProps<{
    sessions: Page<SessionRow>;
    filters: Record<string, string | undefined>;
    filterOptions: { modules: string[] };
    stats: {
        online: number;
        idle: number;
        active_modules: number;
        active_sessions: number;
    };
    pageSizeOptions: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Reporting', href: '/admin/reporting/overview' },
            { title: 'Overview', href: '/admin/reporting/overview' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const module = ref(props.filters.module ?? '');
const status = ref(props.filters.status ?? '');
const perPage = ref(
    Number(props.filters.per_page ?? props.sessions.meta.per_page),
);
const loading = ref(false);

const params = () =>
    Object.fromEntries(
        Object.entries({
            search: search.value,
            module: module.value,
            status: status.value,
            per_page: perPage.value,
        }).filter(([, value]) => value !== ''),
    );

const applyFilters = () => {
    router.get('/admin/reporting/overview', params(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const resetFilters = () => {
    search.value = '';
    module.value = '';
    status.value = '';
    perPage.value = 10;
    applyFilters();
};

const navigatePage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Reporting Overview" />

    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">
                    Reporting Overview
                </h1>
                <p class="text-xs text-slate-500">
                    Live activity by user, module, device, and session.
                </p>
            </div>
            <span
                class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                >5 minute online window</span
            >
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="stat-card">
                <Users class="stat-icon text-emerald-600" /><span>Online</span
                ><strong>{{ stats.online }}</strong>
            </div>
            <div class="stat-card">
                <Clock class="stat-icon text-amber-600" /><span>Idle</span
                ><strong>{{ stats.idle }}</strong>
            </div>
            <div class="stat-card">
                <Activity class="stat-icon text-sky-600" /><span
                    >Active sessions</span
                ><strong>{{ stats.active_sessions }}</strong>
            </div>
            <div class="stat-card">
                <MonitorSmartphone class="stat-icon text-violet-600" /><span
                    >Modules</span
                ><strong>{{ stats.active_modules }}</strong>
            </div>
        </div>

        <div
            class="report-card rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="grid gap-2 lg:grid-cols-[1fr_180px_160px_100px_auto_auto]"
            >
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="search"
                        class="report-input pl-8"
                        placeholder="Search name, email, student or employee ID"
                        @keydown.enter="applyFilters"
                    />
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
                <select v-model="status" class="report-input">
                    <option value="">All status</option>
                    <option value="online">Online</option>
                    <option value="idle">Idle</option>
                </select>
                <select
                    v-model.number="perPage"
                    class="report-input"
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
                <button class="report-btn-primary" @click="applyFilters">
                    <Search class="h-3.5 w-3.5" />Search
                </button>
                <button class="report-btn" @click="resetFilters">
                    <RefreshCw class="h-3.5 w-3.5" />Reset
                </button>
            </div>
        </div>

        <div
            class="report-card overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                v-if="loading"
                class="border-b border-slate-100 px-4 py-2 text-xs font-semibold text-sky-600 dark:border-white/10"
            >
                Loading activity...
            </div>
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                >
                    <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                        <tr>
                            <th class="report-th">User</th>
                            <th class="report-th">Role</th>
                            <th class="report-th">Module</th>
                            <th class="report-th">URL</th>
                            <th class="report-th">Browser</th>
                            <th class="report-th">IP</th>
                            <th class="report-th">Device</th>
                            <th class="report-th">Last Activity</th>
                            <th class="report-th">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr
                            v-for="session in sessions.data"
                            :key="session.id"
                            class="hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                        >
                            <td
                                class="report-td font-semibold text-slate-900 dark:text-white"
                            >
                                {{ session.name }}
                                <div
                                    class="text-[11px] font-normal text-slate-500"
                                >
                                    {{
                                        session.student_no ||
                                        session.employee_no ||
                                        'No ID'
                                    }}
                                    · {{ session.email }}
                                </div>
                            </td>
                            <td class="report-td">
                                {{ session.roles.join(', ') || 'No role' }}
                            </td>
                            <td class="report-td">
                                {{ session.current_module }}
                            </td>
                            <td class="report-td max-w-xs truncate">
                                {{ session.current_url }}
                            </td>
                            <td class="report-td">{{ session.browser }}</td>
                            <td class="report-td">{{ session.ip_address }}</td>
                            <td class="report-td capitalize">
                                {{ session.device_type }}
                            </td>
                            <td class="report-td">
                                {{ session.last_activity_at }}
                                <div class="text-[11px] text-slate-400">
                                    {{ session.session_duration }}
                                </div>
                            </td>
                            <td class="report-td">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                        session.status === 'online'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    ]"
                                    >{{ session.status }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="sessions.data.length === 0">
                            <td
                                colspan="9"
                                class="py-12 text-center text-sm text-slate-400"
                            >
                                No active sessions found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-white/10"
            >
                <span
                    >Showing {{ sessions.meta.from ?? 0 }}-{{
                        sessions.meta.to ?? 0
                    }}
                    of {{ sessions.meta.total }}</span
                >
                <div class="flex gap-1">
                    <button
                        v-for="link in sessions.links"
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
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.stat-card {
    @apply rounded-xl border border-slate-200 bg-white p-4 text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-950;
    background-color: #ffffff !important;
    color: #64748b !important;
}
.stat-card strong {
    @apply mt-2 block text-2xl text-slate-900 dark:text-white;
    color: #0f172a !important;
}
.stat-icon {
    @apply mb-3 h-5 w-5;
}
.report-card {
    background-color: #ffffff !important;
    color: #334155 !important;
    border-color: #e2e8f0 !important;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
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
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-3 text-xs font-bold text-white hover:bg-sky-700;
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
.report-td:is(.dark *) {
    color: #cbd5e1 !important;
}
</style>
