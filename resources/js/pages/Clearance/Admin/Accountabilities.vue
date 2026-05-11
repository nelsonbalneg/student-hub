<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    Trash2,
    RotateCcw,
    Eye,
    Pencil,
    AlertTriangle,
    Loader2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    update: any;
    accountabilities: any;
    filters: any;
    offices: any;
}>();

const page = usePage();

const search = ref(props.filters.search ?? '');
const office_id = ref(props.filters.office_id ?? '');
const status = ref(props.filters.status ?? '');

const uploadModal = ref(false);
const uploadForm = useForm({
    file: null as File | null,
    office_id: '',
});

const individualModal = ref(false);
const individualForm = useForm({
    student_id: '',
    office_id: (page.props.auth as any).user.office_id ?? props.filters.office_id ?? '',
    group_title: '',
    items: [
        { title: '', description: '', amount: '' }
    ],
});

const detailsModal = ref(false);
const selectedAcc = ref<any>(null);
const confirmationModal = ref({
    show: false,
    title: '',
    message: '',
    action: () => {},
    loading: false
});

const editModal = ref(false);
const editForm = useForm({
    id: '',
    title: '',
    description: '',
    amount: '',
});

const studentSearch = ref('');
const studentsList = ref<any[]>([]);
const isSearching = ref(false);

const searchStudents = async () => {
    if (studentSearch.value.length < 2) {
        studentsList.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const response = await fetch(`/student-services/clearance/accountabilities/students?search=${studentSearch.value}`);
        studentsList.value = await response.json();
    } catch (e) {
        console.error(e);
    } finally {
        isSearching.value = false;
    }
};

let searchTimeout: any;
watch(studentSearch, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchStudents, 400);
});

const selectStudent = (student: any) => {
    individualForm.student_id = student.id;
    studentSearch.value = `${student.name} (${student.student_no})`;
    studentsList.value = [];
};

const addItem = () => {
    individualForm.items.push({ title: '', description: '', amount: '' });
};

const removeItem = (index: number) => {
    if (individualForm.items.length > 1) {
        individualForm.items.splice(index, 1);
    }
};

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
    confirmationModal.value = {
        show: true,
        title: 'Resolve Accountability',
        message: 'Are you sure you want to resolve this accountability? This will mark it as cleared.',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(`/student-services/clearance/accountabilities/${id}/resolve`, {}, {
                onSuccess: () => {
                    confirmationModal.value.show = false;
                    updateLocalState(id, 'resolved');
                },
                onFinish: () => (confirmationModal.value.loading = false)
            });
        }
    };
};

const waive = (id: number) => {
    confirmationModal.value = {
        show: true,
        title: 'Waive Accountability',
        message: 'Are you sure you want to waive this accountability? This will mark it as waived without requiring payment or compliance.',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(`/student-services/clearance/accountabilities/${id}/waive`, {}, {
                onSuccess: () => {
                    confirmationModal.value.show = false;
                    updateLocalState(id, 'waived');
                },
                onFinish: () => (confirmationModal.value.loading = false)
            });
        }
    };
};

const reset = (id: number) => {
    confirmationModal.value = {
        show: true,
        title: 'Reset to Pending',
        message: 'Are you sure you want to reset this accountability to pending status?',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(`/student-services/clearance/accountabilities/${id}/reset`, {}, {
                onSuccess: () => {
                    confirmationModal.value.show = false;
                    updateLocalState(id, 'pending');
                },
                onFinish: () => (confirmationModal.value.loading = false)
            });
        }
    };
};

const updateLocalState = (id: number, status: string) => {
    if (selectedAcc.value) {
        if (selectedAcc.value.id === id) {
            // Updating the parent
            selectedAcc.value.status = status;
            
            // If it's a parent, update all children too
            if (selectedAcc.value.children) {
                const items = selectedAcc.value.children.data || selectedAcc.value.children;
                items.forEach((child: any) => {
                    child.status = status;
                });
            }
        } else if (selectedAcc.value.children) {
            // Updating a specific child
            const items = selectedAcc.value.children.data || selectedAcc.value.children;
            const child = items.find((c: any) => c.id === id);
            if (child) child.status = status;
        }
    }
};

const viewDetails = (acc: any) => {
    selectedAcc.value = acc;
    detailsModal.value = true;
};

