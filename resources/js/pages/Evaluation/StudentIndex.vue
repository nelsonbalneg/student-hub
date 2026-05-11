<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Eye,
    FileText,
    Loader2,
    MessageSquare,
    Send,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type Period = {
    id: number;
    title: string;
    description: string | null;
    academic_year: string;
    semester: string;
    start_date: string;
    end_date: string;
    status: string;
};

type Feedback = {
    id: number;
    message: string;
    visibility: string;
    created_at: string;
    author: { name: string } | null;
};

type EvaluationRequest = {
    id: number;
    intent: string;
    remarks: string | null;
    status: string;
    registrar_feedback: string | null;
    done_at: string | null;
    created_at: string;
    period: Period | null;
    feedbacks: Feedback[];
};

type Page<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
};

const props = defineProps<{
    activePeriod: Period | null;
    currentRequest: EvaluationRequest | null;
    requests: Page<EvaluationRequest>;
    student: {
        name: string;
        student_no: string | null;
    };
    can: {
        submitIntent: boolean;
    };
}>();

const intentOpen = ref(false);
const cancelTarget = ref<EvaluationRequest | null>(null);
const detailsTarget = ref<EvaluationRequest | null>(null);

const form = useForm({
    evaluation_period_id: props.activePeriod?.id ?? '',
    intent: '',
    remarks: '',
});

const actionForm = useForm({});

const canSubmit = computed(() => props.can.submitIntent && props.activePeriod && !props.currentRequest);

const statusStyles: Record<string, string> = {
    submitted: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200',
    under_evaluation: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200',
    needs_action: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200',
    resolved: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    done: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200',
    cancelled: 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200',
};

const formatDate = (value: string | null) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const submitIntent = () => {
    if (!props.activePeriod) return;

    form.evaluation_period_id = props.activePeriod.id;
    form.post('/student/evaluation/intent', {
        preserveScroll: true,
        onSuccess: () => {
            intentOpen.value = false;
            form.reset('intent', 'remarks');
        },
    });
};

