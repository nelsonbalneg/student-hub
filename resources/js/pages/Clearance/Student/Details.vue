<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Download,
    FileText,
    Building2,
    History,
    Calendar,
    ArrowRight,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    clearance: any;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Clearance', href: '/student-services/clearance/my-clearance' },
            { title: 'Application Details', href: '#' },
        ],
    },
});

const statusColor = (s: string) => {
    switch (s) {
        case 'cleared': return 'text-emerald-600 bg-emerald-50';
        case 'not_cleared': return 'text-red-600 bg-red-50';
        case 'with_accountability': return 'text-amber-600 bg-amber-50';
        default: return 'text-slate-600 bg-slate-50';
    }
};
</script>

<template>
    <Head title="Clearance Details" />

    <div class="flex h-full flex-1 flex-col gap-8 p-8 bg-slate-50/50">
        <!-- Enterprise Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <Link href="/student-services/clearance/my-clearance" class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-400 hover:bg-slate-50 hover:text-indigo-600 transition-all shadow-sm">
                    <ChevronLeft class="h-5 w-5" />
                </Link>
                <div class="grid gap-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-black tracking-tight text-slate-900">{{ clearance.clearance_update?.title || 'Clearance Details' }}</h1>
                        <span class="text-[10px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded-lg border border-slate-200 uppercase tracking-widest">{{ clearance.semester.academic_year }}</span>
                    </div>
                    <p class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        Reference Number: <span class="text-indigo-500 font-black tracking-normal">{{ clearance.reference_no }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Button v-if="clearance.status === 'cleared'" class="h-11 gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 shadow-lg shadow-indigo-600/20">
                    <Download class="h-4 w-4" />
                    Download Official Certificate
                </Button>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-8 space-y-8">
                <!-- Advanced Status Monitor -->
                <div :class="['rounded-[32px] border p-8 flex flex-col md:flex-row items-center justify-between gap-8 transition-all', clearance.status === 'cleared' ? 'bg-white border-emerald-100 shadow-xl shadow-emerald-500/5' : 'bg-white border-amber-100 shadow-xl shadow-amber-500/5']">
                    <div class="flex items-center gap-6">
                        <div :class="['flex h-20 w-20 items-center justify-center rounded-[24px] shadow-lg', clearance.status === 'cleared' ? 'bg-emerald-500 text-white shadow-emerald-500/20' : 'bg-amber-500 text-white shadow-amber-500/20']">
                            <CheckCircle2 v-if="clearance.status === 'cleared'" class="h-10 w-10" />
                            <AlertCircle v-else class="h-10 w-10" />
                        </div>
                        <div class="grid gap-1.5">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Application Progress</p>
                            <h2 :class="['text-3xl font-black tracking-tight', clearance.status === 'cleared' ? 'text-emerald-600' : 'text-amber-600']">
                                {{ clearance.status?.replace('_', ' ').toUpperCase() || 'PENDING' }}
                            </h2>
                        </div>
                    </div>
                    <div class="flex gap-8 border-t md:border-t-0 md:border-l border-slate-100 pt-8 md:pt-0 md:pl-8 w-full md:w-auto">
                        <div v-if="clearance.cleared_at" class="grid gap-1 text-center md:text-left">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Verification Date</p>
                            <p class="text-sm font-black text-slate-700">{{ clearance.cleared_at }}</p>
                        </div>
                        <div class="grid gap-1 text-center md:text-left">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Remaining Balance</p>
                            <p class="text-sm font-black text-slate-900">₱0.00</p>
                        </div>
                    </div>
                </div>

                <!-- Structured Accountabilities -->
                <div class="rounded-[32px] border border-slate-200 bg-white overflow-hidden shadow-sm shadow-slate-200/50">
                    <div class="bg-slate-50/50 border-b border-slate-100 px-8 py-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Active Requirements</h3>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Live Status</span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="!clearance.clearance_update?.accountabilities || clearance.clearance_update.accountabilities.length === 0" class="py-24 text-center">
                        <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-6">
                            <CheckCircle2 class="h-8 w-8 text-slate-200" />
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">Completely Cleared</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-[200px] mx-auto">All office requirements have been fulfilled.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-100">
                        <div v-for="acc in (clearance.clearance_update.accountabilities?.data || clearance.clearance_update.accountabilities)" :key="acc.id" class="p-8 group hover:bg-slate-50/50 transition-colors">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
                                <div class="flex gap-6">
                                    <div class="mt-1 flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-500 shadow-sm border border-red-100 group-hover:scale-105 transition-transform">
                                        <AlertCircle class="h-7 w-7" />
                                    </div>
                                    <div class="grid gap-2">
                                        <h4 class="text-base font-black text-slate-900 tracking-tight">{{ acc.title }}</h4>
                                        <div class="flex flex-wrap items-center gap-4">
                                            <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                                <div class="h-1.5 w-1.5 rounded-full bg-indigo-500"></div>
                                                {{ acc.office?.name || 'Academic Office' }}
                                            </div>
                                            <div v-if="acc.amount" class="text-[10px] font-mono font-bold text-indigo-600 bg-indigo-50/50 px-2.5 py-1 rounded-lg border border-indigo-100">
                                                ₱{{ Number(acc.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <span :class="['rounded-full px-4 py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm border whitespace-nowrap self-start md:self-center', acc.status === 'pending' ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100']">
                                    {{ acc.status }}
                                </span>
                            </div>

                            <!-- Enterprise Sub-items Breakdown -->
                            <div v-if="(acc.children?.length > 0 || acc.children?.data?.length > 0)" class="mt-8 ml-20">
                                <div class="flex items-center gap-4 mb-6">
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-3 py-1 bg-slate-100 rounded-lg">Required Sub-items</span>
                                    <div class="h-px flex-1 bg-slate-100"></div>
                                </div>
                                <div class="grid gap-3">
                                    <div v-for="child in (acc.children?.data || acc.children)" :key="child.id" class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 bg-white shadow-sm hover:border-indigo-200 hover:shadow-indigo-500/5 transition-all">
                                        <div class="flex items-center gap-4">
                                            <div class="h-8 w-8 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400">
                                                {{ child.id }}
                                            </div>
                                            <div class="grid gap-0.5">
                                                <span class="text-xs font-black text-slate-800 tracking-tight">{{ child.title }}</span>
                                                <span class="text-[10px] text-slate-400 font-medium" v-if="child.description">{{ child.description }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span v-if="child.amount" class="text-[11px] font-mono font-bold text-slate-500">₱{{ Number(child.amount).toLocaleString() }}</span>
                                            <div :class="['h-2 w-2 rounded-full', child.status === 'pending' ? 'bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.5)]' : 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.5)]']"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="acc.description && !(acc.children?.length > 0 || acc.children?.data?.length > 0)" class="mt-6 ml-20 p-6 rounded-[24px] bg-slate-50/50 border border-slate-100 text-xs text-slate-500 italic leading-relaxed font-medium">
                                "{{ acc.description }}"
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Sidebar -->
            <div class="lg:col-span-4 space-y-8">
                <!-- Live Timeline -->
                <div class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-200/50">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Activity Log</h3>
                        <History class="h-4 w-4 text-slate-300" />
                    </div>
                    <div class="space-y-8 relative before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-slate-100">
                        <div v-for="log in clearance.logs" :key="log.id" class="relative pl-8">
                            <div class="absolute left-0 top-1 h-4 w-4 rounded-full border-4 border-white bg-indigo-500 shadow-md"></div>
                            <div class="grid gap-1">
                                <p class="text-[10px] font-black text-slate-900 uppercase tracking-widest">{{ log.action?.replace('_', ' ') || 'SYSTEM ACTION' }}</p>
                                <p class="text-[11px] text-slate-500 leading-relaxed">{{ log.remarks }}</p>
                                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-tighter">{{ log.created_at }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Office Verification Network -->
                <div class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-sm shadow-slate-200/50">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Office Verification</h3>
                        <Building2 class="h-4 w-4 text-slate-300" />
                    </div>
                    <div class="grid gap-4">
                        <div v-for="off in clearance.clearance_update?.offices" :key="off.id" class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 group transition-all hover:bg-white hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-500/5">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center shadow-sm">
                                    <Building2 class="h-4 w-4 text-slate-400 group-hover:text-emerald-500 transition-colors" />
                                </div>
                                <span class="text-xs font-black text-slate-700 tracking-tight">{{ off.office?.name || 'Academic Unit' }}</span>
                            </div>
                            <div class="h-6 w-6 rounded-full bg-emerald-500 flex items-center justify-center shadow-md shadow-emerald-500/20">
                                <CheckCircle2 class="h-3.5 w-3.5 text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
