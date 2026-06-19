<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    Lightbulb,
    MessageSquareText,
    Printer,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import {
    analytics as analyticsRoute,
    results as resultsRoute,
} from '@/routes/site-settings/site-evaluation';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

const props = defineProps<{
    periods: Array<{
        id: number;
        title: string;
        start_date: string;
        end_date: string;
    }>;
    selectedPeriod: {
        id: number;
        title: string;
        start_date: string;
        end_date: string;
        template_name: string;
    } | null;
    analytics: {
        total_submissions: number;
        total_dismissals: number;
        dismissed_users: number;
        response_rate: number;
        overall_rating: number;
        campuses: Array<{ campus: string; count: number }>;
        categories: Array<{
            id: number;
            name: string;
            average_rating: number;
            distribution: Array<{
                interpretation: string;
                count: number;
            }>;
            questions: Array<{
                id: number;
                statement: string;
                average: number;
                responses: number;
            }>;
        }>;
        insights: Array<{
            tone: 'positive' | 'warning' | 'neutral';
            title: string;
            message: string;
        }>;
    } | null;
}>();

const periodId = ref(props.selectedPeriod?.id ?? 0);

const changePeriod = () => {
    router.get(
        analyticsRoute.url(),
        { period_id: periodId.value },
        { preserveState: true, replace: true },
    );
};

const categorySeries = computed(() => [
    {
        name: 'Average rating',
        data:
            props.analytics?.categories.map(
                (category) => category.average_rating,
            ) ?? [],
    },
]);
const categoryOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    xaxis: {
        categories:
            props.analytics?.categories.map((category) => category.name) ?? [],
    },
    yaxis: { min: 0, max: 5, tickAmount: 5 },
    plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
    colors: ['#059669'],
    dataLabels: { enabled: true },
}));

const participationSeries = computed(() => [
    props.analytics?.total_submissions ?? 0,
    props.analytics?.dismissed_users ?? 0,
]);
const participationOptions = {
    labels: ['Completed', 'Skipped'],
    colors: ['#10b981', '#f59e0b'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true },
};

const campusSeries = computed(
    () => props.analytics?.campuses.map((item) => item.count) ?? [],
);
const campusOptions = computed(() => ({
    labels: props.analytics?.campuses.map((item) => item.campus) ?? [],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true },
}));

const questionRows = computed(
    () =>
        props.analytics?.categories.flatMap((category) =>
            category.questions.map((question) => ({
                ...question,
                category: category.name,
            })),
        ) ?? [],
);
const questionSeries = computed(() => [
    {
        name: 'Average rating',
        data: questionRows.value.map((question) => question.average),
    },
]);
const questionOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
    xaxis: {
        min: 0,
        max: 5,
        categories: questionRows.value.map((question) =>
            question.statement.length > 55
                ? `${question.statement.slice(0, 55)}…`
                : question.statement,
        ),
    },
    colors: ['#0ea5e9'],
    dataLabels: { enabled: true },
}));

const insightClass = (tone: string) =>
    tone === 'positive'
        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10'
        : tone === 'warning'
          ? 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10'
          : 'border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/[0.04]';

const ratingMeaning = (rating: number) => {
    if (rating < 1.5) return 'Strongly Disagree';
    if (rating < 2.5) return 'Disagree';
    if (rating < 3.5) return 'Neutral';
    if (rating < 4.5) return 'Agree';
    return 'Strongly Agree';
};

const printedAt = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date()),
);

const printReport = async () => {
    const previousTitle = document.title;
    document.title = selectedPeriodTitle.value
        ? `${selectedPeriodTitle.value} - Site Evaluation Analytics`
        : 'Site Evaluation Analytics';

    await nextTick();
    window.setTimeout(() => {
        window.print();
        document.title = previousTitle;
    }, 150);
};

const selectedPeriodTitle = computed(() => props.selectedPeriod?.title ?? '');
</script>

