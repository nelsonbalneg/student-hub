<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { 
    Activity, Dumbbell, Layers, Users, ChevronDown, ChevronRight, FileDown,
    ArrowLeft, BrainCircuit, RefreshCw, BarChart3, PieChart, Loader2
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import AsyncSelect from '@/components/AsyncSelect.vue';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import { useAppearance } from '@/composables/useAppearance';
import { 
    comparative,
    comparativeData as getComparativeData 
} from '@/routes/admin/reporting/pft-result/analytics';
import {
    campuses as filterCampuses,
    colleges as filterColleges,
    sections as filterSections,
    terms as filterTerms,
} from '@/routes/admin/reporting/pft-result/filter';

type SelectOption = { id: string; text: string };

const props = defineProps<{
    filters: Record<string, string>;
    selectedOptions: Record<string, SelectOption | null>;
    canExport: boolean;
}>();

// --- Filters State ---
const filtersPanelOpen = ref(true);
const campusId = ref(props.filters.campus_id ?? '');
const selectedCampus = ref<SelectOption | null>(props.selectedOptions.campus);
const termId = ref(props.filters.term_id ?? '');
const selectedTerm = ref<SelectOption | null>(props.selectedOptions.term);
const collegeId = ref(props.filters.college_id ?? '');
const selectedCollege = ref<SelectOption | null>(props.selectedOptions.college);
const sectionId = ref(props.filters.section_id ?? '');
const selectedSection = ref<SelectOption | null>(props.selectedOptions.section);
const yearLevelId = ref(props.filters.year_level_id ?? '');
const sex = ref(props.filters.sex ?? '');

const loading = ref(true);
const apiData = ref<any>(null);
const { resolvedAppearance } = useAppearance();

const chartTheme = computed(() => ({
    theme: { mode: resolvedAppearance.value },
    foreColor: resolvedAppearance.value === 'dark' ? '#cbd5e1' : '#334155',
    grid: { borderColor: resolvedAppearance.value === 'dark' ? '#334155' : '#e2e8f0' },
    tooltip: { theme: resolvedAppearance.value },
}));

const activeQuery = () => ({
    campus_id: campusId.value,
    term_id: termId.value,
    college_id: collegeId.value,
    section_id: sectionId.value,
    year_level_id: yearLevelId.value,
    sex: sex.value,
});

const toggleFiltersPanel = () => {
    filtersPanelOpen.value = !filtersPanelOpen.value;
};

// Handlers for filters
const onCampusChange = (selected: SelectOption | null) => {
    selectedCampus.value = selected;
    campusId.value = selected?.id ?? '';
    termId.value = '';
    selectedTerm.value = null;
    collegeId.value = '';
    selectedCollege.value = null;
    sectionId.value = '';
    selectedSection.value = null;
    apiData.value = null;
};

const onTermChange = (selected: SelectOption | null) => {
    selectedTerm.value = selected;
    termId.value = selected?.id ?? '';
    if (campusId.value && termId.value) fetchAnalytics();
};
const onCollegeChange = (selected: SelectOption | null) => {
    selectedCollege.value = selected;
    collegeId.value = selected?.id ?? '';
    sectionId.value = '';
    selectedSection.value = null;
    if (campusId.value && termId.value) fetchAnalytics();
};
const onSectionChange = (selected: SelectOption | null) => {
    selectedSection.value = selected;
    sectionId.value = selected?.id ?? '';
    if (campusId.value && termId.value) fetchAnalytics();
};

const resetFilters = () => {
    campusId.value = '';
    selectedCampus.value = null;
    termId.value = '';
    selectedTerm.value = null;
    collegeId.value = '';
    selectedCollege.value = null;
    sectionId.value = '';
    selectedSection.value = null;
    yearLevelId.value = '';
    sex.value = '';
    apiData.value = null;
    const url = new URL(window.location.href);
    url.search = '';
    window.history.replaceState({}, '', url.toString());
};

const fetchAnalytics = async () => {
    if (!campusId.value || !termId.value) return;
    loading.value = true;
    try {
        const response = await fetch(getComparativeData.url({ query: activeQuery() }), {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        if (payload.success) {
            apiData.value = payload.data;
            window.history.replaceState({}, '', comparative.url({ query: activeQuery() }));
        }
    } catch (e) {
        console.error('Error fetching comparative analytics', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (campusId.value && termId.value) {
        fetchAnalytics();
    } else {
        loading.value = false;
    }
});

// --- Chart Options ---
const campusChartOptions = computed(() => {
    if (!apiData.value) return {};
    return {
        ...chartTheme.value,
        chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: apiData.value.campus_comparison.labels },
        yaxis: { title: { text: 'Metrics' } },
        fill: { opacity: 1 },
        colors: ['#2563eb', '#10b981', '#f59e0b']
    };
});
const campusChartSeries = computed(() => {
    if (!apiData.value) return [];
    const comp = apiData.value.campus_comparison;
    return [
        { name: 'Fitness Index', data: comp.fitness_index },
        { name: 'Participation %', data: comp.participation },
        { name: 'Average BMI', data: comp.bmi }
    ];
});

const demographicChartOptions = computed(() => {
    return {
        ...chartTheme.value,
        chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit', stacked: false },
        plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '55%' } },
        colors: ['#06b6d4', '#ec4899'], // cyan for male, pink for female
        xaxis: { categories: ['Avg BMI', 'Avg Push-ups', 'Avg Curl-ups'] },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] }
    };
});