const cancelRequest = () => {
    if (!cancelTarget.value) return;

    actionForm.patch(`/student/evaluation/requests/${cancelTarget.value.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => (cancelTarget.value = null),
    });
};

const navigatePage = (url?: string | null) => {
    if (!url) return;

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Evaluation" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <section class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 pb-5 backdrop-blur dark:border-white/10 dark:bg-slate-950/95">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase text-slate-600 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <ClipboardCheck class="size-3.5 text-sky-600" />
                        Evaluation
                    </div>
                    <h1 class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white">My Evaluation</h1>
                    <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
                        Submit and track your intent for registrar evaluation.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Student</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ student.student_no ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Active</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ activePeriod ? 'Available' : 'None' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <p class="text-[10px] font-bold uppercase text-slate-400">History</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ requests.meta.total }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <div class="xl:col-span-7">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200">
                                <CalendarDays class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-950 dark:text-white">Active Evaluation Period</h2>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Visible only during active registrar calls</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="activePeriod" class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                                        {{ activePeriod.status }}
                                    </span>
                                    <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                                        {{ activePeriod.academic_year }} · {{ activePeriod.semester }}
                                    </span>
                                </div>
                                <h3 class="mt-3 text-xl font-bold text-slate-950 dark:text-white">{{ activePeriod.title }}</h3>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">{{ activePeriod.description }}</p>
                                <div class="mt-4 flex flex-wrap gap-3 text-xs font-bold text-slate-500">
                                    <span class="inline-flex items-center gap-1.5">
                                        <Clock3 class="size-3.5" />
                                        {{ formatDate(activePeriod.start_date) }} to {{ formatDate(activePeriod.end_date) }}
                                    </span>
                                </div>
                            </div>

                            <Button :disabled="!canSubmit" @click="intentOpen = true" class="h-10 rounded-md px-5 font-bold">
                                <Send class="mr-2 size-4" />
                                {{ currentRequest ? 'Intent Submitted' : 'Submit Intent' }}
                            </Button>
                        </div>

                        <div v-if="currentRequest" class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase text-slate-400">Current Request</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ currentRequest.intent }}</p>
                                </div>
                                <span :class="['inline-flex w-fit rounded-md border px-2.5 py-1 text-[10px] font-bold uppercase', statusStyles[currentRequest.status]]">
                                    {{ currentRequest.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <div v-if="currentRequest.registrar_feedback" class="mt-3 rounded-md border border-sky-100 bg-white p-3 text-sm font-medium text-slate-700 dark:border-sky-400/20 dark:bg-slate-900 dark:text-slate-200">
                                {{ currentRequest.registrar_feedback }}
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center">
                        <AlertCircle class="mx-auto size-10 text-slate-300 dark:text-slate-600" />
                        <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">No active evaluation period</h3>
                        <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">Please check back once the Registrar opens an evaluation call.</p>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Evaluation Timeline</h2>
                    <div class="mt-5 space-y-5">
                        <div v-for="item in ['Intent submitted', 'Registrar review', 'Feedback or resolution', 'Marked done']" :key="item" class="relative pl-7 before:absolute before:left-[7px] before:top-5 before:h-8 before:w-[2px] before:bg-slate-100 last:before:hidden dark:before:bg-white/10">
                            <div class="absolute left-0 top-1 size-3.5 rounded-full border-4 border-white bg-sky-500 dark:border-slate-950"></div>
                            <p class="text-xs font-bold text-slate-900 dark:text-white">{{ item }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/10">
                <div>
                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Request History</h2>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Your submitted evaluation intents</p>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase text-slate-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Evaluation Period</th>
                            <th class="px-4 py-3">Intent</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Feedback</th>
                            <th class="px-4 py-3">Date Submitted</th>
                            <th class="px-4 py-3">Date Completed</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="request in requests.data" :key="request.id" class="hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-900 dark:text-white">{{ request.period?.title }}</p>
                                <p class="text-[10px] font-bold uppercase text-slate-400">{{ request.period?.academic_year }} · {{ request.period?.semester }}</p>
                            </td>
                            <td class="max-w-[260px] truncate px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300">{{ request.intent }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase', statusStyles[request.status]]">
                                    {{ request.status.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="max-w-[260px] truncate px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-300">{{ request.registrar_feedback ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs font-medium text-slate-500">{{ formatDate(request.created_at) }}</td>
                            <td class="px-4 py-3 text-xs font-medium text-slate-500">{{ formatDate(request.done_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" size="sm" class="h-8 text-xs font-bold" @click="detailsTarget = request">
                                        <Eye class="mr-1.5 size-3.5" />
                                        Details
                                    </Button>
                                    <Button v-if="request.status === 'submitted'" variant="outline" size="sm" class="h-8 text-xs font-bold text-red-600" @click="cancelTarget = request">
                                        Cancel
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="requests.data.length === 0">
                            <td colspan="7" class="p-10 text-center">
                                <FileText class="mx-auto size-10 text-slate-300 dark:text-slate-600" />
                                <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">No evaluation requests yet</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="requests.meta.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/10">
                <p class="text-xs font-medium text-slate-500">Page {{ requests.meta.current_page }} of {{ requests.meta.last_page }}</p>
                <div class="flex gap-1">
                    <button v-for="link in requests.links" :key="link.label" :disabled="!link.url" class="rounded-md px-3 py-1 text-xs font-bold disabled:opacity-40" :class="link.active ? 'bg-sky-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10'" @click="navigatePage(link.url)" v-html="link.label"></button>
                </div>
            </div>
        </section>
    </div>

    <div v-if="intentOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-xl border bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Submit Intent for Evaluation</h2>
                <button @click="intentOpen = false"><X class="size-5 text-slate-400" /></button>
            </div>
            <div class="mt-4 space-y-4">
                <label class="grid gap-2">
                    <span class="text-xs font-bold uppercase text-slate-500">Intent</span>
                    <textarea v-model="form.intent" class="min-h-28 rounded-md border border-slate-200 bg-white p-3 text-sm dark:border-white/10 dark:bg-slate-900 dark:text-white" placeholder="Describe your intent for evaluation"></textarea>
                    <InputError :message="form.errors.intent" />
                </label>
                <label class="grid gap-2">
                    <span class="text-xs font-bold uppercase text-slate-500">Remarks</span>
                    <textarea v-model="form.remarks" class="min-h-20 rounded-md border border-slate-200 bg-white p-3 text-sm dark:border-white/10 dark:bg-slate-900 dark:text-white" placeholder="Optional notes"></textarea>
                    <InputError :message="form.errors.remarks" />
                </label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="intentOpen = false">Cancel</Button>
                <Button :disabled="form.processing" @click="submitIntent">
                    <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                    <Send v-else class="mr-2 size-4" />
                    Submit
                </Button>
            </div>
        </div>
    </div>

    <div v-if="cancelTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-xl border bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950">
            <XCircle class="size-9 text-red-500" />
            <h2 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">Cancel Request?</h2>
            <p class="mt-1 text-sm text-slate-500">This can only be done while the request is still submitted.</p>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="cancelTarget = null">Keep Request</Button>
                <Button variant="destructive" :disabled="actionForm.processing" @click="cancelRequest">Cancel Request</Button>
            </div>
        </div>
    </div>

    <div v-if="detailsTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-auto rounded-xl border bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Evaluation Details</h2>
                <button @click="detailsTarget = null"><X class="size-5 text-slate-400" /></button>
            </div>
            <div class="mt-5 space-y-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase text-slate-400">Intent</p>
                    <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">{{ detailsTarget.intent }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase text-slate-400">Feedback</p>
                    <div v-if="detailsTarget.feedbacks.length" class="mt-3 space-y-2">
                        <div v-for="feedback in detailsTarget.feedbacks" :key="feedback.id" class="rounded-md bg-white p-3 dark:bg-slate-900">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ feedback.message }}</p>
                            <p class="mt-1 text-[10px] font-bold uppercase text-slate-400">{{ feedback.author?.name ?? 'Registrar' }} · {{ formatDate(feedback.created_at) }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-1 text-sm text-slate-500">No feedback yet.</p>
                </div>
            </div>
        </div>
    </div>
</template>
