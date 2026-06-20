<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    SlidersHorizontal,
    Trash2,
    Pencil,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import * as updateRoutes from '@/routes/clearance/updates';

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
    description: string | null;
    purpose: string | null;
    semester: {
        id: number;
        academic_year: string;
        term: string;
        campus_name?: string;
        campus_id: number;
    };
    type: { id: number; name: string; audience: 'all' | 'individual' };
    targeted_students?: {
        id: number;
        name: string;
        student_no: string | null;
    }[];
    status: string;
    start_date: string;
    end_date: string;
    created_at: string;
};

const props = defineProps<{
    updates: Page<ClearanceUpdate>;
    filters: Record<string, string | undefined>;
    semesters: {
        id: number;
        academic_year: string;
        term: string;
        campus_name?: string;
        campus_id: number;
        site_campus_id?: number | null;
    }[];
    types: { id: number; name: string; audience: 'all' | 'individual'; campus_id: number }[];
    students: {
        id: number;
        name: string;
        student_no: string | null;
        campus_id: number | null;
    }[];
    activeSemester: any;
    can: {
        create: boolean;
        edit: boolean;
        publish: boolean;
        delete: boolean;
    };
} >();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student Services', href: '#' },
            {
                title: 'Clearance Updates',
                href: '/student-services/clearance/updates',
            },
        ],
    },
});

const search = ref(props.filters.search ?? '');
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status ?? '',
    semester_id: props.filters.semester_id ?? '',
});

const confirmModal = ref({
    show: false,
    title: '',
    description: '',
    confirmText: '',
    variant: 'default' as 'default' | 'destructive',
    action: () => {},
    loading: false,
});

const modal = ref<null | { type: 'create' | 'edit'; update?: ClearanceUpdate }>(
    null,
);

const form = useForm({
    semester_id: (props.activeSemester?.id ?? '') as string | number,
    clearance_type_id: '' as string | number,
    title: '',
    description: '',
    purpose: '',
    start_date: '',
    end_date: '',
    selected_student_ids: [] as number[],
});

const studentSearch = ref('');

const selectedSemester = computed(() =>
    props.semesters.find(
        (semester) => semester.id === Number(form.semester_id),
    ),
);

const filteredTypes = computed(() => {
    const siteCampusId = selectedSemester.value?.site_campus_id;
    if (!siteCampusId) return props.types;
    return props.types.filter((type) => type.campus_id === siteCampusId);
});

const selectedType = computed(() =>
    props.types.find((type) => type.id === Number(form.clearance_type_id)),
);

watch(() => form.semester_id, () => {
    const siteCampusId = selectedSemester.value?.site_campus_id;
    if (siteCampusId && selectedType.value && selectedType.value.campus_id !== siteCampusId) {
        form.clearance_type_id = '';
    }
});

const filteredStudents = computed(() => {
    const query = studentSearch.value.trim().toLowerCase();
    const campusId = selectedSemester.value?.campus_id;

    return props.students
        .filter((student) => !campusId || student.campus_id === campusId)
        .filter(
            (student) =>
                !query ||
                student.name.toLowerCase().includes(query) ||
                student.student_no?.toLowerCase().includes(query),
        )
        .slice(0, 50);
});

const toggleStudent = (studentId: number) => {
    form.selected_student_ids = form.selected_student_ids.includes(studentId)
        ? form.selected_student_ids.filter((id) => id !== studentId)
        : [...form.selected_student_ids, studentId];
};

