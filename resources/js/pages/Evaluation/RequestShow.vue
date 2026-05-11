<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    FileText,
    Loader2,
    MessageSquare,
    ShieldCheck,
    User,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type Feedback = {
    id: number;
    message: string;
    visibility: string;
    created_at: string;
    author: { name: string } | null;
};

type ActivityLog = {
    id: number;
    action: string;
    description: string | null;
    created_at: string;
    user: { name: string } | null;
};

type EvaluationRequest = {
    id: number;
    student_no: string | null;
    intent: string;
    remarks: string | null;
    status: string;
    registrar_feedback: string | null;
    evaluated_at: string | null;
    done_at: string | null;
    created_at: string;
    period: {
        title: string;
        description: string | null;
        academic_year: string;
        semester: string;
        start_date: string;
        end_date: string;
        status: string;
    } | null;
    student: {
        name: string;
        email: string | null;
        student_no: string | null;
        campus_name: string | null;
    } | null;
    evaluator: { name: string } | null;
    feedbacks: Feedback[];
};

const props = defineProps<{
    request: EvaluationRequest;
    activityLogs: ActivityLog[];
    can: {
        evaluate: boolean;
        feedback: boolean;
        markDone: boolean;
    };
}>();

const feedbackOpen = ref(false);
const feedbackForm = useForm({
    message: '',
    visibility: 'student_visible',
    status: '',
});

const statusForm = useForm({
    status: '',
});

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
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
};

const openFeedback = (status = '') => {
    feedbackForm.reset();
    feedbackForm.visibility = 'student_visible';
    feedbackForm.status = status;
    feedbackOpen.value = true;
};

const saveFeedback = () => {
    feedbackForm.post(`/admin/evaluations/requests/${props.request.id}/feedback`, {
        preserveScroll: true,
        onSuccess: () => {
            feedbackOpen.value = false;
            feedbackForm.reset();
        },
    });
};

const updateStatus = (status: string) => {
    statusForm.status = status;
    statusForm.patch(`/admin/evaluations/requests/${props.request.id}/status`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Evaluation Request #${request.id}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <section class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 pb-5 backdrop-blur dark:border-white/10 dark:bg-slate-950/95">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-start gap-3">
                    <Link href="/admin/evaluations?tab=requests" class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase text-slate-600 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                            <ClipboardCheck class="size-3.5 text-sky-600" />
                            Request Details
                        </div>
                        <h1 class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white">{{ request.student?.name ?? 'Student Request' }}</h1>
                        <p class="mt-1 text-sm font-medium text-slate-500">{{ request.period?.title }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span :class="['inline-flex items-center rounded-md border px-3 py-2 text-xs font-bold uppercase', statusStyles[request.status]]">{{ request.status.replace('_', ' ') }}</span>
                    <Button v-if="can.evaluate && request.status === 'submitted'" :disabled="statusForm.processing" @click="updateStatus('under_evaluation')">Start Evaluation</Button>
                    <Button v-if="can.feedback" variant="outline" @click="openFeedback()"><MessageSquare class="mr-2 size-4" />Add Feedback</Button>
                    <Button v-if="can.feedback" variant="outline" class="text-amber-600" @click="openFeedback('needs_action')">Needs Action</Button>
                    <Button v-if="can.feedback" variant="outline" class="text-emerald-600" @click="openFeedback('resolved')">Resolved</Button>
                    <Button v-if="can.markDone && request.status !== 'done'" :disabled="statusForm.processing" @click="updateStatus('done')"><CheckCircle2 class="mr-2 size-4" />Mark Done</Button>
                </div>
            </div>
        </section>

        <main class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <section class="space-y-5 xl:col-span-8">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <User class="size-5 text-sky-600" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Student Information</h2>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"><p class="text-[10px] font-bold uppercase text-slate-400">Student No</p><p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ request.student_no ?? '-' }}</p></div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"><p class="text-[10px] font-bold uppercase text-slate-400">Name</p><p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ request.student?.name ?? '-' }}</p></div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"><p class="text-[10px] font-bold uppercase text-slate-400">Campus</p><p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ request.student?.campus_name ?? '-' }}</p></div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <FileText class="size-5 text-violet-600" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Intent Details</h2>
                    </div>
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm font-medium leading-6 text-slate-800 dark:text-slate-100">{{ request.intent }}</p>
                        <p v-if="request.remarks" class="mt-3 border-t border-slate-200 pt-3 text-sm text-slate-500 dark:border-white/10">{{ request.remarks }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <MessageSquare class="size-5 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Registrar Feedback</h2>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="feedback in request.feedbacks" :key="feedback.id" class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ feedback.message }}</p>
                            <p class="mt-2 text-[10px] font-bold uppercase text-slate-400">{{ feedback.author?.name ?? 'Registrar' }} · {{ feedback.visibility.replace('_', ' ') }} · {{ formatDate(feedback.created_at) }}</p>
                        </div>
                        <p v-if="request.feedbacks.length === 0" class="text-sm text-slate-500">No feedback has been added yet.</p>
                    </div>
                </div>
            </section>

            <aside class="space-y-5 xl:col-span-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <ShieldCheck class="size-5 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Period Information</h2>
                    </div>
                    <div class="mt-4 space-y-3 text-sm">
                        <p class="font-bold text-slate-900 dark:text-white">{{ request.period?.title }}</p>
                        <p class="text-slate-500">{{ request.period?.academic_year }} · {{ request.period?.semester }}</p>
                        <p class="text-slate-500">{{ formatDate(request.period?.start_date ?? null) }} to {{ formatDate(request.period?.end_date ?? null) }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <Clock3 class="size-5 text-sky-600" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Status Timeline</h2>
                    </div>
                    <div class="mt-5 space-y-5">
                        <div v-for="log in activityLogs" :key="log.id" class="relative pl-7 before:absolute before:left-[7px] before:top-5 before:h-8 before:w-[2px] before:bg-slate-100 last:before:hidden dark:before:bg-white/10">
                            <div class="absolute left-0 top-1 size-3.5 rounded-full border-4 border-white bg-sky-500 dark:border-slate-950"></div>
                            <p class="text-xs font-bold text-slate-900 dark:text-white">{{ log.action }}</p>
                            <p class="mt-0.5 text-[10px] font-medium text-slate-500">{{ formatDate(log.created_at) }} · {{ log.user?.name ?? 'System' }}</p>
                        </div>
                    </div>
                </div>
            </aside>
        </main>
    </div>

    <div v-if="feedbackOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-xl border bg-white p-5 shadow-2xl dark:border-white/10 dark:bg-slate-950">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Add Feedback</h2>
                <button @click="feedbackOpen = false"><X class="size-5 text-slate-400" /></button>
            </div>
            <div class="mt-5 space-y-4">
                <textarea v-model="feedbackForm.message" class="min-h-32 w-full rounded-md border border-slate-200 p-3 text-sm dark:border-white/10 dark:bg-slate-900 dark:text-white" placeholder="Feedback message"></textarea>
                <InputError :message="feedbackForm.errors.message" />
                <select v-model="feedbackForm.visibility" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm dark:border-white/10 dark:bg-slate-900 dark:text-white">
                    <option value="student_visible">Student visible</option>
                    <option value="internal">Internal only</option>
                </select>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="feedbackOpen = false">Cancel</Button>
                <Button :disabled="feedbackForm.processing" @click="saveFeedback"><Loader2 v-if="feedbackForm.processing" class="mr-2 size-4 animate-spin" />Save Feedback</Button>
            </div>
        </div>
    </div>
</template>
