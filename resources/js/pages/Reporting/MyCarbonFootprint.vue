<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import { Leaf, Lightbulb, RefreshCw, Search, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

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
type CarbonLog = {
    id: number;
    module: string | null;
    url: string | null;
    estimated_data_kb: number;
    estimated_energy_kwh: number;
    estimated_co2e_grams: number;
    device_type: string | null;
    created_at: string | null;
};
type ChartPoint = { label: string; co2e: number; views: number };

const props = defineProps<{
    logs: Page<CarbonLog>;
    filters: Record<string, string | undefined>;
    stats: {
        total_co2e_grams: number;
        page_views: number;
        sessions: number;
        estimated_energy_kwh: number;
    };
    chart: ChartPoint[];
    modules: { module: string; co2e: number; views: number }[];
    filterOptions: { modules: string[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Carbon Footprint', href: '/my-carbon-footprint' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const module = ref(props.filters.module ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const loading = ref(false);

const chartOptions = computed<ApexOptions>(() => ({
    chart: { type: 'line', toolbar: { show: false } },
    colors: ['#059669'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: { categories: props.chart.map((point) => point.label) },
}));
const chartSeries = computed(() => [
    { name: 'CO2e grams', data: props.chart.map((point) => point.co2e) },
]);

const params = () =>
    Object.fromEntries(
        Object.entries({
            search: search.value,
            module: module.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        }).filter(([, value]) => value !== ''),
    );
const applyFilters = () =>
    router.get('/my-carbon-footprint', params(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
const resetFilters = () => {
    search.value = '';
    module.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};
const navigatePage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};
</script>

<template>
    <Head title="My Carbon Footprint" />
    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950"
    >
        <div>
            <h1 class="text-base font-bold text-slate-900 dark:text-white">
                My Carbon Footprint
            </h1>
            <p class="text-xs text-slate-500">
                Your personal estimated digital footprint inside Student Hub.
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="stat-card">
                <Leaf class="stat-icon text-emerald-600" /><span>My CO2e</span
                ><strong>{{ stats.total_co2e_grams }}g</strong>
            </div>
            <div class="stat-card">
                <Search class="stat-icon text-sky-600" /><span>Page views</span
                ><strong>{{ stats.page_views }}</strong>
            </div>
            <div class="stat-card">
                <Zap class="stat-icon text-yellow-600" /><span>Energy</span
                ><strong>{{ stats.estimated_energy_kwh }}</strong>
            </div>
            <div class="stat-card">
                <Lightbulb class="stat-icon text-amber-600" /><span
                    >Sessions</span
                ><strong>{{ stats.sessions }}</strong>
            </div>
        </div>

        <div
            class="rounded-xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="grid gap-2 lg:grid-cols-[1fr_180px_140px_140px_auto_auto]"
            >
                <input
                    v-model="search"
                    class="report-input"
                    placeholder="Search my activity"
                    @keydown.enter="applyFilters"
                />
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

        <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
            <section
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <h2 class="mb-3 text-xs font-bold text-slate-500 uppercase">
                    My trend
                </h2>
                <VueApexCharts
                    height="260"
                    :options="chartOptions"
                    :series="chartSeries"
                />
            </section>
            <section
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
            >
                <h2 class="mb-3 text-xs font-bold text-slate-500 uppercase">
                    Most used modules
                </h2>
                <div class="grid gap-2">
                    <div
                        v-for="item in modules"
                        :key="item.module"
                        class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-white/[0.04]"
                    >
                        <span
                            class="font-semibold text-slate-700 dark:text-slate-200"
                            >{{ item.module }}</span
                        ><span class="text-slate-500"
                            >{{ item.views }} views · {{ item.co2e }}g</span
                        >
                    </div>
                </div>
            </section>
        </div>

        <section
            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200"
        >
            <h2 class="mb-2 font-bold uppercase">
                Tips to reduce your digital footprint
            </h2>
            <div class="grid gap-2 md:grid-cols-3">
                <p>Close unused tabs and avoid repeated page refreshes.</p>
                <p>Use search and filters to reach records faster.</p>
                <p>Download only the files you need and reuse saved copies.</p>
            </div>
        </section>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                v-if="loading"
                class="border-b border-slate-100 px-4 py-2 text-xs font-semibold text-sky-600 dark:border-white/10"
            >
                Loading your activity...
            </div>
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                >
                    <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                        <tr>
                            <th class="report-th">Time</th>
                            <th class="report-th">Module</th>
                            <th class="report-th">URL</th>
                            <th class="report-th">Data</th>
                            <th class="report-th">Energy</th>
                            <th class="report-th">CO2e</th>
                            <th class="report-th">Device</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                        >
                            <td class="report-td">{{ log.created_at }}</td>
                            <td class="report-td">{{ log.module }}</td>
                            <td class="report-td max-w-sm truncate">
                                {{ log.url }}
                            </td>
                            <td class="report-td">
                                {{ log.estimated_data_kb }} KB
                            </td>
                            <td class="report-td">
                                {{ log.estimated_energy_kwh }}
                            </td>
                            <td
                                class="report-td font-bold text-emerald-700 dark:text-emerald-300"
                            >
                                {{ log.estimated_co2e_grams }}g
                            </td>
                            <td class="report-td capitalize">
                                {{ log.device_type }}
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td
                                colspan="7"
                                class="py-12 text-center text-sm text-slate-400"
                            >
                                No activity found for your account.
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
    </div>
</template>

<style scoped>
@reference "tailwindcss";
.stat-card {
    @apply rounded-xl border border-slate-200 bg-white p-4 text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-950;
}
.stat-card strong {
    @apply mt-2 block text-xl text-slate-900 dark:text-white;
}
.stat-icon {
    @apply mb-3 h-5 w-5;
}
.report-input {
    @apply h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100;
}
.report-btn {
    @apply inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
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
