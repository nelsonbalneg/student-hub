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

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link href="/student-services/clearance/my-clearance" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50">
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-base font-bold text-slate-900 dark:text-white">{{ clearance.clearance_update?.title || 'Clearance Details' }}</h1>
                    <p class="text-xs text-slate-500">Ref: {{ clearance.reference_no }}</p>
                </div>
            </div>
            <Button v-if="clearance.status === 'cleared'" class="h-8 gap-1.5 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700">
                <Download class="h-3.5 w-3.5" />
                Download Certificate
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Status Card -->
                <div :class="['rounded-2xl border p-6 flex items-center justify-between', clearance.status === 'cleared' ? 'bg-emerald-50/50 border-emerald-100' : 'bg-amber-50/50 border-amber-100']">
                    <div class="flex items-center gap-4">
                        <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl', clearance.status === 'cleared' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600']">
                            <CheckCircle2 v-if="clearance.status === 'cleared'" class="h-6 w-6" />
                            <AlertCircle v-else class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Status</p>
                            <h2 :class="['text-lg font-bold', clearance.status === 'cleared' ? 'text-emerald-700' : 'text-amber-700']">
                                {{ clearance.status?.replace('_', ' ').toUpperCase() || 'UNKNOWN' }}
                            </h2>
                        </div>
                    </div>
                    <div v-if="clearance.cleared_at" class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Date Cleared</p>
                        <p class="text-sm font-bold text-slate-700">{{ clearance.cleared_at }}</p>
                    </div>
                </div>

                <!-- Accountabilities -->
                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 p-4 dark:border-white/10">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Active Accountabilities</h3>
                    </div>
                    <div v-if="!clearance.clearance_update?.accountabilities || clearance.clearance_update.accountabilities.length === 0" class="p-8 text-center">
                        <p class="text-sm text-slate-400 font-medium">No Accountabilities</p>
                    </div>
                    <div v-else class="divide-y divide-slate-50 dark:divide-white/5">
                        <div v-for="acc in clearance.clearance_update.accountabilities" :key="acc.id" class="p-4 flex items-start justify-between gap-4">
                            <div class="flex gap-3">
                                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">
                                    <AlertCircle class="h-4 w-4" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ acc.title }}</h4>
                                    <p class="text-[10px] text-slate-500 mt-0.5">{{ acc.description }}</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <div class="flex items-center gap-1 text-[9px] font-bold text-slate-400 uppercase">
                                            <Building2 class="h-3 w-3" />
                                            {{ acc.office?.name || 'Unknown Office' }}
                                        </div>
                                        <div v-if="acc.amount" class="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                                            ₱{{ acc.amount }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <span :class="['rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider', acc.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700']">
                                {{ acc.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Timeline</h3>
                    <div class="space-y-4">
                        <div v-for="log in clearance.logs" :key="log.id" class="relative pl-6 pb-4 border-l border-slate-100 last:border-0 last:pb-0 dark:border-white/10">
                            <div class="absolute -left-1.5 top-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-slate-950"></div>
                            <p class="text-[10px] font-bold text-slate-900 dark:text-white uppercase">{{ log.action?.replace('_', ' ') || 'ACTION' }}</p>
                            <p class="text-[10px] text-slate-500 mt-0.5">{{ log.remarks }}</p>
                            <p class="text-[9px] text-slate-400 mt-1">{{ log.created_at }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Required Offices</h3>
                    <div class="grid gap-3">
                        <div v-for="off in clearance.clearance_update?.offices" :key="off.id" class="flex items-center justify-between text-xs">
                            <span class="text-slate-600">{{ off.office?.name || 'Unknown Office' }}</span>
                            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
