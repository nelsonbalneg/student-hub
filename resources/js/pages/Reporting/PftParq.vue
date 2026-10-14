<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { destroy, destroyHealth, updateHealth, updateStatus as updateParqStatus, index } from '@/actions/App/Http/Controllers/Admin/Reporting/PftParqController';
import { computed, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import {
    Activity,
    Dumbbell,
    FileText,
    Search,
    RefreshCw,
    CheckCircle2,
    XCircle,
    Clock,
    MoreVertical,
    Download,
    ChevronDown,
    ZoomIn,
    ZoomOut,
    Trash2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';

const props = defineProps<{
    parqs: any;
    healthQuestionnaires: any;
    filters: Record<string, string | undefined>;
    stats: {
        total: number;
        verified: number;
        pending_evaluation: number;
        pending: number;
    };
    options: {
        medicalConditions: string[];
        campuses: { value: string; label: string }[];
        terms: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
    };
    pageSizeOptions: number[];
}>();

defineOptions({
    layout: null,
});

const search = ref(props.filters.search || '');
const selectedCampus = ref(props.filters.campus || '');
const selectedTerm = ref(props.filters.term || '');
const selectedStatus = ref(props.filters.status || '');
const perPage = ref(props.parqs.per_page || 20);

const isLoading = ref(false);
const activeTab = ref(props.filters.tab === 'health' ? 'health' : 'parq');
const displayedRecords = computed(() => activeTab.value === 'health' ? props.healthQuestionnaires : props.parqs);
const selectedHealth = ref<any>(null);
const editingHealth = ref<any>(null);
const healthUpdateForm = useForm({
    civil_status: '',
    household_monthly_income: '',
    father_occupation: '',
    mother_occupation: '',
    smoking_status: '',
    alcohol_consumption: '',
    has_medical_condition: false,
    has_medication: false,
    specific_conditions: [] as string[],
    other_condition: '',
    medical_condition_details: '',
    medication_details: '',
    clearance_granted: false,
});
const openHealthUpdate = (health: any) => {
    editingHealth.value = health;
    healthUpdateForm.reset();
    healthUpdateForm.clearErrors();
    Object.assign(healthUpdateForm, {
        civil_status: health.civil_status || '',
        household_monthly_income: health.household_monthly_income || '',
        father_occupation: health.father_occupation || '',
        mother_occupation: health.mother_occupation || '',
        smoking_status: health.smoking_status || '',
        alcohol_consumption: health.alcohol_consumption || '',
        has_medical_condition: health.has_medical_condition,
        has_medication: health.has_medication,
        specific_conditions: [...(health.specific_conditions || [])],
        other_condition: health.other_condition || '',
        medical_condition_details: health.medical_condition_details || '',
        medication_details: health.medication_details || '',
        clearance_granted: health.clearance_granted,
    });
};
const saveHealthUpdate = () => {
    if (!editingHealth.value) return;
    healthUpdateForm.patch(updateHealth.url(editingHealth.value.id), {
        preserveScroll: true,
        onSuccess: () => { editingHealth.value = null; selectedHealth.value = null; },
    });
};
const switchTab = (tab: string) => {
    activeTab.value = tab;
    selectedStatus.value = '';
    applyFilters();
};
const deleteTarget = ref<any>(null);
const isDeleting = ref(false);
const deleteError = ref('');
const deletingHealth = ref(false);

const requestDelete = (parq: any, health = false) => {
    deletingHealth.value = health;
    deleteError.value = '';
    deleteTarget.value = parq;
};

const deleteEntry = () => {
    if (!deleteTarget.value || isDeleting.value) return;
    isDeleting.value = true;
    deleteError.value = '';
    router.delete(deletingHealth.value ? destroyHealth.url(deleteTarget.value.id) : destroy.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (activeParq.value?.id === deleteTarget.value?.id) closePreview();
            deleteTarget.value = null;
            selectedHealth.value = null;
        },
        onError: () => { deleteError.value = 'Unable to delete this entry. Please try again.'; },
        onFinish: () => { isDeleting.value = false; },
    });
};

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        index.url(),
        {
            search: search.value,
            campus: selectedCampus.value,
            term: selectedTerm.value,
            status: selectedStatus.value,
            per_page: perPage.value,
            tab: activeTab.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => (isLoading.value = false),
        }
    );
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 500);

const handleCampusChange = () => {
    selectedTerm.value = '';
    applyFilters();
};

watch(search, () => {
    debouncedSearch();
});

const resetFilters = () => {
    search.value = '';
    selectedCampus.value = '';
    selectedTerm.value = '';
    selectedStatus.value = '';
    perPage.value = 20;
    applyFilters();
};