const demographicChartSeries = computed(() => {
    if (!apiData.value) return [];
    const demo = apiData.value.demographics;
    return [
        { name: 'Male', data: [demo.male.bmi, demo.male.push_up, demo.male.curl_up] },
        { name: 'Female', data: [demo.female.bmi, demo.female.push_up, demo.female.curl_up] }
    ];
});

// BMI Doughnut
const bmiChartOptions = computed(() => {
    if (!apiData.value) return {};
    return {
        ...chartTheme.value,
        chart: { type: 'donut', fontFamily: 'inherit' },
        labels: apiData.value.bmi_distribution.labels,
        colors: ['#f59e0b', '#10b981', '#f97316', '#ef4444'],
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '70%' } } }
    };
});
const bmiChartSeries = computed(() => apiData.value?.bmi_distribution.series ?? []);

// Health Components Radar
const healthRadarOptions = computed(() => {
    if (!apiData.value) return {};
    return {
        ...chartTheme.value,
        chart: { type: 'radar', fontFamily: 'inherit', toolbar: { show: false } },
        labels: apiData.value.health_components.labels,
        stroke: { width: 2 },
        fill: { opacity: 0.2 },
        markers: { size: 4 },
        colors: ['#3b82f6', '#10b981']
    };
});
const healthRadarSeries = computed(() => apiData.value?.health_components.series ?? []);

// Performance Stacked Bar
const performanceStackedOptions = computed(() => {
    if (!apiData.value) return {};
    return {
        ...chartTheme.value,
        chart: { type: 'bar', stacked: true, stackType: '100%', fontFamily: 'inherit', toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 2 } },
        xaxis: { categories: apiData.value.performance_distribution.categories },
        colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
        fill: { opacity: 1 },
        dataLabels: { enabled: false }
    };
});
const performanceStackedSeries = computed(() => apiData.value?.performance_distribution.series ?? []);

</script>

