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
    office_id:
        (page.props.auth as any).user.office_id ??
        props.filters.office_id ??
        '',
    group_title: '',
    items: [{ title: '', description: '', amount: '' }],
});

const detailsModal = ref(false);
const selectedAcc = ref<any>(null);
const confirmationModal = ref({
    show: false,
    title: '',
    message: '',
    action: () => {},
    loading: false,
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
        const response = await fetch(
            `/student-services/clearance/accountabilities/students?search=${studentSearch.value}`,
        );
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
        {
            search: search.value,
            office_id: office_id.value,
            status: status.value,
        },
        { preserveState: true, replace: true },
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
        message:
            'Are you sure you want to resolve this accountability? This will mark it as cleared.',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(
                `/student-services/clearance/accountabilities/${id}/resolve`,
                {},
                {
                    onSuccess: () => {
                        confirmationModal.value.show = false;
                        updateLocalState(id, 'resolved');
                    },
                    onFinish: () => (confirmationModal.value.loading = false),
                },
            );
        },
    };
};

const waive = (id: number) => {
    confirmationModal.value = {
        show: true,
        title: 'Waive Accountability',
        message:
            'Are you sure you want to waive this accountability? This will mark it as waived without requiring payment or compliance.',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(
                `/student-services/clearance/accountabilities/${id}/waive`,
                {},
                {
                    onSuccess: () => {
                        confirmationModal.value.show = false;
                        updateLocalState(id, 'waived');
                    },
                    onFinish: () => (confirmationModal.value.loading = false),
                },
            );
        },
    };
};

const reset = (id: number) => {
    confirmationModal.value = {
        show: true,
        title: 'Reset to Pending',
        message:
            'Are you sure you want to reset this accountability to pending status?',
        loading: false,
        action: () => {
            confirmationModal.value.loading = true;
            router.post(
                `/student-services/clearance/accountabilities/${id}/reset`,
                {},
                {
                    onSuccess: () => {
                        confirmationModal.value.show = false;
                        updateLocalState(id, 'pending');
                    },
                    onFinish: () => (confirmationModal.value.loading = false),
                },
            );
        },
    };
};

