<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import { computed, ref, watch } from 'vue';
import {
    CheckCircle2,
    Loader2,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserRound,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    society: any;
    section: 'officers' | 'advisers' | 'members';
    activeTerm?: any | null;
    currentApplication?: any | null;
    officerPositions?: any[];
}>();

const title = computed(
    () => props.section.charAt(0).toUpperCase() + props.section.slice(1),
);
const rows = computed(() => props.society?.[props.section] ?? []);
const endpoint = computed(
    () =>
        `/societies/manage/${props.society.id}/${props.section === 'members' ? 'members-roster' : props.section}`,
);
const canSearchStudents = computed(() => props.section !== 'advisers');
const canManageRows = computed(
    () => props.section === 'officers' || props.section === 'advisers',
);
const recordLabel = computed(() => props.section.slice(0, -1));

const form = useForm({
    accreditation_request_id: props.currentApplication?.id ?? '',
    position: '',
    student_identifier: '',
    student_id: '',
    full_name: '',
    year_course_section: '',
    permanent_address: '',
    college_unit: '',
    usm_email: '',
    contact_no: '',
    membership_type: 'member',
    commitment_form_accepted: false,
    school_year:
        props.currentApplication?.school_year ??
        props.activeTerm?.school_year ??
        '2026-2027',
    semester:
        props.currentApplication?.semester ??
        props.activeTerm?.semester ??
        '1st Semester',
});

const studentSearch = ref('');
const studentResults = ref<any[]>([]);
const selectedStudent = ref<any | null>(null);
const isSearchingStudents = ref(false);
const studentSearchError = ref('');
const editingRecord = ref<any | null>(null);
const deleteDialog = ref({
    show: false,
    loading: false,
    record: null as any | null,
});
let studentSearchTimeout: ReturnType<typeof setTimeout> | undefined;
let studentSearchAbort: AbortController | null = null;

const searchStudents = async () => {
    const query = studentSearch.value.trim();

    studentSearchError.value = '';

    if (query.length < 2) {
        studentResults.value = [];
        isSearchingStudents.value = false;
        return;
    }

    studentSearchAbort?.abort();
    studentSearchAbort = new AbortController();
    isSearchingStudents.value = true;

    try {
        const response = await fetch(
            `/societies/students/search?search=${encodeURIComponent(query)}`,
            {
                headers: {
                    Accept: 'application/json',
                },
                signal: studentSearchAbort.signal,
            },
        );

        if (!response.ok) {
            throw new Error('Unable to search students.');
        }

        studentResults.value = await response.json();
    } catch (error: any) {
        if (error.name !== 'AbortError') {
            studentSearchError.value =
                'Student search is unavailable right now.';
            studentResults.value = [];
        }
    } finally {
        isSearchingStudents.value = false;
    }
};

watch(studentSearch, () => {
    selectedStudent.value = null;
    clearTimeout(studentSearchTimeout);
    studentSearchTimeout = setTimeout(searchStudents, 350);
});

const selectStudent = (student: any) => {
    selectedStudent.value = student;
    studentSearch.value = `${student.name} (${student.student_no})`;
    studentResults.value = [];
    form.full_name = student.name ?? '';
    form.usm_email = student.email ?? '';

    if (props.section === 'officers') {
        form.student_id = student.id;
        form.student_identifier = student.student_no ?? '';
        return;
    }

    form.student_id = student.student_no ?? '';
};

const clearSelectedStudent = () => {
    selectedStudent.value = null;
    studentSearch.value = '';
    studentResults.value = [];

    if (props.section === 'officers') {
        form.student_id = '';
        form.student_identifier = '';
    } else {
        form.student_id = '';
    }
};

const resetOfficerForm = () => {
    editingRecord.value = null;
    form.reset(
        'position',
        'student_identifier',
        'student_id',
        'full_name',
        'year_course_section',
        'permanent_address',
        'college_unit',
        'usm_email',
        'contact_no',
        'commitment_form_accepted',
    );
    form.clearErrors();
    clearSelectedStudent();
};

const editRecord = (record: any) => {
    editingRecord.value = record;
    studentResults.value = [];
    selectedStudent.value = record.student ?? null;
    studentSearch.value = record.student
        ? `${record.student.name} (${record.student.student_no ?? record.student_identifier ?? 'No ID'})`
        : (record.full_name ?? '');

    form.accreditation_request_id =
        record.accreditation_request_id ?? props.currentApplication?.id ?? '';
    form.position = record.position ?? '';
    form.student_id = record.student_id ?? '';
    form.student_identifier =
        record.student_identifier ?? record.student?.student_no ?? '';
    form.full_name = record.full_name ?? record.student?.name ?? '';
    form.year_course_section = record.year_course_section ?? '';
    form.permanent_address = record.permanent_address ?? '';
    form.college_unit = record.college_unit ?? '';
    form.usm_email = record.usm_email ?? record.student?.email ?? '';
    form.contact_no = record.contact_no ?? '';
    form.commitment_form_accepted = Boolean(record.commitment_form_accepted);
    form.school_year =
        record.school_year ??
        props.currentApplication?.school_year ??
        props.activeTerm?.school_year ??
        form.school_year;
    form.semester =
        record.semester ??
        props.currentApplication?.semester ??
        props.activeTerm?.semester ??
        form.semester;
};

