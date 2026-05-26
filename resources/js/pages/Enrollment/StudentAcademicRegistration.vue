<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import {
    AlertTriangle,
    BadgeCheck,
    BookOpenCheck,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    FileCheck2,
    GraduationCap,
    Loader2,
    RefreshCw,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const isSubmitting = ref(false);
const agreed = ref(false);
const submitted = ref(false);
const submittedAt = ref<string | null>(null);
const selectedTransactionType = ref<'Regular' | 'Irregular' | ''>('');
const showTransactionTypeModal = ref(false);
const statusLoading = ref(false);
const statusData = ref<{
    studentNo: string | null;
    studentName: string | null;
    termId: string | number | null;
    term: string | null;
    status: string | null;
    submitted: boolean;
    transactionType: string | null;
    programName: string | null;
    curriculum: string | null;
    dateCreated: string | null;
    campusName: string | null;
    sarId: number | null;
    updatedAt: string | null;
} | null>(null);

const isAlreadySubmitted = computed(() => {
    const status = String(statusData.value?.status ?? '').toLowerCase();
    return (
        status === 'submitted' ||
        status === 'enrolled' ||
        statusData.value?.submitted === true
    );
});

const formattedDateChecked = computed(() => {
    const raw = statusData.value?.dateCreated;
    if (!raw) return '-';
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date);
});

const formattedUpdatedAt = computed(() => {
    const raw = statusData.value?.updatedAt;
    if (!raw) return '-';
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date);
});

const statusLabel = computed(() => {
    if (statusLoading.value) return 'Checking';
    if (submitted.value || isAlreadySubmitted.value) return 'Submitted';
    return statusData.value?.status || 'Not submitted';
});

const statusTone = computed(() => {
    const status = String(statusLabel.value).toLowerCase();
    if (status === 'submitted' || status === 'enrolled') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
    if (status === 'checking') {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300';
    }
    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300';
});

const recordRows = computed(() => [
    [
        'Student No.',
        statusData.value?.studentNo || user.value?.student_number || '-',
    ],
    ['Student Name', statusData.value?.studentName || user.value?.name || '-'],
    ['Program', statusData.value?.programName || '-'],
    ['Curriculum', statusData.value?.curriculum || '-'],
    ['Campus', statusData.value?.campusName || '-'],
    ['SAR ID', statusData.value?.sarId || '-'],
    ['Transaction Type', statusData.value?.transactionType || '-'],
    ['Date Checked', formattedDateChecked.value],
]);

const getCookie = (name: string): string | null => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() ?? null;
    }
    return null;
};

const loadStatus = async () => {
    statusLoading.value = true;
    try {
        const response = await fetch('/student-academic-registration/status', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (response.ok && data?.data) {
            statusData.value = data.data;
        }
    } catch (error) {
        console.log('Enrollment status error:', error);
    } finally {
        statusLoading.value = false;
    }
};

const submitConfirmation = async () => {
    if (!selectedTransactionType.value) {
        toast.warning('Please select transaction type: Regular or Irregular.');
        return;
    }
    if (isSubmitting.value) return;
    if (!agreed.value) {
        toast.warning(
            'Please read and agree to the terms above before submitting.',
        );
        return;
    }

    isSubmitting.value = true;

    try {
        const xsrfToken = getCookie('XSRF-TOKEN');
        const response = await fetch('/student-academic-registration/confirm', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(xsrfToken
                    ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) }
                    : {}),
            },
            body: JSON.stringify({
                transactionType: selectedTransactionType.value,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            const normalizedMessage = (data?.message || '').toLowerCase();
            if (normalizedMessage === 'added') {
                toast.success(
                    'Enrollment confirmation submitted successfully.',
                );
                submitted.value = true;
                submittedAt.value = new Date().toLocaleString();
            } else if (normalizedMessage === 'exists') {
                toast.warning('Enrollment confirmation already exists.');
                submitted.value = true;
            } else {
                toast.success(
                    data?.message || 'Enrollment confirmation submitted.',
                );
                submitted.value = true;
                submittedAt.value = new Date().toLocaleString();
            }
            await loadStatus();
        } else {
            toast.error(data?.message || 'Unable to submit confirmation.');
        }
    } catch (error) {
        toast.error('Unable to submit confirmation.');
        console.log('Enrollment confirmation error:', error);
    } finally {
        isSubmitting.value = false;
    }
};