<template>
    <Head title="Site Evaluation Analytics" />

    <SiteSettingsLayout>
        <div
            id="site-evaluation-print-report"
            class="space-y-6 p-5 sm:p-7 lg:p-10"
        >
            <div class="hidden print:block">
                <p
                    class="text-xs font-bold tracking-widest text-emerald-700 uppercase"
                >
                    University of Southern Mindanao
                </p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">
                    Site Evaluation Analytics Report
                </h1>
                <div
                    v-if="selectedPeriod"
                    class="mt-3 grid grid-cols-2 gap-3 border-y border-slate-300 py-3 text-xs text-slate-700"
                >
                    <p>
                        <strong>Evaluation period:</strong>
                        {{ selectedPeriod.title }}
                    </p>
                    <p>
                        <strong>Template:</strong>
                        {{ selectedPeriod.template_name }}
                    </p>
                    <p>
                        <strong>Date range:</strong>
                        {{ selectedPeriod.start_date }} -
                        {{ selectedPeriod.end_date }}
                    </p>
                    <p>
                        <strong>Generated:</strong>
                        {{ printedAt }}
                    </p>
                </div>
            </div>

            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <Link
                        :href="
                            resultsRoute.url({
                                query: {
                                    period_id: selectedPeriod?.id,
                                },
                            })
                        "
                        class="no-print mb-3 inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300"
                    >
                        <ArrowLeft class="size-3.5" />
                        Back to results
                    </Link>
                    <div class="no-print flex items-center gap-2">
                        <BarChart3 class="size-6 text-emerald-600" />
                        <h1 class="text-2xl font-bold">
                            Site Evaluation Analytics
                        </h1>
                    </div>
                    <p class="no-print mt-1 text-sm text-slate-500">
                        Visual analysis of ratings, participation, campuses, and
                        improvement priorities.
                    </p>
                </div>
                <div class="no-print flex flex-wrap items-center gap-2">
                    <select
                        v-model="periodId"
                        class="h-10 min-w-64 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold dark:border-white/10 dark:bg-slate-900"
                        @change="changePeriod"
                    >
                        <option
                            v-for="period in periods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.title }}
                        </option>
                    </select>
                    <button
                        v-if="selectedPeriod && analytics"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition-colors hover:bg-emerald-700"
                        @click="printReport"
                    >
                        <Printer class="size-4" />
                        Print / Save PDF
                    </button>
                </div>
            </header>

            <div
                v-if="!selectedPeriod || !analytics"
                class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500"
            >
                No analytics are available yet.
            </div>

            <template v-else>
                <section
                    class="report-summary grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Overall rating
                        </p>
                        <p class="mt-3 text-3xl font-black">
                            {{ analytics.overall_rating }}/5
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ ratingMeaning(analytics.overall_rating) }}
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Responses
                        </p>
                        <p class="mt-3 text-3xl font-black">
                            {{ analytics.total_submissions }}
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Response rate
                        </p>
                        <p class="mt-3 text-3xl font-black">
                            {{ analytics.response_rate }}%
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p class="text-xs font-bold text-slate-500 uppercase">
                            Skip actions
                        </p>
                        <p class="mt-3 text-3xl font-black">
                            {{ analytics.total_dismissals }}
                        </p>
                    </article>
                </section>

                <div
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs leading-relaxed text-blue-900 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200"
                >
                    <strong>How to read ratings:</strong>
                    1 = Strongly Disagree, 2 = Disagree, 3 = Neutral, 4 = Agree,
                    and 5 = Strongly Agree. For example, 1/5 means the average
                    respondent selected Strongly Disagree.
                </div>

                <section class="report-charts grid gap-4 xl:grid-cols-2">
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <h2 class="text-sm font-bold">Ratings by section</h2>
                        <VueApexCharts
                            class="mt-3"
                            height="330"
                            :options="categoryOptions"
                            :series="categorySeries"
                        />
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <h2 class="text-sm font-bold">Participation outcome</h2>
                        <VueApexCharts
                            class="mt-3"
                            type="donut"
                            height="330"
                            :options="participationOptions"
                            :series="participationSeries"
                        />
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <h2 class="text-sm font-bold">Responses by campus</h2>
                        <VueApexCharts
                            v-if="campusSeries.length"
                            class="mt-3"
                            type="donut"
                            height="330"
                            :options="campusOptions"
                            :series="campusSeries"
                        />
                        <p
                            v-else
                            class="grid h-72 place-items-center text-sm text-slate-500"
                        >
                            No campus data available.
                        </p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <h2 class="text-sm font-bold">
                            Question-level ratings
                        </h2>
                        <VueApexCharts
                            class="mt-3"
                            height="330"
                            :options="questionOptions"
                            :series="questionSeries"
                        />
                    </article>
                </section>

                <section class="report-insights">
                    <div class="mb-3 flex items-center gap-2">
                        <Lightbulb class="size-5 text-amber-500" />
                        <h2 class="text-base font-bold">Generated insights</h2>
                    </div>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <article
                            v-for="insight in analytics.insights"
                            :key="insight.title"
                            class="rounded-xl border p-4"
                            :class="insightClass(insight.tone)"
                        >
                            <h3 class="text-sm font-bold">
                                {{ insight.title }}
                            </h3>
                            <p class="mt-1 text-xs leading-relaxed">
                                {{ insight.message }}
                            </p>
                        </article>
                    </div>
                </section>

                <Link
                    :href="
                        resultsRoute.url({
                            query: { period_id: selectedPeriod.id },
                        })
                    "
                    class="no-print inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-bold hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                >
                    <MessageSquareText class="size-4" />
                    View responses and comments
                </Link>
            </template>
        </div>
    </SiteSettingsLayout>
</template>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }

    body * {
        visibility: hidden !important;
    }

    #site-evaluation-print-report,
    #site-evaluation-print-report * {
        visibility: visible !important;
    }

    #site-evaluation-print-report {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        padding: 0 !important;
        color: #0f172a !important;
        background: #fff !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    #site-evaluation-print-report .no-print {
        display: none !important;
    }

    #site-evaluation-print-report > header {
        display: none !important;
    }

    #site-evaluation-print-report article,
    #site-evaluation-print-report .report-insights > div > article {
        break-inside: avoid;
        border-color: #cbd5e1 !important;
        background: #fff !important;
        box-shadow: none !important;
    }

    #site-evaluation-print-report .report-summary {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    #site-evaluation-print-report .report-summary article {
        padding: 12px !important;
    }

    #site-evaluation-print-report .report-charts {
        grid-template-columns: 1fr !important;
    }

    #site-evaluation-print-report .report-charts article {
        break-before: auto;
        break-inside: avoid;
    }

    #site-evaluation-print-report .report-insights {
        break-before: page;
    }

    #site-evaluation-print-report .apexcharts-canvas,
    #site-evaluation-print-report .apexcharts-svg {
        max-width: 100% !important;
    }
}
</style>
