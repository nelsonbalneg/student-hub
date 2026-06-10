<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ society: any; section: string }>();
</script>

<template>
    <Head :title="society.full_name ?? society.name" />

    <div
        class="min-h-screen bg-slate-50/50 px-6 py-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <div
                class="flex items-center justify-between border-b border-slate-200 pb-5 dark:border-slate-800"
            >
                <div>
                    <p
                        class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                    >
                        Society Details
                    </p>
                    <h1
                        class="text-xl font-black text-slate-950 dark:text-white"
                    >
                        {{ society.full_name ?? society.name }}
                    </h1>
                    <p class="text-sm font-semibold text-slate-500">
                        {{ society.abbreviation ?? society.acronym }} ·
                        {{ society.status }}
                    </p>
                </div>
                <Link
                    v-if="society.accreditation_requests?.[0]"
                    :href="`/admin/societies/applications/${society.accreditation_requests[0].id}/review`"
                    class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white dark:bg-sky-600"
                    >Review Latest</Link
                >
            </div>

            <section class="grid gap-3 md:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-black text-slate-500 uppercase">
                        Members
                    </p>
                    <p
                        class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{
                            society.members_count ??
                            society.members?.length ??
                            0
                        }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-black text-slate-500 uppercase">
                        Officers
                    </p>
                    <p
                        class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{
                            society.officers_count ??
                            society.officers?.length ??
                            0
                        }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-black text-slate-500 uppercase">
                        Advisers
                    </p>
                    <p
                        class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{
                            society.advisers_count ??
                            society.advisers?.length ??
                            0
                        }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs font-black text-slate-500 uppercase">
                        Applications
                    </p>
                    <p
                        class="mt-3 text-2xl font-black text-slate-950 dark:text-white"
                    >
                        {{ society.accreditation_requests?.length ?? 0 }}
                    </p>
                </div>
            </section>

            <section
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <h2
                    class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                >
                    Profile
                </h2>
                <p
                    class="mt-3 max-w-3xl text-sm text-slate-600 dark:text-slate-300"
                >
                    {{ society.description ?? 'No description provided.' }}
                </p>
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
                        Accreditation History
                    </h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link
                        v-for="application in society.accreditation_requests"
                        :key="application.id"
                        :href="`/admin/societies/applications/${application.id}/review`"
                        class="grid gap-2 px-5 py-4 md:grid-cols-[1fr_160px_140px]"
                    >
                        <p class="font-bold text-slate-950 dark:text-white">
                            {{
                                application.accreditation_request_no ??
                                'Unassigned'
                            }}
                        </p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            {{ application.semester }}
                            {{ application.school_year }}
                        </p>
                        <p class="text-sm font-black text-sky-700 uppercase">
                            {{ application.status }}
                        </p>
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
