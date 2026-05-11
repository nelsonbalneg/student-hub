<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    SlidersHorizontal,
    X,
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

type PageLink = { url: string | null; label: string; active: boolean };
type PageMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};
type Page<T> = { data: T[]; links: PageLink[]; meta: PageMeta };

type ClearanceUpdate = {
    id: number;
    title: string;
    semester: { id: number; academic_year: string; term: string };
    type: { id: number; name: string };
    status: string;
    start_date: string;
    end_date: string;
    created_at: string;
};

const props = defineProps<{
    updates: Page<ClearanceUpdate>;
    filters: Record<string, string | undefined>;
    semesters: { id: number; academic_year: string; term: string }[];
    types: { id: number; name: string }[];
    activeSemester: any;
    can: {
        create: boolean;
        edit: boolean;
        publish: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student Services', href: '#' },
            { title: 'Clearance Updates', href: '/student-services/clearance/updates' },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status ?? '',
    semester_id: props.filters.semester_id ?? '',
});

const modal = ref<null | { type: 'create' | 'edit'; update?: ClearanceUpdate }>(null);

const form = useForm({
    semester_id: props.activeSemester?.id ?? '',
    clearance_type_id: '',
    title: '',
    description: '',
    purpose: '',
    start_date: '',
    end_date: '',
});

const applyFilters = () => {
    router.get(
        '/student-services/clearance/updates',
        {
            search: search.value,
            ...filters.value,
        },
        { preserveState: true, replace: true },
    );
};

let searchTimeout: any;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});
watch(filters, applyFilters, { deep: true });

const clearFilters = () => {
    search.value = '';
    filters.value = { status: '', semester_id: '' };
    applyFilters();
};

const openCreate = () => {
    form.reset();
    form.semester_id = props.activeSemester?.id ?? '';
    modal.value = { type: 'create' };
};

const submit = () => {
    form.post('/student-services/clearance/updates', {
        onSuccess: () => (modal.value = null),
    });
};

const navigatePage = (url: string | null) => {
    if (url) router.get(url, {}, { preserveState: true });
};

const statusClass = (status: string) => {
    switch (status) {
        case 'published': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'closed': return 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400';
        default: return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }
};
</script>

<template>
    <Head title="Clearance Updates" />

    <div class="flex h-full flex-1 flex-col gap-3 p-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base font-bold text-slate-800 dark:text-white">Clearance Updates</h1>
                <p class="text-xs text-slate-400">Manage semester-based clearance periods and updates.</p>
            </div>
            <Button
                v-if="can.create"
                class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700"
                @click="openCreate"
            >
                <Plus class="h-3.5 w-3.5" />
                New Update
            </Button>
        </div>

        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <Search class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search updates..."
                    class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                />
            </div>
            <button
                :class="['h-8 inline-flex items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium', filterOpen ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600']"
                @click="filterOpen = !filterOpen"
            >
                <SlidersHorizontal class="h-3.5 w-3.5" />
                Filters
            </button>
            <button class="h-8 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600" @click="clearFilters">
                <RefreshCw class="h-3.5 w-3.5" />
                Reset
            </button>
        </div>

        <div v-if="filterOpen" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <label class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                    Semester
                    <select v-model="filters.semester_id" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-white/10 dark:bg-slate-950">
                        <option value="">All Semesters</option>
                        <option v-for="sem in semesters" :key="sem.id" :value="sem.id">{{ sem.academic_year }} - {{ sem.term }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                    Status
                    <select v-model="filters.status" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-white/10 dark:bg-slate-950">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="closed">Closed</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Title</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Semester</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Period</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                    <tr v-for="update in updates.data" :key="update.id" class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]">
                        <td class="px-4 py-3">
                            <Link :href="`/student-services/clearance/updates/${update.id}`" class="text-xs font-bold text-slate-900 hover:text-emerald-600 dark:text-white">
                                {{ update.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                            {{ update.semester.academic_year }} - {{ update.semester.term }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                {{ update.type.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[10px] text-slate-500">
                            {{ update.start_date }} to {{ update.end_date }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', statusClass(update.status)]">
                                {{ update.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/student-services/clearance/updates/${update.id}`" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                <MoreHorizontal class="h-4 w-4" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="updates.meta.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/10">
                <p class="text-xs text-slate-500">Page {{ updates.meta.current_page }} of {{ updates.meta.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-slate-200" @click="navigatePage(updates.links[0]?.url)">
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>
                    <button class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-slate-200" @click="navigatePage(updates.links[updates.links.length - 1]?.url)">
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div v-if="modal?.type === 'create'" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" @click.self="modal = null">
        <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-slate-950">
            <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">Create Clearance Update</h2>
            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Semester
                        <select v-model="form.semester_id" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs">
                            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">{{ sem.academic_year }} - {{ sem.term }}</option>
                        </select>
                        <InputError :message="form.errors.semester_id" />
                    </label>
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Type
                        <select v-model="form.clearance_type_id" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs">
                            <option v-for="type in types" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                        <InputError :message="form.errors.clearance_type_id" />
                    </label>
                </div>
                <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                    Title
                    <input v-model="form.title" type="text" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                    <InputError :message="form.errors.title" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        Start Date
                        <input v-model="form.start_date" type="date" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        <InputError :message="form.errors.start_date" />
                    </label>
                    <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                        End Date
                        <input v-model="form.end_date" type="date" class="h-9 rounded-lg border border-slate-200 px-3 text-xs" />
                        <InputError :message="form.errors.end_date" />
                    </label>
                </div>
                <label class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase">
                    Purpose
                    <textarea v-model="form.purpose" class="rounded-lg border border-slate-200 p-3 text-xs" rows="2"></textarea>
                    <InputError :message="form.errors.purpose" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="modal = null">Cancel</Button>
                    <Button type="submit" class="bg-emerald-600 text-white" :disabled="form.processing">Create Draft</Button>
                </div>
            </form>
        </div>
    </div>
</template>
