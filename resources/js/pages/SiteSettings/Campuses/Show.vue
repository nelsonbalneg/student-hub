<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { 
    Building2, 
    Calendar, 
    Info, 
    Plus, 
    Search, 
    Trash2, 
    Edit, 
    Power,
    ArrowLeft,
    Clock,
    History,
    CheckCircle2,
    XCircle,
    Archive,
    MapPin,
    Fingerprint,
    RefreshCcw
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { format } from 'date-fns';
import * as campusRoutes from '@/routes/site-settings/campuses';
import * as termRoutes from '@/routes/site-settings/campuses/terms';

defineOptions({
    layout: SiteSettingsLayout,
});

interface AcademicTerm {
    id: number;
    school_year: string;
    semester: string;
    term_id: string;
    status: 'Active' | 'Inactive' | 'Archived';
    start_date: string;
    end_date: string;
}

interface Campus {
    id: number;
    campus_name: string;
    campus_address: string;
    campus_logo_path: string;
    real_campus_id: string;
    status: 'Active' | 'Inactive';
    academic_terms: AcademicTerm[];
}

const props = defineProps<{
    campus: Campus;
}>();

const showTermModal = ref(false);
const showDeleteTermModal = ref(false);
const showEditModal = ref(false);
const selectedTerm = ref<AcademicTerm | null>(null);
const searchTerm = ref('');
const activeTab = ref('info');

const form = useForm({
    campus_name: props.campus.campus_name,
    campus_address: props.campus.campus_address,
    real_campus_id: props.campus.real_campus_id,
    status: props.campus.status,
    logo: null as File | null,
});

const termForm = useForm({
    school_year: '',
    semester: '',
    term_id: '',
    status: 'Inactive' as 'Active' | 'Inactive' | 'Archived',
    start_date: '',
    end_date: '',
});

const openEditModal = () => {
    form.campus_name = props.campus.campus_name;
    form.campus_address = props.campus.campus_address;
    form.real_campus_id = props.campus.real_campus_id;
    form.status = props.campus.status;
    form.logo = null;
    showEditModal.value = true;
};

const updateCampus = () => {
    form.post(campusRoutes.update.url(props.campus.id), {
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};

const filteredTerms = computed(() => {
    return props.campus.academic_terms.filter(term => 
        term.school_year.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
        term.semester.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
        term.term_id?.toLowerCase().includes(searchTerm.value.toLowerCase())
    ).sort((a, b) => b.id - a.id);
});

const openCreateTerm = () => {
    selectedTerm.value = null;
    termForm.reset();
    showTermModal.value = true;
};

const openEditTerm = (term: AcademicTerm) => {
    selectedTerm.value = term;
    termForm.school_year = term.school_year;
    termForm.semester = term.semester;
    termForm.term_id = term.term_id || '';
    termForm.status = term.status;
    termForm.start_date = term.start_date || '';
    termForm.end_date = term.end_date || '';
    showTermModal.value = true;
};

const saveTerm = () => {
    if (selectedTerm.value) {
        termForm.patch(termRoutes.update.url({ campus: props.campus.id, term: selectedTerm.value.id }), {
            onSuccess: () => showTermModal.value = false,
        });
    } else {
        termForm.post(termRoutes.store.url(props.campus.id), {
            onSuccess: () => showTermModal.value = false,
        });
    }
};

const activateTerm = (term: AcademicTerm) => {
    termForm.patch(termRoutes.activate.url({ campus: props.campus.id, term: term.id }), {
        onSuccess: () => {},
    });
};

const confirmDeleteTerm = (term: AcademicTerm) => {
    selectedTerm.value = term;
    showDeleteTermModal.value = true;
};

const deleteTerm = () => {
    if (!selectedTerm.value) return;
    termForm.delete(termRoutes.destroy.url({ campus: props.campus.id, term: selectedTerm.value.id }), {
        onSuccess: () => showDeleteTermModal.value = false,
    });
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'Active': return 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400';
        case 'Archived': return 'bg-slate-500/10 text-slate-600 dark:bg-slate-500/20 dark:text-slate-400';
        default: return 'bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400';
    }
};
</script>

<template>
    <Head :title="`${props.campus.campus_name} - Details`" />

    <div class="flex flex-col gap-8 p-6 lg:p-10">
        <!-- Header Section -->
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-6">
                    <div class="relative flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-3xl bg-white p-1 shadow-xl shadow-slate-200/50 ring-1 ring-slate-200 dark:bg-white/5 dark:shadow-none dark:ring-white/10">
                        <img v-if="props.campus.campus_logo_path" :src="`/storage/${props.campus.campus_logo_path}`" :alt="props.campus.campus_name" class="h-full w-full rounded-2xl object-cover" />
                        <Building2 v-else class="size-12 text-slate-200 dark:text-white/10" />
                        <div class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full bg-white p-0.5 shadow-sm dark:bg-slate-900">
                            <div :class="['h-full w-full rounded-full border-2 border-white dark:border-slate-900', props.campus.status === 'Active' ? 'bg-emerald-500' : 'bg-slate-400']"></div>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-extrabold tracking-tight text-slate-950 dark:text-white lg:text-4xl">{{ props.campus.campus_name }}</h1>
                            <Badge :variant="props.campus.status === 'Active' ? 'default' : 'secondary'" class="rounded-full px-3 py-0.5 font-bold shadow-sm">
                                {{ props.campus.status }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-2">
                                <div class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/5">
                                    <MapPin class="size-3" />
                                </div>
                                {{ props.campus.campus_address || 'No address set' }}
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/5">
                                    <Fingerprint class="size-3" />
                                </div>
                                ID: <span class="font-mono font-bold text-slate-900 dark:text-white">{{ props.campus.real_campus_id || 'Not Assigned' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button variant="outline" size="sm" as-child class="h-10 rounded-xl px-4 font-bold shadow-sm hover:bg-slate-50">
                        <Link :href="campusRoutes.index.url()">
                            <ArrowLeft class="mr-2 size-4" />
                            Back to List
                        </Link>
                    </Button>
                    <Button @click="openEditModal" class="h-10 rounded-xl bg-sky-600 px-5 font-bold shadow-lg shadow-sky-200 hover:bg-sky-700 dark:shadow-none">
                        <Edit class="mr-2 size-4" />
                        Edit Campus
                    </Button>
                </div>
            </div>

            <div class="mt-8 space-y-8">
                <!-- Tabs Navigation -->
                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-white/5">
                    <button 
                        @click="activeTab = 'info'"
                        :class="[
                            'group flex items-center gap-2.5 px-6 py-4 text-sm font-bold transition-all relative',
                            activeTab === 'info' 
                                ? 'text-sky-600' 
                                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        ]"
                    >
                        <Info :class="['size-4 transition-colors', activeTab === 'info' ? 'text-sky-600' : 'text-slate-400 group-hover:text-slate-600']" />
                        Overview
                        <div v-if="activeTab === 'info'" class="absolute bottom-0 left-0 h-1 w-full rounded-t-full bg-sky-600"></div>
                    </button>
                    <button 
                        @click="activeTab = 'terms'"
                        :class="[
                            'group flex items-center gap-2.5 px-6 py-4 text-sm font-bold transition-all relative',
                            activeTab === 'terms' 
                                ? 'text-sky-600' 
                                : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                        ]"
                    >
                        <Calendar :class="['size-4 transition-colors', activeTab === 'terms' ? 'text-sky-600' : 'text-slate-400 group-hover:text-slate-600']" />
                        Academic Terms
                        <Badge variant="secondary" :class="['ml-1.5 px-2 py-0 h-5 min-w-[20px] justify-center rounded-full text-[10px] font-black', activeTab === 'terms' ? 'bg-sky-100 text-sky-600' : '']">
                            {{ props.campus.academic_terms?.length || 0 }}
                        </Badge>
                        <div v-if="activeTab === 'terms'" class="absolute bottom-0 left-0 h-1 w-full rounded-t-full bg-sky-600"></div>
                    </button>
                </div>

            <!-- Campus Info Tab Content -->
            <div v-if="activeTab === 'info'" class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <Card class="overflow-hidden border-none shadow-sm ring-1 ring-slate-200 dark:bg-slate-900/50 dark:ring-white/5">
                        <CardHeader class="border-b border-slate-100 bg-slate-50/30 px-6 py-4 dark:border-white/5 dark:bg-white/5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400">
                                    <Building2 class="size-4" />
                                </div>
                                <CardTitle class="text-base">Identity Information</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="grid divide-y divide-slate-100 dark:divide-white/5 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                                <div class="space-y-4 p-6">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                            <Building2 class="size-3" />
                                            Official Name
                                        </div>
                                        <p class="text-base font-bold text-slate-900 dark:text-white">{{ props.campus.campus_name }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                            <Fingerprint class="size-3" />
                                            External System ID
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge variant="secondary" class="font-mono font-bold tracking-tight">
                                                {{ props.campus.real_campus_id || 'Not Set' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-6">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                            <MapPin class="size-3" />
                                            Physical Address
                                        </div>
                                        <p class="text-sm font-medium leading-relaxed text-slate-700 dark:text-slate-300">
                                            {{ props.campus.campus_address || 'No physical address provided for this campus.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="overflow-hidden border-none shadow-sm ring-1 ring-slate-200 dark:bg-slate-900/50 dark:ring-white/5">
                        <CardHeader class="border-b border-slate-100 bg-slate-50/30 px-6 py-4 dark:border-white/5 dark:bg-white/5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                                    <History class="size-4" />
                                </div>
                                <CardTitle class="text-base">System Audit</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid gap-8 sm:grid-cols-2">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                        <Clock class="size-6" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Created On</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ props.campus.created_at ? format(new Date(props.campus.created_at), 'MMMM d, yyyy') : 'N/A' }}
                                        </p>
                                        <p class="text-[10px] text-slate-500">System initialization date</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-white/10">
                                        <RefreshCcw class="size-6" />
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Last Updated</p>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ props.campus.updated_at ? format(new Date(props.campus.updated_at), 'MMMM d, yyyy') : 'N/A' }}
                                        </p>
                                        <p class="text-[10px] text-slate-500">Recent modification timestamp</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-6">
                    <Card class="overflow-hidden border-none shadow-sm ring-1 ring-slate-200 dark:bg-emerald-950/20 dark:ring-white/5">
                        <CardHeader class="border-b border-emerald-100 bg-emerald-50/50 px-6 py-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                            <CardTitle class="text-base text-emerald-800 dark:text-emerald-400">Current Status</CardTitle>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-100 dark:bg-emerald-900/40 dark:ring-emerald-500/20">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-emerald-900 dark:text-emerald-200">Campus Visibility</span>
                                    <Badge :variant="props.campus.status === 'Active' ? 'default' : 'secondary'" class="bg-emerald-600 text-white hover:bg-emerald-600 dark:bg-emerald-500">
                                        {{ props.campus.status }}
                                    </Badge>
                                </div>
                                <div class="mt-4 flex gap-3">
                                    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-200 text-emerald-700 dark:bg-emerald-500/30 dark:text-emerald-400">
                                        <CheckCircle2 class="size-3" />
                                    </div>
                                    <p class="text-xs font-medium leading-relaxed text-emerald-800/80 dark:text-emerald-400/80">
                                        {{ props.campus.status === 'Active' 
                                            ? 'This campus is fully functional and visible to authorized users and students.' 
                                            : 'This campus is currently restricted. Users may not be able to access related records.' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

                <!-- Academic Terms Tab Content -->
                <div v-if="activeTab === 'terms'" class="space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full max-w-sm">
                            <Search class="absolute left-2.5 top-2.5 size-4 text-slate-400" />
                            <Input v-model="searchTerm" placeholder="Search terms..." class="pl-9" />
                        </div>
                        <Button @click="openCreateTerm" class="bg-sky-600 hover:bg-sky-700">
                            <Plus class="mr-2 size-4" />
                            Add Term
                        </Button>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden dark:border-white/5 dark:bg-slate-950">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3">Academic Period</th>
                                    <th class="px-6 py-3">Term ID</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <th class="px-6 py-3">Start / End Date</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr v-for="term in filteredTerms" :key="term.id" class="group hover:bg-slate-50/50 dark:hover:bg-white/5">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-slate-900 dark:text-white">{{ term.school_year }}</span>
                                            <span class="text-xs text-slate-500">{{ term.semester }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs font-bold text-sky-600 dark:text-sky-400">
                                        {{ term.term_id || '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <Badge :class="getStatusBadgeClass(term.status)">
                                                <component :is="term.status === 'Active' ? CheckCircle2 : (term.status === 'Archived' ? Archive : Clock)" class="mr-1 size-3" />
                                                {{ term.status }}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-medium text-slate-600 dark:text-slate-400">
                                        <div class="flex items-center gap-2">
                                            <span>{{ term.start_date ? format(new Date(term.start_date), 'MMM d, yyyy') : 'N/A' }}</span>
                                            <span class="text-slate-300">-</span>
                                            <span>{{ term.end_date ? format(new Date(term.end_date), 'MMM d, yyyy') : 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button v-if="term.status !== 'Active'" variant="ghost" size="icon" @click="activateTerm(term)" class="size-8 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700" title="Set as Active">
                                                <Power class="size-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="openEditTerm(term)" class="size-8 text-slate-600 hover:bg-slate-100" title="Edit">
                                                <Edit class="size-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" @click="confirmDeleteTerm(term)" class="size-8 text-red-600 hover:bg-red-50 hover:text-red-700" title="Delete">
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredTerms.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                        <div class="flex flex-col items-center">
                                            <Calendar class="size-8 text-slate-300 mb-2" />
                                            <p>No academic terms found for this campus.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- Term Modal -->
        <Dialog v-model:open="showTermModal">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ selectedTerm ? 'Edit Academic Term' : 'New Academic Term' }}</DialogTitle>
                    <DialogDescription>Configure academic period settings and synchronization ID.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="saveTerm" class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="sy">School Year</Label>
                            <Input id="sy" v-model="termForm.school_year" placeholder="2025-2026" required />
                            <div v-if="termForm.errors.school_year" class="text-xs text-red-500">{{ termForm.errors.school_year }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <Select v-model="termForm.semester">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1st Semester">1st Semester</SelectItem>
                                    <SelectItem value="2nd Semester">2nd Semester</SelectItem>
                                    <SelectItem value="Summer">Summer</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="termForm.errors.semester" class="text-xs text-red-500">{{ termForm.errors.semester }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="term_id">Term ID (API)</Label>
                            <Input id="term_id" v-model="termForm.term_id" placeholder="102" />
                            <div v-if="termForm.errors.term_id" class="text-xs text-red-500">{{ termForm.errors.term_id }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="term_status">Status</Label>
                            <Select v-model="termForm.status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Active">Active</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                    <SelectItem value="Archived">Archived</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="termForm.errors.status" class="text-xs text-red-500">{{ termForm.errors.status }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="start_date">Start Date</Label>
                            <Input id="start_date" type="date" v-model="termForm.start_date" />
                            <div v-if="termForm.errors.start_date" class="text-xs text-red-500">{{ termForm.errors.start_date }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="end_date">End Date</Label>
                            <Input id="end_date" type="date" v-model="termForm.end_date" />
                            <div v-if="termForm.errors.end_date" class="text-xs text-red-500">{{ termForm.errors.end_date }}</div>
                        </div>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="submit" :disabled="termForm.processing" class="bg-sky-600 hover:bg-sky-700">
                            {{ selectedTerm ? 'Update Term' : 'Create Term' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Campus Modal -->
        <Dialog v-model:open="showEditModal">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Campus</DialogTitle>
                    <DialogDescription>Update campus details and configuration.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="updateCampus" class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="edit_name">Campus Name</Label>
                        <Input id="edit_name" v-model="form.campus_name" required />
                        <div v-if="form.errors.campus_name" class="text-xs text-red-500">{{ form.errors.campus_name }}</div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_address">Address</Label>
                        <Input id="edit_address" v-model="form.campus_address" />
                        <div v-if="form.errors.campus_address" class="text-xs text-red-500">{{ form.errors.campus_address }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="edit_real_id">Real Campus ID</Label>
                            <Input id="edit_real_id" v-model="form.real_campus_id" />
                            <div v-if="form.errors.real_campus_id" class="text-xs text-red-500">{{ form.errors.real_campus_id }}</div>
                        </div>
                        <div class="space-y-2">
                            <Label for="edit_status">Status</Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Active">Active</SelectItem>
                                    <SelectItem value="Inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="form.errors.status" class="text-xs text-red-500">{{ form.errors.status }}</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="edit_logo">Campus Logo (Leave blank to keep current)</Label>
                        <Input id="edit_logo" type="file" @input="form.logo = $event.target.files[0]" accept="image/*" />
                        <div v-if="form.errors.logo" class="text-xs text-red-500 font-medium">{{ form.errors.logo }}</div>
                    </div>
                    <DialogFooter class="pt-4">
                        <Button type="submit" :disabled="form.processing" class="bg-sky-600 hover:bg-sky-700">Update Campus</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmationModal
            v-if="selectedTerm"
            :show="showDeleteTermModal"
            title="Delete Academic Term"
            :message="`Are you sure you want to delete ${selectedTerm.school_year} ${selectedTerm.semester}? This action cannot be undone.`"
            confirm-button-text="Delete Term"
            @confirm="deleteTerm"
            @close="showDeleteTermModal = false"
        />
    </div>
</template>
