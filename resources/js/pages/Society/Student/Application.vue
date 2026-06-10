<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    store as storeAccreditation,
    submit as submitAccreditation,
} from '@/routes/societies/manage/accreditation';
import {
    destroy as destroyRequirement,
    store as storeRequirement,
} from '@/routes/societies/manage/accreditation/requirements';
import {
    CheckCircle2,
    Clock,
    FileUp,
    AlertCircle,
    Sparkles,
    FileText,
    Trash2,
    Users,
    UserCheck,
    Info,
    ChevronRight,
    ArrowRight,
    HelpCircle,
    Pencil,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    society: any;
    currentApplication?: any | null;
    requirements: any[];
    stats?: any | null;
    activeTerm?: any | null;
    submitter?: {
        name?: string;
        position?: string;
    };
    officerReadiness?: {
        school_year?: string | null;
        missing_positions?: string[];
    };
}>();

const applicationForm = useForm({
    semester: props.activeTerm?.semester ?? '1st Semester',
    school_year: props.activeTerm?.school_year ?? '2026-2027',
    mode_of_submission: 'online',
    submitted_by_name: props.submitter?.name ?? '',
    submitted_by_position: props.submitter?.position ?? '',
    submitted_by_signature: '',
});

const requirementForm = useForm({
    requirement_id: '',
    file: null as File | null,
    remarks: '',
});

const fileInput = ref<HTMLInputElement | null>(null);
const uploadSection = ref<HTMLElement | null>(null);
const editingSubmission = ref<any | null>(null);
const deletingSubmission = ref<any | null>(null);
const selectedFileName = ref<string>('');
const selectedFileSize = ref<string>('');
const isDragActive = ref<boolean>(false);

const handleFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    setFile(file);
};