const pendingStatusChange = ref<{ id: number; status: string; name: string } | null>(null);
const isUpdatingStatus = ref(false);
const statusError = ref('');
const statusLabels: Record<string, string> = {
    verified: 'Verified',
    pending_evaluation: 'Pending Evaluation',
    pending: 'Clearance Required',
};
const updateStatus = (parqId: number, status: string) => {
    const parq = props.parqs.data.find((entry: any) => entry.id === parqId) || activeParq.value;
    statusError.value = '';
    pendingStatusChange.value = { id: parqId, status, name: parq?.user?.name || 'this student' };
};
const confirmStatusChange = () => {
    if (!pendingStatusChange.value || isUpdatingStatus.value) return;
    const { id: parqId, status } = pendingStatusChange.value;
    isUpdatingStatus.value = true;
    router.patch(updateParqStatus.url(parqId), { status }, {
        preserveScroll: true,
        onSuccess: () => {
            pendingStatusChange.value = null;
            if (activeParq.value && activeParq.value.id === parqId) {
                activeParq.value = props.parqs.data.find((p: any) => p.id === parqId) || activeParq.value;
            }
        },
        onError: () => { statusError.value = 'Unable to update the status. Please try again.'; },
        onFinish: () => { isUpdatingStatus.value = false; },
    });
};

const isPreviewOpen = ref(false);
const activeParq = ref<any>(null);
const previewZoom = ref(100);

const openPreview = (parq: any) => {
    activeParq.value = parq;
    previewZoom.value = 100;
    isPreviewOpen.value = true;
};

const closePreview = () => {
    isPreviewOpen.value = false;
    activeParq.value = null;
    previewZoom.value = 100;
};

const isPdf = (path: string | null | undefined) => {
    if (!path) return false;
    return path.toLowerCase().endsWith('.pdf');
};

