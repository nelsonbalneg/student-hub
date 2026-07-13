<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
    MoreHorizontal,
    Download,
    ChevronDown,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';

const props = defineProps<{
    parqs: any;
    filters: Record<string, string | undefined>;
    stats: {
        total: number;
        verified: number;
        pending_evaluation: number;
        pending: number;
    };
    options: {
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

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        '/admin/reporting/pft-parq',
        {
            search: search.value,
            campus: selectedCampus.value,
            term: selectedTerm.value,
            status: selectedStatus.value,
            per_page: perPage.value,
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

const updateStatus = (parqId: number, status: string) => {
    if (!confirm(`Are you sure you want to update the status?`)) return;
    router.patch(`/admin/reporting/pft-parq/${parqId}/update-status`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            if (activeParq.value && activeParq.value.id === parqId) {
                activeParq.value = props.parqs.data.find((p: any) => p.id === parqId) || activeParq.value;
            }
        }
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
                        <Activity class="h-5 w-5 text-blue-600" /> PFT PAR-Q Entries
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
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:flex lg:items-center">
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
                    <div class="relative min-w-[140px]">
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
                Showing {{ parqs.from || 0 }}–{{ parqs.to || 0 }} of {{ parqs.total }} records
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
        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs dark:border-white/10 dark:bg-slate-950 relative">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50/50 font-semibold text-slate-500 dark:border-white/10 dark:bg-slate-900/50 dark:text-slate-400 sticky top-0 backdrop-blur-sm z-10">
                        <tr>
                            <th class="px-4 py-2.5">Student</th>
                            <th class="px-4 py-2.5">Campus & Term</th>
                            <th class="px-4 py-2.5">Details</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Medical Clearance</th>
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
                                    <span class="text-[10px] text-slate-500 mt-0.5">AY {{ parq.term?.school_year }} - {{ parq.term?.semester }}</span>
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
                        </tr>
                        
                        <!-- Empty State -->
                        <tr v-if="parqs.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center">
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
