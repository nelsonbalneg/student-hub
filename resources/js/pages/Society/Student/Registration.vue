<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Users,
    FileText,
    ShieldCheck,
    CheckCircle2,
    Building2,
    ArrowRight,
} from 'lucide-vue-next';

const steps = [
    {
        id: 1,
        title: 'Basic Info',
        description: 'Society name & acronym',
        status: 'current',
    },
    {
        id: 2,
        title: 'Officers',
        description: 'Assign leadership roles',
        status: 'upcoming',
    },
    {
        id: 3,
        title: 'Constitution',
        description: 'Upload bylaws & rules',
        status: 'upcoming',
    },
    {
        id: 4,
        title: 'Review',
        description: 'Final submission',
        status: 'upcoming',
    },
];
</script>

<template>
    <Head title="Society Registration" />

    <div
        class="mx-0 min-h-screen w-full max-w-none space-y-8 bg-slate-50/30 px-6 py-8 lg:px-8"
    >
        <!-- Header Section -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-8 md:flex-row md:items-center dark:border-slate-800"
        >
            <div class="grid gap-1">
                <h1
                    class="text-base font-black tracking-tight text-slate-900 uppercase dark:text-white"
                >
                    Society Registration
                </h1>
                <p
                    class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                >
                    Initialize a new student organization
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex flex-col items-end">
                    <span
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >Process Status</span
                    >
                    <span
                        class="text-[10px] font-bold tracking-tight text-blue-600 uppercase dark:text-blue-400"
                        >Drafting Application</span
                    >
                </div>
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400"
                >
                    <Building2 class="h-5 w-5" />
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 gap-8 xl:grid-cols-4">
            <!-- Left Column: Steps -->
            <div class="space-y-4 xl:col-span-1">
                <div
                    v-for="step in steps"
                    :key="step.id"
                    class="rounded-2xl border p-4 transition-all"
                    :class="
                        step.status === 'current'
                            ? 'border-blue-200 bg-white shadow-sm dark:border-blue-800 dark:bg-slate-800'
                            : 'border-slate-100 bg-transparent opacity-60 dark:border-slate-800'
                    "
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-[11px] font-black"
                            :class="
                                step.status === 'current'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-100 text-slate-400 dark:bg-slate-800'
                            "
                        >
                            {{ step.id }}
                        </div>
                        <div>
                            <h3
                                class="text-[11px] font-black tracking-tight uppercase"
                                :class="
                                    step.status === 'current'
                                        ? 'text-slate-900 dark:text-white'
                                        : 'text-slate-400'
                                "
                            >
                                {{ step.title }}
                            </h3>
                            <p
                                class="text-[9px] font-bold tracking-tighter text-slate-400 uppercase"
                            >
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Form Container -->
            <div class="xl:col-span-3">
                <div
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div
                        class="border-b border-slate-100 p-8 dark:border-slate-700/50"
                    >
                        <h2
                            class="text-lg font-black tracking-tight text-slate-900 uppercase dark:text-white"
                        >
                            Basic Information
                        </h2>
                        <p
                            class="mt-1 text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                        >
                            Identify your organization in the registry
                        </p>
                    </div>

                    <div class="space-y-6 p-8">
                        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    class="ml-1 text-[9px] font-black tracking-[0.2em] text-slate-500 uppercase"
                                    >Official Name</label
                                >
                                <input
                                    type="text"
                                    placeholder="e.g. Society of Computer Scientists"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-[13px] font-semibold transition-all focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900/50"
                                />
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="ml-1 text-[9px] font-black tracking-[0.2em] text-slate-500 uppercase"
                                    >Acronym</label
                                >
                                <input
                                    type="text"
                                    placeholder="e.g. SOCS"
                                    class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-[13px] font-semibold transition-all focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900/50"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="ml-1 text-[9px] font-black tracking-[0.2em] text-slate-500 uppercase"
                                >Organization Description</label
                            >
                            <textarea
                                rows="4"
                                placeholder="Briefly describe the purpose and goals of your society..."
                                class="w-full resize-none rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-[13px] font-semibold transition-all focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900/50"
                            ></textarea>
                        </div>
                    </div>

                    <div
                        class="flex justify-end border-t border-slate-100 bg-slate-50/50 p-8 dark:border-slate-700/50 dark:bg-slate-900/20"
                    >
                        <button
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-8 py-3 text-[10px] font-black tracking-[0.2em] text-white uppercase shadow-lg shadow-blue-200 transition-all hover:bg-blue-700 dark:shadow-none"
                        >
                            Next Step
                            <ArrowRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
