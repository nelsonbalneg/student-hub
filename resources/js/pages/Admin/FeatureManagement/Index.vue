<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Search, Shield, ToggleLeft, ToggleRight, Wrench, RefreshCw,
    History, AlertTriangle, CheckCircle2, XCircle,
    Clock, X, User, Loader2, PlugZap
} from 'lucide-vue-next';

interface Feature {
    id: number;
    module_name: string;
    menu_name: string;
    feature_name: string;
    feature_key: string;
    route_name: string | null;
    description: string | null;
    status: 'active' | 'maintenance' | 'disabled';
    maintenance_message: string | null;
    updated_by?: {
        id: number;
        name: string;
    } | null;
    updated_at: string;
}

interface FeaturesPagination {
    data: Feature[];
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface HistoryLog {
    id: number;
    old_status: string | null;
    new_status: string;
    reason: string | null;
    changed_by: string;
    created_at: string;
}

const props = defineProps<{
    features: FeaturesPagination;
    modules: string[];
    filters: {
        module?: string;
        status?: string;
        search?: string;
    };
    statusOptions: string[];
}>();

const page = usePage();

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const can = (permission: string): boolean => {
    return (
        roles.value.includes('Super Admin') ||
        permissions.value.includes(permission)
    );
};

// ── Filters ───────────────────────────────────────────────────────────────────
const search = ref(props.filters?.search || '');
const moduleFilter = ref(props.filters?.module || '');
const statusFilter = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get('/settings/feature-management', {
        search:  search.value,
        module:  moduleFilter.value,
        status:  statusFilter.value,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    moduleFilter.value = '';
    statusFilter.value = '';
    applyFilters();
};

const hasActiveFilters = computed(() => search.value || moduleFilter.value || statusFilter.value);

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusConfig: Record<string, { label: string; icon: any }> = {
    active:      { label: 'Active',      icon: CheckCircle2 },
    maintenance: { label: 'Maintenance', icon: Wrench       },
    disabled:    { label: 'Disabled',    icon: XCircle      },
};

const statusBadgeClass = (status: string) => ({
    active:      'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-800/50',
    maintenance: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800/50',
    disabled:    'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800/50',
}[status] || 'bg-slate-100 text-slate-500 ring-slate-200');

// ── Status Update ──────────────────────────────────────────────────────────────
const showStatusModal = ref(false);
const selectedFeature = ref<Feature | null>(null);
const pendingStatus = ref('');

const statusForm = useForm({
    status:              '',
    maintenance_message: '',
    reason:              '',
});

const openStatusModal = (feature: Feature, status: string) => {
    selectedFeature.value = feature;
    pendingStatus.value = status;
    statusForm.status = status;
    statusForm.maintenance_message = feature.maintenance_message || '';
    statusForm.reason = '';
    showStatusModal.value = true;
};

const closeStatusModal = () => {
    showStatusModal.value = false;
    selectedFeature.value = null;
    statusForm.reset();
};

const submitStatus = () => {
    if (!selectedFeature.value) return;
    statusForm.patch(`/settings/feature-management/${selectedFeature.value.id}/status`, {
        onSuccess: () => closeStatusModal(),
    });
};

// ── History Modal ─────────────────────────────────────────────────────────────
const showHistoryModal = ref(false);
const historyFeature = ref<Feature | null>(null);
const historyLogs = ref<HistoryLog[]>([]);
const historyLoading = ref(false);

const openHistory = async (feature: Feature) => {
    historyFeature.value = feature;
    historyLogs.value = [];
    showHistoryModal.value = true;
    historyLoading.value = true;

    try {
        const response = await fetch(`/settings/feature-management/${feature.id}/history`);
        if (!response.ok) throw new Error('Failed to fetch history');
        const data = await response.json();
        historyLogs.value = data.logs;
    } catch {
        historyLogs.value = [];
    } finally {
        historyLoading.value = false;
    }
};

const closeHistory = () => {
    showHistoryModal.value = false;
    historyFeature.value = null;
    historyLogs.value = [];
};

// ── Sync ──────────────────────────────────────────────────────────────────────
const showSyncConfirm = ref(false);
const syncForm = useForm({});

const doSync = () => {
    syncForm.post('/settings/feature-management/sync', {
        onSuccess: () => { showSyncConfirm.value = false; },
    });
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (iso: string) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/dashboard',
            },
            {
                title: 'Feature Management',
                href: '/settings/feature-management',
            },
        ],
    },
});
</script>

