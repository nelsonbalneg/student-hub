<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    Eye,
    MessageSquareText,
    Search,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import {
    index as siteEvaluationIndex,
    analytics as analyticsRoute,
    results as resultsRoute,
} from '@/routes/site-settings/site-evaluation';

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
    user: {
        id: number;
        name: string;
        email: string | null;
        student_no: string | null;
        campus_name: string | null;
        user_type: string | null;
    };
    interpretations: Interpretation[];
};
type LinkItem = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    periods: Array<{
        id: number;
        title: string;
        start_date: string;
        end_date: string;
        status: string;
        submissions_count: number;
        dismissals_count: number;
        skips_count: number;
        template_name: string;
        submissions_count: number;
        dismissals_count: number;
        skips_count: number;
        max_skips: number;
    }>;
    selectedPeriod: {
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
    } | null;
    submissions: {
        data: Submission[];
        links: LinkItem[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    } | null;
    comments: {
        data: Array<{
            id: string;
            question: string;
            comment: string;
            submitted_at: string;
            user: {
                name: string;
                campus_name: string | null;
            };
        }>;
        links: LinkItem[];
        current_page: number;
        last_page: number;
        total: number;
    } | null;
    campuses: string[];
    filters: {
        period_id?: number;
        search?: string;
        campus?: string;
        submitted_from?: string;
        submitted_to?: string;
        per_page?: number;
    };
}>();

