<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { index as profileIndex } from '@/routes/site-settings/student-profile';
import * as awardRoutes from '@/routes/site-settings/student-profile/awards';
import * as physicalFitnessPermissionRoutes from '@/routes/site-settings/student-profile/physical-fitness-permission';
import * as trainingRoutes from '@/routes/site-settings/student-profile/trainings';
import {
    Award,
    CalendarDays,
    Dumbbell,
    Edit2,
    Medal,
    Plus,
    Search,
    Trash2,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Student = {
    id: number;
    name: string;
    email: string | null;
    student_no: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type AwardRecord = {
    id: number;
    user_id: number;
    title: string;
    date_received: string;
    awarder: string | null;
    description: string | null;
    user: Student;
};

type TrainingRecord = {
    id: number;
    user_id: number;
    title: string;
    date_from: string;
    date_to: string | null;
    venue: string | null;
    organizer: string | null;
    user: Student;
};

type PhysicalFitnessSetting = {
    permission: string;
    options: Array<{
        label: string;
        value: string;
    }>;
};

const props = defineProps<{
    activeTab: 'awards' | 'trainings' | 'physical-fitness';
    filters: {
        search: string;
    };
    students: Student[];
    awards: Paginator<AwardRecord>;
    trainings: Paginator<TrainingRecord>;
    physicalFitnessSetting: PhysicalFitnessSetting;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        managePhysicalFitnessPermission: boolean;
    };
}>();

const search = ref(props.filters.search || '');
const activeTab = computed(() => props.activeTab);
const modalMode = ref<'award' | 'training' | null>(null);
const editingAward = ref<AwardRecord | null>(null);
const editingTraining = ref<TrainingRecord | null>(null);
const deleteTarget = ref<{
    type: 'award' | 'training';
    id: number;
    title: string;
} | null>(null);

const awardForm = useForm({
    user_id: '',
    title: '',
    date_received: '',
    awarder: '',
    description: '',
});

const trainingForm = useForm({
    user_id: '',
    title: '',
    date_from: '',
    date_to: '',
    venue: '',
    organizer: '',
});

const physicalFitnessForm = useForm({
    permission: props.physicalFitnessSetting.permission,
});

const visitTab = (tab: 'awards' | 'trainings' | 'physical-fitness') => {
    router.get(
        profileIndex.url({ query: { tab, search: search.value || undefined } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const submitPhysicalFitnessPermission = () => {
    physicalFitnessForm.patch(physicalFitnessPermissionRoutes.update.url(), {
        preserveScroll: true,
        preserveState: true,
    });
};

const applySearch = () => {
    router.get(
        profileIndex.url({
            query: { tab: activeTab.value, search: search.value || undefined },
        }),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const studentLabel = (student?: Student | null) => {
    if (!student) return 'Unassigned student';

    return [student.name, student.student_no].filter(Boolean).join(' - ');
};

const closeModal = () => {
    modalMode.value = null;
    editingAward.value = null;
    editingTraining.value = null;
    awardForm.reset();
    awardForm.clearErrors();
    trainingForm.reset();
    trainingForm.clearErrors();
};

const openAwardModal = (record: AwardRecord | null = null) => {
    editingAward.value = record;
    awardForm.user_id = record ? String(record.user_id) : '';
    awardForm.title = record?.title ?? '';
    awardForm.date_received = record?.date_received ?? '';
    awardForm.awarder = record?.awarder ?? '';
    awardForm.description = record?.description ?? '';
    modalMode.value = 'award';
};

const openTrainingModal = (record: TrainingRecord | null = null) => {
    editingTraining.value = record;
    trainingForm.user_id = record ? String(record.user_id) : '';
    trainingForm.title = record?.title ?? '';
    trainingForm.date_from = record?.date_from ?? '';
    trainingForm.date_to = record?.date_to ?? '';
    trainingForm.venue = record?.venue ?? '';
    trainingForm.organizer = record?.organizer ?? '';
    modalMode.value = 'training';
};

const submitAward = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingAward.value) {
        awardForm.patch(awardRoutes.update.url(editingAward.value.id), options);
        return;
    }

    awardForm.post(awardRoutes.store.url(), options);
};

const submitTraining = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (editingTraining.value) {
        trainingForm.patch(
            trainingRoutes.update.url(editingTraining.value.id),
            options,
        );
        return;
    }

    trainingForm.post(trainingRoutes.store.url(), options);
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;

    const route =
        deleteTarget.value.type === 'award'
            ? awardRoutes.destroy.url(deleteTarget.value.id)
            : trainingRoutes.destroy.url(deleteTarget.value.id);

    router.delete(route, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
};
</script>

<template>
    <Head title="Student Profile Records" />

    <SiteSettingsLayout>
        <div class="space-y-5 p-4 lg:p-6">
            <div
                class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <div>
                    <p
                        class="text-[11px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300"
                    >
                        Student Profile
                    </p>
                    <h1
                        class="mt-1 text-xl font-bold tracking-tight text-slate-950 dark:text-white"
                    >
                        Awards and Trainings
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Manage student-owned profile records with server-side
                        pagination.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search student or record"
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-sm text-slate-900 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 sm:w-72 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:focus:ring-emerald-500/20"
                            @keyup.enter="applySearch"
                        />
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                        @click="applySearch"
                    >
                        Search
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[220px_1fr]">
                <aside
                    class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition"
                        :class="
                            activeTab === 'awards'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                        "
                        @click="visitTab('awards')"
                    >
                        <Medal class="size-4" />
                        <span class="text-sm font-bold">Awards</span>
                        <span
                            class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-white/10"
                            >{{ awards.total }}</span
                        >
                    </button>
                    <button
                        type="button"
                        class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition"
                        :class="
                            activeTab === 'trainings'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                        "
                        @click="visitTab('trainings')"
                    >
                        <CalendarDays class="size-4" />
                        <span class="text-sm font-bold">Trainings</span>
                        <span
                            class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-white/10"
                            >{{ trainings.total }}</span
                        >
                    </button>
                    <button
                        type="button"
                        class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition"
                        :class="
                            activeTab === 'physical-fitness'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                        "
                        @click="visitTab('physical-fitness')"
                    >
                        <Dumbbell class="size-4" />
                        <span class="text-sm font-bold"
                            >Physical Fitness</span
                        >
                    </button>
                </aside>

                <section
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                {{
                                    activeTab === 'physical-fitness'
                                        ? 'Physical Fitness'
                                        : activeTab === 'awards'
                                        ? 'Award Records'
                                        : 'Training Records'
                                }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{
                                    activeTab === 'awards'
                                        ? `${awards.from || 0}-${awards.to || 0} of ${awards.total}`
                                        : activeTab === 'trainings'
                                          ? `${trainings.from || 0}-${trainings.to || 0} of ${trainings.total}`
                                          : 'Control who can submit Physical Fitness Test records'
                                }}
                            </p>
                        </div>

                        <button
                            v-if="can.create && activeTab !== 'physical-fitness'"
                            type="button"
                            class="inline-flex h-8 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"
                            @click="
                                activeTab === 'awards'
                                    ? openAwardModal()
                                    : openTrainingModal()
                            "
                        >
                            <Plus class="size-3.5" />
                            Add
                        </button>
                    </div>

                    <div
                        v-if="activeTab === 'physical-fitness'"
                        class="space-y-4 p-4"
                    >
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <p
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                PFT Fill-up Permission
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Choose whether only students enrolled in
                                PE/PATHFIT subjects can encode PFT results, or
                                all students can fill up the form.
                            </p>

                            <form
                                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                                @submit.prevent="
                                    submitPhysicalFitnessPermission
                                "
                            >
                                <label class="flex-1">
                                    <span
                                        class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                    >
                                        Permission
                                    </span>
                                    <select
                                        v-model="
                                            physicalFitnessForm.permission
                                        "
                                        class="mt-1 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:focus:ring-emerald-500/20"
                                    >
                                        <option
                                            v-for="option in physicalFitnessSetting.options"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </label>

                                <button
                                    type="submit"
                                    class="inline-flex h-10 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="
                                        !can.managePhysicalFitnessPermission ||
                                        physicalFitnessForm.processing
                                    "
                                >
                                    Save Setting
                                </button>
                            </form>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead
                                class="bg-slate-50 text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:bg-white/[0.03] dark:text-slate-400"
                            >
                                <tr v-if="activeTab === 'awards'">
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Award</th>
                                    <th class="px-4 py-3">Awarder</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                                <tr v-else>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Training</th>
                                    <th class="px-4 py-3">Organizer</th>
                                    <th class="px-4 py-3">Dates</th>
                                    <th class="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <tr
                                    v-if="
                                        activeTab === 'awards' &&
                                        awards.data.length === 0
                                    "
                                >
                                    <td colspan="5" class="px-4 py-10">
                                        <div class="text-center">
                                            <Award
                                                class="mx-auto size-8 text-slate-300"
                                            />
                                            <p
                                                class="mt-2 text-sm font-bold text-slate-900 dark:text-white"
                                            >
                                                No awards found
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-for="record in awards.data"
                                    v-if="activeTab === 'awards'"
                                    :key="record.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                                >
                                    <td class="px-4 py-3">
                                        <p
                                            class="font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ record.user.name }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                record.user.student_no ||
                                                record.user.email ||
                                                '-'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="font-semibold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ record.title }}
                                        </p>
                                        <p
                                            class="max-w-md truncate text-xs text-slate-500"
                                        >
                                            {{ record.description || '-' }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-600"
                                    >
                                        {{ record.awarder || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-600"
                                    >
                                        {{ record.date_received }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="inline-flex items-center gap-1"
                                        >
                                            <button
                                                v-if="can.update"
                                                type="button"
                                                class="action-button"
                                                @click="openAwardModal(record)"
                                            >
                                                <Edit2 class="size-3.5" />
                                            </button>
                                            <button
                                                v-if="can.delete"
                                                type="button"
                                                class="action-button text-red-600 hover:border-red-200 hover:bg-red-50"
                                                @click="
                                                    deleteTarget = {
                                                        type: 'award',
                                                        id: record.id,
                                                        title: record.title,
                                                    }
                                                "
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-if="
                                        activeTab === 'trainings' &&
                                        trainings.data.length === 0
                                    "
                                >
                                    <td colspan="5" class="px-4 py-10">
                                        <div class="text-center">
                                            <CalendarDays
                                                class="mx-auto size-8 text-slate-300"
                                            />
                                            <p
                                                class="mt-2 text-sm font-bold text-slate-900 dark:text-white"
                                            >
                                                No trainings found
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-for="record in trainings.data"
                                    v-if="activeTab === 'trainings'"
                                    :key="record.id"
                                    class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                                >
                                    <td class="px-4 py-3">
                                        <p
                                            class="font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ record.user.name }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                record.user.student_no ||
                                                record.user.email ||
                                                '-'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="font-semibold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ record.title }}
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{ record.venue || '-' }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-600"
                                    >
                                        {{ record.organizer || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-slate-600"
                                    >
                                        {{ record.date_from }}
                                        <span v-if="record.date_to">
                                            - {{ record.date_to }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div
                                            class="inline-flex items-center gap-1"
                                        >
                                            <button
                                                v-if="can.update"
                                                type="button"
                                                class="action-button"
                                                @click="
                                                    openTrainingModal(record)
                                                "
                                            >
                                                <Edit2 class="size-3.5" />
                                            </button>
                                            <button
                                                v-if="can.delete"
                                                type="button"
                                                class="action-button text-red-600 hover:border-red-200 hover:bg-red-50"
                                                @click="
                                                    deleteTarget = {
                                                        type: 'training',
                                                        id: record.id,
                                                        title: record.title,
                                                    }
                                                "
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <p class="text-xs text-slate-500">
                            Page
                            {{
                                activeTab === 'awards'
                                    ? awards.current_page
                                    : trainings.current_page
                            }}
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <Link
                                v-for="link in activeTab === 'awards'
                                    ? awards.links
                                    : trainings.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                preserve-scroll
                                :class="[
                                    'rounded-md px-2.5 py-1 text-xs font-bold',
                                    link.active
                                        ? 'bg-emerald-600 text-white'
                                        : link.url
                                          ? 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10'
                                          : 'pointer-events-none text-slate-300',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-if="modalMode"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-2xl dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <div>
                        <h2
                            class="text-base font-bold text-slate-950 dark:text-white"
                        >
                            {{
                                modalMode === 'award'
                                    ? editingAward
                                        ? 'Edit Award'
                                        : 'Add Award'
                                    : editingTraining
                                      ? 'Edit Training'
                                      : 'Add Training'
                            }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            Select a student and complete the record details.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="action-button"
                        @click="closeModal"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <form
                    v-if="modalMode === 'award'"
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-white p-5 dark:bg-slate-950"
                    @submit.prevent="submitAward"
                >
                    <label class="form-field">
                        <span>Student</span>
                        <select v-model="awardForm.user_id" class="form-input">
                            <option value="">Select student</option>
                            <option
                                v-for="student in students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ studentLabel(student) }}
                            </option>
                        </select>
                        <small v-if="awardForm.errors.user_id">{{
                            awardForm.errors.user_id
                        }}</small>
                    </label>
                    <label class="form-field">
                        <span>Award Title</span>
                        <input v-model="awardForm.title" class="form-input" />
                        <small v-if="awardForm.errors.title">{{
                            awardForm.errors.title
                        }}</small>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>Awarder</span>
                            <input
                                v-model="awardForm.awarder"
                                class="form-input"
                            />
                        </label>
                        <label class="form-field">
                            <span>Date Received</span>
                            <input
                                v-model="awardForm.date_received"
                                type="date"
                                class="form-input"
                            />
                            <small v-if="awardForm.errors.date_received">{{
                                awardForm.errors.date_received
                            }}</small>
                        </label>
                    </div>
                    <label class="form-field">
                        <span>Description</span>
                        <textarea
                            v-model="awardForm.description"
                            rows="3"
                            class="form-input min-h-20"
                        />
                    </label>
                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button
                            type="button"
                            class="secondary-button"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="primary-button"
                            :disabled="awardForm.processing"
                        >
                            Save Award
                        </button>
                    </div>
                </form>

                <form
                    v-else
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-white p-5 dark:bg-slate-950"
                    @submit.prevent="submitTraining"
                >
                    <label class="form-field">
                        <span>Student</span>
                        <select
                            v-model="trainingForm.user_id"
                            class="form-input"
                        >
                            <option value="">Select student</option>
                            <option
                                v-for="student in students"
                                :key="student.id"
                                :value="String(student.id)"
                            >
                                {{ studentLabel(student) }}
                            </option>
                        </select>
                        <small v-if="trainingForm.errors.user_id">{{
                            trainingForm.errors.user_id
                        }}</small>
                    </label>
                    <label class="form-field">
                        <span>Training Title</span>
                        <input
                            v-model="trainingForm.title"
                            class="form-input"
                        />
                        <small v-if="trainingForm.errors.title">{{
                            trainingForm.errors.title
                        }}</small>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>Date From</span>
                            <input
                                v-model="trainingForm.date_from"
                                type="date"
                                class="form-input"
                            />
                            <small v-if="trainingForm.errors.date_from">{{
                                trainingForm.errors.date_from
                            }}</small>
                        </label>
                        <label class="form-field">
                            <span>Date To</span>
                            <input
                                v-model="trainingForm.date_to"
                                type="date"
                                class="form-input"
                            />
                            <small v-if="trainingForm.errors.date_to">{{
                                trainingForm.errors.date_to
                            }}</small>
                        </label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>Venue</span>
                            <input
                                v-model="trainingForm.venue"
                                class="form-input"
                            />
                        </label>
                        <label class="form-field">
                            <span>Organizer</span>
                            <input
                                v-model="trainingForm.organizer"
                                class="form-input"
                            />
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button
                            type="button"
                            class="secondary-button"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="primary-button"
                            :disabled="trainingForm.processing"
                        >
                            Save Training
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="deleteTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            @click.self="deleteTarget = null"
        >
            <div
                class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <h2 class="text-base font-bold text-slate-950 dark:text-white">
                    Delete record?
                </h2>
                <p class="mt-2 text-sm text-slate-500">
                    This will permanently remove
                    <span class="font-bold text-slate-900 dark:text-white">{{
                        deleteTarget.title
                    }}</span>
                    from the selected student profile.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="secondary-button"
                        @click="deleteTarget = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-red-600 px-4 text-xs font-bold text-white transition hover:bg-red-700"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </SiteSettingsLayout>
</template>

<style scoped>
@reference "tailwindcss";

.action-button {
    @apply inline-flex size-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500/30 focus-visible:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:shadow-none dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300;
}

.form-field {
    @apply grid gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-300;
}

.form-field small {
    @apply text-[11px] font-semibold text-red-600;
}

.form-input {
    @apply min-h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm transition outline-none placeholder:text-slate-400 hover:border-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:shadow-none dark:hover:border-white/20 dark:focus:ring-emerald-500/20;
    color-scheme: light;
}

:global(.dark) .form-input {
    color-scheme: dark;
}

.primary-button {
    @apply inline-flex h-9 items-center justify-center rounded-md bg-emerald-600 px-4 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60;
}

.secondary-button {
    @apply inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-slate-400/30 focus-visible:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:shadow-none dark:hover:bg-white/5;
}
</style>
