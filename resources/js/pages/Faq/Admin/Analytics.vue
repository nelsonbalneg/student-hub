<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    TrendingUp,
    Search,
    ThumbsUp,
    ChevronLeft,
    HelpCircle,
    PieChart,
    BarChart,
    Target,
} from 'lucide-vue-next';
import VueApexCharts from 'vue3-apexcharts';
import { computed } from 'vue';

interface MostViewed {
    id: number;
    question: string;
    view_count: number;
}

interface SearchTrend {
    keyword: string;
    count: number;
    avg_results: number;
}

interface CategoryStat {
    id: number;
    name: string;
    faqs_count: number;
}

interface Props {
    mostViewed: MostViewed[];
    searchTrends: SearchTrend[];
    categoryStats: CategoryStat[];
    helpfulnessStats: {
        helpful: number;
        not_helpful: number;
    };
}

const props = defineProps<Props>();

// Chart Options: Category Distribution
const categoryChartOptions = {
    chart: { type: 'donut' },
    labels: props.categoryStats.map(s => s.name),
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total FAQs',
                        formatter: () => props.categoryStats.reduce((a, b) => a + b.faqs_count, 0)
                    }
                }
            }
        }
    }
};

const categoryChartSeries = props.categoryStats.map(s => s.faqs_count);

// Chart Options: Helpfulness
const helpfulnessChartOptions = {
    chart: { type: 'pie' },
    labels: ['Helpful', 'Not Helpful'],
    colors: ['#10b981', '#f43f5e'],
    legend: { position: 'bottom' },
};

const helpfulnessChartSeries = [props.helpfulnessStats.helpful, props.helpfulnessStats.not_helpful];

// Chart Options: Most Viewed
const viewedChartOptions = {
    chart: { type: 'bar' },
    plotOptions: {
        bar: {
            borderRadius: 4,
            horizontal: true,
        }
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: props.mostViewed.map(v => v.question.substring(0, 30) + '...'),
    },
    colors: ['#10b981'],
};

const viewedChartSeries = [{
    name: 'Views',
    data: props.mostViewed.map(v => v.view_count)
}];

const totalViews = computed(() => props.mostViewed.reduce((a, b) => a + b.view_count, 0));
const totalSearches = computed(() => props.searchTrends.reduce((a, b) => a + b.count, 0));
</script>

<template>
    <Head title="FAQ Performance Analytics" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <Link href="/faqs/manage/faqs" class="inline-flex h-7 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                            <ChevronLeft class="mr-1 size-3.5" />
                            Back to Articles
                        </Link>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                            <TrendingUp class="size-6" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-normal text-slate-950 dark:text-white">Knowledge Base Performance</h1>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Track article engagement, search trends, and user feedback.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-500/10">
                        <Eye class="size-5" />
                    </div>
                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Engagement</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalViews }}</h3>
                    <p class="text-xs text-slate-500">Total Article Views</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-500/10">
                        <Search class="size-5" />
                    </div>
                    <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Discovery</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">{{ totalSearches }}</h3>
                    <p class="text-xs text-slate-500">Total Keyword Searches</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-500/10">
                        <ThumbsUp class="size-5" />
                    </div>
                    <span class="text-[10px] font-bold text-amber-600 uppercase tracking-widest">Sentiment</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ helpfulnessStats.helpful + helpfulnessStats.not_helpful > 0 
                            ? Math.round((helpfulnessStats.helpful / (helpfulnessStats.helpful + helpfulnessStats.not_helpful)) * 100) 
                            : 0 }}%
                    </h3>
                    <p class="text-xs text-slate-500">Positive Helpfulness</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div class="rounded-lg bg-purple-50 p-2 text-purple-600 dark:bg-purple-500/10">
                        <HelpCircle class="size-5" />
                    </div>
                    <span class="text-[10px] font-bold text-purple-600 uppercase tracking-widest">Inventory</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white">{{ categoryStats.reduce((a, b) => a + b.faqs_count, 0) }}</h3>
                    <p class="text-xs text-slate-500">Active FAQ Articles</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Category Distribution -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center gap-2 mb-6">
                    <PieChart class="size-4 text-emerald-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Category Density</h3>
                </div>
                <VueApexCharts height="300" :options="categoryChartOptions" :series="categoryChartSeries" />
            </div>

            <!-- Helpfulness Ratio -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex items-center gap-2 mb-6">
                    <ThumbsUp class="size-4 text-emerald-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Helpfulness Ratio</h3>
                </div>
                <VueApexCharts height="300" :options="helpfulnessChartOptions" :series="helpfulnessChartSeries" />
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="text-center p-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                        <span class="block text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ helpfulnessStats.helpful }}</span>
                        <span class="text-[10px] font-bold text-emerald-600/60 uppercase">Helpful</span>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-rose-50 dark:bg-rose-500/10">
                        <span class="block text-xl font-bold text-rose-700 dark:text-rose-400">{{ helpfulnessStats.not_helpful }}</span>
                        <span class="text-[10px] font-bold text-rose-600/60 uppercase">Not Helpful</span>
                    </div>
                </div>
            </div>

            <!-- Search Trends -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900 flex flex-col">
                <div class="flex items-center gap-2 mb-6">
                    <Target class="size-4 text-emerald-600" />
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Top Search Terms</h3>
                </div>
                <div class="flex-1 overflow-auto">
                    <div v-for="trend in searchTrends" :key="trend.keyword" class="flex items-center justify-between py-3 border-b border-slate-100 dark:border-white/5 last:border-0">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ trend.keyword }}</span>
                            <span class="text-[10px] text-slate-500">Avg. {{ Math.round(trend.avg_results) }} results</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ trend.count }}</span>
                            <div class="h-1 w-12 rounded-full bg-slate-100 dark:bg-white/5 overflow-hidden">
                                <div class="h-full bg-emerald-500" :style="{ width: (trend.count / searchTrends[0].count * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                    <div v-if="searchTrends.length === 0" class="flex flex-col items-center justify-center h-full opacity-50 py-10">
                        <Search class="size-8 mb-2" />
                        <span class="text-xs font-bold">No search data yet</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Most Viewed Articles -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div class="flex items-center gap-2 mb-6">
                <BarChart3 class="size-4 text-emerald-600" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Most Popular Articles</h3>
            </div>
            <div class="h-[400px]">
                <VueApexCharts height="100%" width="100%" :options="viewedChartOptions" :series="viewedChartSeries" />
            </div>
        </section>
    </div>
</template>
