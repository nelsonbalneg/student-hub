<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    FileText,
    Megaphone,
    Users,
} from 'lucide-vue-next';

const props = defineProps<{ society?: any | null; section: string }>();

const labels: Record<string, string> = {
    bylaws: 'Bylaws',
    announcements: 'Announcements',
    events: 'Events',
    attendance: 'Attendance',
};

const iconFor = {
    bylaws: FileText,
    announcements: Megaphone,
    events: CalendarDays,
    attendance: ClipboardList,
};
</script>

<template>
    <Head :title="labels[section] ?? 'Society Workspace'" />

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
                        {{
                            society?.full_name ??
                            society?.name ??
                            'Society Module'
                        }}
                    </p>
                    <h1
                        class="text-xl font-black text-slate-950 dark:text-white"
                    >
                        {{ labels[section] ?? section }}
                    </h1>
                </div>
                <Link
                    v-if="society"
                    :href="`/societies/manage/${society.id}/accreditation`"
                    class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                    >Application</Link
                >
            </div>

            <section class="grid gap-4 md:grid-cols-3">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <component
                        :is="iconFor[section as keyof typeof iconFor] ?? Users"
                        class="size-6 text-sky-600"
                    />
                    <h2
                        class="mt-4 text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                    >
                        Operational Workspace
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        This page is wired into the society lifecycle and ready
                        for detailed records, approvals, and exports.
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-black tracking-wider text-slate-500 uppercase"
                    >
                        Records
                    </p>
                    <p
                        class="mt-3 text-3xl font-black text-slate-950 dark:text-white"
                    >
                        {{ society?.[section]?.length ?? 0 }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="text-xs font-black tracking-wider text-slate-500 uppercase"
                    >
                        Accreditation Rule
                    </p>
                    <p
                        class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Records remain tied to their semester and school year
                        for historical review.
                    </p>
                </div>
            </section>

            <section
                class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <h2 class="text-base font-black text-slate-950 dark:text-white">
                    Detailed {{ labels[section] ?? section }} management
                </h2>
                <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-500">
                    The accreditation workflow, officer/adviser/member encoding,
                    requirement review, and printable summaries are active. This
                    area can now be extended with specialized create/edit forms
                    for this record type.
                </p>
            </section>
        </div>
    </div>
</template>
