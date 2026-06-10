<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, RotateCcw, XCircle } from 'lucide-vue-next';

defineProps<{ applications: any; filters?: any; stats: any; title?: string }>();

const statusClass = (status: string) =>
    ({
        approved:
            'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        rejected: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        returned:
            'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        submitted:
            'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
        under_review:
            'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    })[status] ??
    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
</script>

<template>
    <Head :title="title ?? 'Accreditation Requests'" />

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
                        OSA Review Desk
                    </p>
                    <h1
                        class="text-xl font-black text-slate-950 dark:text-white"
                    >
                        {{ title ?? 'Accreditation Requests' }}
                    </h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/admin/societies/applications"
                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >All</Link
                    >
                    <Link
                        href="/admin/societies/pending-review"
                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >Pending</Link
                    >
                    <Link
                        href="/admin/societies/returned"
                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >Returned</Link
                    >
                    <Link
                        href="/admin/societies/requirements"
                        class="rounded-md bg-slate-950 px-3 py-2 text-xs font-black text-white uppercase dark:bg-sky-600"
                        >Checklist</Link
                    >
                </div>
            </div>

            <section class="grid gap-3 md:grid-cols-5">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <Clock class="size-5 text-sky-600" />
                    <p class="mt-3 text-xs font-black text-slate-500 uppercase">
                        Submitted
                    </p>
                    <p
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.submitted }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <Clock class="size-5 text-violet-600" />
                    <p class="mt-3 text-xs font-black text-slate-500 uppercase">
                        Review
                    </p>
                    <p
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.under_review }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <RotateCcw class="size-5 text-amber-600" />
                    <p class="mt-3 text-xs font-black text-slate-500 uppercase">
                        Returned
                    </p>
                    <p
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.returned }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <CheckCircle2 class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs font-black text-slate-500 uppercase">
                        Approved
                    </p>
                    <p
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.approved }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <XCircle class="size-5 text-red-600" />
                    <p class="mt-3 text-xs font-black text-slate-500 uppercase">
                        Rejected
                    </p>
                    <p
                        class="text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ stats.rejected }}
                    </p>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800"
                    >
                        <thead
                            class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                        >
                            <tr>
                                <th class="px-4 py-3 text-left">Request</th>
                                <th class="px-4 py-3 text-left">Society</th>
                                <th class="px-4 py-3 text-left">Term</th>
                                <th class="px-4 py-3 text-left">Checklist</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="application in applications.data"
                                :key="application.id"
                            >
                                <td
                                    class="px-4 py-3 font-mono text-xs font-bold text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        application.accreditation_request_no ??
                                        'Unassigned'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        application.society?.full_name ??
                                        application.society?.name
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                >
                                    {{ application.semester }}
                                    {{ application.school_year }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        application.submissions?.filter(
                                            (item: any) =>
                                                [
                                                    'complete',
                                                    'not_applicable',
                                                ].includes(item.status),
                                        ).length ?? 0
                                    }}
                                    / {{ application.submissions?.length ?? 0 }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-md px-2 py-1 text-xs font-black uppercase"
                                        :class="statusClass(application.status)"
                                        >{{ application.status }}</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="`/admin/societies/applications/${application.id}/review`"
                                        class="text-sm font-black text-sky-700 dark:text-sky-300"
                                        >Review</Link
                                    >
                                </td>
                            </tr>
                            <tr v-if="applications.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center font-semibold text-slate-500"
                                >
                                    No applications in this queue.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
