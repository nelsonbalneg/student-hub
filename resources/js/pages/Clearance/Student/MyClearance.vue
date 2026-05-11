<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    AlertCircle,
    ArrowRight,
    Download,
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

const statusColor = (s: string) => {
    switch (s) {
        case 'cleared': return 'text-emerald-600 bg-emerald-50';
        case 'not_cleared': return 'text-red-600 bg-red-50';
        case 'with_accountability': return 'text-amber-600 bg-amber-50';
        case 'pending_review': return 'text-blue-600 bg-blue-50';
        case 'completed': return 'text-indigo-600 bg-indigo-50';
        default: return 'text-slate-600 bg-slate-50';
    }
};
</script>

<template>
    <Head title="My Clearance" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">My Clearance</h1>
            <p class="text-sm text-slate-500">View and manage your semestral clearances.</p>
        </div>

        <div v-if="activeSemester" class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6 dark:border-emerald-500/20 dark:from-emerald-500/5 dark:to-slate-950">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <Calendar class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Current Active Semester</p>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ activeSemester.academic_year }} - {{ activeSemester.term }}</h2>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="update in activeUpdates" :key="update.id" class="flex flex-col rounded-xl border border-white bg-white/50 p-4 shadow-sm backdrop-blur-sm dark:border-white/10 dark:bg-white/5">
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ update.title }}</h3>
                        <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ update.description || 'Semester clearance update.' }}</p>
                        <div class="mt-3 flex items-center gap-2 text-[10px] font-medium text-slate-400">
                            <Clock class="h-3 w-3" />
                            Until {{ update.end_date }}
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-white/10">
                        <Button 
                            class="w-full h-8 gap-1.5 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700" 
                            @click="apply(update.id)"
                            :disabled="myClearances.some(c => c.clearance_update.id === update.id)"
                        >
                            {{ myClearances.some(c => c.clearance_update.id === update.id) ? 'Applied' : 'Apply Clearance' }}
                            <ArrowRight v-if="!myClearances.some(c => c.clearance_update.id === update.id)" class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                <div v-if="activeUpdates.length === 0" class="col-span-full py-8 text-center border-2 border-dashed border-slate-200 rounded-xl">
                    <p class="text-sm text-slate-400 font-medium">No active clearance updates found for this semester.</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Clearance History</h3>
            
            <div v-if="myClearances.length === 0" class="rounded-xl border border-slate-200 bg-white p-12 text-center dark:border-white/10 dark:bg-slate-950">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-white/5">
                    <FileText class="h-6 w-6" />
                </div>
                <p class="mt-4 text-sm font-medium text-slate-500">You haven't applied for any clearance yet.</p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="clr in myClearances" :key="clr.id" :href="`/student-services/clearance/my-clearance/${clr.id}`" class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-emerald-300 hover:shadow-md dark:border-white/10 dark:bg-slate-950">
                    <div class="mb-3 flex items-center justify-between">
                        <span :class="['rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider', statusColor(clr.status)]">
                            {{ clr.status.replace('_', ' ') }}
                        </span>
                        <p class="text-[10px] font-mono text-slate-400 group-hover:text-emerald-500">{{ clr.reference_no }}</p>
                    </div>
                    <h4 class="font-bold text-slate-900 dark:text-white group-hover:text-emerald-600">{{ clr.clearance_update.title }}</h4>
                    <p class="mt-1 text-xs text-slate-500">{{ clr.semester.academic_year }} - {{ clr.semester.term }}</p>
                    
                    <div class="mt-6 flex items-center justify-between border-t border-slate-50 pt-3 dark:border-white/5">
                        <div class="flex flex-col">
                            <p class="text-[9px] font-bold text-slate-400 uppercase">Applied On</p>
                            <p class="text-[10px] font-medium text-slate-600">{{ clr.applied_at_formatted || clr.applied_at }}</p>
                        </div>
                        <Download v-if="clr.status === 'cleared' || clr.status === 'completed'" class="h-4 w-4 text-emerald-500" />
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