const setFile = (file: File | null) => {
    requirementForm.file = file;
    if (file) {
        selectedFileName.value = file.name;
        selectedFileSize.value = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
    } else {
        selectedFileName.value = '';
        selectedFileSize.value = '';
    }
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const removeFile = () => {
    requirementForm.file = null;
    selectedFileName.value = '';
    selectedFileSize.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    isDragActive.value = true;
};

const handleDragLeave = () => {
    isDragActive.value = false;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragActive.value = false;
    const file = e.dataTransfer?.files?.[0] ?? null;
    if (file && file.type === 'application/pdf') {
        setFile(file);
    }
};

const completion = computed(
    () => props.currentApplication?.completion_percentage ?? 0,
);
const canEdit = computed(
    () =>
        !props.currentApplication ||
        props.currentApplication.status !== 'approved',
);
const missingOfficerPositions = computed(
    () => props.officerReadiness?.missing_positions ?? [],
);
const isSocietyPublished = computed(
    () => String(props.society?.status ?? '').toLowerCase() !== 'draft',
);
const canCreateApplication = computed(
    () =>
        isSocietyPublished.value && missingOfficerPositions.value.length === 0,
);
const canSubmitRequirement = computed(() => {
    if (!requirementForm.requirement_id) {
        return false;
    }

    return Boolean(
        requirementForm.file ||
        editingSubmission.value?.file_path ||
        requirementForm.remarks,
    );
});
const activeTermLabel = computed(() => {
    if (!props.activeTerm) {
        return 'Select semester';
    }

    return [
        props.activeTerm.semester,
        props.activeTerm.school_year,
        props.activeTerm.term_id ? `Term ID ${props.activeTerm.term_id}` : null,
    ]
        .filter(Boolean)
        .join(' · ');
});

const currentApplicationRouteParams = () => ({
    society: props.society.id,
    accreditationRequest: props.currentApplication.id,
});

const createApplication = () =>
    applicationForm.post(storeAccreditation.url(props.society));
const submitApplication = () => {
    if (!props.currentApplication) return;

    router.post(submitAccreditation.url(currentApplicationRouteParams()));
};
const uploadRequirement = () => {
    if (!props.currentApplication) return;
    requirementForm.post(
        storeRequirement.url(currentApplicationRouteParams()),
        {
            forceFormData: true,
            onSuccess: () => {
                editingSubmission.value = null;
                requirementForm.reset('requirement_id', 'file', 'remarks');
                removeFile();
            },
        },
    );
};

const editSubmission = (submission: any) => {
    editingSubmission.value = submission;
    requirementForm.clearErrors();
    requirementForm.requirement_id = String(submission.requirement_id);
    requirementForm.remarks = submission.remarks ?? '';
    removeFile();
    uploadSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const cancelEditSubmission = () => {
    editingSubmission.value = null;
    requirementForm.reset('requirement_id', 'file', 'remarks');
    removeFile();
};

const deleteSubmission = (submission: any) => {
    deletingSubmission.value = submission;
};

const cancelDeleteSubmission = () => {
    deletingSubmission.value = null;
};

const confirmDeleteSubmission = () => {
    if (!props.currentApplication || !deletingSubmission.value) {
        return;
    }

    router.delete(
        destroyRequirement.url({
            ...currentApplicationRouteParams(),
            submission: deletingSubmission.value.id,
        }),
        {
            onFinish: () => {
                deletingSubmission.value = null;
            },
        },
    );
};

const statusColors = (status: string) => {
    return (
        {
            approved:
                'bg-emerald-50 text-emerald-700 border-emerald-200/50 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
            rejected:
                'bg-rose-50 text-rose-700 border-rose-200/50 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20',
            returned:
                'bg-amber-50 text-amber-700 border-amber-200/50 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
            submitted:
                'bg-indigo-50 text-indigo-700 border-indigo-200/50 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20',
            under_review:
                'bg-violet-50 text-violet-700 border-violet-200/50 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/20',
            draft: 'bg-slate-50 text-slate-700 border-slate-200/50 dark:bg-slate-800/50 dark:text-slate-300 dark:border-slate-700/50',
            pending:
                'bg-slate-50 text-slate-500 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-400 dark:border-slate-700/30',
            complete:
                'bg-emerald-50 text-emerald-700 border-emerald-200/50 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
        }[status.toLowerCase()] ??
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
    );
};
</script>

<template>
    <Head title="Accreditation Application" />

    <div
        class="flex min-h-screen flex-col bg-slate-50/50 p-4 lg:p-6 dark:bg-slate-950"
    >
        <div class="flex min-h-0 flex-1 flex-col gap-5">
            <!-- Header Card -->
            <div
                class="relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-5 shadow-sm backdrop-blur-xl dark:border-slate-800/60 dark:bg-slate-900/60"
            >
                <div
                    class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-emerald-500/10 blur-2xl dark:bg-emerald-500/5"
                ></div>
                <div
                    class="absolute -bottom-10 -left-10 h-28 w-28 rounded-full bg-sky-500/10 blur-2xl dark:bg-sky-500/5"
                ></div>

                <div
                    class="relative flex flex-col justify-between gap-4 md:flex-row md:items-center"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-[9px] font-black tracking-wider text-sky-700 uppercase dark:bg-sky-950/50 dark:text-sky-400"
                                >Portal</span
                            >
                            <span class="text-slate-300 dark:text-slate-700"
                                >·</span
                            >
                            <p
                                class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                            >
                                Society Accreditation
                            </p>
                        </div>
                        <h1
                            class="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ society.full_name ?? society.name }}
                        </h1>
                        <p
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            Yearly and semestral OSA accreditation checklist &
                            requirement tracker
                        </p>
                    </div>

                    <div
                        v-if="currentApplication"
                        class="flex items-center gap-4 rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-2.5 dark:border-slate-800/40 dark:bg-slate-900/40"
                    >
                        <div class="text-right">
                            <p
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Checklist Progress
                            </p>
                            <p
                                class="text-xs font-black text-slate-700 dark:text-slate-200"
                            >
                                {{ completion }}% Complete
                            </p>
                        </div>
                        <div class="relative flex items-center justify-center">
                            <!-- Circular progress representation -->
                            <svg class="h-10 w-10 -rotate-90 transform">
                                <circle
                                    cx="20"
                                    cy="20"
                                    r="16"
                                    stroke="currentColor"
                                    class="text-slate-200 dark:text-slate-800"
                                    stroke-width="3"
                                    fill="transparent"
                                />
                                <circle
                                    cx="20"
                                    cy="20"
                                    r="16"
                                    stroke="currentColor"
                                    class="text-emerald-500"
                                    stroke-width="3.5"
                                    fill="transparent"
                                    :stroke-dasharray="2 * Math.PI * 16"
                                    :stroke-dashoffset="
                                        2 *
                                        Math.PI *
                                        16 *
                                        (1 - completion / 100)
                                    "
                                />
                            </svg>
                            <span
                                class="absolute text-[10px] font-black text-slate-800 dark:text-white"
                                >{{ completion }}%</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Split Layout -->
            <div class="grid min-h-0 flex-1 gap-5 xl:grid-cols-[380px_1fr]">
                <!-- Sidebar forms -->
                <aside class="space-y-5">
                    <!-- New Request Card -->
                    <div
                        class="relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-800/60"
                        >
                            <Sparkles class="size-4 text-sky-500" />
                            <h2
                                class="text-xs font-black tracking-wider text-slate-900 uppercase dark:text-white"
                            >
                                Initialize Application
                            </h2>
                        </div>

                        <!-- Warnings & Requirements block -->
                        <div
                            v-if="activeTerm"
                            class="mt-4 flex gap-2 rounded-lg border border-sky-100 bg-sky-50/50 p-3 text-[11px] text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-300"
                        >
                            <Info class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-bold">Active Academic Term</p>
                                <p
                                    class="mt-0.5 font-medium text-sky-700/95 dark:text-sky-300/80"
                                >
                                    {{ activeTermLabel }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="!isSocietyPublished"
                            class="mt-3 flex gap-2 rounded-lg border border-rose-100 bg-rose-50/50 p-3 text-[11px] text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/5 dark:text-rose-300"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-bold">Society Profile Draft</p>
                                <p class="mt-0.5 font-medium">
                                    Please publish your society profile before
                                    seeking official accreditation.
                                </p>
                                <Link
                                    :href="`/societies/manage/${society.id}/profile`"
                                    class="mt-1.5 inline-flex items-center gap-1 font-bold underline hover:text-rose-900 dark:hover:text-rose-200"
                                >
                                    Publish Profile
                                    <ChevronRight class="size-3" />
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="missingOfficerPositions.length"
                            class="mt-3 flex gap-2 rounded-lg border border-amber-100 bg-amber-50/50 p-3 text-[11px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-300"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="font-bold">Setup Required Officers</p>
                                <p class="mt-0.5 leading-relaxed font-medium">
                                    Add active officers for
                                    <span class="font-bold">{{
                                        officerReadiness?.school_year ??
                                        applicationForm.school_year
                                    }}</span
                                    >. Missing:
                                    <span class="font-bold">{{
                                        missingOfficerPositions.join(', ')
                                    }}</span
                                    >.
                                </p>
                                <Link
                                    :href="`/societies/manage/${society.id}/officers`"
                                    class="mt-1.5 inline-flex items-center gap-1 font-bold underline hover:text-amber-900 dark:hover:text-amber-200"
                                >
                                    Manage Officers
                                    <ChevronRight class="size-3" />
                                </Link>
                            </div>
                        </div>

                        <p
                            v-if="applicationForm.errors.society"
                            class="mt-3 rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600 dark:border-rose-500/20 dark:bg-rose-500/5"
                        >
                            {{ applicationForm.errors.society }}
                        </p>

                        <!-- Form inputs -->
                        <form
                            class="mt-4 space-y-3"
                            @submit.prevent="createApplication"
                        >
                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Academic Semester</label
                                >
                                <select
                                    v-model="applicationForm.semester"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                >
                                    <option
                                        v-if="activeTerm"
                                        :value="activeTerm.semester"
                                    >
                                        {{ activeTerm.semester }}
                                    </option>
                                    <template v-else>
                                        <option value="">
                                            Select Semester
                                        </option>
                                        <option>1st Semester</option>
                                        <option>2nd Semester</option>
                                        <option>Midyear</option>
                                    </template>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >School Year</label
                                >
                                <input
                                    v-model="applicationForm.school_year"
                                    :readonly="Boolean(activeTerm)"
                                    placeholder="e.g. 2026-2027"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Submission Mode</label
                                >
                                <select
                                    v-model="applicationForm.mode_of_submission"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                >
                                    <option value="online">
                                        Online Submission
                                    </option>
                                    <option value="onsite">
                                        Onsite Document Submission
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Authorized Submitter Name</label
                                >
                                <input
                                    v-model="applicationForm.submitted_by_name"
                                    placeholder="Full name of representative"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Representative Position</label
                                >
                                <input
                                    v-model="
                                        applicationForm.submitted_by_position
                                    "
                                    placeholder="Position in society"
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <button
                                :disabled="
                                    applicationForm.processing ||
                                    !canCreateApplication
                                "
                                class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-xs font-black tracking-wider text-white uppercase shadow-sm transition-all hover:bg-sky-500 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:bg-sky-600 dark:hover:bg-sky-500"
                            >
                                Create Draft Application
                            </button>
                        </form>
                    </div>

                    <!-- Current Request Details -->
                    <div
                        v-if="currentApplication"
                        class="relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800/60"
                        >
                            <h2
                                class="text-xs font-black tracking-wider text-slate-900 uppercase dark:text-white"
                            >
                                Current Application
                            </h2>
                            <span
                                class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                                :class="statusColors(currentApplication.status)"
                            >
                                {{ currentApplication.status }}
                            </span>
                        </div>

                        <dl class="mt-4 space-y-3 text-xs">
                            <div
                                class="flex items-center justify-between gap-3 border-b border-slate-50 py-1.5 dark:border-slate-800/20"
                            >
                                <dt class="font-medium text-slate-400">
                                    Request No.
                                </dt>
                                <dd
                                    class="font-mono font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        currentApplication.accreditation_request_no ??
                                        'Awaiting OSA Review'
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3 border-b border-slate-50 py-1.5 dark:border-slate-800/20"
                            >
                                <dt class="font-medium text-slate-400">
                                    Active Term
                                </dt>
                                <dd
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ currentApplication.semester }} ·
                                    {{ currentApplication.school_year }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3 border-b border-slate-50 py-1.5 dark:border-slate-800/20"
                            >
                                <dt class="font-medium text-slate-400">Mode</dt>
                                <dd
                                    class="font-bold text-slate-700 uppercase dark:text-slate-200"
                                >
                                    {{ currentApplication.mode_of_submission }}
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3 py-1.5"
                            >
                                <dt class="font-medium text-slate-400">
                                    Submitter
                                </dt>
                                <dd
                                    class="text-right font-bold text-slate-700 dark:text-slate-200"
                                >
                                    <p>
                                        {{
                                            currentApplication.submitted_by_name
                                        }}
                                    </p>
                                    <p
                                        class="text-[10px] font-semibold text-slate-400"
                                    >
                                        {{
                                            currentApplication.submitted_by_position
                                        }}
                                    </p>
                                </dd>
                            </div>
                        </dl>

                        <!-- Submit application button -->
                        <button
                            v-if="canEdit"
                            :disabled="completion < 100"
                            class="dark:hover:bg-slate-850 mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-black tracking-wider text-slate-700 uppercase transition-all hover:bg-slate-100 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                            @click="submitApplication"
                        >
                            Submit Request to OSA
                            <ArrowRight class="size-3.5" />
                        </button>
                        <p
                            v-if="canEdit && completion < 100"
                            class="mt-2 flex items-center justify-center gap-1 text-center text-[10px] font-bold text-slate-400"
                        >
                            <Info class="size-3 text-sky-500" /> Upload all
                            checklist items to enable submission.
                        </p>
                    </div>
                </aside>

                <!-- Main Content Area -->
                <main class="min-h-0 space-y-5">
                    <!-- Quick Stats Dashboard -->
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div
                            class="group relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-4 shadow-sm transition-all hover:scale-[1.01] hover:shadow-md dark:border-slate-800/60 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <Clock class="size-5 text-sky-500" />
                                <span
                                    class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Total Items</span
                                >
                            </div>
                            <p
                                class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                {{
                                    stats?.total_requirements ??
                                    requirements.length
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-bold text-slate-400"
                            >
                                Checklist requirements
                            </p>
                        </div>

                        <div
                            class="group relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-4 shadow-sm transition-all hover:scale-[1.01] hover:shadow-md dark:border-slate-800/60 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <CheckCircle2 class="size-5 text-emerald-500" />
                                <span
                                    class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Approved</span
                                >
                            </div>
                            <p
                                class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ stats?.complete_requirements ?? 0 }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-bold text-slate-400"
                            >
                                Approved by OSA
                            </p>
                        </div>

                        <Link
                            :href="`/societies/manage/${society.id}/officers`"
                            class="group relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-4 shadow-sm transition-all hover:scale-[1.01] hover:shadow-md dark:border-slate-800/60 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <Users class="size-5 text-indigo-500" />
                                <span
                                    class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Officers</span
                                >
                            </div>
                            <p
                                class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ stats?.officers ?? 0 }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-bold text-slate-400"
                            >
                                Assigned roster
                            </p>
                        </Link>

                        <Link
                            :href="`/societies/manage/${society.id}/advisers`"
                            class="group relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-4 shadow-sm transition-all hover:scale-[1.01] hover:shadow-md dark:border-slate-800/60 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <UserCheck class="size-5 text-violet-500" />
                                <span
                                    class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Advisers</span
                                >
                            </div>
                            <p
                                class="mt-3 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                            >
                                {{ stats?.advisers ?? 0 }}
                            </p>
                            <p
                                class="mt-0.5 text-[10px] font-bold text-slate-400"
                            >
                                Appointed advisers
                            </p>
                        </Link>
                    </div>

                    <!-- Upload Form Dropzone Section -->
                    <div
                        v-if="currentApplication && canEdit"
                        ref="uploadSection"
                        class="relative overflow-hidden rounded-xl border border-slate-200/60 bg-white p-5 shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-800/60"
                        >
                            <div class="flex items-center gap-2">
                                <FileUp class="size-4 text-sky-500" />
                                <h2
                                    class="text-xs font-black tracking-wider text-slate-900 uppercase dark:text-white"
                                >
                                    {{
                                        editingSubmission
                                            ? 'Edit Requirement Document'
                                            : 'Upload Requirement Document'
                                    }}
                                </h2>
                            </div>
                            <button
                                v-if="editingSubmission"
                                type="button"
                                class="dark:hover:bg-slate-850 inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 dark:border-slate-800 dark:text-slate-400 dark:hover:text-slate-100"
                                title="Cancel edit"
                                @click="cancelEditSubmission"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <form
                            class="space-y-4"
                            @submit.prevent="uploadRequirement"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-1">
                                    <label
                                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >Select Checklist Item</label
                                    >
                                    <select
                                        v-model="requirementForm.requirement_id"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                    >
                                        <option value="">
                                            Choose requirement...
                                        </option>
                                        <option
                                            v-for="requirement in requirements"
                                            :key="requirement.id"
                                            :value="requirement.id"
                                        >
                                            {{ requirement.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="
                                            requirementForm.errors
                                                .requirement_id
                                        "
                                        class="mt-0.5 text-[10px] font-bold text-rose-500"
                                    >
                                        {{
                                            requirementForm.errors
                                                .requirement_id
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >Submission Remarks (Optional)</label
                                    >
                                    <input
                                        v-model="requirementForm.remarks"
                                        placeholder="Add comments or resubmission notes..."
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 text-xs font-medium focus:border-sky-500 focus:bg-white focus:ring-1 focus:ring-sky-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Beautiful Dropzone Area -->
                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Upload PDF File</label
                                >

                                <div
                                    class="group relative flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-6 transition-all"
                                    :class="[
                                        isDragActive
                                            ? 'border-sky-500 bg-sky-500/5'
                                            : 'dark:border-slate-850 border-slate-200 bg-slate-50/30 hover:border-slate-300 hover:bg-slate-50/70 dark:bg-slate-900/20 dark:hover:bg-slate-900/40',
                                        requirementForm.file
                                            ? 'border-emerald-500/40 bg-emerald-500/[0.02]'
                                            : '',
                                    ]"
                                    @click="triggerFileInput"
                                    @dragover="handleDragOver"
                                    @dragleave="handleDragLeave"
                                    @drop="handleDrop"
                                >
                                    <input
                                        type="file"
                                        ref="fileInput"
                                        accept=".pdf"
                                        class="hidden"
                                        @change="handleFileChange"
                                    />

                                    <!-- Inner Display (No File Selected) -->
                                    <template v-if="!requirementForm.file">
                                        <div
                                            class="dark:bg-slate-850 rounded-full bg-slate-100 p-3 transition-all duration-300 group-hover:scale-110"
                                        >
                                            <FileUp
                                                class="size-6 text-slate-400 transition-colors group-hover:text-sky-500"
                                            />
                                        </div>
                                        <div class="text-center">
                                            <p
                                                class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                            >
                                                Click to upload or drag & drop
                                                file
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] font-semibold text-slate-400"
                                            >
                                                Only PDF documents are accepted
                                                (Max 20MB)
                                            </p>
                                        </div>
                                    </template>

                                    <!-- Inner Display (File Selected) -->
                                    <template v-else>
                                        <div
                                            class="flex w-full items-center justify-between gap-4 px-2"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-3"
                                            >
                                                <div
                                                    class="shrink-0 rounded-lg bg-emerald-500/10 p-2.5 dark:bg-emerald-500/20"
                                                >
                                                    <FileText
                                                        class="size-6 text-emerald-600 dark:text-emerald-400"
                                                    />
                                                </div>
                                                <div class="min-w-0">
                                                    <p
                                                        class="truncate pr-4 text-xs font-bold text-slate-700 dark:text-slate-200"
                                                    >
                                                        {{ selectedFileName }}
                                                    </p>
                                                    <p
                                                        class="mt-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                                                    >
                                                        {{ selectedFileSize }} ·
                                                        Ready to Upload
                                                    </p>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                class="dark:hover:bg-slate-850 shrink-0 rounded-lg p-2 text-slate-400 transition-all hover:bg-slate-100 hover:text-rose-600 active:scale-95"
                                                @click.stop="removeFile"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <p
                                    v-if="requirementForm.errors.file"
                                    class="mt-0.5 text-[10px] font-bold text-rose-500"
                                >
                                    {{ requirementForm.errors.file }}
                                </p>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button
                                    :disabled="
                                        requirementForm.processing ||
                                        !canSubmitRequirement
                                    "
                                    class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-5 py-2 text-xs font-black text-white uppercase shadow-sm transition-all hover:bg-sky-500 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <FileUp class="size-3.5" />
                                    {{
                                        requirementForm.processing
                                            ? 'Saving Document...'
                                            : editingSubmission
                                              ? 'Save Changes'
                                              : 'Submit to Checklist'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Checklist Section -->
                    <section
                        class="overflow-hidden rounded-xl border border-slate-200/60 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800/60"
                        >
                            <div class="flex items-center gap-2">
                                <FileText class="size-4 text-sky-500" />
                                <h2
                                    class="text-xs font-black tracking-wider text-slate-900 uppercase dark:text-white"
                                >
                                    Accreditation Checklist
                                </h2>
                            </div>
                            <span
                                v-if="currentApplication"
                                class="text-[10px] font-bold text-slate-400"
                            >
                                {{
                                    currentApplication.submissions?.length ?? 0
                                }}
                                Requirements
                            </span>
                        </div>

                        <div
                            class="dark:divide-slate-850 divide-y divide-slate-100"
                        >
                            <!-- Checklist Item -->
                            <div
                                v-for="submission in currentApplication?.submissions ??
                                []"
                                :key="submission.id"
                                class="flex flex-col gap-3 p-5 transition-colors hover:bg-slate-50/30 md:flex-row md:items-center md:justify-between dark:hover:bg-slate-900/30"
                            >
                                <div class="min-w-0 flex-1 space-y-1 pr-4">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p
                                            class="truncate text-xs font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ submission.requirement?.name }}
                                        </p>
                                        <span
                                            v-if="
                                                submission.requirement
                                                    ?.is_required
                                            "
                                            class="inline-flex items-center rounded-full bg-rose-50 px-1.5 py-0.5 text-[8px] font-black text-rose-700 uppercase dark:bg-rose-950/40 dark:text-rose-400"
                                            >Required</span
                                        >
                                    </div>
                                    <div
                                        class="flex min-w-0 items-center gap-2 text-[10px] font-semibold text-slate-400"
                                    >
                                        <FileText class="size-3.5 shrink-0" />
                                        <span class="truncate">{{
                                            submission.original_file_name ??
                                            'No file submitted yet'
                                        }}</span>
                                        <template v-if="submission.file_path">
                                            <span
                                                class="text-slate-300 dark:text-slate-700"
                                                >·</span
                                            >
                                            <a
                                                :href="`/storage/${submission.file_path}`"
                                                target="_blank"
                                                class="font-bold text-sky-600 hover:underline"
                                                >View PDF</a
                                            >
                                        </template>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 flex-wrap items-center gap-3"
                                >
                                    <!-- Status badge -->
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-0.5 text-[9px] font-black tracking-wider uppercase"
                                        :class="statusColors(submission.status)"
                                    >
                                        {{ submission.status }}
                                    </span>

                                    <!-- Reviewer / Date info -->
                                    <div
                                        class="hidden w-36 text-right sm:block md:text-right"
                                    >
                                        <p
                                            class="text-[9px] font-bold text-slate-400 uppercase"
                                        >
                                            Review Status
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                submission.checked_at
                                                    ? 'Verified by OSA'
                                                    : 'Awaiting Review'
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="canEdit"
                                        class="flex items-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-slate-800 dark:text-slate-400 dark:hover:border-sky-500/30 dark:hover:bg-sky-500/10 dark:hover:text-sky-300"
                                            title="Edit requirement submission"
                                            @click="editSubmission(submission)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="
                                                !submission.file_path &&
                                                !submission.remarks
                                            "
                                            class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:text-slate-400 dark:hover:border-rose-500/30 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                                            title="Delete requirement attachment"
                                            @click="
                                                deleteSubmission(submission)
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Remarks display if returned -->
                                <div
                                    v-if="
                                        submission.status === 'returned' &&
                                        submission.remarks
                                    "
                                    class="mt-1 flex w-full items-start gap-1 border-t border-rose-100/50 pt-2 text-[10px] text-rose-600 dark:border-rose-500/10 dark:text-rose-400"
                                >
                                    <AlertCircle
                                        class="mt-0.5 size-3.5 shrink-0"
                                    />
                                    <div>
                                        <span class="font-bold"
                                            >OSA Correction Note:
                                        </span>
                                        <span class="font-semibold">{{
                                            submission.remarks
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty Checklist State -->
                            <div
                                v-if="!currentApplication"
                                class="flex flex-col items-center justify-center p-12 text-center"
                            >
                                <div
                                    class="rounded-full bg-slate-50 p-4 dark:bg-slate-900/50"
                                >
                                    <HelpCircle
                                        class="size-8 text-slate-300 dark:text-slate-700"
                                    />
                                </div>
                                <h3
                                    class="mt-4 text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    No Checklist Generated
                                </h3>
                                <p
                                    class="mt-1 max-w-sm text-[11px] leading-relaxed font-semibold text-slate-400"
                                >
                                    Please choose active terms and
                                    representative details in the sidebar to
                                    initialize an accreditation draft. This will
                                    automatically populate the official OSA
                                    checklist.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <div
            v-if="deletingSubmission"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300"
                    >
                        <Trash2 class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h2
                            class="text-sm font-black tracking-wider text-slate-950 uppercase dark:text-white"
                        >
                            Delete Attachment
                        </h2>
                        <p
                            class="mt-1 text-xs leading-relaxed font-semibold text-slate-500 dark:text-slate-400"
                        >
                            This will remove the uploaded file and reset
                            <span
                                class="font-black text-slate-700 dark:text-slate-200"
                                >{{
                                    deletingSubmission.requirement?.name ??
                                    'this requirement'
                                }}</span
                            >
                            back to pending.
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="dark:hover:bg-slate-850 rounded-lg border border-slate-200 px-4 py-2 text-xs font-black tracking-wider text-slate-600 uppercase transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300"
                        @click="cancelDeleteSubmission"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-black tracking-wider text-white uppercase transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="confirmDeleteSubmission"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
