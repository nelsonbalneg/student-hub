<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
    RefreshCw,
    Plus,
    Upload,
    MoreHorizontal,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Download,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    update: any;
    accountabilities: any;
    filters: any;
    offices: any;
}>();

const search = ref(props.filters.search ?? '');
const office_id = ref(props.filters.office_id ?? '');
const status = ref(props.filters.status ?? '');

const uploadModal = ref(false);
const uploadForm = useForm({
    file: null as File | null,
    office_id: '',
});

const applyFilters = () => {
    router.get(
        `/student-services/clearance/updates/${props.update.id}/accountabilities`,
        { search: search.value, office_id: office_id.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

let timeout: any;
watch([search, office_id, status], () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 400);
});

const resolve = (id: number) => {
    if (confirm('Are you sure you want to resolve this accountability?')) {
        router.post(`/student-services/clearance/accountabilities/${id}/resolve`);
    }
};

const waive = (id: number) => {
    if (confirm('Are you sure you want to waive this accountability?')) {
        router.post(`/student-services/clearance/accountabilities/${id}/waive`);
    }
};

const handleUpload = () => {
    uploadForm.post(`/student-services/clearance/updates/${props.update.id}/accountabilities/upload-preview`, {
        onSuccess: () => (uploadModal.value = false),
    });
};

const statusBadge = (s: string) => {
    switch (s) {
        case 'pending': return 'bg-amber-100 text-amber-700';
        case 'resolved': return 'bg-emerald-100 text-emerald-700';
        case 'waived': return 'bg-indigo-100 text-indigo-700';
        default: return 'bg-slate-100 text-slate-600';
    }
};
</script>

<template>
    <Head title="Manage Accountabilities" />

    <div class="flex h-full flex-1 flex-col gap-3 p-3">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link :href="`/student-services/clearance/updates/${update.id}`" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50">
                    <ChevronLeft class="h-4 w-4" />
                </Link>
                <div>
                    <h1 class="text-base font-bold text-slate-800 dark:text-white">Accountabilities</h1>
                    <p class="text-xs text-slate-400">{{ update.title }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button @click="uploadModal = true" class="h-8 gap-1.5 rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-700">
                    <Upload class="h-3.5 w-3.5" />
                    Bulk Upload
                </Button>
                <Button class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700">
                    <Plus class="h-3.5 w-3.5" />
                    Add Individual
                </Button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by student name or ID..."
                    class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs text-slate-900 focus:border-emerald-400 focus:outline-none"
                />
            </div>
            <select v-model="office_id" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs">
                <option value="">All Offices</option>
                <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.name }}</option>
            </select>
            <select v-model="status" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="waived">Waived</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Student</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Office</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Accountability</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="acc in accountabilities.data" :key="acc.id" class="hover:bg-slate-50/70">
                        <td class="px-4 py-3">
                            <p class="text-xs font-bold text-slate-900">{{ acc.student.name }}</p>
                            <p class="text-[10px] text-slate-400">{{ acc.student.student_no }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ acc.office.name }}</td>
                        <td class="px-4 py-3">
                            <p class="text-xs font-medium text-slate-700">{{ acc.title }}</p>
                            <p class="text-[10px] text-slate-400 truncate max-w-xs">{{ acc.description }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-600">
                            {{ acc.amount ? '₱' + acc.amount : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', statusBadge(acc.status)]">
                                {{ acc.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Button v-if="acc.status === 'pending'" size="sm" variant="ghost" class="h-7 px-2 text-[10px] font-bold text-emerald-600" @click="resolve(acc.id)">Resolve</Button>
                                <Button v-if="acc.status === 'pending'" size="sm" variant="ghost" class="h-7 px-2 text-[10px] font-bold text-indigo-600" @click="waive(acc.id)">Waive</Button>
                                <Button variant="ghost" size="sm" class="h-7 w-7 p-0"><MoreHorizontal class="h-4 w-4" /></Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="accountabilities.data.length === 0">
                        <td colspan="6" class="py-12 text-center text-slate-400">No accountabilities found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Upload Modal -->
    <div v-if="uploadModal" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="uploadModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h2 class="mb-4 text-sm font-bold text-slate-900">Bulk Upload Accountabilities</h2>
            <form class="grid gap-4" @submit.prevent="handleUpload">
                <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                    Select Office
                    <select v-model="uploadForm.office_id" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs">
                        <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                    CSV File
                    <input type="file" @input="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" class="text-xs" accept=".csv" />
                </label>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[10px] text-slate-500">CSV format: Student No/Email, Title, Description, Amount</p>
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="uploadModal = false">Cancel</Button>
                    <Button type="submit" class="bg-indigo-600 text-white" :disabled="uploadForm.processing">Upload & Preview</Button>
                </div>
            </form>
        </div>
    </div>
</template>
