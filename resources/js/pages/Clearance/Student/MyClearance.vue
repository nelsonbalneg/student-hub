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
    router.post(`/student-services/clearance/updates/${updateId}/apply`);
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
    const accs = clearance.clearance_update?.accountabilities;
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

    <div class="flex h-full flex-1 flex-col gap-6 p-6 bg-slate-50/30">
        <!-- Compact Enterprise Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="grid gap-0.5">
                <h1 class="text-base font-black tracking-tight text-slate-900 uppercase tracking-widest">Clearance Portal</h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">Academic Year & Term Monitor</p>
            </div>
            <div v-if="activeSemester" class="flex items-center gap-3 px-3 py-1.5 rounded-xl bg-white border border-slate-200 shadow-sm">
                <div class="h-8 w-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <Calendar class="h-4 w-4" />
                </div>
                <div class="grid gap-0">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none">Active Session</span>
                    <span class="text-[11px] font-black text-slate-700 uppercase tracking-tight">{{ activeSemester.academic_year }} • {{ activeSemester.term }}</span>
                </div>
            </div>
        </div>

        <div v-if="activeSemester" class="space-y-6">
            <!-- High-Density Desktop Monitor -->
            <div class="hidden md:block rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50 text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">
                            <th class="px-6 py-3">Clearance Specification</th>
                            <th class="px-6 py-3">Reference & Details</th>
                            <th class="px-6 py-3 text-right">Fulfillment Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <template v-for="update in activeUpdates" :key="update.id">
                            <tr class="group hover:bg-slate-50/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-sm">
                                            <FileText class="h-4 w-4" />
                                        </div>
                                        <div class="grid gap-0">
                                            <p class="text-xs font-black text-slate-900 tracking-tight">{{ update.title }}</p>
                                            <p class="text-[9px] font-mono text-indigo-500 font-bold uppercase tracking-widest">SEC-{{ update.id.toString().padStart(4, '0') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-[10px] font-bold text-slate-500">
                                    <p class="line-clamp-1 max-w-xs">{{ update.description || 'General Semestral Clearance' }}</p>
                                    <p class="text-[9px] text-slate-400 mt-0.5 uppercase tracking-tighter italic">Deadline: {{ formatDate(update.end_date) }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="flex items-center justify-end gap-4">
                                        <div class="flex flex-col items-end gap-1">
                                            <span :class="['rounded-full px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest border', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                                            </span>
                                            <button 
                                                class="text-[8px] font-black text-slate-400 hover:text-indigo-600 uppercase tracking-tighter"
                                                @click="toggleOffices(update.id)"
                                            >
                                                {{ expandedUpdates.includes(update.id) ? 'Hide Grid' : 'Review Nodes' }}
                                            </button>
                                        </div>
                                        <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="h-8 px-4 flex items-center justify-center rounded-lg bg-slate-900 text-white text-[10px] font-black hover:bg-indigo-600 transition-all shadow-lg shadow-slate-900/10">
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
                                <td colspan="3" class="px-6 py-0 bg-slate-50/50">
                                    <div class="py-4 border-t border-slate-100 grid grid-cols-4 lg:grid-cols-6 gap-2 animate-in fade-in slide-in-from-top-2 duration-300">
                                        <div v-for="off in update.offices" :key="off.id" class="p-2 rounded-xl bg-white border border-slate-200 flex items-center justify-between group/node hover:border-indigo-300 hover:shadow-md transition-all">
                                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-tighter truncate">{{ off.office.name }}</span>
                                            <div v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared">
                                                <CheckCircle2 class="h-3 w-3 text-emerald-500" />
                                            </div>
                                            <div v-else class="group relative flex items-center">
                                                <AlertCircle class="h-3 w-3 text-amber-500" />
                                                <div class="invisible group-hover:visible absolute right-0 top-6 z-50 w-48 rounded-xl bg-slate-900 p-3 text-[9px] text-white shadow-2xl border border-white/10">
                                                    <p class="font-black border-b border-white/5 pb-2 mb-2 uppercase tracking-widest text-amber-400">Blocked By:</p>
                                                    <ul class="space-y-1.5">
                                                        <li v-for="acc in getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).accountabilities" :key="acc.id" class="flex items-start gap-1.5 leading-tight">
                                                            <span class="text-amber-500">•</span>
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
                <div v-for="update in activeUpdates" :key="update.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="grid gap-0">
                            <h3 class="text-sm font-black text-slate-900 tracking-tight">{{ update.title }}</h3>
                            <p class="text-[9px] font-mono text-indigo-500 font-bold uppercase">{{ formatDate(update.end_date) }}</p>
                        </div>
                        <div v-if="myClearances.find(c => c.clearance_update.id === update.id)">
                            <span :class="['rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                    
                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="space-y-4">
                        <div class="flex items-center justify-between border-t border-slate-50 pt-4">
                            <button 
                                class="text-[9px] font-black text-slate-400 hover:text-indigo-600 uppercase tracking-widest"
                                @click="toggleOffices(update.id)"
                            >
                                {{ expandedUpdates.includes(update.id) ? 'Hide Nodes' : 'Review Nodes' }}
                            </button>
                            <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">
                                Manage Application
                            </Link>
                        </div>

                        <div v-if="expandedUpdates.includes(update.id)" class="grid grid-cols-2 gap-2 pt-2 animate-in fade-in slide-in-from-top-1">
                            <div v-for="off in update.offices" :key="off.id" class="flex items-center justify-between rounded-xl bg-slate-50 p-2 border border-slate-100">
                                <span class="text-[9px] font-black text-slate-600 uppercase tracking-tighter truncate">{{ off.office.name }}</span>
                                <CheckCircle2 v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared" class="h-3 w-3 text-emerald-500" />
                                <AlertCircle v-else class="h-3 w-3 text-amber-500" />
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