<template>
    <Head title="Comparative Analytics" />
    <div class="pft-comparative-page min-h-screen font-sans bg-slate-50 text-slate-800 lg:flex dark:bg-slate-950 dark:text-slate-100">
        
        <FitnessIntelligenceSidebar
            active="comparative"
            :campus-id="campusId"
            :term-id="termId"
        />

        <!-- Main Content -->
        <main class="min-w-0 flex-1 relative flex flex-col h-screen overflow-y-auto">
            <!-- Header -->
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <button class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 lg:hidden">☰</button>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <Layers class="h-5 w-5 text-blue-600" /> Comparative Analytics
                            </h2>
                            <p class="hidden text-xs text-slate-500 sm:block">Campus, college, section, and term trend comparisons</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                            :href="`/admin/reporting/pft-result/analytics?campus_id=${campusId}&term_id=${termId}`"
                        >
                            <ArrowLeft class="h-4 w-4" /> Back to Dashboard
                        </Link>
                        
                        <button class="hidden items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 md:flex">
                            <FileDown class="h-4 w-4" /> Export View
                        </button>
                    </div>
                </div>
            </header>

        <!-- Global Filters Panel -->
        <section class="sticky top-16 z-20 border-b border-slate-200 bg-slate-50/95 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between cursor-pointer" @click="toggleFiltersPanel">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700">Global Comparisons & Filters</h3>
                        <p class="mt-1 text-xs text-slate-500">All rankings, charts, and AI insights reflect the selected filters below.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition" @click.stop="fetchAnalytics">
                            Apply Filters
                        </button>
                        <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition" @click.stop="resetFilters">
                            ↻ Reset
                        </button>
                        <button type="button" class="ml-2 text-slate-400 hover:text-slate-600">
                            <ChevronDown v-if="filtersPanelOpen" class="h-5 w-5" />
                            <ChevronRight v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div v-show="filtersPanelOpen" class="p-5">
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Campus</span>
                            <AsyncSelect
                                v-model="campusId"
                                :selected="selectedCampus"
                                :endpoint="filterCampuses.url()"
                                placeholder="Search campus"
                                @select="onCampusChange"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Academic Term</span>
                            <AsyncSelect
                                v-model="termId"
                                :selected="selectedTerm"
                                :endpoint="filterTerms.url()"
                                :params="{ campus_id: campusId }"
                                :disabled="!campusId"
                                placeholder="Search term"
                                @select="onTermChange"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">College</span>
                            <AsyncSelect
                                v-model="collegeId"
                                :selected="selectedCollege"
                                :endpoint="filterColleges.url()"
                                :params="{ campus_id: campusId }"
                                :disabled="!campusId"
                                placeholder="Compare specific college"
                                @select="onCollegeChange"
                            />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Section</span>
                            <AsyncSelect
                                v-model="sectionId"
                                :selected="selectedSection"
                                :endpoint="filterSections.url()"
                                :params="{ campus_id: campusId, term_id: termId, college_id: collegeId }"
                                :disabled="!campusId || !termId || !collegeId"
                                placeholder="Compare specific section"
                                @select="onSectionChange"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Dashboard Content -->
        <div class="p-4 sm:p-6 lg:px-8 space-y-8 pb-20">
            
            <div v-if="loading" class="flex h-64 items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-blue-500" />
            </div>

            <div v-else-if="!apiData" class="flex h-64 flex-col items-center justify-center text-center">
                <div class="rounded-full bg-slate-100 p-4">
                    <BarChart3 class="h-8 w-8 text-slate-400" />
                </div>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">No Data Loaded</h3>
                <p class="mt-1 text-sm text-slate-500">Please select a campus and academic term to generate comparative analytics.</p>
            </div>

            <template v-else>
                <!-- 1. Comparison Summary Cards -->
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                        <Activity class="h-4 w-4" /> Comparison Summary
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Compared Campuses</p>
                            <p class="mt-2 text-3xl font-black text-slate-800">{{ apiData.summary.total_campuses }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Compared Colleges</p>
                            <p class="mt-2 text-3xl font-black text-slate-800">{{ apiData.summary.total_colleges }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Avg Fitness Index</p>
                            <p class="mt-2 text-3xl font-black text-blue-600">{{ apiData.summary.average_fitness_index }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm col-span-1 sm:col-span-2 xl:col-span-1">
                            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Best Campus</p>
                            <p class="mt-2 text-lg font-bold text-emerald-800">{{ apiData.summary.best_campus }}</p>
                        </div>
                        <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm col-span-1 sm:col-span-2 xl:col-span-1">
                            <p class="text-xs font-semibold text-rose-600 uppercase tracking-wide">Lowest Campus</p>
                            <p class="mt-2 text-lg font-bold text-rose-800">{{ apiData.summary.lowest_campus }}</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm col-span-1 sm:col-span-2 xl:col-span-1">
                            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Highest Growth</p>
                            <p class="mt-2 text-lg font-bold text-amber-800 line-clamp-1">{{ apiData.summary.highest_growth }}</p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-8 lg:grid-cols-2">
                    <!-- 2. Campus Comparison Chart -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-800">Campus Performance Comparison</h3>
                                <p class="text-xs text-slate-500 mt-1">Comparing Fitness Index, Participation, and BMI</p>
                            </div>
                        </div>
                        <div class="h-[300px] w-full">
                            <VueApexCharts type="bar" height="100%" :options="campusChartOptions" :series="campusChartSeries" />
                        </div>
                    </article>

                    <!-- 4. College Ranking Table -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-base font-bold text-slate-800">College Rankings</h3>
                                <p class="text-xs text-slate-500 mt-1">Top performing colleges based on Fitness Index</p>
                            </div>
                        </div>
                        <div class="flex-1 overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="pb-3 pr-4">Rank</th>
                                        <th class="pb-3 pr-4">College</th>
                                        <th class="pb-3 px-4 text-center">Fitness Index</th>
                                        <th class="pb-3 px-4 text-center">Participation</th>
                                        <th class="pb-3 pl-4 text-right">Avg BMI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="col in apiData.college_comparison" :key="col.name" class="hover:bg-slate-50 transition">
                                        <td class="py-4 pr-4 font-bold text-slate-400">#{{ col.rank }}</td>
                                        <td class="py-4 pr-4 font-medium text-slate-800">{{ col.name }}</td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                                {{ col.fitness_index }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center text-slate-600">{{ col.participation }}%</td>
                                        <td class="py-4 pl-4 text-right font-medium text-slate-700">{{ col.bmi }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- 9. BMI Comparison Doughnut -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-6">
                            <h3 class="text-base font-bold text-slate-800">BMI Distribution</h3>
                            <p class="text-xs text-slate-500 mt-1">Overall classification</p>
                        </div>
                        <div class="h-[250px] w-full flex items-center justify-center">
                            <VueApexCharts type="donut" height="100%" :options="bmiChartOptions" :series="bmiChartSeries" />
                        </div>
                    </article>

                    <!-- 12. Health Components Radar -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-2">
                            <h3 class="text-base font-bold text-slate-800">Health Components Radar</h3>
                            <p class="text-xs text-slate-500 mt-1">Average vs Target performance</p>
                        </div>
                        <div class="h-[250px] w-full">
                            <VueApexCharts type="radar" height="100%" :options="healthRadarOptions" :series="healthRadarSeries" />
                        </div>
                    </article>

                    <!-- 15. Performance Distribution Stacked Bar -->
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="mb-6">
                            <h3 class="text-base font-bold text-slate-800">Performance Distribution</h3>
                            <p class="text-xs text-slate-500 mt-1">100% Stacked rating</p>
                        </div>
                        <div class="h-[250px] w-full">
                            <VueApexCharts type="bar" height="100%" :options="performanceStackedOptions" :series="performanceStackedSeries" />
                        </div>
                    </article>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- 8. Male vs Female -->
                    <article class="col-span-1 lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-bold text-slate-800">Demographic Comparison: Male vs Female</h3>
                        <p class="text-xs text-slate-500 mt-1 mb-6">Benchmarking average results by sex</p>
                        <div class="h-[250px] w-full">
                            <VueApexCharts type="bar" height="100%" :options="demographicChartOptions" :series="demographicChartSeries" />
                        </div>
                    </article>

                    <!-- 20. AI Comparative Insights -->
                    <article class="col-span-1 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50/50 to-white p-6 shadow-sm relative overflow-hidden flex flex-col">
                        <!-- Background decoration -->
                        <div class="absolute -right-6 -top-6 text-indigo-100 opacity-50">
                            <BrainCircuit class="h-32 w-32" />
                        </div>
                        
                        <div class="relative z-10 flex-1 flex flex-col">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-2">
                                    <BrainCircuit class="h-5 w-5 text-indigo-600" />
                                    <h3 class="text-base font-bold text-indigo-900">AI Comparative Insights</h3>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 flex-1">
                                <li v-for="(insight, idx) in apiData.ai_insights" :key="idx" 
                                    class="flex items-start gap-3 bg-white/80 p-4 rounded-xl border border-indigo-100 shadow-sm backdrop-blur">
                                    <div class="mt-0.5 rounded-full bg-indigo-100 p-1">
                                        <Activity class="h-3 w-3 text-indigo-600" />
                                    </div>
                                    <p class="text-sm text-indigo-900 leading-snug font-medium">{{ insight }}</p>
                                </li>
                            </ul>

                            <button class="mt-6 w-full py-2.5 rounded-xl border border-indigo-200 bg-white text-xs font-semibold text-indigo-600 hover:bg-indigo-50 transition shadow-sm">
                                Configure AI API Settings
                            </button>
                        </div>
                    </article>
                </div>

            </template>
            </div>
        </main>
    </div>
</template>

<style>
@reference "tailwindcss";

.dark .pft-comparative-page {
    background-color: #020617 !important;
    color: #cbd5e1 !important;
}

.dark .pft-comparative-page [class*='bg-white'],
.dark .pft-comparative-page [class*='bg-slate-50'] {
    background-color: #0f172a !important;
}

.dark .pft-comparative-page [class*='bg-white/'],
.dark .pft-comparative-page [class*='bg-slate-50/'] {
    background-color: rgba(15, 23, 42, 0.84) !important;
}

.dark .pft-comparative-page [class*='border-slate-100'],
.dark .pft-comparative-page [class*='border-slate-200'] {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.dark .pft-comparative-page [class*='divide-slate-100'] > :not([hidden]) ~ :not([hidden]) {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.dark .pft-comparative-page [class*='text-slate-900'],
.dark .pft-comparative-page [class*='text-slate-800'],
.dark .pft-comparative-page [class*='text-slate-700'] {
    color: #f8fafc !important;
}

.dark .pft-comparative-page [class*='text-slate-600'],
.dark .pft-comparative-page [class*='text-slate-500'] {
    color: #94a3b8 !important;
}

.dark .pft-comparative-page [class*='hover:bg-slate-50']:hover {
    background-color: #1e293b !important;
}

.dark .pft-comparative-page [class*='bg-emerald-50'],
.dark .pft-comparative-page [class*='bg-rose-50'],
.dark .pft-comparative-page [class*='bg-amber-50'],
.dark .pft-comparative-page [class*='from-indigo-50'] {
    background-color: #111827 !important;
    background-image: none !important;
}

.dark .pft-comparative-page [class*='text-emerald-800'],
.dark .pft-comparative-page [class*='text-emerald-700'] {
    color: #6ee7b7 !important;
}

.dark .pft-comparative-page [class*='text-rose-800'],
.dark .pft-comparative-page [class*='text-rose-700'] {
    color: #fda4af !important;
}

.dark .pft-comparative-page [class*='text-amber-800'],
.dark .pft-comparative-page [class*='text-amber-700'] {
    color: #fcd34d !important;
}

.dark .pft-comparative-page [class*='text-indigo-900'] {
    color: #c7d2fe !important;
}
</style>
