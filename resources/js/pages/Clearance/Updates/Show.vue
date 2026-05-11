<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    Users,
    Building2,
    AlertCircle,
    History,
    FileBarChart,
    ChevronLeft,
    CheckCircle,
    XCircle,
    Play,
    StopCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    update: any;
    can: any;
}>();

const activeTab = ref('overview');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clearance Updates', href: '/student-services/clearance/updates' },
            { title: 'Update Details', href: '#' },
        ],
    },
});

const publish = () => {
    if (confirm('Are you sure you want to publish this clearance update? Students will be able to apply once published.')) {
        router.post(`/student-services/clearance/updates/${props.update.id}/publish`);
    }
};

const closeUpdate = () => {
    if (confirm('Are you sure you want to close this clearance update? Students will no longer be able to apply.')) {
        router.post(`/student-services/clearance/updates/${props.update.id}/close`);
    }
};

const tabs = [
    { id: 'overview', name: 'Overview', icon: FileText },
    { id: 'offices', name: 'Participating Offices', icon: Building2 },
    { id: 'applications', name: 'Student Applications', icon: Users },
    { id: 'accountabilities', name: 'Accountabilities', icon: AlertCircle },
    { id: 'uploads', name: 'Upload History', icon: History },
    { id: 'reports', name: 'Reports', icon: FileBarChart },
];

const statusClass = (status: string) => {
    switch (status) {
        case 'published': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'closed': return 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400';
        default: return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }
};
const syncOffices = () => {
    router.post(`/student-services/clearance/updates/${props.update.id}/sync-offices`);
};
</script>

<template>
    <Head :title="update.title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/student-services/clearance/updates" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50">
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ update.title }}</h1>
                        <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', statusClass(update.status)]">
                            {{ update.status }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">{{ update.semester.academic_year }} - {{ update.semester.term }} | {{ update.type.name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button v-if="update.status === 'draft' && update.offices.length > 0 && can.publish" variant="outline" class="h-8 gap-1.5 border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100" @click="publish">
                    <Play class="h-3.5 w-3.5" />
                    Publish Update
                </Button>
                <Button v-if="update.status === 'published' && can.close" variant="outline" class="h-8 gap-1.5 border-amber-200 bg-amber-50 text-xs font-bold text-amber-700 hover:bg-amber-100" @click="closeUpdate">
                    <StopCircle class="h-3.5 w-3.5" />
                    Close Update
                </Button>
                <Button v-if="update.status === 'draft' && can.edit" variant="outline" class="h-8 text-xs font-bold">
                    Edit Details
                </Button>
            </div>
        </div>

        <div class="flex items-center gap-1 overflow-x-auto border-b border-slate-100 dark:border-white/10">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="['flex items-center gap-2 border-b-2 px-4 py-2 text-xs font-medium transition-colors', activeTab === tab.id ? 'border-emerald-500 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700']"
            >
                <component :is="tab.icon" class="h-3.5 w-3.5" />
                {{ tab.name }}
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div v-if="activeTab === 'overview'" class="grid gap-4 md:grid-cols-3">
                <div class="col-span-2 space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950">
                        <h3 class="mb-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Update Information</h3>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Description</p>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ update.description || 'No description provided.' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Purpose</p>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ update.purpose || 'No purpose specified.' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950">
                        <h3 class="mb-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Application Period</h3>
                        <div class="flex items-center gap-8">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <Calendar class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Start Date</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ update.start_date }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                    <Calendar class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">End Date</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ update.end_date }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950">
                        <h3 class="mb-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Statistics</h3>
                        <div class="grid gap-4">
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                                <div class="flex items-center gap-2">
                                    <Users class="h-4 w-4 text-slate-400" />
                                    <span class="text-xs text-slate-600">Total Applied</span>
                                </div>
                                <span class="text-sm font-bold">0</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50/50 p-3 dark:bg-emerald-500/5">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="h-4 w-4 text-emerald-500" />
                                    <span class="text-xs text-emerald-700">Cleared</span>
                                </div>
                                <span class="text-sm font-bold text-emerald-700">0</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-red-50/50 p-3 dark:bg-red-500/5">
                                <div class="flex items-center gap-2">
                                    <AlertCircle class="h-4 w-4 text-red-500" />
                                    <span class="text-xs text-red-700">With Accountability</span>
                                </div>
                                <span class="text-sm font-bold text-red-700">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'offices'" class="rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
                <div class="flex items-center justify-between border-b border-slate-100 p-4 dark:border-white/10">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Participating Offices</h3>
                    <Button v-if="update.status === 'draft'" variant="outline" size="sm" class="h-7 text-[10px] font-bold" @click="syncOffices">Assign All Offices</Button>
                </div>
                <div class="p-8 text-center" v-if="update.offices.length === 0">
                    <p class="text-xs text-slate-400">No participating offices added yet.</p>
                </div>
                <table v-else class="min-w-full divide-y divide-slate-100 dark:divide-white/10">
                    <thead class="bg-slate-50/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Office</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Required</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Upload Acc.</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase">Resolve Acc.</th>
                            <th class="px-4 py-3 text-right text-[10px] font-bold text-slate-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                        <tr v-for="off in update.offices" :key="off.id">
                            <td class="px-4 py-3 text-xs font-medium">{{ off.office.name }}</td>
                            <td class="px-4 py-3">
                                <CheckCircle v-if="off.is_required" class="h-4 w-4 text-emerald-500" />
                                <XCircle v-else class="h-4 w-4 text-slate-300" />
                            </td>
                            <td class="px-4 py-3">
                                <CheckCircle v-if="off.can_upload_accountability" class="h-4 w-4 text-emerald-500" />
                                <XCircle v-else class="h-4 w-4 text-slate-300" />
                            </td>
                            <td class="px-4 py-3">
                                <CheckCircle v-if="off.can_resolve_accountability" class="h-4 w-4 text-emerald-500" />
                                <XCircle v-else class="h-4 w-4 text-slate-300" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button variant="ghost" size="sm" class="h-7 w-7 p-0"><MoreHorizontal class="h-4 w-4" /></Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="activeTab === 'accountabilities'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Accountability Management</h3>
                    <div class="flex gap-2">
                        <Link :href="`/student-services/clearance/updates/${update.id}/accountabilities`" class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700">
                            <Plus class="h-3.5 w-3.5" />
                            Manage Accountabilities
                        </Link>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-12 text-center dark:border-white/10 dark:bg-slate-950">
                    <p class="text-sm text-slate-500">View and manage student accountabilities for this clearance period.</p>
                    <Link :href="`/student-services/clearance/updates/${update.id}/accountabilities`" class="mt-4 inline-flex text-xs font-bold text-emerald-600 hover:underline">View All Accountabilities →</Link>
                </div>
            </div>
        </div>
    </div>
</template>
