<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
    return status === 'submitted' || status === 'enrolled' || statusData.value?.submitted === true;
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
        toast.warning('Please read and agree to the terms above before submitting.');
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
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
            },
            body: JSON.stringify({ transactionType: selectedTransactionType.value }),
        });

        const data = await response.json();

        if (response.ok) {
            const normalizedMessage = (data?.message || '').toLowerCase();
            if (normalizedMessage === 'added') {
                toast.success('Enrollment confirmation submitted successfully.');
                submitted.value = true;
                submittedAt.value = new Date().toLocaleString();
            } else if (normalizedMessage === 'exists') {
                toast.warning('Enrollment confirmation already exists.');
                submitted.value = true;
            } else {
                toast.success(data?.message || 'Enrollment confirmation submitted.');
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
        toast.warning('Please read and agree to the terms above before submitting.');
        return;
    }
    if (isAlreadySubmitted.value || isSubmitting.value) return;
    selectedTransactionType.value = '';
    showTransactionTypeModal.value = true;
};

onMounted(loadStatus);

watch(isAlreadySubmitted, (value) => {
    if (value) {
        agreed.value = true;
    }
}, { immediate: true });
</script>

<template>
    <Head title="Student Academic Registration" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                        Student Academic Registration
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Confirm your enrollment for the upcoming semester.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-slate-200">
                        {{ submitted || isAlreadySubmitted ? 'SUBMITTED' : 'NOT SUBMITTED' }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-slate-200">
                        Semester: {{ statusData?.term ?? 'N/A' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white">IMPORTANT REMINDERS</h2>
            <ul class="mt-4 list-disc space-y-3 pl-5 text-base text-slate-700 dark:text-slate-300">
                <li>
                    This is an important reminder about
                    <b class="text-red-600 dark:text-red-400">providing accurate</b> and
                    <b class="text-red-600 dark:text-red-400"> truthful information</b>
                    in all profiles and submissions within our academic institution systems.
                </li>
                <li>
                    Providing incorrect data <b class="text-red-600 dark:text-red-400">violates</b> our academic policies and can lead to significant issues.
                </li>
                <li>
                    <b class="text-red-600 dark:text-red-400">Please be aware that taking screenshots of the data in this system is prohibited.</b>
                    Sensitive information must remain secure.
                </li>
            </ul>
        </section>

        <section class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <h2 class="text-2xl font-black tracking-tight text-emerald-900 dark:text-emerald-200">
                Enrollment Confirmation
            </h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-lg text-emerald-900 dark:text-emerald-100">
                <li>I confirm to enroll at the University of Southern Mindanao for the next semester</li>
                <li>I have reviewed and understood the course offerings, schedules, and academic policies.</li>
                <li>I agree to comply with all the institution's guidelines, deadlines, and financial obligations associated with my enrollment.</li>
            </ul>

            <button
                type="button"
                class="mt-6 inline-flex h-10 items-center justify-center rounded-md bg-emerald-600 px-5 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-70"
                :disabled="isSubmitting || !agreed || isAlreadySubmitted"
                @click="openConfirmationModal"
            >
                {{ isAlreadySubmitted ? 'ALREADY SUBMITTED' : (isSubmitting ? 'SUBMITTING...' : 'SUBMIT CONFIRMATION') }}
            </button>

            <label class="mt-4 flex items-center gap-2 text-sm text-emerald-900 dark:text-emerald-100">
                <input
                    v-model="agreed"
                    type="checkbox"
                    class="h-4 w-4 rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500"
                    :disabled="isAlreadySubmitted"
                />
                <span>I have read and agree to the terms above.</span>
            </label>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <h2 class="text-xl font-black tracking-tight text-slate-900 dark:text-white">CURRENT SAR RECORD</h2>
            <div v-if="statusLoading" class="mt-4 text-sm text-slate-500 dark:text-slate-400">Loading...</div>
            <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.status || '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Semester</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.term || '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Program</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.programName || '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Transaction Type</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.transactionType || '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Curriculum</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ statusData?.curriculum || '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5 md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Date Checked</p>
                    <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">
                        {{ formattedDateChecked }}
                    </p>
                </div>
            </div>
        </section>

        <div
            v-if="showTransactionTypeModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-slate-950">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Select Transaction Type</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Choose your transaction type before final submit.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-lg border p-3 text-left transition"
                        :class="selectedTransactionType === 'Regular'
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:border-emerald-400 dark:bg-emerald-500/20 dark:text-emerald-100'
                            : 'border-slate-300 bg-white text-slate-900 hover:border-emerald-400 dark:border-white/20 dark:bg-transparent dark:text-white'"
                        @click="selectedTransactionType = 'Regular'"
                    >
                        <p class="text-sm font-bold">Regular</p>
                        <p class="mt-1 text-xs opacity-90">Full load based on advised curriculum</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border p-3 text-left transition"
                        :class="selectedTransactionType === 'Irregular'
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:border-emerald-400 dark:bg-emerald-500/20 dark:text-emerald-100'
                            : 'border-slate-300 bg-white text-slate-900 hover:border-emerald-400 dark:border-white/20 dark:bg-transparent dark:text-white'"
                        @click="selectedTransactionType = 'Irregular'"
                    >
                        <p class="text-sm font-bold">Irregular</p>
                        <p class="mt-1 text-xs opacity-90">Customized or partial load</p>
                    </button>
                </div>

                <p v-if="!selectedTransactionType" class="mt-2 text-xs font-semibold text-red-600">
                    Please choose Regular or Irregular to continue.
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 dark:border-white/20 dark:text-white"
                        @click="showTransactionTypeModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-md bg-emerald-600 px-3 text-sm font-bold text-white disabled:opacity-70"
                        :disabled="!selectedTransactionType || isSubmitting"
                        @click="showTransactionTypeModal = false; submitConfirmation()"
                    >
                        Continue Submit
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