const exportCSV = () => {
    if (activeTab.value === 'health') {
        const headers = ['Student', 'ID Number', 'Campus', 'Term', 'Medical Condition', 'Conditions', 'Other Condition', 'Condition Details', 'Medication Affecting Exercise', 'Medication Details'];
        const rows = props.healthQuestionnaires.data.map((health: any) => [
            health.user?.name || '', health.user?.id_number || '', health.user?.campus_name || '',
            health.term ? `AY ${health.term.school_year} - ${health.term.semester}` : health.term_id,
            health.has_medical_condition ? 'Yes' : 'No', (health.specific_conditions || []).join('; '),
            health.other_condition || '', health.medical_condition_details || '',
            health.has_medication ? 'Yes' : 'No', health.medication_details || '',
        ]);
        const csv = [headers, ...rows].map((row: any[]) => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `pft_health_answers_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
        return;
    }
    const headers = ['Student Name', 'ID Number', 'Campus', 'Academic Term', 'Status', 'Verified By'];
    const rows = props.parqs.data.map((parq: any) => [
        parq.user?.name || '',
        parq.user?.id_number || '',
        parq.user?.campus_name || '',
        `AY ${parq.term?.school_year} - ${parq.term?.semester}`,
        parq.clearance_status === 'verified' ? 'Verified' : (parq.clearance_status === 'pending_evaluation' ? 'Pending Evaluation' : 'Clearance Required'),
        parq.verifier?.name || 'N/A'
    ]);
    const csvContent = "data:text/csv;charset=utf-8," 
        + [headers.join(','), ...rows.map((e: any) => e.map((val: any) => `"${val}"`).join(','))].join('\n');
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `pft_parq_entries_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head title="PFT PAR-Q" />

    <div class="min-h-screen font-sans bg-slate-50 text-slate-800 lg:flex dark:bg-slate-950">
        <FitnessIntelligenceSidebar active="parq" />

        <main id="parq" class="flex min-w-0 flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950">
        <!-- 1. HEADER & KPI STATS -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start justify-between gap-3 lg:block">
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                        <Activity class="h-5 w-5 text-blue-600" /> PFT Health & PAR-Q Answers
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Review and verify student physical activity readiness questionnaires and medical clearances.
                    </p>
                </div>
                <div class="lg:hidden">
                    <AppearanceToggle />
                </div>
            </div>
            
            <!-- Compact Statistic Cards -->
            <div v-if="activeTab === 'parq'" class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:flex lg:items-center">
                <div class="hidden lg:block">
                    <AppearanceToggle />
                </div>
                <!-- Total Records -->
                <div class="rounded-xl border border-slate-200 bg-white p-2 px-3 shadow-xs dark:border-white/10 dark:bg-slate-900 min-w-[100px] flex-1">
                    <div class="text-[9px] font-semibold uppercase tracking-wider text-slate-500">Total</div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ stats.total }}</div>
                </div>
                <!-- Verified Count -->
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/20 p-2 px-3 shadow-xs dark:border-emerald-500/10 dark:bg-emerald-500/5 min-w-[100px] flex-1">
                    <div class="text-[9px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Verified</div>
                    <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ stats.verified }}</div>
                </div>
                <!-- Pending Evaluation -->
                <div class="rounded-xl border border-blue-100 bg-blue-50/20 p-2 px-3 shadow-xs dark:border-blue-500/10 dark:bg-blue-500/5 min-w-[100px] flex-1">
                    <div class="text-[9px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Pending</div>
                    <div class="text-sm font-bold text-blue-700 dark:text-blue-300 mt-0.5">{{ stats.pending_evaluation }}</div>
                </div>
                <!-- Clearance Required -->
                <div class="rounded-xl border border-amber-100 bg-amber-50/20 p-2 px-3 shadow-xs dark:border-amber-500/10 dark:bg-amber-500/5 min-w-[100px] flex-1">
                    <div class="text-[9px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Required</div>
                    <div class="text-sm font-bold text-amber-700 dark:text-amber-300 mt-0.5">{{ stats.pending }}</div>
                </div>
            </div>
        </div>

        <!-- 2. FILTER SECTION -->
        <div class="flex gap-2" aria-label="Questionnaire tabs">
            <Button :variant="activeTab === 'parq' ? 'default' : 'outline'" :aria-pressed="activeTab === 'parq'" @click="switchTab('parq')">PAR-Q</Button>
            <Button :variant="activeTab === 'health' ? 'default' : 'outline'" :aria-pressed="activeTab === 'health'" @click="switchTab('health')">Health Condition</Button>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-slate-900">
            <div class="flex flex-col gap-2.5 lg:flex-row lg:items-center">
                <!-- Search Student Input -->
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                    <Input
                        v-model="search"
                        placeholder="Search student name or ID..."
                        class="h-8.5 pl-9 text-xs rounded-md border-slate-200 dark:border-white/10 focus-visible:ring-blue-500"
                    />
                </div>
                
                <!-- Filter Dropdowns -->
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:w-auto lg:flex lg:items-center">
                    <!-- Campus -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedCampus" class="h-8.5 w-full appearance-none rounded-md border border-slate-200 bg-transparent pl-3 pr-8 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:border-white/10 dark:bg-slate-900 text-slate-700 dark:text-slate-300" @change="handleCampusChange">
                            <option value="">All Campuses</option>
                            <option v-for="campus in options.campuses" :key="campus.value" :value="campus.value">
                                {{ campus.label }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-2.5 top-2.5 h-3.5 w-3.5 pointer-events-none text-slate-400" />
                    </div>

                    <!-- Term -->
                    <div class="relative min-w-[140px]">
                        <select v-model="selectedTerm" class="h-8.5 w-full appearance-none rounded-md border border-slate-200 bg-transparent pl-3 pr-8 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:border-white/10 dark:bg-slate-900 text-slate-700 dark:text-slate-300" @change="applyFilters">
                            <option value="">All Terms</option>
                            <option v-for="term in options.terms" :key="term.value" :value="term.value">
                                {{ term.label }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-2.5 top-2.5 h-3.5 w-3.5 pointer-events-none text-slate-400" />
                    </div>

                    <!-- Status -->
                    <div v-if="activeTab === 'parq'" class="relative min-w-[140px]">
                        <select v-model="selectedStatus" class="h-8.5 w-full appearance-none rounded-md border border-slate-200 bg-transparent pl-3 pr-8 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:border-white/10 dark:bg-slate-900 text-slate-700 dark:text-slate-300" @change="applyFilters">
                            <option value="">All Statuses</option>
                            <option v-for="status in options.statuses" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                        <ChevronDown class="absolute right-2.5 top-2.5 h-3.5 w-3.5 pointer-events-none text-slate-400" />
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-1.5">
                    <Button @click="applyFilters" class="h-8.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow-xs text-xs">
                        Search
                    </Button>
                    <Button @click="resetFilters" variant="outline" class="h-8.5 px-3 border-slate-200 hover:bg-slate-50 rounded-md text-slate-600 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800 text-xs">
                        Reset
                    </Button>
                </div>
            </div>
        </div>

        <!-- 3. TABLE TOOLBAR -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between px-0.5">
            <!-- Left: Page Size -->
            <div class="flex items-center gap-2">
                <select v-model="perPage" class="h-8 w-16 rounded-md border border-slate-200 bg-transparent px-2 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 dark:border-white/10 dark:bg-slate-900" @change="applyFilters">
                    <option v-for="size in pageSizeOptions" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
                <span class="text-xs text-slate-500 font-medium">per page</span>
                <span v-if="isLoading" class="text-xs text-blue-600 font-semibold animate-pulse ml-2 flex items-center gap-1">
                    <RefreshCw class="h-3 w-3 animate-spin" /> Updating...
                </span>
            </div>

            <!-- Center: Record info -->
            <div class="text-xs text-slate-600 dark:text-slate-400 font-medium sm:text-center">
                Showing {{ displayedRecords.from || 0 }}–{{ displayedRecords.to || 0 }} of {{ displayedRecords.total }} records
            </div>

            <!-- Right: Table Actions -->
            <div class="flex items-center gap-1.5">
                <Button variant="outline" size="sm" @click="exportCSV" class="h-8 gap-1 border-slate-200 text-xs hover:bg-slate-50 dark:border-white/10 dark:hover:bg-slate-800 font-medium">
                    <Download class="h-3 w-3" /> Export
                </Button>
                <Button variant="outline" size="sm" @click="applyFilters" class="h-8 gap-1 border-slate-200 text-xs hover:bg-slate-50 dark:border-white/10 dark:hover:bg-slate-800 font-medium">
                    <RefreshCw class="h-3 w-3" :class="{ 'animate-spin': isLoading }" /> Refresh
                </Button>
            </div>
        </div>

        <!-- 4. TABLE SECTION -->
        <div v-if="activeTab === 'parq'" class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs dark:border-white/10 dark:bg-slate-950 relative">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50/50 font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-900/50 dark:text-slate-400 sticky top-0 backdrop-blur-sm z-10">
                        <tr>
                            <th class="px-4 py-2.5">Student</th>
                            <th class="px-4 py-2.5">Campus & Term</th>
                            <th class="px-4 py-2.5">Details</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Medical Clearance</th>
                            <th class="px-4 py-2.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="parq in parqs.data" :key="parq.id" class="hover:bg-slate-50/40 dark:hover:bg-white/5 transition duration-150 ease-in-out" :class="{ 'bg-blue-50/10 dark:bg-blue-500/5': parq.clearance_status === 'pending_evaluation' }">
                            <!-- Student Details -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-semibold text-slate-900 dark:text-white text-xs">{{ parq.user?.name }}</span>
                                    <span class="text-[10px] text-slate-500 mt-0.5">{{ parq.user?.id_number }}</span>
                                </div>
                            </td>
                            <!-- Campus & Term -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-medium text-slate-700 dark:text-slate-300 text-xs">{{ parq.user?.campus_name || 'N/A' }}</span>
                                    <span class="text-[10px] text-slate-500 mt-0.5">AY {{ parq.term?.school_year }} - {{ parq.term?.semester }} · {{ parq.test_phase === 'post' ? 'Post-test' : 'Pre-test' }}</span>
                                </div>
                            </td>
                            <!-- Questionnaire Yes Answers -->
                            <td class="px-4 py-2">
                                <div class="max-w-xs space-y-0.5 text-[10px] text-slate-600 dark:text-slate-400">
                                    <div v-if="parq.q1" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Heart Condition / Doctor Rec</div>
                                    <div v-if="parq.q2" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Chest Pain During Activity</div>
                                    <div v-if="parq.q3" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Chest Pain (Past Month)</div>
                                    <div v-if="parq.q4" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Dizziness / Loss of Balance</div>
                                    <div v-if="parq.q5" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Bone/Joint Problem</div>
                                    <div v-if="parq.q6" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Prescribed BP/Heart Drugs</div>
                                    <div v-if="parq.q7" class="flex items-center gap-1"><span class="h-1 w-1 rounded-full bg-red-500"></span> Other Reason</div>
                                    <div v-if="!(parq.q1 || parq.q2 || parq.q3 || parq.q4 || parq.q5 || parq.q6 || parq.q7)" class="text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                        <CheckCircle2 class="size-3" /> No conditions reported
                                    </div>
                                </div>
                            </td>
                            <!-- Status Badges -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <!-- Verified -->
                                <span v-if="parq.clearance_status === 'verified'" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200/50 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20">
                                    <CheckCircle2 class="size-3" /> Verified
                                </span>
                                <!-- Pending Evaluation -->
                                <span v-else-if="parq.clearance_status === 'pending_evaluation'" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200/50 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20">
                                    <Clock class="size-3 animate-pulse" /> Pending
                                </span>
                                <!-- Clearance Required -->
                                <span v-else class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 border border-amber-200/50 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20">
                                    <XCircle class="size-3" /> Clearance Required
                                </span>
                            </td>
                            <!-- Medical Clearance actions -->
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <!-- Grouped Button for actions -->
                                    <div class="inline-flex rounded-md border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900 shadow-xs overflow-hidden">
                                        <Button
                                            v-if="parq.medical_clearance_path"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 rounded-none border-r border-slate-200 dark:border-white/10 text-[11px] px-2.5 hover:bg-slate-50 dark:hover:bg-slate-800"
                                            @click="openPreview(parq)"
                                        >
                                            <FileText class="size-3" /> Preview
                                        </Button>
                                        <span v-else class="h-7 flex items-center px-2.5 border-r border-slate-200 dark:border-white/10 text-[10px] text-slate-400 italic">
                                            No Document
                                        </span>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-7 gap-0.5 rounded-none text-[11px] px-2 hover:bg-slate-50 dark:hover:bg-slate-800 font-semibold"
                                                >
                                                    Status <ChevronDown class="size-3 text-slate-500" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-[190px] rounded-lg shadow-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-1 z-[9999]">
                                                <DropdownMenuItem @click="updateStatus(parq.id, 'verified')" :disabled="parq.clearance_status === 'verified'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                                    <CheckCircle2 class="size-3.5 text-emerald-500" /> Mark as Verified
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="updateStatus(parq.id, 'pending_evaluation')" :disabled="parq.clearance_status === 'pending_evaluation'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                                    <Clock class="size-3.5 text-blue-500" /> Mark as Pending Evaluation
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="updateStatus(parq.id, 'pending')" :disabled="parq.clearance_status === 'pending'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                                    <XCircle class="size-3.5 text-amber-500" /> Mark as Clearance Required
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <Button variant="outline" size="sm" class="mr-2 h-7 text-xs" @click="openPreview(parq)">View answers</Button>
                                <Button variant="outline" size="sm" class="h-7 gap-1 text-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950" @click="requestDelete(parq)">
                                    <Trash2 class="size-3.5" /> Delete
                                </Button>
                            </td>
                        </tr>
                        
                        <!-- Empty State -->
                        <tr v-if="parqs.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="h-12 w-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 dark:bg-slate-900 mb-3 shadow-inner">
                                        <Search class="h-6 w-6" />
                                    </div>
                                    <h3 class="text-xs font-semibold text-slate-900 dark:text-white">No entries found</h3>
                                    <p class="text-[10px] text-slate-500 mt-0.5">We couldn't find any PAR-Q entries matching your current filters. Try adjusting your search query or reset the filters.</p>
                                    <Button variant="outline" @click="resetFilters" class="mt-3 h-7.5 text-xs border-slate-200 rounded-md">
                                        Reset Filters
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- 7. PAGINATION SECTION -->
            <div class="flex items-center justify-center border-t border-slate-100 p-2.5 dark:border-white/10" v-if="parqs.links">
                <div class="flex items-center gap-1">
                    <Link
                        v-for="(link, idx) in parqs.links"
                        :key="idx"
                        :href="link.url || '#'"
                        class="flex min-w-[28px] h-7 items-center justify-center rounded-lg text-xs font-semibold transition-all duration-200"
                        :class="[
                            link.active
                                ? 'bg-blue-600 text-white shadow-xs'
                                : link.url
                                  ? 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 dark:bg-slate-900 dark:border-white/10 dark:text-slate-300 dark:hover:bg-slate-800'
                                  : 'cursor-not-allowed bg-slate-50 text-slate-400 border border-slate-100 opacity-50 dark:bg-white/5 dark:text-slate-600 dark:border-white/5',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Document Preview Dialog -->
        <Dialog :open="pendingStatusChange !== null" @update:open="(open) => { if (!open && !isUpdatingStatus) pendingStatusChange = null; }">
            <DialogContent class="z-[100] sm:max-w-md" :show-close-button="!isUpdatingStatus" @escape-key-down="(event) => { if (isUpdatingStatus) event.preventDefault(); }" @interact-outside="(event) => { if (isUpdatingStatus) event.preventDefault(); }">
                <DialogHeader>
                    <DialogTitle>Confirm status change</DialogTitle>
                    <DialogDescription>
                        Change the PAR-Q status for {{ pendingStatusChange?.name }} to
                        {{ statusLabels[pendingStatusChange?.status || ''] }}?
                    </DialogDescription>
                </DialogHeader>
                <p v-if="statusError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ statusError }}</p>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" :disabled="isUpdatingStatus" @click="pendingStatusChange = null">Cancel</Button>
                    <Button :disabled="isUpdatingStatus" @click="confirmStatusChange">{{ isUpdatingStatus ? 'Updating...' : 'Confirm' }}</Button>
                </div>
            </DialogContent>
        </Dialog>
        <div v-if="activeTab === 'health'" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-white/10 dark:bg-slate-950">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Campus & Term</th>
                            <th class="px-4 py-3">Existing Medical Condition</th>
                            <th class="px-4 py-3">Medication Affecting Exercise</th>
                            <th class="px-4 py-3">Clearance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="health in healthQuestionnaires.data" :key="health.id" class="align-top">
                            <td class="px-4 py-3"><p class="font-semibold text-slate-900 dark:text-white">{{ health.user?.name }}</p><p class="text-slate-500">{{ health.user?.id_number }}</p></td>
                            <td class="px-4 py-3"><p>{{ health.user?.campus_name || 'N/A' }}</p><p class="text-slate-500">{{ health.term ? `AY ${health.term.school_year} - ${health.term.semester}` : health.term_id }} · {{ health.test_phase === 'post' ? 'Post-test' : 'Pre-test' }}</p></td>
                            <td class="max-w-sm px-4 py-3">
                                <p :class="health.has_medical_condition ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">{{ health.has_medical_condition ? 'Yes' : 'No' }}</p>
                                <p v-if="health.specific_conditions?.length" class="mt-1">{{ health.specific_conditions.join(', ') }}</p>
                                <p v-if="health.other_condition" class="mt-1">Other: {{ health.other_condition }}</p>
                                <p v-if="health.medical_condition_details" class="mt-1 text-slate-500">{{ health.medical_condition_details }}</p>
                            </td>
                            <td class="max-w-sm px-4 py-3"><p :class="health.has_medication ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">{{ health.has_medication ? 'Yes' : 'No' }}</p><p v-if="health.medication_details" class="mt-1">{{ health.medication_details }}</p></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 whitespace-nowrap">
                                <p class="text-xs font-semibold" :class="health.clearance_granted ? 'text-emerald-600' : 'text-amber-600'">{{ health.clearance_granted ? 'Clearance granted' : health.medical_clearance_path ? 'Awaiting review' : 'No clearance' }}</p>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="size-7 shrink-0" :aria-label="`Actions for ${health.user?.name || 'health submission'}`"><MoreVertical class="size-4" /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @select="selectedHealth = health">View answers</DropdownMenuItem>
                                        <DropdownMenuItem @select="openHealthUpdate(health)">Update</DropdownMenuItem>
                                        <DropdownMenuItem class="text-red-600 focus:text-red-600" @select="requestDelete(health, true)">Delete</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!healthQuestionnaires.data.length"><td colspan="5" class="px-4 py-10 text-center text-slate-500">No health condition answers found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-center gap-1 border-t border-slate-100 p-3 dark:border-white/10">
                <Link v-for="(link, idx) in healthQuestionnaires.links" :key="idx" :href="link.url || '#'" :preserve-scroll="true" class="rounded-lg px-3 py-1 text-xs" :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'text-slate-600 dark:text-slate-300' : 'pointer-events-none text-slate-300'" v-html="link.label" />
            </div>
        </div>
        <Dialog :open="selectedHealth !== null" @update:open="(open) => { if (!open) selectedHealth = null; }">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Health Questionnaire Answers</DialogTitle>
                    <DialogDescription>{{ selectedHealth?.user?.name }} — {{ selectedHealth?.term ? `AY ${selectedHealth.term.school_year} - ${selectedHealth.term.semester}` : selectedHealth?.term_id }}</DialogDescription>
                </DialogHeader>
                <dl v-if="selectedHealth" class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div v-for="(label, field) in { civil_status: 'Civil Status', household_monthly_income: 'Household Monthly Income', father_occupation: `Father’s Occupation`, mother_occupation: `Mother’s Occupation`, smoking_status: 'Smoking Status', alcohol_consumption: 'Alcohol Consumption', other_condition: 'Other Condition', medical_condition_details: 'Condition Details', medication_details: 'Medication Details' }" :key="field">
                        <dt class="text-slate-500">{{ label }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ selectedHealth[field] || 'Not provided' }}</dd>
                    </div>
                    <div><dt class="text-slate-500">Existing Medical Condition</dt><dd>{{ selectedHealth.has_medical_condition ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-slate-500">Medication Affecting Exercise</dt><dd>{{ selectedHealth.has_medication ? 'Yes' : 'No' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Selected Conditions</dt><dd class="mt-1">{{ selectedHealth.specific_conditions?.join(', ') || 'None selected' }}</dd></div>
                </dl>
                <div v-if="selectedHealth?.medical_clearance_path" class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <p class="text-sm font-semibold">Uploaded Medical Certificate</p>
                    <a :href="`/storage/${selectedHealth.medical_clearance_path}`" target="_blank" rel="noopener" class="text-sm text-blue-600 underline">Open medical certificate</a>
                    <iframe v-if="isPdf(selectedHealth.medical_clearance_path)" :src="`/storage/${selectedHealth.medical_clearance_path}`" title="Medical certificate" class="h-80 w-full rounded border" />
                    <img v-else :src="`/storage/${selectedHealth.medical_clearance_path}`" alt="Medical certificate" class="max-h-96 w-full object-contain" />
                </div>
                <p v-else class="rounded-lg border p-4 text-sm text-slate-500">No medical certificate uploaded.</p>
                <Button v-if="selectedHealth" @click="openHealthUpdate(selectedHealth); selectedHealth = null">Update / Grant Clearance</Button>
            </DialogContent>
        </Dialog>

        <Dialog :open="editingHealth !== null" @update:open="(open) => { if (!open && !healthUpdateForm.processing) editingHealth = null; }">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader><DialogTitle class="text-lg font-semibold">Health Questionnaire</DialogTitle><DialogDescription>Please provide your updated health and socio-demographic information.</DialogDescription></DialogHeader>
                <form class="space-y-4" @submit.prevent="saveHealthUpdate">
                    <div class="space-y-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Civil Status</Label>
                        <select
                            v-model="healthUpdateForm.civil_status"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="">Select...</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label>Household Monthly Income</Label>
                        <select
                            v-model="healthUpdateForm.household_monthly_income"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="">Select...</option>
                            <option value="Less than ₱10,000">Less than ₱10,000</option>
                            <option value="₱10,000–₱19,999">₱10,000–₱19,999</option>
                            <option value="₱20,000–₱39,999">₱20,000–₱39,999</option>
                            <option value="₱40,000–₱59,999">₱40,000–₱59,999</option>
                            <option value="₱60,000–₱99,999">₱60,000–₱99,999</option>
                            <option value="₱100,000 and above">₱100,000 and above</option>
                            <option value="Prefer not to answer">Prefer not to answer</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Father's Occupation</Label>
                        <Input v-model="healthUpdateForm.father_occupation" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2">
                        <Label>Mother's Occupation</Label>
                        <Input v-model="healthUpdateForm.mother_occupation" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Smoking Status</Label>
                        <select
                            v-model="healthUpdateForm.smoking_status"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="">Select...</option>
                            <option value="Never">Never</option>
                            <option value="Former">Former</option>
                            <option value="Current">Current</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label>Alcohol Consumption</Label>
                        <select
                            v-model="healthUpdateForm.alcohol_consumption"
                            class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="">Select...</option>
                            <option value="Never">Never</option>
                            <option value="Occasional">Occasional</option>
                            <option value="Regular">Regular</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-3">
                    <Label class="text-base font-medium">Do you have any existing medical condition?</Label>
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center space-x-2">
                            <input type="radio" :value="false" v-model="healthUpdateForm.has_medical_condition" class="text-emerald-600 focus:ring-emerald-500 h-4 w-4 rounded-full border-slate-300" />
                            <span>No</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" :value="true" v-model="healthUpdateForm.has_medical_condition" class="text-emerald-600 focus:ring-emerald-500 h-4 w-4 rounded-full border-slate-300" />
                            <span>Yes</span>
                        </label>
                    </div>
                    <div v-if="healthUpdateForm.has_medical_condition" class="space-y-3 rounded-md bg-slate-50 p-4 dark:bg-white/5">
                        <Label class="font-medium">Select all that apply:</Label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="condition in [...new Set([...options.medicalConditions, ...healthUpdateForm.specific_conditions])]" :key="condition" class="flex items-start space-x-2">
                                <input type="checkbox" :value="condition" v-model="healthUpdateForm.specific_conditions" class="text-emerald-600 focus:ring-emerald-500 mt-1 h-4 w-4 rounded border-slate-300" />
                                <span class="text-sm leading-tight">{{ condition }}</span>
                            </label>
                        </div>

                        <div class="space-y-2 pt-2">
                            <Label>Other Condition (Specify)</Label>
                            <Input v-model="healthUpdateForm.other_condition" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900" />
                        </div>

                        <div class="space-y-2 pt-2">
                            <Label>Further Details (Optional)</Label>
                            <Input v-model="healthUpdateForm.medical_condition_details" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900" placeholder="Additional details..." />
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <Label class="text-base font-medium">Current Medication Affecting Exercise</Label>
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center space-x-2">
                            <input type="radio" :value="false" v-model="healthUpdateForm.has_medication" class="text-emerald-600 focus:ring-emerald-500 h-4 w-4 rounded-full border-slate-300" />
                            <span>No</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" :value="true" v-model="healthUpdateForm.has_medication" class="text-emerald-600 focus:ring-emerald-500 h-4 w-4 rounded-full border-slate-300" />
                            <span>Yes</span>
                        </label>
                    </div>
                    <div v-if="healthUpdateForm.has_medication" class="space-y-2">
                        <Label>If Yes, Please Specify</Label>
                        <Input v-model="healthUpdateForm.medication_details" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900" />
                    </div>
                </div>


</div>
                    <div v-if="editingHealth?.medical_clearance_path" class="space-y-2">
                        <a :href="`/storage/${editingHealth.medical_clearance_path}`" target="_blank" rel="noopener" class="text-sm text-blue-600 underline">Open medical certificate</a>
                        <iframe v-if="isPdf(editingHealth.medical_clearance_path)" :src="`/storage/${editingHealth.medical_clearance_path}`" title="Medical certificate" class="h-72 w-full rounded border" />
                        <img v-else :src="`/storage/${editingHealth.medical_clearance_path}`" alt="Medical certificate" class="max-h-72 w-full object-contain" />
                    </div>
                    <p v-else class="text-sm text-amber-600">The student must upload a medical certificate before clearance can be granted.</p>
                    <label class="flex items-center gap-2 rounded border p-3 text-sm font-semibold"><input v-model="healthUpdateForm.clearance_granted" type="checkbox" :disabled="!editingHealth?.medical_clearance_path" /> Grant Clearance to take the test</label>
                    <p v-for="(error, field) in healthUpdateForm.errors" :key="field" class="text-sm text-red-600" role="alert">{{ error }}</p>
                    <div class="flex justify-end gap-2"><Button type="button" variant="outline" :disabled="healthUpdateForm.processing" @click="editingHealth = null">Cancel</Button><Button type="submit" :disabled="healthUpdateForm.processing">{{ healthUpdateForm.processing ? 'Saving...' : 'Save update' }}</Button></div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleteTarget !== null" @update:open="(open) => { if (!open && !isDeleting) deleteTarget = null; }">
            <DialogContent class="sm:max-w-md" @escape-key-down="(event) => { if (isDeleting) event.preventDefault(); }" @interact-outside="(event) => { if (isDeleting) event.preventDefault(); }">
                <DialogHeader>
                    <DialogTitle>Delete {{ deletingHealth ? 'health submission' : 'PAR-Q entry' }}?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete the {{ deletingHealth ? 'health submission' : 'PAR-Q entry' }} for {{ deleteTarget?.user?.name }}
                        (AY {{ deleteTarget?.term?.school_year }} - {{ deleteTarget?.term?.semester }}).
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <p v-if="deleteError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ deleteError }}</p>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" :disabled="isDeleting" @click="deleteTarget = null">Cancel</Button>
                    <Button variant="destructive" :disabled="isDeleting" @click="deleteEntry">
                        {{ isDeleting ? 'Deleting...' : 'Delete entry' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="isPreviewOpen" @update:open="closePreview">
            <DialogContent class="sm:max-w-5xl max-h-[90vh] flex flex-col p-5 rounded-xl">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-slate-900 dark:text-white">Medical Clearance & PAR-Q Review</DialogTitle>
                    <DialogDescription class="text-[11px] mt-0.5">
                        Reviewing records for <strong>{{ activeParq?.user?.name }}</strong> ({{ activeParq?.user?.id_number }}).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mt-3 overflow-hidden flex-1">
                    <!-- Left Side: PAR-Q Answers (col-span-5) -->
                    <div class="lg:col-span-5 flex flex-col gap-3 overflow-y-auto pr-1">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">PAR-Q Answers</h3>
                        <div class="space-y-2">
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">1. Heart Condition / Doctor Rec</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Has a doctor said you have a heart condition?</span>
                                    <span :class="[activeParq?.q1 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q1 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">2. Chest Pain During Activity</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Do you feel chest pain when you do activity?</span>
                                    <span :class="[activeParq?.q2 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q2 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">3. Chest Pain (Past Month)</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Have you had chest pain when not doing activity?</span>
                                    <span :class="[activeParq?.q3 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q3 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">4. Dizziness / Loss of Balance</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Do you lose balance or consciousness?</span>
                                    <span :class="[activeParq?.q4 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q4 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">5. Bone / Joint Problem</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Do you have a bone/joint problem?</span>
                                    <span :class="[activeParq?.q5 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q5 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">6. BP / Heart Drugs</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Are you currently taking drugs for BP/heart?</span>
                                    <span :class="[activeParq?.q6 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q6 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-white/5 dark:bg-white/5 flex flex-col gap-0.5">
                                <span class="text-[10px] font-semibold text-slate-800 dark:text-slate-200">7. Other Reason</span>
                                <div class="flex items-center justify-between text-[10px] mt-0.5">
                                    <span class="text-slate-500">Any other reason why you should not do activity?</span>
                                    <span :class="[activeParq?.q7 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', 'px-1.5 py-0.2 rounded font-bold text-[8px]']">
                                        {{ activeParq?.q7 ? 'YES' : 'NO' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Document Preview (col-span-7) -->
                    <div class="lg:col-span-7 flex flex-col gap-3 overflow-hidden">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Uploaded Document</h3>
                            <div
                                v-if="activeParq?.medical_clearance_path"
                                class="flex w-full items-center gap-2 rounded-md border border-slate-200 bg-white px-2 py-1.5 dark:border-white/10 dark:bg-slate-900 sm:w-56"
                            >
                                <ZoomOut class="size-3.5 shrink-0 text-slate-400" />
                                <input
                                    v-model.number="previewZoom"
                                    type="range"
                                    min="50"
                                    max="200"
                                    step="10"
                                    aria-label="Document zoom"
                                    class="h-1.5 w-full cursor-pointer accent-blue-600"
                                />
                                <ZoomIn class="size-3.5 shrink-0 text-slate-400" />
                                <span class="w-9 text-right text-[10px] font-semibold text-slate-500">{{ previewZoom }}%</span>
                            </div>
                        </div>
                        <div class="flex-1 min-h-[350px] border border-slate-200 rounded-lg bg-slate-100 dark:border-white/10 dark:bg-slate-900 p-1.5 overflow-auto">
                            <template v-if="activeParq?.medical_clearance_path">
                                <div class="flex min-h-full min-w-full items-start justify-center">
                                    <iframe
                                        v-if="isPdf(activeParq.medical_clearance_path)"
                                        :src="`/storage/${activeParq.medical_clearance_path}`"
                                        class="h-[68vh] min-h-[350px] max-w-none rounded bg-white"
                                        :style="{ width: `${previewZoom}%` }"
                                        frameborder="0"
                                    ></iframe>
                                    <img
                                        v-else
                                        :src="`/storage/${activeParq.medical_clearance_path}`"
                                        class="h-auto max-w-none rounded bg-white object-contain shadow-xs"
                                        :style="{ width: `${previewZoom}%` }"
                                        alt="Medical Clearance"
                                    />
                                </div>
                            </template>
                            <div v-else class="flex min-h-full flex-col items-center justify-center gap-1.5 text-xs font-medium text-slate-400">
                                <FileText class="size-6 text-slate-300" />
                                No document uploaded
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-3 mt-3 dark:border-white/10">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] text-slate-500 font-medium">Current Status:</span>
                        <span v-if="activeParq?.clearance_status === 'verified'" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-semibold text-emerald-700 border border-emerald-200/50">
                            <CheckCircle2 class="size-3" /> Verified
                        </span>
                        <span v-else-if="activeParq?.clearance_status === 'pending_evaluation'" class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-semibold text-blue-700 border border-blue-200/50">
                            <Clock class="size-3" /> Pending Evaluation
                        </span>
                        <span v-else class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-semibold text-amber-700 border border-amber-200/50">
                            <XCircle class="size-3" /> Clearance Required
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm" class="h-8 gap-1 bg-slate-50 border-slate-200 hover:bg-slate-100 dark:bg-slate-900 dark:border-white/10 dark:hover:bg-slate-800 text-xs">
                                    Change Status <ChevronDown class="size-3.5 text-slate-500" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-[190px] rounded-lg shadow-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 p-1 z-[9999]">
                                <DropdownMenuItem @click="updateStatus(activeParq?.id, 'verified')" :disabled="activeParq?.clearance_status === 'verified'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                    <CheckCircle2 class="mr-1.5 size-4 text-emerald-500" /> Mark as Verified
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="updateStatus(activeParq?.id, 'pending_evaluation')" :disabled="activeParq?.clearance_status === 'pending_evaluation'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                    <Clock class="mr-1.5 size-4 text-blue-500" /> Mark as Pending Evaluation
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="updateStatus(activeParq?.id, 'pending')" :disabled="activeParq?.clearance_status === 'pending'" class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                                    <XCircle class="mr-1.5 size-4 text-amber-500" /> Mark as Clearance Required
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button variant="secondary" size="sm" class="h-8 text-xs" @click="closePreview">
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
        </main>
    </div>
</template>
