<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    AlertCircle,
    ArrowRight,
    Download,
    Check,
    X,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    activeSemester: any;
    activeUpdates: any[];
    myClearances: any[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student Services', href: '#' },
            { title: 'My Clearance', href: '/student-services/clearance/my-clearance' },
        ],
    },
});

const apply = (updateId: number) => {
    router.post(`/student-services/clearance/updates/${updateId}/apply`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const expandedUpdates = ref<number[]>([]);

const toggleOffices = (id: number) => {
    if (expandedUpdates.value.includes(id)) {
        expandedUpdates.value = expandedUpdates.value.filter(i => i !== id);
    } else {
        expandedUpdates.value.push(id);
    }
};

const statusColor = (s: string) => {
    switch (s) {
        case 'cleared': return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'not_cleared': return 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400';
        case 'with_accountability': return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400';
        case 'pending_review': return 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400';
        case 'completed': return 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400';
        default: return 'text-slate-600 bg-slate-50 dark:bg-white/5 dark:text-slate-400';
    }
};

const getOfficeStatus = (clearance: any, officeId: number) => {
    let accs = clearance.clearance_update?.accountabilities;
    if (accs && !Array.isArray(accs) && accs.data) {
        accs = accs.data;
    }
    
    if (!accs || !Array.isArray(accs)) return { cleared: true };
    
    const accountabilities = accs.filter(
        (acc: any) => acc.office?.id === officeId && acc.status === 'pending'
    );
    
    if (accountabilities.length === 0) return { cleared: true };
    return { cleared: false, accountabilities };
};

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="My Clearance" />

    <div class="flex h-full flex-1 flex-col gap-6 bg-slate-50/30 p-6 dark:bg-slate-950">
        <!-- Compact Enterprise Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="grid gap-0.5">
                <h1 class="text-base font-black uppercase tracking-widest text-slate-900 dark:text-slate-100">Clearance Portal</h1>
                <p class="text-[10px] font-bold uppercase tracking-normal text-slate-400 dark:text-slate-500">Academic Year & Term Monitor</p>
            </div>
            <div v-if="activeSemester" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                    <Calendar class="h-4 w-4" />
                </div>
                <div class="grid gap-0">
                    <span class="text-[8px] font-black uppercase leading-none tracking-widest text-slate-400 dark:text-slate-500">Active Session</span>
                    <span class="text-[11px] font-black uppercase tracking-normal text-slate-700 dark:text-slate-200">{{ activeSemester.academic_year }} • {{ activeSemester.term }}</span>
                </div>
            </div>
        </div>

        <div v-if="activeSemester" class="space-y-6">
            <!-- High-Density Desktop Monitor -->
            <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900 md:block">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
                            <th class="px-6 py-3">Clearance Specification</th>
                            <th class="px-6 py-3">Reference & Details</th>
                            <th class="px-6 py-3 text-right">Fulfillment Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/10">
                        <template v-for="update in activeUpdates" :key="update.id">
                            <tr class="group transition-colors hover:bg-slate-50/30 dark:hover:bg-white/5">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 shadow-sm transition-all group-hover:bg-indigo-600 group-hover:text-white dark:bg-white/5">
                                            <FileText class="h-4 w-4" />
                                        </div>
                                        <div class="grid gap-0">
                                            <p class="text-xs font-black tracking-normal text-slate-900 dark:text-slate-100">{{ update.title }}</p>
                                            <p class="font-mono text-[9px] font-bold uppercase tracking-widest text-indigo-500 dark:text-indigo-300">SEC-{{ update.id.toString().padStart(4, '0') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                    <p class="line-clamp-1 max-w-xs">{{ update.description || 'General Semestral Clearance' }}</p>
                                    <p class="mt-0.5 text-[9px] uppercase tracking-normal text-slate-400 italic dark:text-slate-500">Deadline: {{ formatDate(update.end_date) }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="flex items-center justify-end gap-4">
                                        <div class="flex flex-col items-end gap-1">
                                            <span :class="['rounded-full px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest border', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                                            </span>
                                            <button 
                                                class="text-[8px] font-black uppercase tracking-normal text-slate-400 hover:text-indigo-600 dark:text-slate-500 dark:hover:text-indigo-300"
                                                @click="toggleOffices(update.id)"
                                            >
                                                {{ expandedUpdates.includes(update.id) ? 'Hide Grid' : 'Review Nodes' }}
                                            </button>
                                        </div>
                                        <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="flex h-8 items-center justify-center rounded-lg bg-slate-900 px-4 text-[10px] font-black text-white shadow-lg shadow-slate-900/10 transition-all hover:bg-indigo-600 dark:bg-white/10 dark:hover:bg-indigo-600">
                                            Manage
                                        </Link>
                                    </div>
                                    <div v-else class="flex justify-end">
                                        <Button 
                                            class="h-8 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black px-5 rounded-lg shadow-lg shadow-emerald-600/10" 
                                            @click="apply(update.id)"
                                        >
                                            Initiate Application
                                            <ArrowRight class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expandedUpdates.includes(update.id)">
                                <td colspan="3" class="bg-slate-50/50 px-6 py-0 dark:bg-white/[0.03]">
                                    <div class="grid grid-cols-4 gap-2 border-t border-slate-100 py-4 animate-in fade-in slide-in-from-top-2 duration-300 dark:border-white/10 lg:grid-cols-6">
                                        <div v-for="off in update.offices" :key="off.id" class="group/node flex items-center justify-between rounded-xl border border-slate-200 bg-white p-2 transition-all hover:border-indigo-300 hover:shadow-md dark:border-white/10 dark:bg-slate-950 dark:hover:border-indigo-400/50">
                                            <span class="truncate text-[9px] font-black uppercase tracking-normal text-slate-500 dark:text-slate-300">{{ off.office.name }}</span>
                                            <div v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared">
                                                <CheckCircle2 class="h-3 w-3 text-emerald-500" />
                                            </div>
                                            <div v-else class="group relative flex items-center">
                                                <X class="h-3 w-3 text-red-500" />
                                                <div class="invisible group-hover:visible absolute right-0 top-6 z-50 w-48 rounded-xl bg-slate-900 p-3 text-[9px] text-white shadow-2xl border border-white/10">
                                                    <p class="font-black border-b border-white/5 pb-2 mb-2 uppercase tracking-widest text-red-400">Tagged Accountability:</p>
                                                    <ul class="space-y-1.5">
                                                        <li v-for="acc in getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).accountabilities" :key="acc.id" class="flex items-start gap-1.5 leading-tight">
                                                            <span class="text-red-500">•</span>
                                                            {{ acc.title }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Compact Mobile Layout -->
            <div class="grid gap-4 md:hidden">
                <div v-for="update in activeUpdates" :key="update.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="grid gap-0">
                            <h3 class="text-sm font-black tracking-normal text-slate-900 dark:text-slate-100">{{ update.title }}</h3>
                            <p class="font-mono text-[9px] font-bold uppercase text-indigo-500 dark:text-indigo-300">{{ formatDate(update.end_date) }}</p>
                        </div>
                        <div v-if="myClearances.find(c => c.clearance_update.id === update.id)">
                            <span :class="['rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                    
                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="space-y-4">
                        <div class="flex items-center justify-between border-t border-slate-50 pt-4 dark:border-white/10">
                            <button 
                                class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-indigo-600 dark:text-slate-500 dark:hover:text-indigo-300"
                                @click="toggleOffices(update.id)"
                            >
                                {{ expandedUpdates.includes(update.id) ? 'Hide Nodes' : 'Review Nodes' }}
                            </button>
                            <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">
                                Manage Application
                            </Link>
                        </div>

                        <div v-if="expandedUpdates.includes(update.id)" class="grid grid-cols-2 gap-2 pt-2 animate-in fade-in slide-in-from-top-1">
                            <div v-for="off in update.offices" :key="off.id" class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2 dark:border-white/10 dark:bg-white/5">
                                <span class="truncate text-[9px] font-black uppercase tracking-normal text-slate-600 dark:text-slate-300">{{ off.office.name }}</span>
                                <CheckCircle2 v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared" class="h-3 w-3 text-emerald-500" />
                                <X v-else class="h-3 w-3 text-red-500" />
                            </div>
                        </div>
                    </div>
                    <Button 
                        v-else
                        class="w-full h-9 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase shadow-lg shadow-emerald-600/10" 
                        @click="apply(update.id)"
                    >
                        Initiate Application
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
