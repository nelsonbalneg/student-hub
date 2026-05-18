<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CheckCircle2, Clock, FileUp } from 'lucide-vue-next';

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

const completion = computed(() => props.currentApplication?.completion_percentage ?? 0);
const canEdit = computed(() => !props.currentApplication || props.currentApplication.status !== 'approved');
const missingOfficerPositions = computed(() => props.officerReadiness?.missing_positions ?? []);
const isSocietyPublished = computed(() => String(props.society?.status ?? '').toLowerCase() !== 'draft');
const canCreateApplication = computed(() => isSocietyPublished.value && missingOfficerPositions.value.length === 0);
const activeTermLabel = computed(() => {
    if (!props.activeTerm) {
        return 'Select semester';
    }

    return [
        props.activeTerm.semester,
        props.activeTerm.school_year,
        props.activeTerm.term_id ? `Term ID ${props.activeTerm.term_id}` : null,
    ].filter(Boolean).join(' · ');
});

const createApplication = () => applicationForm.post(`/societies/manage/${props.society.id}/accreditation`);
const submitApplication = () => router.post(`/societies/manage/${props.society.id}/accreditation/${props.currentApplication.id}/submit`);
const uploadRequirement = () => {
    if (!props.currentApplication) return;
    requirementForm.post(`/societies/manage/${props.society.id}/accreditation/${props.currentApplication.id}/requirements`, {
        forceFormData: true,
        onSuccess: () => requirementForm.reset('requirement_id', 'file', 'remarks'),
    });
};
</script>

