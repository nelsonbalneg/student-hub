<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    CheckCircle,
    XCircle,
    AlertCircle,
    Save,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    update: any;
    office: any;
    results: any;
    filename: string;
}>();

const save = () => {
    router.post(
        `/student-services/clearance/updates/${props.update.id}/accountabilities/upload-save`,
        {
            data: props.results.data,
            office_id: props.office.id,
            filename: props.filename,
        },
    );
};
</script>

<template>
    <Head title="Upload Preview" />

    <div class="flex h-full flex-1 flex-col gap-3 p-3">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link
                    :href="`/student-services/clearance/updates/${update.id}/accountabilities`"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-base font-bold text-slate-800">
                        Upload Preview
                    </h1>
                    <p class="text-xs text-slate-400">
                        {{ filename }} for {{ office.name }}
                    </p>
                </div>
            </div>
            <Button
                @click="save"
                class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700"
            >
                <Save class="h-3.5 w-3.5" />
                Save Accountabilities
            </Button>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase">
                    Total Rows
                </p>
                <p class="text-xl font-bold text-slate-900">
                    {{ results.total }}
                </p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-[10px] font-bold text-emerald-400 uppercase">
                    Matched Students
                </p>
                <p class="text-xl font-bold text-emerald-700">
                    {{ results.matched }}
                </p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-[10px] font-bold text-red-400 uppercase">
                    Failed/Invalid
                </p>
                <p class="text-xl font-bold text-red-700">
                    {{ results.failed }}
                </p>
            </div>
        </div>

        <div
            class="flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white"
        >
            <div class="h-full overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Student Identifier
                            </th>
                            <th
                                class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Name/Info
                            </th>
                            <th
                                class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Title
                            </th>
                            <th
                                class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                            >
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="(row, idx) in results.data"
                            :key="idx"
                            :class="row.valid ? '' : 'bg-red-50/30'"
                        >
                            <td class="px-4 py-3">
                                <CheckCircle
                                    v-if="row.valid"
                                    class="h-4 w-4 text-emerald-500"
                                />
                                <AlertCircle
                                    v-else
                                    class="h-4 w-4 text-red-500"
                                />
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-slate-600"
                            >
                                {{ row.student_no || row.identifier }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <p
                                    v-if="row.valid"
                                    class="font-bold text-slate-900"
                                >
                                    {{ row.student_name }}
                                </p>
                                <p
                                    v-else
                                    class="font-medium text-red-600 italic"
                                >
                                    {{ row.error }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ row.title }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs text-slate-600"
                            >
                                {{ row.amount ? '₱' + row.amount : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
