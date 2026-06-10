<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    application: any;
    requirements: any[];
    stats: any;
}>();

const reviewForm = useForm({ status: 'under_review', remarks: '' });
const requirementForms = new Map<number, ReturnType<typeof useForm>>();

const formFor = (submission: any) => {
    if (!requirementForms.has(submission.id)) {
        requirementForms.set(
            submission.id,
            useForm({
                status: submission.status,
                remarks: submission.remarks ?? '',
            }),
        );
    }

    return requirementForms.get(submission.id)!;
};

const updateApplication = (status: string) => {
    reviewForm.status = status;
    reviewForm.patch(
        `/admin/societies/applications/${props.application.id}/review`,
    );
};

const updateRequirement = (submission: any) => {
    formFor(submission).patch(
        `/admin/societies/applications/${props.application.id}/requirements/${submission.id}`,
    );
};
</script>

<template>
    <Head title="Review Accreditation" />

    <div
        class="min-h-screen bg-slate-50/50 px-6 py-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <div
                class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-end dark:border-slate-800"
            >
                <div>
                    <p
                        class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                    >
                        {{
                            application.accreditation_request_no ??
                            'OSA request pending'
                        }}
                    </p>
                    <h1
                        class="text-xl font-black text-slate-950 dark:text-white"
                    >
                        {{
                            application.society?.full_name ??
                            application.society?.name
                        }}
                    </h1>
                    <p class="text-sm font-semibold text-slate-500">
                        {{ application.semester }} ·
                        {{ application.school_year }} · {{ application.status }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="`/admin/societies/applications/${application.id}/print/summary`"
                        class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                        >Print</Link
                    >
                    <button
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-black text-white"
                        @click="updateApplication('approved')"
                    >
                        Approve
                    </button>
                    <button
                        class="rounded-md bg-amber-500 px-4 py-2 text-sm font-black text-white"
                        @click="updateApplication('returned')"
                    >
                        Return
                    </button>
                    <button
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-black text-white"
                        @click="updateApplication('rejected')"
                    >
                        Reject
                    </button>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
                <main class="space-y-4">
                    <section class="grid gap-3 md:grid-cols-4">
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Completion
                            </p>
                            <p
                                class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                            >
                                {{ application.completion_percentage }}%
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Officers
                            </p>
                            <p
                                class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                            >
                                {{ stats.officers }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Advisers
                            </p>
                            <p
                                class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                            >
                                {{ stats.advisers }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p
                                class="text-xs font-black text-slate-500 uppercase"
                            >
                                Members
                            </p>
                            <p
                                class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                            >
                                {{ stats.members }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                        >
                            <h2
                                class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                            >
                                Requirement Review
                            </h2>
                        </div>
                        <div
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <form
                                v-for="submission in application.submissions"
                                :key="submission.id"
                                class="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_180px_auto]"
                                @submit.prevent="updateRequirement(submission)"
                            >
                                <div>
                                    <p
                                        class="text-sm font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ submission.requirement?.name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            submission.original_file_name ??
                                            'No upload'
                                        }}
                                    </p>
                                </div>
                                <select
                                    v-model="formFor(submission).status"
                                    class="rounded-md border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="complete">Complete</option>
                                    <option value="incomplete">
                                        Incomplete
                                    </option>
                                    <option value="returned">Returned</option>
                                    <option value="not_applicable">
                                        Not applicable
                                    </option>
                                </select>
                                <button
                                    class="rounded-md bg-slate-950 px-4 py-2 text-sm font-black text-white dark:bg-sky-600"
                                >
                                    Save
                                </button>
                            </form>
                        </div>
                    </section>
                </main>

                <aside class="space-y-4">
                    <form
                        class="sticky top-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        @submit.prevent="updateApplication(reviewForm.status)"
                    >
                        <h2
                            class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                        >
                            Review Remarks
                        </h2>
                        <textarea
                            v-model="reviewForm.remarks"
                            rows="5"
                            class="mt-3 w-full rounded-md border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            placeholder="Remarks for the society"
                        />
                        <button
                            class="mt-3 w-full rounded-md border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 uppercase dark:border-slate-700 dark:text-slate-200"
                        >
                            Mark Under Review
                        </button>
                    </form>

                    <section
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2
                            class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                        >
                            Timeline
                        </h2>
                        <div class="mt-4 space-y-4">
                            <div
                                v-for="log in application.logs"
                                :key="log.id"
                                class="border-l-2 border-sky-200 pl-3 dark:border-sky-900"
                            >
                                <p
                                    class="text-sm font-black text-slate-900 dark:text-white"
                                >
                                    {{ log.action }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ log.remarks }}
                                </p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>
