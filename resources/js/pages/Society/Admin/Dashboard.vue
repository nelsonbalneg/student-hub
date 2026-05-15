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
    FileText
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

        <div class="w-full max-w-none mx-0 px-6 lg:px-8 py-8 space-y-8 bg-slate-50/30 min-h-screen">
            <!-- Header Section: High-Density Enterprise style -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-8">
                <div class="grid gap-1">
                    <h1 class="text-base font-black tracking-tight text-slate-900 dark:text-white uppercase">Society Dashboard</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Student Organization & Accreditation Monitor</p>
                </div>

                <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white dark:bg-slate-800 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <span>Admin</span>
                    <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                    <span>Society</span>
                    <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                    <span class="text-indigo-500">Overview</span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                <!-- Total Societies -->
                <div class="group bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-start justify-between">
                        <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center transition-transform group-hover:scale-110">
                            <Home class="w-5 h-5" />
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border border-slate-100 bg-slate-50 text-slate-500 dark:bg-white/5 dark:border-white/10 dark:text-slate-400">
                            Registry
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Total Societies</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-0.5">{{ stats.total_societies }}</h3>
                    </div>
                </div>

                <!-- Accredited -->
                <div class="group bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-start justify-between">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center transition-transform group-hover:scale-110">
                            <ClipboardCheck class="w-5 h-5" />
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border border-emerald-100 bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            Accredited
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Active Status</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-0.5">{{ stats.accredited_societies }}</h3>
                    </div>
                </div>

                <!-- Pending -->
                <div class="group bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-start justify-between">
                        <div class="h-10 w-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex items-center justify-center transition-transform group-hover:scale-110">
                            <Clock class="w-5 h-5" />
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border border-amber-100 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            Awaiting
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Pending Apps</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-0.5">{{ stats.pending_applications }}</h3>
                    </div>
                </div>

                <!-- Expired -->
                <div class="group bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-start justify-between">
                        <div class="h-10 w-10 rounded-xl bg-rose-50 dark:bg-rose-900/20 text-rose-600 dark:text-rose-400 flex items-center justify-center transition-transform group-hover:scale-110">
                            <AlertCircle class="w-5 h-5" />
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border border-red-100 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                            Expired
                        </span>
                    </div>
                    <div class="mt-4">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Overdue Renewal</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight mt-0.5">{{ stats.expired_accreditations }}</h3>
                    </div>
                </div>
            </div>

            <!-- Secondary Stats: Desktop Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Total Members -->
                <div class="flex items-center gap-6 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="h-14 w-14 rounded-2xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shadow-inner">
                        <Users class="w-7 h-7" />
                    </div>
                    <div class="grid gap-0">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Total Membership</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ stats.total_members }}</h3>
                            <span class="text-[9px] font-black text-emerald-500 uppercase tracking-tighter bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded-lg border border-emerald-100 dark:border-emerald-500/20">+12%</span>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="flex items-center gap-6 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="h-14 w-14 rounded-2xl bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shadow-inner">
                        <Calendar class="w-7 h-7" />
                    </div>
                    <div class="grid gap-0">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Active Calendar</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ stats.upcoming_events }}</h3>
                    </div>
                </div>

                <!-- Pending Events -->
                <div class="flex items-center gap-6 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="h-14 w-14 rounded-2xl bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 flex items-center justify-center shadow-inner">
                        <FileText class="w-7 h-7" />
                    </div>
                    <div class="grid gap-0">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Pending Events</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ stats.pending_events }}</h3>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                <div class="group relative bg-indigo-600 p-8 rounded-3xl text-white shadow-xl shadow-indigo-200 dark:shadow-none overflow-hidden transition-all hover:scale-[1.01]">
                    <div class="absolute -right-10 -top-10 h-40 w-40 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all"></div>
                    <h2 class="text-xl font-black mb-1 uppercase tracking-tight">Review Applications</h2>
                    <p class="text-[11px] font-bold text-indigo-100 mb-6 uppercase tracking-widest opacity-80">Pending OSA evaluation: {{ stats.pending_applications }} items</p>
                    <Link :href="societies.applications.index().url" class="inline-flex items-center justify-center px-8 py-3 bg-white text-indigo-600 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-indigo-50 transition-colors shadow-lg">
                        Execute Review
                    </Link>
                </div>

                <div class="group relative bg-emerald-600 p-8 rounded-3xl text-white shadow-xl shadow-emerald-200 dark:shadow-none overflow-hidden transition-all hover:scale-[1.01]">
                    <div class="absolute -right-10 -top-10 h-40 w-40 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all"></div>
                    <h2 class="text-xl font-black mb-1 uppercase tracking-tight">Accredited Societies</h2>
                    <p class="text-[11px] font-bold text-emerald-100 mb-6 uppercase tracking-widest opacity-80">Database population: {{ stats.accredited_societies }} entities</p>
                    <Link :href="societies.accredited.index().url" class="inline-flex items-center justify-center px-8 py-3 bg-white text-emerald-600 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-emerald-50 transition-colors shadow-lg">
                        Manage Registry
                    </Link>
                </div>
            </div>
        </div>

</template>