const updateLocalState = (id: number, status: string) => {
    if (selectedAcc.value) {
        if (selectedAcc.value.id === id) {
            // Updating the parent
            selectedAcc.value.status = status;

            // If it's a parent, update all children too
            if (selectedAcc.value.children) {
                const items =
                    selectedAcc.value.children.data ||
                    selectedAcc.value.children;
                items.forEach((child: any) => {
                    child.status = status;
                });
            }
        } else if (selectedAcc.value.children) {
            // Updating a specific child
            const items =
                selectedAcc.value.children.data || selectedAcc.value.children;
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
    editForm.patch(
        `/student-services/clearance/accountabilities/${editForm.id}`,
        {
            onSuccess: () => {
                editModal.value = false;
                editForm.reset();
            },
        },
    );
};

const deleteAcc = (id: number) => {
    if (
        confirm(
            'Are you sure you want to delete this accountability? This action cannot be undone.',
        )
    ) {
        router.delete(`/student-services/clearance/accountabilities/${id}`, {
            onSuccess: () => {
                detailsModal.value = false;
            },
        });
    }
};

const handleUpload = () => {
    uploadForm.post(
        `/student-services/clearance/updates/${props.update.id}/accountabilities/upload-preview`,
        {
            onSuccess: () => (uploadModal.value = false),
        },
    );
};

const handleIndividualAdd = () => {
    individualForm.post(
        `/student-services/clearance/updates/${props.update.id}/accountabilities`,
        {
            onSuccess: () => {
                individualModal.value = false;
                individualForm.reset();
                studentSearch.value = '';
            },
        },
    );
};

const statusBadge = (s: string) => {
    switch (s) {
        case 'pending':
            return 'bg-amber-100 text-amber-700';
        case 'resolved':
            return 'bg-emerald-100 text-emerald-700';
        case 'waived':
            return 'bg-indigo-100 text-indigo-700';
        default:
            return 'bg-slate-100 text-slate-600';
    }
};
</script>

<template>
    <Head title="Manage Accountabilities" />

    <div class="flex h-full flex-1 flex-col gap-4 bg-slate-50/30 p-4">
        <!-- Compact Enterprise Header -->
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm md:flex-row md:items-center"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 shadow-lg shadow-indigo-500/20"
                >
                    <RotateCcw class="h-5 w-5 text-white" />
                </div>
                <div class="grid gap-0.5">
                    <h1
                        class="text-base font-black tracking-tight text-slate-900"
                    >
                        Accountabilities Center
                    </h1>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                            >{{ props.update.title }}</span
                        >
                        <span
                            class="h-0.5 w-0.5 rounded-full bg-slate-300"
                        ></span>
                        <span
                            class="text-[9px] font-bold tracking-widest text-indigo-500 uppercase"
                            >{{ props.update.semester.academic_year }}</span
                        >
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    class="h-9 gap-1.5 rounded-lg border-slate-200 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50"
                    @click="uploadModal = true"
                >
                    <Upload class="h-3.5 w-3.5" />
                    Bulk Upload
                </Button>
                <Button
                    class="h-9 gap-1.5 rounded-lg bg-indigo-600 px-4 text-xs font-bold text-white shadow-md shadow-indigo-600/10 hover:bg-indigo-700"
                    @click="individualModal = true"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Add Individual
                </Button>
            </div>
        </div>

        <!-- High-Density Filter Suite -->
        <div
            class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-4"
        >
            <div class="group relative md:col-span-2">
                <Search
                    class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-indigo-500"
                />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name or ID..."
                    class="h-9 w-full rounded-lg border-slate-200 bg-slate-50/50 pr-3 pl-9 text-xs font-medium transition-all placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5"
                />
            </div>
            <select
                v-model="office_id"
                class="h-9 rounded-lg border-slate-200 bg-slate-50/50 text-xs font-bold text-slate-600 transition-all focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5"
            >
                <option value="">All Departments</option>
                <option
                    v-for="office in offices"
                    :key="office.id"
                    :value="office.id"
                >
                    {{ office.name }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-9 rounded-lg border-slate-200 bg-slate-50/50 text-xs font-bold text-slate-600 transition-all focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5"
            >
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="waived">Waived</option>
            </select>
        </div>

        <!-- Compact Data Grid -->
        <div
            class="flex flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th
                                class="w-12 px-5 py-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                ID
                            </th>
                            <th
                                class="px-5 py-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Student Information
                            </th>
                            <th
                                class="px-5 py-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Requirement
                            </th>
                            <th
                                class="px-5 py-3 text-center text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Items
                            </th>
                            <th
                                class="px-5 py-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Amount
                            </th>
                            <th
                                class="px-5 py-3 text-left text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Status
                            </th>
                            <th
                                class="px-5 py-3 text-right text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr
                            v-for="acc in accountabilities.data"
                            :key="acc.id"
                            class="group transition-colors hover:bg-slate-50/50"
                        >
                            <td class="px-5 py-3">
                                <span
                                    class="font-mono text-[10px] font-bold text-slate-300"
                                    >#{{ acc.id }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-100 bg-slate-50 text-slate-400 transition-colors group-hover:bg-indigo-50 group-hover:text-indigo-600"
                                    >
                                        <span class="text-[10px] font-black">{{
                                            acc.student.name.charAt(0)
                                        }}</span>
                                    </div>
                                    <div class="grid gap-0">
                                        <span
                                            class="text-xs leading-tight font-bold text-slate-900"
                                            >{{ acc.student.name }}</span
                                        >
                                        <span
                                            class="font-mono text-[9px] tracking-tighter text-slate-400 uppercase"
                                            >{{ acc.student.student_no }}</span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="grid gap-0">
                                    <span
                                        class="text-xs font-bold text-slate-700"
                                        >{{ acc.title }}</span
                                    >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                                    >
                                        {{ acc.office.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-center">
                                    <div
                                        v-if="
                                            acc.children &&
                                            acc.children.length > 0
                                        "
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="rounded-full border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[8px] font-black tracking-tighter text-indigo-500 uppercase"
                                        >
                                            {{ acc.children.length }} items
                                        </span>
                                    </div>
                                    <span
                                        v-else
                                        class="text-[8px] font-bold tracking-widest text-slate-300 uppercase"
                                        >Single</span
                                    >
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="font-mono text-[10px] font-bold"
                                    :class="
                                        acc.amount > 0
                                            ? 'text-slate-900'
                                            : 'text-slate-300'
                                    "
                                >
                                    {{
                                        acc.amount > 0
                                            ? '₱' +
                                              Number(acc.amount).toLocaleString(
                                                  undefined,
                                                  { minimumFractionDigits: 2 },
                                              )
                                            : '-'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase',
                                        statusBadge(acc.status),
                                    ]"
                                >
                                    <div
                                        class="h-1 w-1 rounded-full bg-current"
                                    ></div>
                                    {{ acc.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div
                                    class="flex translate-x-1 items-center justify-end gap-1.5 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                >
                                    <button
                                        @click="viewDetails(acc)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-all hover:border-indigo-600 hover:bg-indigo-600 hover:text-white"
                                    >
                                        <Eye class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="openEdit(acc)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-all hover:border-amber-500 hover:bg-amber-500 hover:text-white"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="deleteAcc(acc.id)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-all hover:border-red-500 hover:bg-red-500 hover:text-white"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Compact Pagination -->
            <div
                v-if="accountabilities.data.length > 0"
                class="mt-auto flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-5 py-3"
            >
                <p
                    class="text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                >
                    Showing {{ accountabilities.from }}-{{
                        accountabilities.to
                    }}
                    of {{ accountabilities.total }}
                </p>
                <div class="flex gap-1.5">
                    <Link
                        v-for="(link, k) in accountabilities.links"
                        :key="k"
                        :href="link.url || '#'"
                        v-html="link.label"
                        :class="[
                            'flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2 text-[10px] font-black transition-all',
                            link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/10'
                                : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div
        v-if="uploadModal"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="uploadModal = false"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h2 class="mb-4 text-sm font-bold text-slate-900">
                Bulk Upload Accountabilities
            </h2>
            <form class="grid gap-4" @submit.prevent="handleUpload">
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    Select Office
                    <select
                        v-model="uploadForm.office_id"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs"
                    >
                        <option
                            v-for="off in offices"
                            :key="off.id"
                            :value="off.id"
                        >
                            {{ off.name }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    CSV File
                    <input
                        type="file"
                        @input="
                            uploadForm.file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                        class="text-xs"
                        accept=".csv"
                    />
                </label>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-[10px] text-slate-500">
                        CSV format: Student No/Email, Title, Description, Amount
                    </p>
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="uploadModal = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        class="bg-indigo-600 text-white"
                        :disabled="uploadForm.processing"
                        >Upload & Preview</Button
                    >
                </div>
            </form>
        </div>
    </div>

    <!-- Compact Individual Add Modal -->
    <div
        v-if="individualModal"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="individualModal = false"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4"
            >
                <div>
                    <h2
                        class="text-sm font-black tracking-widest text-slate-900 uppercase"
                    >
                        Add Individual Accountability
                    </h2>
                    <p
                        class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                    >
                        Register new student requirement
                    </p>
                </div>
                <button
                    @click="individualModal = false"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-slate-100 hover:text-slate-600"
                >
                    <XCircle class="h-5 w-5" />
                </button>
            </div>

            <form
                class="flex-1 overflow-y-auto p-6"
                @submit.prevent="handleIndividualAdd"
            >
                <div class="mb-6 grid gap-4 md:grid-cols-2">
                    <div class="relative grid gap-1.5">
                        <label
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Student Lookup</label
                        >
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-300"
                            />
                            <input
                                v-model="studentSearch"
                                type="text"
                                placeholder="Name or Student ID..."
                                class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50/50 pr-3 pl-9 text-xs transition-all focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/5"
                            />
                        </div>
                        <div
                            v-if="studentsList.length > 0"
                            class="absolute top-full left-0 z-20 mt-1 max-h-40 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                        >
                            <button
                                v-for="s in studentsList"
                                :key="s.id"
                                type="button"
                                @click="selectStudent(s)"
                                class="group flex w-full flex-col rounded-lg px-3 py-2 text-left transition-colors hover:bg-indigo-50"
                            >
                                <span
                                    class="text-xs font-bold text-slate-700 group-hover:text-indigo-600"
                                    >{{ s.name }}</span
                                >
                                <span
                                    class="font-mono text-[9px] text-slate-400"
                                    >{{ s.student_no }}</span
                                >
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="!(page.props.auth as any).user.office_id"
                        class="grid gap-1.5"
                    >
                        <label
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Department</label
                        >
                        <select
                            v-model="individualForm.office_id"
                            class="h-9 rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-bold text-slate-600"
                        >
                            <option
                                v-for="off in offices"
                                :key="off.id"
                                :value="off.id"
                            >
                                {{ off.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="individualForm.items.length > 1"
                    class="mb-6 grid gap-1.5"
                >
                    <label
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >Group Heading</label
                    >
                    <input
                        v-model="individualForm.group_title"
                        type="text"
                        placeholder="e.g. Enrollment Requirements Pack"
                        class="h-9 rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-bold"
                        required
                    />
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <div class="mb-4 flex items-center justify-between">
                        <h3
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >
                            Accountability Line Items
                        </h3>
                        <button
                            type="button"
                            @click="addItem"
                            class="flex h-7 items-center gap-1.5 rounded-lg border border-emerald-100 bg-emerald-50 px-3 text-[10px] font-black text-emerald-600 transition-all hover:bg-emerald-600 hover:text-white"
                        >
                            <Plus class="h-3 w-3" />
                            Add Item
                        </button>
                    </div>

                    <div class="grid gap-3">
                        <div
                            v-for="(item, index) in individualForm.items"
                            :key="index"
                            class="group/item relative grid gap-3 rounded-xl border border-slate-100 bg-slate-50/30 p-4"
                        >
                            <button
                                v-if="individualForm.items.length > 1"
                                type="button"
                                @click="removeItem(index)"
                                class="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-lg text-slate-300 transition-all hover:bg-red-50 hover:text-red-500"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="grid gap-1">
                                    <label
                                        class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                        >Item Title</label
                                    >
                                    <input
                                        v-model="item.title"
                                        type="text"
                                        placeholder="e.g. PSA Birth Certificate"
                                        class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-[11px] font-bold"
                                        required
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <label
                                        class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                        >Fee (Optional)</label
                                    >
                                    <input
                                        v-model="item.amount"
                                        type="number"
                                        step="0.01"
                                        class="h-8 rounded-lg border border-slate-200 bg-white px-3 font-mono text-[11px]"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <label
                                    class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                    >Detailed Remarks</label
                                >
                                <textarea
                                    v-model="item.description"
                                    rows="1"
                                    class="rounded-lg border border-slate-200 bg-white p-2 text-[11px] leading-relaxed"
                                    placeholder="Additional details..."
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div
                class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4"
            >
                <Button
                    type="button"
                    variant="ghost"
                    class="h-9 px-4 text-xs font-bold text-slate-500"
                    @click="individualModal = false"
                    >Discard</Button
                >
                <Button
                    type="submit"
                    @click="handleIndividualAdd"
                    class="h-9 bg-emerald-600 px-6 text-xs font-black text-white shadow-lg shadow-emerald-600/10 hover:bg-emerald-700"
                    :disabled="
                        individualForm.processing || !individualForm.student_id
                    "
                >
                    Finalize & Save
                </Button>
            </div>
        </div>
    </div>

    <!-- Compact Details Modal -->
    <div
        v-if="detailsModal && selectedAcc"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="detailsModal = false"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            <div class="relative bg-slate-900 px-6 py-5 text-white">
                <button
                    @click="detailsModal = false"
                    class="absolute top-4 right-4 text-slate-500 transition-colors hover:text-white"
                >
                    <XCircle class="h-5 w-5" />
                </button>
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 shadow-lg shadow-indigo-500/20"
                    >
                        <RotateCcw class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h2
                            class="text-base leading-tight font-black tracking-tight"
                        >
                            {{ selectedAcc.title }}
                        </h2>
                        <div class="mt-0.5 flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded-full border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase',
                                    statusBadge(selectedAcc.status),
                                ]"
                            >
                                {{ selectedAcc.status }}
                            </span>
                            <span
                                class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                                >{{ selectedAcc.office.name }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto p-6">
                <div class="grid gap-3 md:grid-cols-3">
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3"
                    >
                        <span
                            class="mb-1 block text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >Student</span
                        >
                        <p
                            class="text-xs leading-tight font-bold text-slate-900"
                        >
                            {{ selectedAcc.student.name }}
                        </p>
                        <p class="font-mono text-[9px] text-slate-500">
                            {{ selectedAcc.student.student_no }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3"
                    >
                        <span
                            class="mb-1 block text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >Total Due</span
                        >
                        <p class="font-mono text-sm font-black text-slate-900">
                            {{
                                selectedAcc.amount
                                    ? '₱' +
                                      Number(selectedAcc.amount).toLocaleString(
                                          undefined,
                                          { minimumFractionDigits: 2 },
                                      )
                                    : '-'
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3"
                    >
                        <span
                            class="mb-1 block text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >Timeline</span
                        >
                        <p
                            class="text-[10px] leading-tight font-bold text-slate-900"
                        >
                            {{ selectedAcc.created_at }}
                        </p>
                        <p class="text-[9px] text-slate-400">
                            By {{ selectedAcc.uploader.name }}
                        </p>
                    </div>
                </div>

                <div v-if="selectedAcc.description" class="space-y-1.5">
                    <span
                        class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                        >Administrative Remarks</span
                    >
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 text-[11px] leading-relaxed text-slate-600 italic"
                    >
                        "{{ selectedAcc.description }}"
                    </div>
                </div>

                <!-- Sub-items Breakdown -->
                <div
                    v-if="
                        selectedAcc.children?.length > 0 ||
                        selectedAcc.children?.data?.length > 0
                    "
                    class="space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >Child Requirements Breakdown</span
                        >
                        <span
                            class="rounded-full border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[8px] font-black tracking-widest text-indigo-600 uppercase"
                            >{{
                                (
                                    selectedAcc.children?.data ||
                                    selectedAcc.children
                                ).length
                            }}
                            total</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <div
                            v-for="child in selectedAcc.children?.data ||
                            selectedAcc.children"
                            :key="child.id"
                            class="group/child flex flex-col rounded-xl border border-slate-100 p-3 transition-all hover:bg-slate-50"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-[9px] font-black text-slate-400 transition-all group-hover/child:border-indigo-500 group-hover/child:bg-indigo-500 group-hover/child:text-white"
                                    >
                                        {{ child.id }}
                                    </div>
                                    <div class="grid gap-0">
                                        <span
                                            class="text-[11px] leading-tight font-bold text-slate-800"
                                            >{{ child.title }}</span
                                        >
                                        <span
                                            class="font-mono text-[9px] text-slate-500"
                                            >{{
                                                child.amount
                                                    ? '₱' +
                                                      Number(
                                                          child.amount,
                                                      ).toLocaleString()
                                                    : 'No charge'
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            'rounded-full border px-2 py-0.5 text-[8px] font-black tracking-widest uppercase',
                                            statusBadge(child.status),
                                        ]"
                                    >
                                        {{ child.status }}
                                    </span>
                                    <div
                                        class="flex items-center opacity-0 transition-all group-hover/child:opacity-100"
                                    >
                                        <button
                                            @click="openEdit(child)"
                                            class="flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-amber-100 hover:text-amber-600"
                                        >
                                            <Pencil class="h-3 w-3" />
                                        </button>
                                        <button
                                            @click="deleteAcc(child.id)"
                                            class="flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-red-100 hover:text-red-600"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="mt-2 flex justify-end gap-1.5 border-t border-slate-100/50 pt-2"
                            >
                                <template v-if="child.status === 'pending'">
                                    <button
                                        @click="resolve(child.id)"
                                        class="h-6 rounded-lg border border-emerald-100 bg-emerald-50 px-2.5 text-[9px] font-black text-emerald-600 transition-all hover:bg-emerald-600 hover:text-white"
                                    >
                                        Clear
                                    </button>
                                    <button
                                        @click="waive(child.id)"
                                        class="h-6 rounded-lg border border-indigo-100 bg-indigo-50 px-2.5 text-[9px] font-black text-indigo-600 transition-all hover:bg-indigo-600 hover:text-white"
                                    >
                                        Waive
                                    </button>
                                </template>
                                <template v-else>
                                    <button
                                        @click="reset(child.id)"
                                        class="h-6 rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-[9px] font-black text-slate-600 transition-all hover:bg-slate-200"
                                    >
                                        Reset
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="
                        selectedAcc.status !== 'pending' && selectedAcc.resolver
                    "
                    class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/30 px-4 py-3"
                >
                    <div class="flex items-center gap-3">
                        <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        <div>
                            <p
                                class="text-[8px] font-black tracking-widest text-emerald-800 uppercase"
                            >
                                Resolution Audit
                            </p>
                            <p class="text-[10px] font-bold text-emerald-700">
                                Approved by {{ selectedAcc.resolver.name }} on
                                {{ selectedAcc.resolved_at }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-6 py-4"
            >
                <div class="flex gap-1.5">
                    <Button
                        variant="outline"
                        class="h-8 gap-1.5 border-slate-200 px-3 text-[10px] font-black text-slate-600"
                        @click="openEdit(selectedAcc)"
                    >
                        <Pencil class="h-3 w-3" /> Edit Group
                    </Button>
                    <Button
                        variant="outline"
                        class="h-8 gap-1.5 border-red-100 px-3 text-[10px] font-black text-red-600"
                        @click="deleteAcc(selectedAcc.id)"
                    >
                        <Trash2 class="h-3 w-3" /> Purge
                    </Button>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="ghost"
                        class="h-8 px-4 text-[10px] font-black text-slate-500"
                        @click="detailsModal = false"
                        >Close</Button
                    >
                    <Button
                        v-if="selectedAcc.status === 'pending'"
                        class="h-8 bg-indigo-600 px-5 text-[10px] font-black text-white shadow-lg shadow-indigo-600/10 hover:bg-indigo-700"
                        @click="resolve(selectedAcc.id)"
                        >Approve All</Button
                    >
                    <Button
                        v-else
                        class="h-8 bg-slate-900 px-5 text-[10px] font-black text-white shadow-lg shadow-slate-900/10 hover:bg-slate-800"
                        @click="reset(selectedAcc.id)"
                        >Revert Status</Button
                    >
                </div>
            </div>
        </div>
    </div>

    <!-- Compact Edit Modal -->
    <div
        v-if="editModal"
        class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="editModal = false"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4"
            >
                <div>
                    <h2
                        class="text-sm font-black tracking-widest text-slate-900 uppercase"
                    >
                        Edit Accountability
                    </h2>
                    <p
                        class="text-[10px] font-bold tracking-tighter text-slate-400 uppercase"
                    >
                        Modify requirement details
                    </p>
                </div>
                <button
                    @click="editModal = false"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-slate-100 hover:text-slate-600"
                >
                    <XCircle class="h-5 w-5" />
                </button>
            </div>

            <form class="grid gap-4 p-6" @submit.prevent="handleUpdate">
                <div class="grid gap-1.5">
                    <label
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >Requirement Title</label
                    >
                    <input
                        v-model="editForm.title"
                        type="text"
                        class="h-9 rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-bold"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <label
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >Fee / Amount</label
                    >
                    <input
                        v-model="editForm.amount"
                        type="number"
                        step="0.01"
                        class="h-9 rounded-lg border border-slate-200 bg-slate-50/50 px-3 font-mono text-xs"
                        placeholder="0.00"
                    />
                </div>
                <div class="grid gap-1.5">
                    <label
                        class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                        >Administrative Remarks</label
                    >
                    <textarea
                        v-model="editForm.description"
                        rows="3"
                        class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 text-xs leading-relaxed"
                        placeholder="Add context..."
                    ></textarea>
                </div>
                <div
                    class="mt-2 flex justify-end gap-2 border-t border-slate-100 pt-4"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 px-4 text-xs font-bold text-slate-500"
                        @click="editModal = false"
                        >Discard</Button
                    >
                    <Button
                        type="submit"
                        class="h-9 bg-indigo-600 px-6 text-xs font-black text-white shadow-lg shadow-indigo-600/10 hover:bg-indigo-700"
                        :disabled="editForm.processing"
                    >
                        Save Changes
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Compact Confirmation Modal -->
    <div
        v-if="confirmationModal.show"
        class="fixed inset-0 z-[100] grid place-items-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
            <div class="p-6">
                <div class="mb-4 flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600"
                    >
                        <AlertTriangle class="h-6 w-6" />
                    </div>
                    <div>
                        <h3
                            class="text-sm font-black tracking-widest text-slate-900 uppercase"
                        >
                            {{ confirmationModal.title }}
                        </h3>
                        <p
                            class="text-[11px] leading-relaxed font-medium text-slate-500"
                        >
                            {{ confirmationModal.message }}
                        </p>
                    </div>
                </div>
            </div>
            <div
                class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4"
            >
                <Button
                    variant="ghost"
                    class="h-9 px-4 text-xs font-bold text-slate-500"
                    @click="confirmationModal.show = false"
                    :disabled="confirmationModal.loading"
                    >Discard</Button
                >
                <Button
                    class="h-9 bg-slate-900 px-6 text-xs font-black text-white shadow-lg shadow-slate-900/10"
                    @click="confirmationModal.action"
                    :disabled="confirmationModal.loading"
                >
                    <span
                        v-if="confirmationModal.loading"
                        class="flex items-center gap-2"
                    >
                        <Loader2 class="h-3.5 w-3.5 animate-spin" /> Working...
                    </span>
                    <span v-else>Proceed</span>
                </Button>
            </div>
        </div>
    </div>
</template>