<template>
    <Head title="Accreditation Application" />

    <div class="flex min-h-screen flex-col bg-slate-50/70 p-4 dark:bg-slate-950 lg:p-5">
        <div class="flex min-h-0 flex-1 flex-col gap-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-sky-600 dark:text-sky-400">Registration / Accreditation</p>
                    <h1 class="text-xl font-black text-slate-950 dark:text-white">{{ society.full_name ?? society.name }}</h1>
                    <p class="text-sm font-semibold text-slate-500">Yearly and semestral OSA application history</p>
                </div>
                <div v-if="currentApplication" class="flex items-center gap-3">
                    <div class="h-2 w-44 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${completion}%` }" />
                    </div>
                    <span class="text-sm font-black text-slate-700 dark:text-slate-200">{{ completion }}%</span>
                </div>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[420px_1fr]">
                <aside class="space-y-4 overflow-y-auto pr-0 xl:pr-1">
                    <form class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="createApplication">
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">New Accreditation</h2>
                        <p v-if="activeTerm" class="mt-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-[11px] font-semibold text-sky-800 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-300">
                            Active term: {{ activeTermLabel }}
                        </p>
                        <div v-if="!isSocietyPublished" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] font-semibold text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300">
                            Publish the society registration before creating an accreditation application.
                            <Link :href="`/societies/manage/${society.id}/profile`" class="ml-1 underline">Open profile</Link>
                        </div>
                        <div v-if="missingOfficerPositions.length" class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300">
                            Add active officers for {{ officerReadiness?.school_year ?? applicationForm.school_year }} before creating an application. Missing: {{ missingOfficerPositions.join(', ') }}.
                            <Link :href="`/societies/manage/${society.id}/officers`" class="ml-1 underline">Manage officers</Link>
                        </div>
                        <p v-if="applicationForm.errors.society" class="mt-3 text-xs font-semibold text-red-600">
                            {{ applicationForm.errors.society }}
                        </p>
                        <div class="mt-4 space-y-3">
                            <select v-model="applicationForm.semester" class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option v-if="activeTerm" :value="activeTerm.semester">
                                    {{ activeTermLabel }}
                                </option>
                                <template v-else>
                                    <option value="">Select semester</option>
                                    <option>1st Semester</option>
                                    <option>2nd Semester</option>
                                    <option>Midyear</option>
                                </template>
                            </select>
                            <input v-model="applicationForm.school_year" :readonly="Boolean(activeTerm)" placeholder="School year" class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                            <select v-model="applicationForm.mode_of_submission" class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="online">Online</option>
                                <option value="onsite">Onsite</option>
                            </select>
                            <input v-model="applicationForm.submitted_by_name" placeholder="Submitted by" class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                            <input v-model="applicationForm.submitted_by_position" placeholder="Position" class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                            <button :disabled="applicationForm.processing || !canCreateApplication" class="w-full rounded-md bg-slate-950 px-4 py-3 text-sm font-black uppercase tracking-wider text-white disabled:cursor-not-allowed disabled:opacity-60 dark:bg-sky-600">Create Draft</button>
                        </div>
                    </form>

                    <div v-if="currentApplication" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Current Request</h2>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">No.</dt><dd class="font-bold text-slate-900 dark:text-white">{{ currentApplication.accreditation_request_no ?? 'Pending OSA' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Term</dt><dd class="font-bold text-slate-900 dark:text-white">{{ currentApplication.semester }} {{ currentApplication.school_year }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd class="font-black uppercase text-sky-600">{{ currentApplication.status }}</dd></div>
                        </dl>
                        <button v-if="canEdit" class="mt-4 w-full rounded-md border border-slate-200 px-4 py-3 text-sm font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="submitApplication">Submit to OSA</button>
                    </div>
                </aside>

                <main class="min-h-0 space-y-4 overflow-y-auto">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <Clock class="size-5 text-amber-600" />
                            <p class="mt-3 text-xs font-bold uppercase text-slate-500">Requirements</p>
                            <p class="text-2xl font-black text-slate-950 dark:text-white">{{ stats?.total_requirements ?? requirements.length }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <CheckCircle2 class="size-5 text-emerald-600" />
                            <p class="mt-3 text-xs font-bold uppercase text-slate-500">Complete</p>
                            <p class="text-2xl font-black text-slate-950 dark:text-white">{{ stats?.complete_requirements ?? 0 }}</p>
                        </div>
                        <Link :href="`/societies/manage/${society.id}/officers`" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-bold uppercase text-slate-500">Officers</p>
                            <p class="mt-4 text-2xl font-black text-slate-950 dark:text-white">{{ stats?.officers ?? 0 }}</p>
                        </Link>
                        <Link :href="`/societies/manage/${society.id}/advisers`" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-bold uppercase text-slate-500">Advisers</p>
                            <p class="mt-4 text-2xl font-black text-slate-950 dark:text-white">{{ stats?.advisers ?? 0 }}</p>
                        </Link>
                    </div>

                    <form v-if="currentApplication && canEdit" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="uploadRequirement">
                        <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                            <select v-model="requirementForm.requirement_id" class="h-10 rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="">Select requirement</option>
                                <option v-for="requirement in requirements" :key="requirement.id" :value="requirement.id">{{ requirement.name }}</option>
                            </select>
                            <input type="file" accept=".pdf" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white" @input="requirementForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                            <button class="rounded-md bg-sky-600 px-4 py-2 text-sm font-black text-white"><FileUp class="inline size-4" /> Upload</button>
                        </div>
                        <textarea v-model="requirementForm.remarks" rows="2" placeholder="Submission remarks" class="mt-3 w-full rounded-md border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                    </form>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white">Requirements Checklist</h2>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="submission in currentApplication?.submissions ?? []" :key="submission.id" class="grid gap-3 px-5 py-4 md:grid-cols-[1fr_140px_160px] md:items-center">
                                <div>
                                    <p class="text-sm font-bold text-slate-950 dark:text-white">{{ submission.requirement?.name }}</p>
                                    <p class="text-xs text-slate-500">{{ submission.original_file_name ?? 'No file uploaded yet' }}</p>
                                </div>
                                <span class="rounded-md px-2 py-1 text-center text-xs font-black uppercase" :class="submission.status === 'complete' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'">{{ submission.status }}</span>
                                <p class="text-xs text-slate-500">{{ submission.checked_at ? 'Checked' : 'Awaiting review' }}</p>
                            </div>
                            <div v-if="!currentApplication" class="p-10 text-center text-sm font-semibold text-slate-500">
                                Create an accreditation draft to generate the OSA checklist.
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </div>
</template>