const openConfirmationModal = () => {
    if (!agreed.value) {
        toast.warning(
            'Please read and agree to the terms above before submitting.',
        );
        return;
    }
    if (isAlreadySubmitted.value || isSubmitting.value) return;
    selectedTransactionType.value = '';
    showTransactionTypeModal.value = true;
};

onMounted(loadStatus);

watch(
    isAlreadySubmitted,
    (value) => {
        if (value) {
            agreed.value = true;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Student Academic Registration" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-6">
        <section
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/10"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300"
                    >
                        <GraduationCap class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            Student Academic Registration
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Enrollment confirmation workspace for the active
                            academic term.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border px-2.5 text-xs font-semibold"
                        :class="statusTone"
                    >
                        <Loader2
                            v-if="statusLoading"
                            class="size-3.5 animate-spin"
                        />
                        <CheckCircle2 v-else class="size-3.5" />
                        {{ statusLabel }}
                    </span>
                    <span
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                    >
                        <CalendarDays class="size-3.5 text-slate-400" />
                        {{ statusData?.term ?? 'No active term' }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 disabled:opacity-60 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                        :disabled="statusLoading"
                        @click="loadStatus"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="{ 'animate-spin': statusLoading }"
                        />
                        Refresh
                    </button>
                </div>
            </div>

            <div
                class="grid divide-y divide-slate-200 text-xs sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4 dark:divide-white/10"
            >
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-medium text-slate-500">Student</span>
                    <span
                        class="truncate text-right font-semibold text-slate-900 dark:text-white"
                    >
                        {{
                            statusData?.studentNo || user?.student_number || '-'
                        }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-medium text-slate-500">Campus</span>
                    <span
                        class="truncate text-right font-semibold text-slate-900 dark:text-white"
                    >
                        {{ statusData?.campusName || '-' }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-medium text-slate-500">Type</span>
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.transactionType || 'Pending' }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-medium text-slate-500">Updated</span>
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{ formattedUpdatedAt }}
                    </span>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
            <div class="space-y-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <div class="flex items-center gap-2">
                            <UserRound class="size-4 text-slate-400" />
                            <h2
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                Current SAR Record
                            </h2>
                        </div>
                        <span
                            v-if="statusLoading"
                            class="inline-flex items-center gap-1 text-xs font-medium text-slate-500"
                        >
                            <Loader2 class="size-3.5 animate-spin" />
                            Loading
                        </span>
                    </div>

                    <dl
                        v-if="!statusLoading"
                        class="grid divide-y divide-slate-100 md:grid-cols-2 md:divide-x dark:divide-white/10 md:[&>*:nth-child(odd)]:border-l-0"
                    >
                        <div
                            v-for="([label, value], index) in recordRows"
                            :key="label"
                            class="grid gap-1 px-4 py-3"
                            :class="
                                index === 2 || index === 3
                                    ? 'md:col-span-2 md:border-l-0'
                                    : ''
                            "
                        >
                            <dt
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase"
                            >
                                {{ label }}
                            </dt>
                            <dd
                                class="min-h-5 text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ value }}
                            </dd>
                        </div>
                    </dl>
                    <div v-else class="grid gap-2 p-4 sm:grid-cols-2">
                        <div
                            v-for="item in 6"
                            :key="item"
                            class="h-14 animate-pulse rounded-md bg-slate-100 dark:bg-white/10"
                        ></div>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <ShieldCheck class="size-4 text-slate-400" />
                        <h2
                            class="text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            Compliance Reminders
                        </h2>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <div class="flex gap-3 px-4 py-3">
                            <BadgeCheck
                                class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300"
                            />
                            <p
                                class="text-sm text-slate-700 dark:text-slate-300"
                            >
                                Provide accurate and truthful information in all
                                academic profiles and submissions.
                            </p>
                        </div>
                        <div class="flex gap-3 px-4 py-3">
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                            />
                            <p
                                class="text-sm text-slate-700 dark:text-slate-300"
                            >
                                Incorrect data violates academic policy and can
                                delay enrollment processing.
                            </p>
                        </div>
                        <div class="flex gap-3 px-4 py-3">
                            <FileCheck2
                                class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-300"
                            />
                            <p
                                class="text-sm text-slate-700 dark:text-slate-300"
                            >
                                Screenshots or unauthorized sharing of sensitive
                                academic records are prohibited.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <aside
                class="rounded-lg border border-emerald-200 bg-white shadow-sm dark:border-emerald-500/20 dark:bg-slate-950"
            >
                <div
                    class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                >
                    <div class="flex items-center gap-2">
                        <ClipboardCheck
                            class="size-4 text-emerald-700 dark:text-emerald-300"
                        />
                        <h2
                            class="text-sm font-semibold text-emerald-950 dark:text-emerald-100"
                        >
                            Enrollment Confirmation
                        </h2>
                    </div>
                    <p
                        class="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80"
                    >
                        Confirm intent to enroll for the active term.
                    </p>
                </div>

                <div class="space-y-4 p-4">
                    <div class="space-y-2">
                        <div
                            class="flex items-start gap-2 rounded-md border border-emerald-100 bg-emerald-50/60 p-3 text-sm text-emerald-950 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100"
                        >
                            <BookOpenCheck class="mt-0.5 size-4 shrink-0" />
                            <span>
                                I confirm to enroll at the University of
                                Southern Mindanao for the next semester.
                            </span>
                        </div>
                        <div
                            class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm text-slate-700 dark:border-white/10 dark:text-slate-300"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span>
                                I have reviewed the course offerings, schedules,
                                and academic policies.
                            </span>
                        </div>
                        <div
                            class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm text-slate-700 dark:border-white/10 dark:text-slate-300"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                            />
                            <span>
                                I agree to comply with institutional guidelines,
                                deadlines, and financial obligations.
                            </span>
                        </div>
                    </div>

                    <label
                        class="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <input
                            v-model="agreed"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            :disabled="isAlreadySubmitted"
                        />
                        <span>I have read and agree to the terms above.</span>
                    </label>

                    <button
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="
                            isSubmitting || !agreed || isAlreadySubmitted
                        "
                        @click="openConfirmationModal"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="size-4 animate-spin"
                        />
                        {{
                            isAlreadySubmitted
                                ? 'Already Submitted'
                                : isSubmitting
                                  ? 'Submitting'
                                  : 'Submit Confirmation'
                        }}
                    </button>
                </div>
            </aside>
        </div>

        <div
            v-if="showTransactionTypeModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                >
                    <div>
                        <h3
                            class="text-base font-semibold text-slate-900 dark:text-white"
                        >
                            Select Transaction Type
                        </h3>
                        <p
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Choose the enrollment type to complete submission.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                        @click="showTransactionTypeModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-md border p-3 text-left transition"
                        :class="
                            selectedTransactionType === 'Regular'
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:border-emerald-400 dark:bg-emerald-500/20 dark:text-emerald-100'
                                : 'border-slate-300 bg-white text-slate-900 hover:border-emerald-400 dark:border-white/20 dark:bg-transparent dark:text-white'
                        "
                        @click="selectedTransactionType = 'Regular'"
                    >
                        <p class="text-sm font-bold">Regular</p>
                        <p class="mt-1 text-xs opacity-90">
                            Full load based on advised curriculum
                        </p>
                    </button>
                    <button
                        type="button"
                        class="rounded-md border p-3 text-left transition"
                        :class="
                            selectedTransactionType === 'Irregular'
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:border-emerald-400 dark:bg-emerald-500/20 dark:text-emerald-100'
                                : 'border-slate-300 bg-white text-slate-900 hover:border-emerald-400 dark:border-white/20 dark:bg-transparent dark:text-white'
                        "
                        @click="selectedTransactionType = 'Irregular'"
                    >
                        <p class="text-sm font-bold">Irregular</p>
                        <p class="mt-1 text-xs opacity-90">
                            Customized or partial load
                        </p>
                    </button>

                    <p
                        v-if="!selectedTransactionType"
                        class="text-xs font-semibold text-red-600 sm:col-span-2"
                    >
                        Please choose Regular or Irregular to continue.
                    </p>
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3 dark:border-white/10"
                >
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 dark:border-white/20 dark:text-white"
                        @click="showTransactionTypeModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-md bg-emerald-600 px-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-70"
                        :disabled="!selectedTransactionType || isSubmitting"
                        @click="
                            showTransactionTypeModal = false;
                            submitConfirmation();
                        "
                    >
                        Continue Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