const applyFilters = () => {
    router.get(
        updateRoutes.index.url(),
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
    form.selected_student_ids = [];
    studentSearch.value = '';
    modal.value = { type: 'create' };
};

const openEdit = (update: ClearanceUpdate) => {
    form.clearErrors();
    form.semester_id = update.semester.id;
    form.clearance_type_id = update.type.id;
    form.title = update.title;
    form.description = update.description ?? '';
    form.purpose = update.purpose ?? '';
    form.start_date = update.start_date;
    form.end_date = update.end_date;
    form.selected_student_ids =
        update.targeted_students?.map((student) => student.id) ?? [];
    studentSearch.value = '';
    modal.value = { type: 'edit', update };
};

const submit = () => {
    if (modal.value?.type === 'create') {
        form.post(updateRoutes.store.url(), {
            onSuccess: () => (modal.value = null),
        });
    } else if (modal.value?.type === 'edit' && modal.value.update) {
        form.patch(updateRoutes.update.url(modal.value.update.id), {
            onSuccess: () => (modal.value = null),
        });
    }
};

const deleteUpdate = (id: number) => {
    confirmModal.value = {
        show: true,
        title: 'Delete Clearance Update',
        description:
            'Are you sure you want to delete this clearance update? This action cannot be undone.',
        confirmText: 'Delete Update',
        variant: 'destructive',
        action: () => {
            router.delete(updateRoutes.destroy.url(id), {
                onStart: () => (confirmModal.value.loading = true),
                onFinish: () => {
                    confirmModal.value.loading = false;
                    confirmModal.value.show = false;
                },
            });
        },
        loading: false,
    };
};

const navigatePage = (url: string | null) => {
    if (url) {
        router.get(url, {}, { preserveState: true });
    }
};

const statusClass = (status: string) => {
    switch (status) {
        case 'published':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
        case 'closed':
            return 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-400';
        default:
            return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }
};
</script>

<template>
    <Head title="Clearance Updates" />

    <div class="flex h-full flex-1 flex-col gap-3 p-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-base font-bold text-slate-800 dark:text-white">
                    Manage Clearance Updates
                </h1>
                <p class="text-xs text-slate-400">
                    Manage semester-based clearance periods and updates.
                </p>
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
                <Search
                    class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search updates..."
                    class="h-8 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-8 text-xs text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                />
            </div>
            <button
                :class="[
                    'inline-flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium',
                    filterOpen
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                        : 'border-slate-200 bg-white text-slate-600',
                ]"
                @click="filterOpen = !filterOpen"
            >
                <SlidersHorizontal class="h-3.5 w-3.5" />
                Filters
            </button>
            <button
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600"
                @click="clearFilters"
            >
                <RefreshCw class="h-3.5 w-3.5" />
                Reset
            </button>
        </div>

        <div
            v-if="filterOpen"
            class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950"
        >
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <label
                    class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                >
                    Semester
                    <select
                        v-model="filters.semester_id"
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-white/10 dark:bg-slate-950"
                    >
                        <option value="">All Semesters</option>
                        <option
                            v-for="sem in semesters"
                            :key="sem.id"
                            :value="sem.id"
                        >
                            {{ sem.academic_year }} - {{ sem.term }} -
                            {{ sem.campus_name }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                >
                    Status
                    <select
                        v-model="filters.status"
                        class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-xs dark:border-white/10 dark:bg-slate-950"
                    >
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="closed">Closed</option>
                    </select>
                </label>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <table
                class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
            >
                <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Title
                        </th>
                        <th
                            class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Semester
                        </th>
                        <th
                            class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Type
                        </th>
                        <th
                            class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Period
                        </th>
                        <th
                            class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Status
                        </th>
                        <th
                            class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 uppercase"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-white/5">
                    <tr
                        v-for="update in updates.data"
                        :key="update.id"
                        class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="updateRoutes.show.url(update.id)"
                                class="text-xs font-bold text-slate-900 hover:text-emerald-600 dark:text-white"
                            >
                                {{ update.title }}
                            </Link>
                        </td>
                        <td
                            class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300"
                        >
                            {{ update.semester.academic_year }} -
                            {{ update.semester.term }} -
                            {{ update.semester.campus_name }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                            >
                                {{ update.type.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[10px] text-slate-500">
                            {{ update.start_date }} to {{ update.end_date }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                    statusClass(update.status),
                                ]"
                            >
                                {{ update.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button
                                    v-if="update.status === 'draft' && can.edit"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-emerald-600"
                                    @click="openEdit(update)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <Link
                                    :href="updateRoutes.show.url(update.id)"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <MoreHorizontal class="h-4 w-4" />
                                </Link>
                                <button
                                    v-if="
                                        update.status === 'draft' && can.delete
                                    "
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    @click="deleteUpdate(update.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="updates.meta.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/10"
            >
                <p class="text-xs text-slate-500">
                    Page {{ updates.meta.current_page }} of
                    {{ updates.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200"
                        @click="navigatePage(updates.links[0]?.url)"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>
                    <button
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200"
                        @click="
                            navigatePage(
                                updates.links[updates.links.length - 1]?.url,
                            )
                        "
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
        v-if="modal"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="modal = null"
    >
        <div
            class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-slate-950"
        >
            <h2 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">
                {{ modal.type === 'create' ? 'Create' : 'Edit' }} Clearance
                Update
            </h2>
            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid gap-3">
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Semester
                        <select
                            v-model="form.semester_id"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option
                                v-for="sem in semesters"
                                :key="sem.id"
                                :value="sem.id"
                            >
                                {{ sem.academic_year }} - {{ sem.term }} -
                                {{ sem.campus_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.semester_id" />
                    </label>
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Type
                        <select
                            v-model="form.clearance_type_id"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option value="" disabled>Select Type</option>
                            <option
                                v-for="type in filteredTypes"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                                {{
                                    type.audience === 'individual'
                                        ? ' (Individual)'
                                        : ' (All students)'
                                }}
                            </option>
                        </select>
                        <InputError :message="form.errors.clearance_type_id" />
                    </label>
                    <div
                        v-if="selectedType?.audience === 'individual'"
                        class="rounded-xl border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-500/20 dark:bg-violet-500/5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-bold text-violet-800 uppercase dark:text-violet-300"
                                >
                                    Select Students
                                </p>
                                <p
                                    class="text-[10px] text-violet-600 dark:text-violet-400"
                                >
                                    Only selected students can see and apply for
                                    this clearance.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                            >
                                {{ form.selected_student_ids.length }} selected
                            </span>
                        </div>
                        <div class="relative mt-3">
                            <Search
                                class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="studentSearch"
                                type="search"
                                placeholder="Search student name or ID"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white pr-3 pl-9 text-xs text-slate-900 focus:border-violet-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                            />
                        </div>
                        <div
                            class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                        >
                            <label
                                v-for="student in filteredStudents"
                                :key="student.id"
                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2 last:border-b-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        form.selected_student_ids.includes(
                                            student.id,
                                        )
                                    "
                                    class="size-4 rounded border-slate-300 text-violet-600"
                                    @change="toggleStudent(student.id)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-xs font-semibold text-slate-900 dark:text-white"
                                        >{{ student.name }}</span
                                    >
                                    <span
                                        class="block text-[10px] text-slate-500"
                                        >{{
                                            student.student_no ||
                                            'No student number'
                                        }}</span
                                    >
                                </span>
                            </label>
                            <p
                                v-if="filteredStudents.length === 0"
                                class="p-4 text-center text-xs text-slate-500"
                            >
                                No students found for the selected campus.
                            </p>
                        </div>
                        <InputError
                            :message="form.errors.selected_student_ids"
                        />
                    </div>
                </div>
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    Title
                    <input
                        v-model="form.title"
                        type="text"
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        placeholder="e.g. 1st Semester Clearance 2025"
                    />
                    <InputError :message="form.errors.title" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        Start Date
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <InputError :message="form.errors.start_date" />
                    </label>
                    <label
                        class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                    >
                        End Date
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        />
                        <InputError :message="form.errors.end_date" />
                    </label>
                </div>
                <label
                    class="grid gap-1 text-[11px] font-bold text-slate-500 uppercase"
                >
                    Purpose
                    <textarea
                        v-model="form.purpose"
                        class="w-full rounded-lg border border-slate-200 bg-white p-3 text-xs focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        rows="2"
                        placeholder="Brief description of the clearance purpose..."
                    ></textarea>
                    <InputError :message="form.errors.purpose" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="modal = null"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        class="bg-emerald-600 text-white"
                        :disabled="form.processing"
                    >
                        {{
                            modal.type === 'create'
                                ? 'Create Draft'
                                : 'Update changes'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <ConfirmationModal
        :show="confirmModal.show"
        :title="confirmModal.title"
        :description="confirmModal.description"
        :confirm-text="confirmModal.confirmText"
        :variant="confirmModal.variant"
        :loading="confirmModal.loading"
        @close="confirmModal.show = false"
        @confirm="confirmModal.action"
    />
</template>
