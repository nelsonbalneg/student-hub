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

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">My Clearance</h1>
            <p class="text-sm text-slate-500">View and manage your semestral clearances.</p>
        </div>

        <div v-if="activeSemester" class="space-y-4">
            <div class="flex items-center gap-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <Calendar class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Current Semester</p>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ activeSemester.academic_year }} - {{ activeSemester.term }}</h2>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block rounded-xl border border-slate-200 bg-white overflow-hidden dark:border-white/10 dark:bg-slate-950">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:border-white/10 dark:bg-white/5">
                            <th class="px-4 py-3">Clearance Title</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Status / Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <template v-for="update in activeUpdates" :key="update.id">
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/5">
                                <td class="px-4 py-4">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">{{ update.title }}</p>
                                    <p class="text-[10px] text-slate-400 mt-1">End Date: {{ formatDate(update.end_date) }}</p>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-500 max-w-xs truncate">{{ update.description || 'No description' }}</td>
                                <td class="px-4 py-4">
                                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="flex flex-col gap-2">
                                        <div class="flex items-center justify-between gap-4">
                                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                                            </span>
                                            <div class="flex items-center gap-3">
                                                <button 
                                                    class="flex items-center gap-1 text-[10px] font-bold text-slate-400 hover:text-emerald-600 transition-colors"
                                                    @click="toggleOffices(update.id)"
                                                >
                                                    {{ expandedUpdates.includes(update.id) ? 'Hide Offices' : 'Show Offices' }}
                                                    <ChevronUp v-if="expandedUpdates.includes(update.id)" class="h-3 w-3" />
                                                    <ChevronDown v-else class="h-3 w-3" />
                                                </button>
                                                <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="text-[10px] font-bold text-emerald-600 hover:underline">
                                                    View Details
                                                </Link>
                                            </div>
                                        </div>
                                        
                                        <div v-if="expandedUpdates.includes(update.id)" class="mt-2 rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 overflow-hidden transition-all">
                                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-2">Office Status</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div v-for="off in update.offices" :key="off.id" class="flex items-center justify-between rounded bg-white p-1.5 border border-slate-100 dark:bg-slate-900 dark:border-white/5">
                                                    <span class="text-[9px] font-medium text-slate-600 truncate mr-2">{{ off.office.name }}</span>
                                                    <div v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared">
                                                        <Check class="h-3 w-3 text-emerald-500" />
                                                    </div>
                                                    <div v-else class="group relative">
                                                        <X class="h-3 w-3 text-red-500" />
                                                        <div class="invisible group-hover:visible absolute right-0 top-5 z-50 w-48 rounded-lg bg-slate-900 p-2 text-[9px] text-white shadow-xl">
                                                            <p class="font-bold border-b border-white/10 pb-1 mb-1">Accountabilities:</p>
                                                            <ul class="space-y-1">
                                                                <li v-for="acc in getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).accountabilities" :key="acc.id">
                                                                    • {{ acc.title }}
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <Button 
                                        v-else
                                        class="h-7 gap-1.5 bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700" 
                                        @click="apply(update.id)"
                                    >
                                        Apply Clearance
                                        <ArrowRight class="h-3 w-3" />
                                    </Button>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="activeUpdates.length === 0">
                            <td colspan="3" class="py-12 text-center text-xs text-slate-400 italic">No active clearance updates found for this semester.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="grid gap-4 md:hidden">
                <div v-for="update in activeUpdates" :key="update.id" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ update.title }}</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">End Date: {{ formatDate(update.end_date) }}</p>
                        </div>
                        <div v-if="myClearances.find(c => c.clearance_update.id === update.id)">
                            <span :class="['rounded-full px-2 py-0.5 text-[9px] font-bold uppercase', statusColor(myClearances.find(c => c.clearance_update.id === update.id).status)]">
                                {{ myClearances.find(c => c.clearance_update.id === update.id).status.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-xs text-slate-500 mb-4 line-clamp-2">{{ update.description || 'No description' }}</p>

                    <div v-if="myClearances.find(c => c.clearance_update.id === update.id)" class="space-y-3">
                        <div class="flex items-center justify-between border-t border-slate-50 pt-3 dark:border-white/5">
                            <button 
                                class="flex items-center gap-1 text-[10px] font-bold text-slate-400 hover:text-emerald-600"
                                @click="toggleOffices(update.id)"
                            >
                                {{ expandedUpdates.includes(update.id) ? 'Hide Offices' : 'Show Offices' }}
                                <ChevronDown v-if="!expandedUpdates.includes(update.id)" class="h-3 w-3" />
                                <ChevronUp v-else class="h-3 w-3" />
                            </button>
                            <Link :href="`/student-services/clearance/my-clearance/${myClearances.find(c => c.clearance_update.id === update.id).id}`" class="text-[10px] font-bold text-emerald-600">
                                View Details
                            </Link>
                        </div>

                        <div v-if="expandedUpdates.includes(update.id)" class="space-y-2 pt-2 border-t border-slate-50 dark:border-white/5">
                            <div v-for="off in update.offices" :key="off.id" class="flex items-center justify-between rounded-lg bg-slate-50 p-2 dark:bg-white/5">
                                <span class="text-[10px] font-medium text-slate-700 dark:text-slate-300">{{ off.office.name }}</span>
                                <div class="flex items-center gap-2">
                                    <div v-if="getOfficeStatus(myClearances.find(c => c.clearance_update.id === update.id), off.office.id).cleared">
                                        <span class="text-[9px] font-bold text-emerald-600">CLEARED</span>
                                        <Check class="h-3 w-3 text-emerald-500 inline ml-1" />
                                    </div>
                                    <div v-else class="text-right">
                                        <span class="text-[9px] font-bold text-red-500">PENDING</span>
                                        <X class="h-3 w-3 text-red-500 inline ml-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Button 
                        v-else
                        class="w-full h-8 gap-1.5 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700" 
                        @click="apply(update.id)"
                    >
                        Apply Clearance
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