const openDeleteRecord = (record: any) => {
    deleteDialog.value = {
        show: true,
        loading: false,
        record,
    };
};

const confirmDeleteRecord = () => {
    if (!deleteDialog.value.record || !canManageRows.value) {
        return;
    }

    const deletingRecordId = deleteDialog.value.record.id;

    router.delete(
        `/societies/manage/${props.society.id}/${props.section}/${deletingRecordId}`,
        {
            preserveScroll: true,
            onStart: () => {
                deleteDialog.value.loading = true;
            },
            onFinish: () => {
                deleteDialog.value.loading = false;
                deleteDialog.value.show = false;
                deleteDialog.value.record = null;
            },
            onSuccess: () => {
                if (editingRecord.value?.id === deletingRecordId) {
                    resetOfficerForm();
                }
            },
        },
    );
};

const submit = () => {
    if (canManageRows.value && editingRecord.value) {
        form.patch(
            `/societies/manage/${props.society.id}/${props.section}/${editingRecord.value.id}`,
            {
                preserveScroll: true,
                onSuccess: resetOfficerForm,
            },
        );

        return;
    }

    form.post(endpoint.value, {
        onSuccess: () => {
            resetOfficerForm();
        },
    });
};
</script>

<template>
    <Head :title="title" />

    <div
        class="flex min-h-screen flex-col bg-slate-50/70 p-4 lg:p-5 dark:bg-slate-950"
    >
        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <div
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                        >
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                            >
                                {{ society.full_name ?? society.name }}
                            </p>
                            <h1
                                class="text-xl font-black text-slate-950 dark:text-white"
                            >
                                {{ title }}
                            </h1>
                        </div>
                    </div>
                    <div
                        class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        {{ form.semester }} · {{ form.school_year }}
                        <span v-if="activeTerm?.term_id" class="text-slate-400"
                            >· Term ID {{ activeTerm.term_id }}</span
                        >
                    </div>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[420px_1fr]">
                <form
                    class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    @submit.prevent="submit"
                >
                    <div
                        class="border-b border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2
                                    class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                                >
                                    {{
                                        editingRecord
                                            ? `Edit ${recordLabel}`
                                            : `Add ${recordLabel}`
                                    }}
                                </h2>
                                <p
                                    class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        canSearchStudents
                                            ? 'Search student records server-side, then complete the required role details.'
                                            : 'Complete the adviser details and digital commitment acknowledgement.'
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="editingRecord"
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-3 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                                @click="resetOfficerForm"
                            >
                                <Plus class="size-3.5" />
                                New
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 overflow-y-auto p-5">
                        <div v-if="canSearchStudents" class="space-y-1.5">
                            <label
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Search Student
                            </label>
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="studentSearch"
                                    placeholder="Type student no., name, or email"
                                    class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 pr-10 pl-9 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                                />
                                <Loader2
                                    v-if="isSearchingStudents"
                                    class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-slate-400"
                                />
                                <button
                                    v-else-if="studentSearch"
                                    type="button"
                                    class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                                    @click="clearSelectedStudent"
                                >
                                    <X class="size-3.5" />
                                </button>

                                <div
                                    v-if="studentResults.length > 0"
                                    class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/10 dark:border-white/10 dark:bg-slate-900"
                                >
                                    <button
                                        v-for="student in studentResults"
                                        :key="student.id"
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                                        @click="selectStudent(student)"
                                    >
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                                        >
                                            <UserRound class="size-4" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-bold text-slate-900 dark:text-white"
                                            >
                                                {{ student.name }}
                                            </p>
                                            <p
                                                class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                {{ student.student_no }} ·
                                                {{
                                                    student.email ?? 'No email'
                                                }}
                                            </p>
                                        </div>
                                        <CheckCircle2
                                            v-if="
                                                selectedStudent?.id ===
                                                student.id
                                            "
                                            class="size-4 text-emerald-500"
                                        />
                                    </button>
                                </div>
                            </div>
                            <p
                                v-if="studentSearchError"
                                class="text-xs font-semibold text-red-600"
                            >
                                {{ studentSearchError }}
                            </p>
                            <p
                                v-else
                                class="text-[11px] font-medium text-slate-500 dark:text-slate-400"
                            >
                                Enter at least 2 characters. Results are limited
                                for fast lookup.
                            </p>
                        </div>

                        <select
                            v-if="section === 'officers'"
                            v-model="form.position"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">Select officer position</option>
                            <option
                                v-for="position in officerPositions ?? []"
                                :key="position.id"
                                :value="position.name"
                            >
                                {{ position.name
                                }}{{ position.is_required ? ' *' : '' }}
                            </option>
                        </select>
                        <input
                            v-if="section === 'officers'"
                            v-model="form.student_identifier"
                            placeholder="Student ID"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-if="section === 'members'"
                            v-model="form.student_id"
                            placeholder="Student ID"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-model="form.full_name"
                            placeholder="Full name"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-if="section === 'advisers'"
                            v-model="form.college_unit"
                            placeholder="College / unit"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-if="section !== 'advisers'"
                            v-model="form.year_course_section"
                            placeholder="Year, course, section"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <textarea
                            v-if="section === 'officers'"
                            v-model="form.permanent_address"
                            rows="2"
                            placeholder="Permanent address"
                            class="rounded-md border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-model="form.usm_email"
                            placeholder="USM email"
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-if="section !== 'advisers'"
                            v-model="form.contact_no"
                            placeholder="Contact no."
                            class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <input
                                v-model="form.school_year"
                                placeholder="School year"
                                class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                            <select
                                v-model="form.semester"
                                class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                                <option>1st Semester</option>
                                <option>2nd Semester</option>
                                <option>Midyear</option>
                            </select>
                        </div>
                        <label
                            v-if="section === 'advisers'"
                            class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
                        >
                            <input
                                v-model="form.commitment_form_accepted"
                                type="checkbox"
                                class="mt-1 rounded border-slate-300"
                            />
                            <span
                                >I commit to supervise the organization, attend
                                important meetings and activities, prohibit
                                hazing, observe membership limitations, and
                                accept responsibility for the welfare of members
                                and applicants.</span
                            >
                        </label>
                        <button
                            class="mt-1 inline-flex h-10 items-center justify-center rounded-md bg-slate-950 px-4 text-xs font-black tracking-wider text-white uppercase transition hover:bg-sky-700 disabled:opacity-60 dark:bg-sky-600"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : editingRecord
                                      ? 'Update Record'
                                      : 'Save Record'
                            }}
                        </button>
                    </div>
                </form>

                <section
                    class="min-h-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                    >
                        <h2
                            class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                        >
                            Historical Records
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800"
                        >
                            <thead
                                class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <tr>
                                    <th class="px-4 py-3 text-left">Name</th>
                                    <th class="px-4 py-3 text-left">
                                        Role / Course
                                    </th>
                                    <th class="px-4 py-3 text-left">Term</th>
                                    <th class="px-4 py-3 text-left">Email</th>
                                    <th
                                        v-if="canManageRows"
                                        class="px-4 py-3 text-right"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr v-for="row in rows" :key="row.id">
                                    <td
                                        class="px-4 py-3 font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ row.full_name ?? row.student?.name }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            row.position ??
                                            row.year_course_section ??
                                            row.college_unit
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ row.semester }} {{ row.school_year }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">
                                        {{ row.usm_email }}
                                    </td>
                                    <td v-if="canManageRows" class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                                                @click="editRecord(row)"
                                            >
                                                <Pencil class="size-3.5" />
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 text-[11px] font-bold text-red-700 transition hover:bg-red-100 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                                                @click="openDeleteRecord(row)"
                                            >
                                                <Trash2 class="size-3.5" />
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="rows.length === 0">
                                    <td
                                        :colspan="canManageRows ? 5 : 4"
                                        class="px-4 py-10 text-center font-semibold text-slate-500"
                                    >
                                        No records yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <ConfirmationModal
            :show="deleteDialog.show"
            :title="`Delete ${recordLabel.charAt(0).toUpperCase() + recordLabel.slice(1)} Record`"
            :description="`Delete ${deleteDialog.record?.full_name ?? deleteDialog.record?.student?.name ?? `this ${recordLabel}`} from the ${section} list? This action cannot be undone.`"
            :confirm-text="
                deleteDialog.loading
                    ? 'Deleting...'
                    : `Delete ${recordLabel.charAt(0).toUpperCase() + recordLabel.slice(1)}`
            "
            :cancel-text="`Keep ${recordLabel.charAt(0).toUpperCase() + recordLabel.slice(1)}`"
            variant="destructive"
            :loading="deleteDialog.loading"
            compact
            @close="deleteDialog.show = false"
            @confirm="confirmDeleteRecord"
        >
            <template #confirm-icon>
                <Trash2 class="size-3.5" />
            </template>
        </ConfirmationModal>
    </div>
</template>
