<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardList,
    AlertCircle,
    Calendar,
    Building2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    updates: any[];
    userOffice: string;
}>();

const statusColor = (s: string) => {
    switch (s) {
        case 'active':
            return 'text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20';
        case 'closed':
            return 'text-slate-600 bg-slate-50 border-slate-100 dark:bg-white/5 dark:text-slate-400 dark:border-white/10';
        case 'published':
            return 'text-blue-600 bg-blue-50 border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20';
        default:
            return 'text-slate-600 bg-slate-50 border-slate-100 dark:bg-white/5 dark:text-slate-400 dark:border-white/10';
    }
};

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <Head title="Accountabilities Center" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                Accountabilities Center
            </h1>
            <p class="text-sm text-slate-500">
                Manage student accountabilities for:
                <span class="font-bold text-emerald-600">{{ userOffice }}</span>
            </p>
        </div>

        <div
            v-if="updates.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-white/10 dark:bg-white/5"
        >
            <div
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-slate-900"
            >
                <AlertCircle class="h-8 w-8 text-slate-400" />
            </div>
            <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">
                No Participating Clearances
            </h3>
            <p class="mt-2 max-w-sm text-sm text-slate-500">
                Your office is currently not participating in any active or
                published clearance updates.
            </p>
        </div>

        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:border-white/10 dark:bg-white/5"
                    >
                        <th class="px-6 py-4">Clearance Update</th>
                        <th class="px-6 py-4">Semester</th>
                        <th class="px-6 py-4">End Date</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                    <tr
                        v-for="update in updates"
                        :key="update.id"
                        class="group transition-colors hover:bg-slate-50/50 dark:hover:bg-white/5"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10"
                                >
                                    <ClipboardList class="h-4.5 w-4.5" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ update.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 line-clamp-1 max-w-md text-[11px] text-slate-400"
                                    >
                                        {{
                                            update.description ||
                                            'No description'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <Calendar class="h-3.5 w-3.5 text-slate-400" />
                                <span
                                    class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >{{ update.semester.academic_year }} -
                                    {{ update.semester.term }}</span
                                >
                            </div>
                        </td>
                        <td
                            class="px-6 py-4 text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ formatDate(update.end_date) }}
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'rounded-full border px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase',
                                    statusColor(update.status),
                                ]"
                            >
                                {{ update.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link
                                :href="`/student-services/clearance/updates/${update.id}/accountabilities`"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-600 transition-all hover:bg-emerald-600 hover:text-white dark:bg-emerald-500/10 dark:hover:bg-emerald-600 dark:hover:text-white"
                            >
                                Manage
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
