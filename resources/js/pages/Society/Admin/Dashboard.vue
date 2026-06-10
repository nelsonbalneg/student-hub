<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import societies from '@/routes/admin/societies';
import { dashboard as mainDashboard } from '@/routes';
import {
    Users,
    Home,
    ClipboardCheck,
    Calendar,
    AlertCircle,
    Clock,
    FileText,
} from 'lucide-vue-next';

interface Props {
    stats: {
        total_societies: number;
        accredited_societies: number;
        pending_applications: number;
        expired_accreditations: number;
        total_members: number;
        upcoming_events: number;
        pending_events: number;
    };
}

const props = defineProps<Props>();
</script>

<template>
    <Head title="Society Dashboard" />

    <div
        class="mx-0 min-h-screen w-full max-w-none space-y-8 bg-slate-50/30 px-6 py-8 lg:px-8"
    >
        <!-- Header Section: High-Density Enterprise style -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-8 md:flex-row md:items-center dark:border-slate-800"
        >
            <div class="grid gap-1">
                <h1
                    class="text-base font-black tracking-tight text-slate-900 uppercase dark:text-white"
                >
                    Society Dashboard
                </h1>
                <p
                    class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                >
                    Student Organization & Accreditation Monitor
                </p>
            </div>

            <div
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-[10px] font-black tracking-widest text-slate-400 uppercase shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <span>Admin</span>
                <span
                    class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-700"
                ></span>
                <span>Society</span>
                <span
                    class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-700"
                ></span>
                <span class="text-indigo-500">Overview</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            <!-- Total Societies -->
            <div
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition-transform group-hover:scale-110 dark:bg-indigo-900/20 dark:text-indigo-400"
                    >
                        <Home class="h-5 w-5" />
                    </div>
                    <span
                        class="rounded-full border border-slate-100 bg-slate-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                    >
                        Registry
                    </span>
                </div>
                <div class="mt-4">
                    <p
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Total Societies
                    </p>
                    <h3
                        class="mt-0.5 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.total_societies }}
                    </h3>
                </div>
            </div>

            <!-- Accredited -->
            <div
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-transform group-hover:scale-110 dark:bg-emerald-900/20 dark:text-emerald-400"
                    >
                        <ClipboardCheck class="h-5 w-5" />
                    </div>
                    <span
                        class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-emerald-600 uppercase dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        Accredited
                    </span>
                </div>
                <div class="mt-4">
                    <p
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Active Status
                    </p>
                    <h3
                        class="mt-0.5 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.accredited_societies }}
                    </h3>
                </div>
            </div>

            <!-- Pending -->
            <div
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-transform group-hover:scale-110 dark:bg-amber-900/20 dark:text-amber-400"
                    >
                        <Clock class="h-5 w-5" />
                    </div>
                    <span
                        class="rounded-full border border-amber-100 bg-amber-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-amber-600 uppercase dark:bg-amber-500/10 dark:text-amber-400"
                    >
                        Awaiting
                    </span>
                </div>
                <div class="mt-4">
                    <p
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Pending Apps
                    </p>
                    <h3
                        class="mt-0.5 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.pending_applications }}
                    </h3>
                </div>
            </div>

            <!-- Expired -->
            <div
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-transform group-hover:scale-110 dark:bg-rose-900/20 dark:text-rose-400"
                    >
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <span
                        class="rounded-full border border-red-100 bg-red-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-red-600 uppercase dark:bg-red-500/10 dark:text-red-400"
                    >
                        Expired
                    </span>
                </div>
                <div class="mt-4">
                    <p
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                    >
                        Overdue Renewal
                    </p>
                    <h3
                        class="mt-0.5 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.expired_accreditations }}
                    </h3>
                </div>
            </div>
        </div>

        <!-- Secondary Stats: Desktop Grid -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <!-- Total Members -->
            <div
                class="flex items-center gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 shadow-inner dark:bg-blue-900/20 dark:text-blue-400"
                >
                    <Users class="h-7 w-7" />
                </div>
                <div class="grid gap-0">
                    <p
                        class="mb-1 text-[9px] leading-none font-black tracking-widest text-slate-400 uppercase"
                    >
                        Total Membership
                    </p>
                    <div class="flex items-baseline gap-2">
                        <h3
                            class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ stats.total_members }}
                        </h3>
                        <span
                            class="rounded-lg border border-emerald-100 bg-emerald-50 px-1.5 py-0.5 text-[9px] font-black tracking-tighter text-emerald-500 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10"
                            >+12%</span
                        >
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div
                class="flex items-center gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-50 text-purple-600 shadow-inner dark:bg-purple-900/20 dark:text-purple-400"
                >
                    <Calendar class="h-7 w-7" />
                </div>
                <div class="grid gap-0">
                    <p
                        class="mb-1 text-[9px] leading-none font-black tracking-widest text-slate-400 uppercase"
                    >
                        Active Calendar
                    </p>
                    <h3
                        class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.upcoming_events }}
                    </h3>
                </div>
            </div>

            <!-- Pending Events -->
            <div
                class="flex items-center gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 shadow-inner dark:bg-orange-900/20 dark:text-orange-400"
                >
                    <FileText class="h-7 w-7" />
                </div>
                <div class="grid gap-0">
                    <p
                        class="mb-1 text-[9px] leading-none font-black tracking-widest text-slate-400 uppercase"
                    >
                        Pending Events
                    </p>
                    <h3
                        class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ stats.pending_events }}
                    </h3>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 gap-8 md:grid-cols-2">
            <div
                class="group relative overflow-hidden rounded-3xl bg-indigo-600 p-8 text-white shadow-xl shadow-indigo-200 transition-all hover:scale-[1.01] dark:shadow-none"
            >
                <div
                    class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl transition-all group-hover:bg-white/20"
                ></div>
                <h2 class="mb-1 text-xl font-black tracking-tight uppercase">
                    Review Applications
                </h2>
                <p
                    class="mb-6 text-[11px] font-bold tracking-widest text-indigo-100 uppercase opacity-80"
                >
                    Pending OSA evaluation:
                    {{ stats.pending_applications }} items
                </p>
                <Link
                    :href="societies.applications.index().url"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-8 py-3 text-[10px] font-black tracking-[0.2em] text-indigo-600 uppercase shadow-lg transition-colors hover:bg-indigo-50"
                >
                    Execute Review
                </Link>
            </div>

            <div
                class="group relative overflow-hidden rounded-3xl bg-emerald-600 p-8 text-white shadow-xl shadow-emerald-200 transition-all hover:scale-[1.01] dark:shadow-none"
            >
                <div
                    class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-3xl transition-all group-hover:bg-white/20"
                ></div>
                <h2 class="mb-1 text-xl font-black tracking-tight uppercase">
                    Accredited Societies
                </h2>
                <p
                    class="mb-6 text-[11px] font-bold tracking-widest text-emerald-100 uppercase opacity-80"
                >
                    Database population:
                    {{ stats.accredited_societies }} entities
                </p>
                <Link
                    :href="societies.accredited.index().url"
                    class="inline-flex items-center justify-center rounded-xl bg-white px-8 py-3 text-[10px] font-black tracking-[0.2em] text-emerald-600 uppercase shadow-lg transition-colors hover:bg-emerald-50"
                >
                    Manage Registry
                </Link>
            </div>
        </div>
    </div>
</template>
