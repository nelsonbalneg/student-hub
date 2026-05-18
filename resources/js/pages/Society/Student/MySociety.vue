<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, ClipboardCheck, FileText, Users } from 'lucide-vue-next';

defineProps<{ society?: any | null; requirements: any[] }>();
</script>

<template>
    <Head title="My Society" />

    <div class="min-h-screen bg-slate-50/50 px-6 py-6 dark:bg-slate-950 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-sky-600 dark:text-sky-400">Society Workspace</p>
                    <h1 class="text-xl font-black text-slate-950 dark:text-white">My Society</h1>
                </div>
                <Link v-if="!society" href="/societies/registration" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white dark:bg-sky-600">Create Profile</Link>
            </div>

            <div v-if="society" class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start gap-4">
                        <div class="flex size-12 items-center justify-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                            <Building2 class="size-6" />
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-950 dark:text-white">{{ society.full_name ?? society.name }}</h2>
                            <p class="text-sm font-semibold text-slate-500">{{ society.abbreviation ?? society.acronym }} · {{ society.college_unit ?? society.college }}</p>
                            <p class="mt-3 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{{ society.description ?? 'No description provided yet.' }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-2 gap-3">
                    <Link :href="`/societies/manage/${society.id}/accreditation`" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <ClipboardCheck class="size-5 text-emerald-600" />
                        <p class="mt-3 text-xs font-black uppercase tracking-wider text-slate-500">Application</p>
                        <p class="text-lg font-black text-slate-950 dark:text-white">{{ society.accreditation_requests?.length ?? 0 }}</p>
                    </Link>
                    <Link :href="`/societies/manage/${society.id}/officers`" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <Users class="size-5 text-sky-600" />
                        <p class="mt-3 text-xs font-black uppercase tracking-wider text-slate-500">Officers</p>
                        <p class="text-lg font-black text-slate-950 dark:text-white">{{ society.officers?.length ?? 0 }}</p>
                    </Link>
                    <Link :href="`/societies/manage/${society.id}/members-roster`" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <Users class="size-5 text-violet-600" />
                        <p class="mt-3 text-xs font-black uppercase tracking-wider text-slate-500">Members</p>
                        <p class="text-lg font-black text-slate-950 dark:text-white">{{ society.members?.length ?? 0 }}</p>
                    </Link>
                    <Link :href="`/societies/manage/${society.id}/bylaws`" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <FileText class="size-5 text-amber-600" />
                        <p class="mt-3 text-xs font-black uppercase tracking-wider text-slate-500">Requirements</p>
                        <p class="text-lg font-black text-slate-950 dark:text-white">{{ requirements.length }}</p>
                    </Link>
                </section>
            </div>

            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-black text-slate-950 dark:text-white">No society profile yet</h2>
                <p class="mt-2 text-sm text-slate-500">Create the organization profile first, then submit semestral accreditation.</p>
            </div>
        </div>
    </div>
</template>