const openEdit = (acc: any) => {
    editForm.id = acc.id;
    editForm.title = acc.title;
    editForm.description = acc.description;
    editForm.amount = acc.amount;
    editModal.value = true;
    detailsModal.value = false;
};

const handleUpdate = () => {
    editForm.patch(`/student-services/clearance/accountabilities/${editForm.id}`, {
        onSuccess: () => {
            editModal.value = false;
            editForm.reset();
        },
    });
};

const deleteAcc = (id: number) => {
    if (confirm('Are you sure you want to delete this accountability? This action cannot be undone.')) {
        router.delete(`/student-services/clearance/accountabilities/${id}`, {
            onSuccess: () => {
                detailsModal.value = false;
            }
        });
    }
};

const handleUpload = () => {
    uploadForm.post(`/student-services/clearance/updates/${props.update.id}/accountabilities/upload-preview`, {
        onSuccess: () => (uploadModal.value = false),
    });
};

const handleIndividualAdd = () => {
    individualForm.post(`/student-services/clearance/updates/${props.update.id}/accountabilities`, {
        onSuccess: () => {
            individualModal.value = false;
            individualForm.reset();
            studentSearch.value = '';
        },
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

    <div class="flex h-full flex-1 flex-col gap-4 p-4 bg-slate-50/30">
        <!-- Compact Enterprise Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white px-5 py-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="h-11 w-11 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <RotateCcw class="h-5 w-5 text-white" />
                </div>
                <div class="grid gap-0.5">
                    <h1 class="text-base font-black tracking-tight text-slate-900">Accountabilities Center</h1>
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ props.update.title }}</span>
                        <span class="h-0.5 w-0.5 rounded-full bg-slate-300"></span>
                        <span class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest">{{ props.update.semester.academic_year }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" class="h-9 gap-1.5 rounded-lg border-slate-200 hover:bg-slate-50 text-slate-600 font-bold px-4 text-xs" @click="uploadModal = true">
                    <Upload class="h-3.5 w-3.5" />
                    Bulk Upload
                </Button>
                <Button class="h-9 gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 text-xs shadow-md shadow-indigo-600/10" @click="individualModal = true">
                    <Plus class="h-3.5 w-3.5" />
                    Add Individual
                </Button>
            </div>
        </div>

        <!-- High-Density Filter Suite -->
        <div class="grid md:grid-cols-4 gap-3 bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
            <div class="relative group md:col-span-2">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 group-focus-within:text-indigo-500 transition-colors" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name or ID..."
                    class="w-full h-9 pl-9 pr-3 rounded-lg border-slate-200 bg-slate-50/50 text-xs font-medium placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5 transition-all"
                />
            </div>
            <select v-model="office_id" class="h-9 rounded-lg border-slate-200 bg-slate-50/50 text-xs font-bold text-slate-600 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5 transition-all">
                <option value="">All Departments</option>
                <option v-for="office in offices" :key="office.id" :value="office.id">{{ office.name }}</option>
            </select>
            <select v-model="status" class="h-9 rounded-lg border-slate-200 bg-slate-50/50 text-xs font-bold text-slate-600 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5 transition-all">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="waived">Waived</option>
            </select>
        </div>

        <!-- Compact Data Grid -->
        <div class="flex-1 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest w-12">ID</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Student Information</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Requirement</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest text-center">Items</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Amount</th>
                            <th class="px-5 py-3 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="px-5 py-3 text-right text-[9px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="acc in accountabilities.data" :key="acc.id" class="group transition-colors hover:bg-slate-50/50">
                            <td class="px-5 py-3">
                                <span class="text-[10px] font-mono font-bold text-slate-300">#{{ acc.id }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors border border-slate-100">
                                        <span class="text-[10px] font-black">{{ acc.student.name.charAt(0) }}</span>
                                    </div>
                                    <div class="grid gap-0">
                                        <span class="text-xs font-bold text-slate-900 leading-tight">{{ acc.student.name }}</span>
                                        <span class="text-[9px] font-mono text-slate-400 uppercase tracking-tighter">{{ acc.student.student_no }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid gap-0">
                                    <span class="text-xs font-bold text-slate-700">{{ acc.title }}</span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1">
                                        {{ acc.office.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-center">
                                    <div v-if="acc.children && acc.children.length > 0" class="flex items-center gap-1">
                                        <span class="rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[8px] font-black text-indigo-500 uppercase tracking-tighter">
                                            {{ acc.children.length }} items
                                        </span>
                                    </div>
                                    <span v-else class="text-[8px] text-slate-300 font-bold uppercase tracking-widest">Single</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-[10px] font-mono font-bold" :class="acc.amount > 0 ? 'text-slate-900' : 'text-slate-300'">
                                    {{ acc.amount > 0 ? '₱' + Number(acc.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[8px] font-black uppercase tracking-widest border', statusBadge(acc.status)]">
                                    <div class="h-1 w-1 rounded-full bg-current"></div>
                                    {{ acc.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-all translate-x-1 group-hover:translate-x-0">
                                    <button @click="viewDetails(acc)" class="h-7 w-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all">
                                        <Eye class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="openEdit(acc)" class="h-7 w-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-amber-500 hover:text-white hover:border-amber-500 transition-all">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="deleteAcc(acc.id)" class="h-7 w-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Compact Pagination -->
            <div v-if="accountabilities.data.length > 0" class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between mt-auto">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Showing {{ accountabilities.from }}-{{ accountabilities.to }} of {{ accountabilities.total }}</p>
                <div class="flex gap-1.5">
                    <Link
                        v-for="(link, k) in accountabilities.links"
                        :key="k"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'h-8 min-w-[32px] px-2 flex items-center justify-center rounded-lg text-[10px] font-black transition-all border',
                            link.active ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/10' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
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

    <!-- Individual Add Modal -->
    <div v-if="individualModal" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="individualModal = false">
        <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-slate-900">Add Individual Accountability</h2>
                <Button variant="ghost" size="sm" @click="individualModal = false"><XCircle class="h-5 w-5 text-slate-400" /></Button>
            </div>
            
            <form class="grid gap-6" @submit.prevent="handleIndividualAdd">
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="relative grid gap-1">
                        <label class="text-[11px] font-bold text-slate-500 uppercase">Search Student</label>
                        <input 
                            v-model="studentSearch"
                            type="text" 
                            placeholder="Search by name or student no..."
                            class="h-10 rounded-lg border border-slate-200 bg-white pr-9 pl-3 text-sm focus:border-emerald-400 focus:outline-none"
                        />
                        <div v-if="isSearching" class="absolute top-1/2 right-3 translate-y-1/2">
                            <RefreshCw class="h-3.5 w-3.5 animate-spin text-slate-400" />
                        </div>
                        <div v-if="studentsList.length > 0" class="absolute top-full left-0 z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-1 shadow-lg max-h-48 overflow-y-auto">
                            <button 
                                v-for="s in studentsList" 
                                :key="s.id"
                                type="button"
                                @click="selectStudent(s)"
                                class="flex w-full flex-col px-3 py-2 text-left hover:bg-slate-50 rounded-md transition-colors"
                            >
                                <span class="text-xs font-bold text-slate-900">{{ s.name }}</span>
                                <span class="text-[10px] text-slate-400">{{ s.student_no }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!(page.props.auth as any).user.office_id" class="grid gap-1">
                        <label class="text-[11px] font-bold text-slate-500 uppercase">Office</label>
                        <select v-model="individualForm.office_id" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm">
                            <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="individualForm.items.length > 1" class="grid gap-1">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Main Group Title</label>
                    <input v-model="individualForm.group_title" type="text" placeholder="e.g. Incomplete Enrollment Documents" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm" required />
                    <p class="text-[10px] text-slate-400 italic">This will be the main heading for this group of accountabilities.</p>
                </div>

                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Accountability Items</h3>
                        <Button type="button" size="sm" variant="outline" class="h-8 gap-1.5 text-emerald-600 border-emerald-100 hover:bg-emerald-50" @click="addItem">
                            <Plus class="h-3.5 w-3.5" />
                            Add Item
                        </Button>
                    </div>

                    <div class="grid gap-4">
                        <div v-for="(item, index) in individualForm.items" :key="index" class="relative grid gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50/50">
                            <button 
                                v-if="individualForm.items.length > 1"
                                type="button" 
                                @click="removeItem(index)"
                                class="absolute top-2 right-2 text-slate-300 hover:text-red-500 transition-colors"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>

                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="grid gap-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Accountability Title</label>
                                    <input v-model="item.title" type="text" placeholder="e.g. Missing Equipment" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs" required />
                                </div>
                                <div class="grid gap-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Amount (Optional)</label>
                                    <input v-model="item.amount" type="number" step="0.01" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs" placeholder="0.00" />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Description</label>
                                <textarea v-model="item.description" rows="2" class="rounded-lg border border-slate-200 bg-white p-3 text-xs" placeholder="Optional details..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2 border-t pt-6">
                    <Button type="button" variant="ghost" @click="individualModal = false">Cancel</Button>
                    <Button type="submit" class="bg-emerald-600 text-white px-6 font-bold" :disabled="individualForm.processing || !individualForm.student_id">Save Accountabilities</Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Details Modal -->
    <div v-if="detailsModal && selectedAcc" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="detailsModal = false">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-0 shadow-2xl overflow-hidden border border-slate-200">
            <div class="relative bg-slate-900 p-8 text-white">
                <button @click="detailsModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-colors">
                    <XCircle class="h-6 w-6" />
                </button>
                <div class="flex items-center gap-4 mb-4">
                    <div class="h-12 w-12 rounded-xl bg-emerald-500 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <AlertCircle class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold tracking-tight">{{ selectedAcc.title }}</h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="['rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest', statusBadge(selectedAcc.status)]">
                                {{ selectedAcc.status }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium uppercase tracking-widest">{{ selectedAcc.office.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-8 grid gap-8 bg-white">
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="space-y-1 p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Student Information</span>
                        <p class="text-sm font-bold text-slate-900 leading-tight">{{ selectedAcc.student.name }}</p>
                        <p class="text-[11px] text-slate-500">{{ selectedAcc.student.student_no }}</p>
                    </div>
                    <div class="space-y-1 p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Amount</span>
                        <p class="text-lg font-mono font-bold text-slate-900">{{ selectedAcc.amount ? '₱' + Number(selectedAcc.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-' }}</p>
                    </div>
                    <div class="space-y-1 p-4 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Recorded On</span>
                        <p class="text-sm font-bold text-slate-900">{{ selectedAcc.created_at }}</p>
                        <p class="text-[11px] text-slate-500">By {{ selectedAcc.uploader.name }}</p>
                    </div>
                </div>

                <div v-if="selectedAcc.description" class="space-y-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Description / Remarks</span>
                    <div class="p-4 rounded-xl bg-slate-50/50 border border-slate-100 text-xs text-slate-600 leading-relaxed italic">
                        "{{ selectedAcc.description }}"
                    </div>
                </div>

                <!-- Sub-items (Children) -->
                <div v-if="(selectedAcc.children?.length > 0 || selectedAcc.children?.data?.length > 0)" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Itemized Breakdown</span>
                        <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">{{ (selectedAcc.children?.data || selectedAcc.children).length }} items total</span>
                    </div>
                    <div class="grid gap-3 max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="child in (selectedAcc.children?.data || selectedAcc.children)" :key="child.id" class="flex flex-col rounded-xl border border-slate-100 p-4 transition-all hover:border-indigo-200 hover:shadow-md hover:shadow-indigo-500/5 group/child bg-white">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 group-hover/child:bg-indigo-500 group-hover/child:text-white transition-colors">
                                        <span class="text-xs font-bold">{{ child.id }}</span>
                                    </div>
                                    <div class="grid gap-0.5">
                                        <span class="text-sm font-bold text-slate-800">{{ child.title }}</span>
                                        <span class="text-[10px] font-mono text-slate-500">{{ child.amount ? '₱' + Number(child.amount).toLocaleString() : 'No fee' }}</span>
                                    </div>
                                </div>
                                <span :class="['rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider', statusBadge(child.status)]">
                                    {{ child.status }}
                                </span>
                            </div>

                            <div v-if="child.description" class="mb-3 p-2 rounded-lg bg-slate-50 text-[10px] text-slate-500 italic">
                                {{ child.description }}
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-slate-50">
                                <div class="flex gap-1">
                                    <button @click="openEdit(child)" class="h-7 px-2 text-[10px] font-bold text-slate-500 hover:bg-slate-100 rounded transition-colors flex items-center gap-1">
                                        <Pencil class="h-3 w-3" /> Edit
                                    </button>
                                    <button @click="deleteAcc(child.id)" class="h-7 px-2 text-[10px] font-bold text-red-400 hover:bg-red-50 rounded transition-colors flex items-center gap-1">
                                        <Trash2 class="h-3 w-3" /> Delete
                                    </button>
                                </div>
                                <div class="flex gap-1">
                                    <template v-if="child.status === 'pending'">
                                        <button @click="resolve(child.id)" class="h-7 px-3 text-[10px] font-bold text-white bg-emerald-500 hover:bg-emerald-600 rounded shadow-sm transition-all">Resolve</button>
                                        <button @click="waive(child.id)" class="h-7 px-3 text-[10px] font-bold text-white bg-indigo-500 hover:bg-indigo-600 rounded shadow-sm transition-all">Waive</button>
                                    </template>
                                    <template v-else>
                                        <button @click="reset(child.id)" class="h-7 px-3 text-[10px] font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded transition-all">Reset to Pending</button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="selectedAcc.status !== 'pending' && selectedAcc.resolver" class="rounded-xl border border-emerald-100 bg-emerald-50/30 p-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center">
                            <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-emerald-800 uppercase tracking-widest">Resolution Information</p>
                            <p class="text-xs text-emerald-700 font-medium">Cleared by <span class="font-bold">{{ selectedAcc.resolver.name }}</span> on {{ selectedAcc.resolved_at }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between border-t bg-slate-50/50 p-6">
                <div class="flex gap-2">
                    <Button variant="outline" class="h-10 gap-2 text-slate-600 border-slate-200 hover:bg-white px-4" @click="openEdit(selectedAcc)">
                        <Pencil class="h-4 w-4" />
                        Edit Group
                    </Button>
                    <Button variant="outline" class="h-10 gap-2 text-red-600 border-red-100 hover:bg-red-50 px-4" @click="deleteAcc(selectedAcc.id)">
                        <Trash2 class="h-4 w-4" />
                        Delete All
                    </Button>
                </div>
                <div class="flex gap-3">
                    <Button variant="ghost" class="h-10 px-6 font-bold text-slate-500" @click="detailsModal = false">Close</Button>
                    <Button v-if="selectedAcc.status === 'pending'" class="h-10 bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-8 shadow-lg shadow-emerald-500/20" @click="resolve(selectedAcc.id)">Clear Everything</Button>
                    <Button v-else class="h-10 bg-slate-900 hover:bg-slate-800 text-white font-bold px-8 shadow-lg shadow-slate-900/20" @click="reset(selectedAcc.id)">Revert to Pending</Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editModal" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="editModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-sm font-bold text-slate-900 uppercase tracking-wider">Edit Accountability</h2>
            <form class="grid gap-4" @submit.prevent="handleUpdate">
                <div class="grid gap-1">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Title</label>
                    <input v-model="editForm.title" type="text" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs" required />
                </div>
                <div class="grid gap-1">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Amount</label>
                    <input v-model="editForm.amount" type="number" step="0.01" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs" />
                </div>
                <div class="grid gap-1">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Description</label>
                    <textarea v-model="editForm.description" rows="3" class="rounded-lg border border-slate-200 bg-white p-3 text-xs"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <Button type="button" variant="ghost" @click="editModal = false">Cancel</Button>
                    <Button type="submit" class="bg-indigo-600 text-white px-6 font-bold" :disabled="editForm.processing">Update</Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="confirmationModal.show" class="fixed inset-0 z-[100] grid place-items-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center gap-4 mb-4">
                <div class="h-12 w-12 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                    <AlertTriangle class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ confirmationModal.title }}</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">{{ confirmationModal.message }}</p>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <Button variant="ghost" class="h-10 px-4 font-bold text-slate-500" @click="confirmationModal.show = false" :disabled="confirmationModal.loading">Cancel</Button>
                <Button class="h-10 px-6 font-bold bg-slate-900 text-white shadow-lg shadow-slate-900/20" @click="confirmationModal.action" :disabled="confirmationModal.loading">
                    <span v-if="confirmationModal.loading" class="flex items-center gap-2">
                        <Loader2 class="h-4 w-4 animate-spin" /> Processing...
                    </span>
                    <span v-else>Confirm Action</span>
                </Button>
            </div>
        </div>
    </div>
</template>