<template>
    <Head title="Feature Management" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold leading-tight text-slate-800 dark:text-white">Feature Management</h1>
                <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                    Control access to modules, menus, and system features.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden items-center gap-2 text-[10px] font-medium text-slate-400 tracking-wide sm:flex">
                    <PlugZap class="h-3.5 w-3.5" />
                    {{ features.total }} features
                </div>
                <button
                    v-if="can('features.sync')"
                    @click="showSyncConfirm = true"
                    class="inline-flex h-8 items-center gap-2 rounded-xl bg-slate-900 dark:bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-700 dark:hover:bg-emerald-700 active:scale-95"
                >
                    <RefreshCw class="h-3.5 w-3.5" />
                    Sync Routes
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <!-- ── Filter Bar ─────────────────────────────────────────────── -->
            <div class="rounded-xl bg-white dark:bg-slate-900 shadow-sm ring-1 ring-slate-200/50 dark:ring-slate-800 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Search -->
                    <div class="relative flex-1 min-w-48">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <Search class="h-3.5 w-3.5" />
                        </div>
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Search features, routes..."
                            class="block w-full h-9 rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 pl-9 pr-3 text-xs font-medium placeholder-slate-300 dark:placeholder-slate-500 focus:border-emerald-500 focus:ring-emerald-500 transition"
                        />
                    </div>

                    <!-- Module filter -->
                    <select
                        v-model="moduleFilter"
                        @change="applyFilters"
                        class="h-9 rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 py-2 pl-3 pr-8 text-xs font-medium text-slate-600 dark:text-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition"
                    >
                        <option value="">All Modules</option>
                        <option v-for="mod in modules" :key="mod" :value="mod">{{ mod }}</option>
                    </select>

                    <!-- Status filter -->
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="h-9 rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 py-2 pl-3 pr-8 text-xs font-medium text-slate-600 dark:text-slate-300 focus:border-emerald-500 focus:ring-emerald-500 transition"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="s in statusOptions" :key="s" :value="s" class="capitalize">{{ s }}</option>
                    </select>

                    <!-- Clear filters -->
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                    >
                        <X class="h-3.5 w-3.5" />
                        Clear
                    </button>
                </div>
            </div>

            <!-- ── Feature Table ──────────────────────────────────────────── -->
            <div class="rounded-xl bg-white dark:bg-slate-950 shadow-sm border border-slate-200 dark:border-white/10 overflow-hidden">
                <!-- Table header -->
                <div class="hidden md:grid grid-cols-12 gap-3 px-5 py-3 border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5">
                    <div class="col-span-2 text-[9px] font-black uppercase tracking-widest text-slate-400">Module</div>
                    <div class="col-span-2 text-[9px] font-black uppercase tracking-widest text-slate-400">Menu</div>
                    <div class="col-span-2 text-[9px] font-black uppercase tracking-widest text-slate-400">Feature</div>
                    <div class="col-span-2 text-[9px] font-black uppercase tracking-widest text-slate-400">Route Key</div>
                    <div class="col-span-1 text-[9px] font-black uppercase tracking-widest text-slate-400">Status</div>
                    <div class="col-span-1 text-[9px] font-black uppercase tracking-widest text-slate-400">Updated</div>
                    <div class="col-span-2 text-[9px] font-black uppercase tracking-widest text-slate-400 text-right">Actions</div>
                </div>

                <!-- Rows -->
                <div class="divide-y divide-slate-100 dark:divide-white/10">
                    <div
                        v-for="feature in features.data"
                        :key="feature.id"
                        class="group px-5 py-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-white/5"
                    >
                        <div class="md:grid md:grid-cols-12 md:gap-3 md:items-center space-y-2 md:space-y-0">
                            <!-- Module -->
                            <div class="md:col-span-2">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-lg">
                                    {{ feature.module_name }}
                                </span>
                            </div>

                            <!-- Menu -->
                            <div class="md:col-span-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                {{ feature.menu_name }}
                            </div>

                            <!-- Feature name -->
                            <div class="md:col-span-2">
                                <div class="text-xs font-bold text-slate-800 dark:text-white">{{ feature.feature_name }}</div>
                                <div v-if="feature.description" class="mt-0.5 text-[10px] text-slate-400 truncate max-w-[180px]">{{ feature.description }}</div>
                            </div>

                            <!-- Route key -->
                            <div class="md:col-span-2">
                                <code class="text-[10px] font-mono text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-900 px-2 py-0.5 rounded-lg break-all">
                                    {{ feature.feature_key }}
                                </code>
                            </div>

                            <!-- Status badge -->
                            <div class="md:col-span-1">
                                <span :class="statusBadgeClass(feature.status)" class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-transparent">
                                    <component :is="statusConfig[feature.status]?.icon" class="h-3 w-3" />
                                    {{ statusConfig[feature.status]?.label || feature.status }}
                                </span>
                                <div v-if="feature.status === 'maintenance' && feature.maintenance_message" class="mt-1 text-[9px] text-amber-500 dark:text-amber-400 max-w-[120px] truncate">
                                    {{ feature.maintenance_message }}
                                </div>
                            </div>

                            <!-- Last updated -->
                            <div class="md:col-span-1 text-[10px] text-slate-400 space-y-0.5">
                                <div v-if="feature.updated_by">
                                    <span class="font-semibold text-slate-500 dark:text-slate-300">{{ feature.updated_by?.name }}</span>
                                </div>
                                <div>{{ formatDate(feature.updated_at) }}</div>
                            </div>

                            <!-- Actions -->
                            <div class="md:col-span-2 flex items-center justify-start md:justify-end gap-1 flex-wrap">
                                <template v-if="can('features.edit')">
                                    <!-- Enable -->
                                    <button
                                        v-if="feature.status !== 'active'"
                                        @click="openStatusModal(feature, 'active')"
                                        title="Enable"
                                        class="h-7 px-2 inline-flex items-center gap-1 rounded-lg text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200 dark:ring-emerald-800/50 transition hover:bg-emerald-100 dark:hover:bg-emerald-900/40"
                                    >
                                        <ToggleRight class="h-3.5 w-3.5" />
                                        Enable
                                    </button>
                                    <!-- Maintenance -->
                                    <button
                                        v-if="feature.status !== 'maintenance'"
                                        @click="openStatusModal(feature, 'maintenance')"
                                        title="Set Maintenance"
                                        class="h-7 px-2 inline-flex items-center gap-1 rounded-lg text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 ring-1 ring-amber-200 dark:ring-amber-800/50 transition hover:bg-amber-100 dark:hover:bg-amber-900/40"
                                    >
                                        <Wrench class="h-3.5 w-3.5" />
                                        Maint.
                                    </button>
                                    <!-- Disable -->
                                    <button
                                        v-if="feature.status !== 'disabled'"
                                        @click="openStatusModal(feature, 'disabled')"
                                        title="Disable"
                                        class="h-7 px-2 inline-flex items-center gap-1 rounded-lg text-[10px] font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 ring-1 ring-red-200 dark:ring-red-800/50 transition hover:bg-red-100 dark:hover:bg-red-900/40"
                                    >
                                        <ToggleLeft class="h-3.5 w-3.5" />
                                        Disable
                                    </button>
                                </template>
                                <!-- History -->
                                <button
                                    @click="openHistory(feature)"
                                    title="View History"
                                    class="h-7 w-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition"
                                >
                                    <History class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="features.data.length === 0" class="py-20 text-center">
                        <PlugZap class="h-10 w-10 text-slate-200 dark:text-slate-700 mx-auto mb-4" />
                        <p class="text-sm font-medium text-slate-400">No features found.</p>
                        <p class="mt-1 text-xs text-slate-300 dark:text-slate-600">Try syncing routes or adjusting your filters.</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="features.links && features.links.length > 3" class="p-4 border-t border-slate-100 dark:border-white/10 flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-xs text-slate-400">
                        Showing {{ features.from }}–{{ features.to }} of {{ features.total }}
                    </span>
                    <div class="flex items-center gap-1">
                        <template v-for="link in features.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="link.active
                                    ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white'
                                    : 'bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-700'"
                                class="flex-1 min-w-8 text-center px-2 py-1.5 rounded-lg text-[10px] font-semibold transition"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-2 py-1.5 text-[10px] font-semibold text-slate-300 dark:text-slate-600"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Status Update Modal ──────────────────────────────────────────── -->
        <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-[2rem] bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-slate-200/50 dark:ring-slate-800">
                <!-- Header -->
                <div class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                    <div :class="{
                        'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400': pendingStatus === 'active',
                        'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400': pendingStatus === 'maintenance',
                        'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400': pendingStatus === 'disabled',
                    }" class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0">
                        <CheckCircle2 v-if="pendingStatus === 'active'" class="h-5 w-5" />
                        <Wrench v-else-if="pendingStatus === 'maintenance'" class="h-5 w-5" />
                        <XCircle v-else class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white capitalize">
                            Set to {{ pendingStatus }}
                        </h3>
                        <p class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[260px]">
                            {{ selectedFeature?.feature_name }}
                        </p>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Maintenance message (only for maintenance status) -->
                    <div v-if="pendingStatus === 'maintenance'">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">
                            Maintenance Message <span class="text-red-400">*</span>
                        </label>
                        <textarea
                            v-model="statusForm.maintenance_message"
                            rows="3"
                            placeholder="Explain the maintenance reason to users..."
                            class="block w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 p-3 text-sm font-medium text-slate-700 dark:text-slate-200 placeholder-slate-300 dark:placeholder-slate-600 focus:border-amber-500 focus:ring-amber-500 transition resize-none"
                        ></textarea>
                        <p v-if="statusForm.errors.maintenance_message" class="mt-1 text-xs text-red-500">{{ statusForm.errors.maintenance_message }}</p>
                    </div>

                    <!-- Reason (always optional) -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">
                            Reason / Notes <span class="text-slate-300">(optional)</span>
                        </label>
                        <textarea
                            v-model="statusForm.reason"
                            rows="2"
                            placeholder="Internal reason for this change..."
                            class="block w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-950/60 p-3 text-sm font-medium text-slate-700 dark:text-slate-200 placeholder-slate-300 dark:placeholder-slate-600 focus:border-slate-400 focus:ring-slate-400 transition resize-none"
                        ></textarea>
                    </div>

                    <!-- Disabled warning -->
                    <div v-if="pendingStatus === 'disabled'" class="flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-950/30 p-3 ring-1 ring-red-200 dark:ring-red-800/50">
                        <AlertTriangle class="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                        <p class="text-xs font-medium text-red-700 dark:text-red-300">
                            Users will receive a <strong>403 Forbidden</strong> error when accessing this feature.
                        </p>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="flex justify-end gap-2 px-6 pb-6">
                    <button @click="closeStatusModal" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Cancel
                    </button>
                    <button
                        @click="submitStatus"
                        :disabled="statusForm.processing"
                        :class="{
                            'bg-emerald-600 hover:bg-emerald-700': pendingStatus === 'active',
                            'bg-amber-500 hover:bg-amber-600': pendingStatus === 'maintenance',
                            'bg-red-600 hover:bg-red-700': pendingStatus === 'disabled',
                        }"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white transition active:scale-95 disabled:opacity-50"
                    >
                        <Loader2 v-if="statusForm.processing" class="h-3.5 w-3.5 animate-spin" />
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        <!-- ── History Modal ────────────────────────────────────────────────── -->
        <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg overflow-hidden rounded-[2rem] bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-slate-200/50 dark:ring-slate-800">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Status History</h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">{{ historyFeature?.feature_name }}</p>
                    </div>
                    <button @click="closeHistory" class="h-8 w-8 flex items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto p-4 space-y-2">
                    <div v-if="historyLoading" class="py-10 flex items-center justify-center text-slate-400">
                        <Loader2 class="h-5 w-5 animate-spin" />
                    </div>
                    <div v-else-if="historyLogs.length === 0" class="py-10 text-center text-sm font-medium text-slate-400">
                        No status changes recorded.
                    </div>
                    <div
                        v-for="log in historyLogs"
                        :key="log.id"
                        class="flex items-start gap-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3"
                    >
                        <div class="h-6 w-6 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                            :class="{
                                'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400': log.new_status === 'active',
                                'bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400': log.new_status === 'maintenance',
                                'bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400': log.new_status === 'disabled',
                            }"
                        >
                            <CheckCircle2 v-if="log.new_status === 'active'" class="h-3.5 w-3.5" />
                            <Wrench v-else-if="log.new_status === 'maintenance'" class="h-3.5 w-3.5" />
                            <XCircle v-else class="h-3.5 w-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 capitalize">{{ log.new_status }}</span>
                                <span v-if="log.old_status" class="text-[10px] text-slate-400">from <span class="capitalize">{{ log.old_status }}</span></span>
                            </div>
                            <div v-if="log.reason" class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">{{ log.reason }}</div>
                            <div class="mt-1 flex items-center gap-2 text-[10px] text-slate-400">
                                <User class="h-3 w-3" /> {{ log.changed_by }}
                                <Clock class="h-3 w-3 ml-1" /> {{ formatDate(log.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Sync Confirm Modal ───────────────────────────────────────────── -->
        <div v-if="showSyncConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm overflow-hidden rounded-[2rem] bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-slate-200/50 dark:ring-slate-800">
                <div class="p-6 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <RefreshCw class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Sync Routes</h3>
                            <p class="text-xs font-medium text-slate-500 mt-1">
                                This will scan all named Laravel routes and register any new ones as <span class="font-bold text-emerald-600 dark:text-emerald-400">active</span>. Existing features are not affected.
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="showSyncConfirm = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button
                            @click="doSync"
                            :disabled="syncForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition active:scale-95 disabled:opacity-50"
                        >
                            <Loader2 v-if="syncForm.processing" class="h-3.5 w-3.5 animate-spin" />
                            <RefreshCw v-else class="h-3.5 w-3.5" />
                            Sync Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