const periodId = ref(props.filters.period_id ?? props.selectedPeriod?.id ?? 0);
const search = ref(props.filters.search ?? '');
const campus = ref(props.filters.campus ?? '');
const submittedFrom = ref(props.filters.submitted_from ?? '');
const submittedTo = ref(props.filters.submitted_to ?? '');
const perPage = ref(props.filters.per_page ?? 15);
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
        resultsRoute.url(),
        {
            period_id: periodId.value || undefined,
            search: search.value || undefined,
            campus: campus.value || undefined,
            submitted_from: submittedFrom.value || undefined,
            submitted_to: submittedTo.value || undefined,
            per_page: perPage.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const changePeriod = () => {
    search.value = '';
    campus.value = '';
    submittedFrom.value = '';
    submittedTo.value = '';
    applyFilters();
};

const resetFilters = () => {
    search.value = '';
    campus.value = '';
    submittedFrom.value = '';
    submittedTo.value = '';
    perPage.value = 15;
    applyFilters();
};

const answerLabel = (statement: Statement, answer: Answer) => {
    if (answer === null || answer === undefined || answer === '')
        return 'No response';
    if (Array.isArray(answer)) {
        return answer
            .map(
                (value) =>
                    statement.choices.find((choice) => choice.value === value)
                        ?.label ?? value,
            )
            .join(', ');
    }

    return (
        statement.choices.find(
            (choice) => String(choice.value) === String(answer),
        )?.label ??
        statement.scale_options.find(
            (option) => Number(option.value) === Number(answer),
        )?.label ??
        String(answer)
    );
};
</script>

<template>
    <Head title="Site Evaluation Results" />

    <SiteSettingsLayout>
        <div class="space-y-6 p-5 sm:p-7 lg:p-10">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <Link
                        :href="siteEvaluationIndex.url()"
                        class="mb-3 inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300"
                    >
                        <ArrowLeft class="size-3.5" />
                        Back to periods
                    </Link>
                    <h1 class="text-2xl font-bold">Site Evaluation Results</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Server-generated analytics, response records, and
                        actionable website insights.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Link
                        v-if="selectedPeriod"
                        :href="
                            analyticsRoute.url({
                                query: { period_id: selectedPeriod.id },
                            })
                        "
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700"
                    >
                        <BarChart3 class="size-4" />
                        Analytics
                    </Link>
                    <select
                        v-model="periodId"
                        class="h-10 min-w-64 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        @change="changePeriod"
                    >
                        <option
                            v-for="period in periods"
                            :key="period.id"
                            :value="period.id"
                        >
                            {{ period.title }} ({{ period.start_date }}–{{
                                period.end_date
                            }})
                        </option>
                    </select>
                </div>
            </header>

            <div
                v-if="!selectedPeriod || !submissions"
                class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-sm text-slate-500 dark:border-white/15"
            >
                No site evaluation period is available yet.
            </div>

            <template v-else>
                <section class="grid gap-4 sm:grid-cols-3">
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <Users class="size-5 text-emerald-600" />
                        <p class="mt-4 text-3xl font-black">
                            {{ selectedPeriod.submissions_count }}
                        </p>
                        <p class="text-xs text-slate-500">Responses</p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <MessageSquareText class="size-5 text-blue-600" />
                        <p class="mt-4 text-3xl font-black">
                            {{ comments?.total ?? 0 }}
                        </p>
                        <p class="text-xs text-slate-500">Written comments</p>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <p
                            class="text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                        >
                            Skip actions
                        </p>
                        <p class="mt-4 text-3xl font-black">
                            {{ selectedPeriod.skips_count }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ selectedPeriod.dismissals_count }} users
                        </p>
                    </article>
                </section>

                <section
                    v-if="comments"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <MessageSquareText class="size-5 text-emerald-600" />
                        <div>
                            <h2 class="text-base font-bold">
                                Evaluation comments
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{ comments.total }} written responses from
                                long-answer questions.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="comments.data.length > 0"
                        class="grid gap-4 p-5 lg:grid-cols-2"
                    >
                        <article
                            v-for="comment in comments.data"
                            :key="comment.id"
                            class="rounded-xl border border-slate-200 p-4 dark:border-white/10"
                        >
                            <p
                                class="text-[11px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                            >
                                {{ comment.question }}
                            </p>
                            <p
                                class="mt-3 text-sm leading-relaxed whitespace-pre-wrap text-slate-700 dark:text-slate-200"
                            >
                                “{{ comment.comment }}”
                            </p>
                            <div
                                class="mt-4 flex flex-wrap justify-between gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-white/10"
                            >
                                <span>
                                    {{ comment.user.name }}
                                    <template v-if="comment.user.campus_name">
                                        · {{ comment.user.campus_name }}
                                    </template>
                                </span>
                                <span>{{ comment.submitted_at }}</span>
                            </div>
                        </article>
                    </div>
                    <div
                        v-else
                        class="px-5 py-12 text-center text-sm text-slate-500"
                    >
                        No written comments were submitted for this period.
                    </div>
                    <div
                        v-if="comments.last_page > 1"
                        class="flex flex-wrap justify-end gap-1 border-t border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <template
                            v-for="link in comments.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-bold"
                                :class="
                                    link.active
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-slate-200 dark:border-white/10'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="inline-flex h-8 items-center px-2 text-xs text-slate-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="space-y-4 border-b border-slate-200 p-5 dark:border-white/10"
                    >
                        <div>
                            <h2 class="text-base font-bold">
                                Evaluation responses
                            </h2>
                            <p class="text-xs text-slate-500">
                                Showing {{ submissions.from ?? 0 }}–{{
                                    submissions.to ?? 0
                                }}
                                of {{ submissions.total }}.
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
                                    placeholder="Name, email, or student number"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                                />
                            </label>
                            <select
                                v-model="campus"
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900"
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
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900"
                            />
                            <input
                                v-model="submittedTo"
                                type="date"
                                aria-label="Submitted to"
                                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-xs dark:border-white/10 dark:bg-slate-900"
                            />
                            <div class="flex gap-2">
                                <select
                                    v-model="perPage"
                                    class="h-10 min-w-20 flex-1 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-white/10 dark:bg-slate-900"
                                >
                                    <option :value="10">10</option>
                                    <option :value="15">15</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                </select>
                                <button
                                    type="submit"
                                    class="h-10 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white"
                                >
                                    Filter
                                </button>
                            </div>
                        </form>
                        <button
                            v-if="hasFilters"
                            type="button"
                            class="text-left text-xs font-bold text-slate-500"
                            @click="resetFilters"
                        >
                            Clear filters
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead
                                class="bg-slate-50 text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/[0.03]"
                            >
                                <tr>
                                    <th class="px-4 py-3">User</th>
                                    <th class="px-4 py-3">Campus</th>
                                    <th class="px-4 py-3">Results</th>
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
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-bold">
                                            {{ submission.user.name }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ submission.user.email || '—' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        {{ submission.user.campus_name || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="result in submission.interpretations"
                                                :key="result.category_id"
                                                class="rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                            >
                                                {{ result.category_name }}:
                                                {{ result.interpretation }}
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
                                            class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300"
                                            @click="
                                                selectedSubmission = submission
                                            "
                                        >
                                            <Eye class="size-3.5" />
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="submissions.data.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-12 text-center text-sm text-slate-500"
                                    >
                                        No responses match the filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="submissions.last_page > 1"
                        class="flex flex-wrap justify-end gap-1 border-t border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <template
                            v-for="link in submissions.links"
                            :key="link.label"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-bold"
                                :class="
                                    link.active
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-slate-200 dark:border-white/10'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span
                                v-else
                                class="inline-flex h-8 items-center px-2 text-xs text-slate-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </section>
            </template>
        </div>

        <div
            v-if="selectedSubmission && selectedPeriod"
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/55 backdrop-blur-sm"
        >
            <aside
                class="flex h-full w-full max-w-3xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <header
                    class="flex items-start justify-between border-b border-slate-200 p-5 dark:border-white/10"
                >
                    <div>
                        <h2 class="font-bold">
                            {{ selectedSubmission.user.name }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ selectedSubmission.submitted_at }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                        @click="selectedSubmission = null"
                    >
                        <X class="size-4" />
                    </button>
                </header>
                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <article
                        v-for="category in selectedPeriod.template.categories"
                        :key="category.id"
                        class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10"
                    >
                        <h3
                            class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            {{ category.name }}
                        </h3>
                        <div
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <div
                                v-for="statement in category.statements"
                                :key="statement.id"
                                class="p-4"
                            >
                                <p class="text-sm font-semibold">
                                    {{ statement.statement }}
                                </p>
                                <p
                                    class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100"
                                >
                                    {{
                                        answerLabel(
                                            statement,
                                            selectedSubmission.answers[
                                                statement.id
                                            ],
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </aside>
        </div>
    </SiteSettingsLayout>
</template>
