<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    CalendarDays,
    Eye,
    GraduationCap,
    MapPin,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { index as ccdCaresIndex } from '@/routes/site-settings/ccd-cares';
import { submissions as submissionsRoute } from '@/routes/site-settings/ccd-cares/periods';

type Answer = string | number | string[] | null;

type Interpretation = {
    category_id: number;
    category_name: string;
    score: number;
    interpretation: string;
    suggested_intervention: string | null;
};

type Statement = {
    id: number;
    statement: string;
    help_text: string | null;
    statement_type: string;
    choices: Array<{
        id: number;
        label: string;
        value: string;
        score: number | null;
    }>;
    scale_options: Array<{
        id: number;
        label: string;
        value: number;
        interpretation: string | null;
    }>;
};

type Submission = {
    id: number;
    answers: Record<number, Answer>;
    submitted_at: string;
    student: {
        id: number;
        name: string;
        email: string | null;
        student_no: string | null;
        campus_name: string | null;
    };
    interpretations: Interpretation[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    period: {
        id: number;
        title: string;
        description: string | null;
        start_date: string;
        end_date: string;
        status: string;
        template: {
            id: number;
            name: string;
            categories: Array<{
                id: number;
                name: string;
                description: string | null;
                statements: Statement[];
            }>;
        };
    };
    submissions: {
        data: Submission[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    analytics: {
        total_submissions: number;
        campuses: Array<{ campus: string; count: number }>;
        categories: Array<{
            id: number;
            name: string;
            average_score: number;
            distribution: Array<{
                interpretation: string;
                count: number;
            }>;
        }>;
    };
    campuses: string[];
    filters: {
        search: string;
        campus: string;
        submitted_from: string;
        submitted_to: string;
        per_page: number;
    };
}>();

const search = ref(props.filters.search);
const campus = ref(props.filters.campus);
const submittedFrom = ref(props.filters.submitted_from);
const submittedTo = ref(props.filters.submitted_to);
const perPage = ref(props.filters.per_page);
const selectedSubmission = ref<Submission | null>(null);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        campus.value !== '' ||
        submittedFrom.value !== '' ||
        submittedTo.value !== '' ||
        perPage.value !== 15,
);

const applyFilters = () => {
    router.get(
        submissionsRoute.url(props.period.id),
        {
            search: search.value || undefined,
            campus: campus.value || undefined,
            submitted_from: submittedFrom.value || undefined,
            submitted_to: submittedTo.value || undefined,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    search.value = '';
    campus.value = '';
    submittedFrom.value = '';
    submittedTo.value = '';
    perPage.value = 15;
    applyFilters();
};

const interpretationClass = (value: string) => {
    const normalized = value.toLowerCase();

    if (normalized.includes('extreme') || normalized.includes('critical')) {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300';
    }

    if (normalized.includes('severe') || normalized.includes('high')) {
        return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300';
    }

    if (normalized.includes('moderate') || normalized.includes('average')) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (normalized.includes('mild') || normalized.includes('low')) {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
};

const campusPercentage = (count: number) =>
    props.analytics.total_submissions === 0
        ? 0
        : Math.round((count / props.analytics.total_submissions) * 100);

const answerLabel = (statement: Statement, answer: Answer) => {
    if (answer === null || answer === undefined || answer === '') {
        return 'No response';
    }

    if (Array.isArray(answer)) {
        return answer
            .map(
                (value) =>
                    statement.choices.find((choice) => choice.value === value)
                        ?.label ?? value,
            )
            .join(', ');
    }

    const choice = statement.choices.find(
        (item) => String(item.value) === String(answer),
    );
    const scale = statement.scale_options.find(
        (item) => Number(item.value) === Number(answer),
    );

    return choice?.label ?? scale?.label ?? String(answer);
};
</script>

<template>
    <Head :title="`${period.title} - CCD Cares Responses`" />

    <SiteSettingsLayout>
        <div class="space-y-6 p-5 sm:p-7 lg:p-10">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-2">
                    <Link
                        :href="ccdCaresIndex.url()"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <ArrowLeft class="size-3.5" />
                        Back to periods
                    </Link>
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ period.title }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ period.template.name }} ·
                            {{ period.start_date }}–{{ period.end_date }}
                        </p>
                    </div>
                </div>
                <span
                    class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold text-emerald-700 uppercase dark:bg-emerald-500/15 dark:text-emerald-300"
                >
                    {{ period.status }}
                </span>
            </header>

            <section class="space-y-4">
                <div class="flex items-center gap-2">
                    <BarChart3 class="size-5 text-emerald-600" />
                    <div>
                        <h2 class="text-base font-bold">Period analytics</h2>
                        <p class="text-xs text-slate-500">
                            Results include every response submitted during this
                            evaluation period.
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                                >Respondents</span
                            >
                            <Users class="size-5 text-emerald-600" />
                        </div>
                        <p class="mt-4 text-3xl font-black">
                            {{ analytics.total_submissions }}
                        </p>
                    </article>

                    <article
                        v-for="category in analytics.categories"
                        :key="category.id"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p
                            class="truncate text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                        >
                            {{ category.name }}
                        </p>
                        <p class="mt-3 text-2xl font-black">
                            {{ category.average_score }}
                        </p>
                        <p class="text-[11px] text-slate-500">Average score</p>
                        <div class="mt-3 flex flex-wrap gap-1">
                            <span
                                v-for="item in category.distribution"
                                :key="item.interpretation"
                                class="rounded-full border px-2 py-0.5 text-[9px] font-bold"
                                :class="
                                    interpretationClass(item.interpretation)
                                "
                            >
                                {{ item.interpretation }}: {{ item.count }}
                            </span>
                            <span
                                v-if="category.distribution.length === 0"
                                class="text-xs text-slate-400"
                            >
                                No results yet
                            </span>
                        </div>
                    </article>
                </div>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-2">
                        <MapPin class="size-4 text-emerald-600" />
                        <h3 class="text-sm font-bold">
                            Participation by campus
                        </h3>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in analytics.campuses"
                            :key="item.campus"
                            class="rounded-xl border border-slate-100 bg-slate-50 p-4 dark:border-white/5 dark:bg-white/[0.03]"
                        >
                            <div class="flex justify-between gap-3">
                                <span class="truncate text-xs font-bold">{{
                                    item.campus
                                }}</span>
                                <span
                                    class="text-xs font-black text-emerald-600"
                                    >{{ campusPercentage(item.count) }}%</span
                                >
                            </div>
                            <p class="mt-2 text-xl font-black">
                                {{ item.count }}
                            </p>
                            <div
                                class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10"
                            >
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{
                                        width: `${campusPercentage(item.count)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <p
                            v-if="analytics.campuses.length === 0"
                            class="text-xs text-slate-500"
                        >
                            No campus data available.
                        </p>
                    </div>
                </article>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="space-y-4 border-b border-slate-200 p-5 dark:border-white/10"
                >
                    <div>
                        <h2 class="text-base font-bold">Student responses</h2>
                        <p class="text-xs text-slate-500">
                            Showing {{ submissions.from ?? 0 }}–{{
                                submissions.to ?? 0
                            }}
                            of {{ submissions.total }} matching records.
                        </p>
                    </div>

                    <form
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6"
                        @submit.prevent="applyFilters"
                    >
                        <label class="relative xl:col-span-2">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Name, student number, or email"
                                class="h-10 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                            />
                        </label>
                        <select
                            v-model="campus"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="">All campuses</option>
                            <option
                                v-for="item in campuses"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select>
                        <input
                            v-model="submittedFrom"
                            type="date"
                            aria-label="Submitted from"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        />
                        <input
                            v-model="submittedTo"
                            type="date"
                            aria-label="Submitted to"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        />
                        <div class="flex gap-2">
                            <select
                                v-model="perPage"
                                aria-label="Rows per page"
                                class="h-10 min-w-20 flex-1 rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                            >
                                <option :value="10">10 rows</option>
                                <option :value="15">15 rows</option>
                                <option :value="25">25 rows</option>
                                <option :value="50">50 rows</option>
                            </select>
                            <button
                                type="submit"
                                class="h-10 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                Filter
                            </button>
                        </div>
                    </form>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="text-left text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white"
                        @click="resetFilters"
                    >
                        Clear all filters
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead
                            class="bg-slate-50 text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/[0.03]"
                        >
                            <tr>
                                <th class="px-4 py-3">Student</th>
                                <th class="px-4 py-3">Student number</th>
                                <th class="px-4 py-3">Campus</th>
                                <th class="px-4 py-3">Interpretation</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="submission in submissions.data"
                                :key="submission.id"
                                class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-bold">
                                        {{ submission.student.name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ submission.student.email || '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold">
                                    {{ submission.student.student_no || '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    {{ submission.student.campus_name || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex max-w-xl flex-wrap gap-1">
                                        <span
                                            v-for="result in submission.interpretations"
                                            :key="result.category_id"
                                            class="rounded-full border px-2 py-1 text-[9px] font-bold"
                                            :class="
                                                interpretationClass(
                                                    result.interpretation,
                                                )
                                            "
                                        >
                                            {{ result.category_name }}:
                                            {{ result.interpretation }}
                                            ({{ result.score }})
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs whitespace-nowrap text-slate-500"
                                >
                                    {{ submission.submitted_at }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-emerald-200 px-2.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/20 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                        @click="selectedSubmission = submission"
                                    >
                                        <Eye class="size-3.5" />
                                        View answers
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="submissions.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-14 text-center text-sm text-slate-500"
                                >
                                    No student responses match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="submissions.last_page > 1"
                    class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                >
                    <span class="text-xs text-slate-500">
                        Page {{ submissions.current_page }} of
                        {{ submissions.last_page }}
                    </span>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in submissions.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2.5 text-xs font-bold"
                                :class="
                                    link.active
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-100 px-2.5 text-xs text-slate-300 dark:border-white/5 dark:text-slate-600"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="selectedSubmission"
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/55 backdrop-blur-sm"
        >
            <aside
                class="flex h-full w-full max-w-3xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-white/10"
                >
                    <div>
                        <h2 class="text-base font-bold">
                            {{ selectedSubmission.student.name }}
                        </h2>
                        <div
                            class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500"
                        >
                            <span class="flex items-center gap-1">
                                <GraduationCap class="size-3.5" />
                                {{
                                    selectedSubmission.student.student_no ||
                                    'No student number'
                                }}
                            </span>
                            <span class="flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                {{
                                    selectedSubmission.student.campus_name ||
                                    'No campus'
                                }}
                            </span>
                            <span class="flex items-center gap-1">
                                <CalendarDays class="size-3.5" />
                                {{ selectedSubmission.submitted_at }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                        @click="selectedSubmission = null"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="flex-1 space-y-6 overflow-y-auto p-5">
                    <section>
                        <h3
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >
                            Scores and interpretation
                        </h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <article
                                v-for="result in selectedSubmission.interpretations"
                                :key="result.category_id"
                                class="rounded-xl border border-slate-200 p-4 dark:border-white/10"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="text-xs font-bold">
                                            {{ result.category_name }}
                                        </p>
                                        <p class="mt-1 text-2xl font-black">
                                            {{ result.score }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full border px-2 py-1 text-[9px] font-bold"
                                        :class="
                                            interpretationClass(
                                                result.interpretation,
                                            )
                                        "
                                    >
                                        {{ result.interpretation }}
                                    </span>
                                </div>
                                <p
                                    v-if="result.suggested_intervention"
                                    class="mt-3 border-t border-slate-100 pt-3 text-xs leading-relaxed text-slate-600 dark:border-white/10 dark:text-slate-300"
                                >
                                    {{ result.suggested_intervention }}
                                </p>
                            </article>
                        </div>
                    </section>

                    <section class="space-y-5">
                        <h3
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >
                            Evaluation answers
                        </h3>
                        <article
                            v-for="category in period.template.categories"
                            :key="category.id"
                            class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10"
                        >
                            <header
                                class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                            >
                                <h4 class="text-sm font-bold">
                                    {{ category.name }}
                                </h4>
                                <p
                                    v-if="category.description"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ category.description }}
                                </p>
                            </header>
                            <div
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <div
                                    v-for="(
                                        statement, index
                                    ) in category.statements"
                                    :key="statement.id"
                                    class="p-4"
                                >
                                    <p class="text-sm font-semibold">
                                        {{ index + 1 }}.
                                        {{ statement.statement }}
                                    </p>
                                    <p
                                        v-if="statement.help_text"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        {{ statement.help_text }}
                                    </p>
                                    <div
                                        class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-bold text-emerald-900 dark:border-emerald-500/15 dark:bg-emerald-500/10 dark:text-emerald-100"
                                    >
                                        {{
                                            answerLabel(
                                                statement,
                                                selectedSubmission.answers[
                                                    statement.id
                                                ],
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </article>
                    </section>
                </div>
            </aside>
        </div>
    </SiteSettingsLayout>
</template>
