<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    GraduationCap,
    LoaderCircle,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const isSubmitting = ref(false);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const policyItems = [
    {
        title: 'Accurate information',
        description:
            'All profile, enrollment, and academic information must be accurate, complete, and truthful.',
        icon: FileText,
    },
    {
        title: 'Academic responsibility',
        description:
            'Incorrect submissions may cause academic, administrative, or policy-related issues.',
        icon: ShieldCheck,
    },
    {
        title: 'Data privacy',
        description:
            'Screenshots or unauthorized sharing of student information from this system are prohibited.',
        icon: LockKeyhole,
    },
];

const confirmationItems = [
    'I confirm my intent to enroll at the University of Southern Mindanao for the next semester.',
    'I have reviewed the course offerings, schedules, and academic policies available to me.',
    'I agree to comply with institutional guidelines, deadlines, and financial obligations related to enrollment.',
];

const getCookie = (name: string): string | null => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() ?? null;
    }

    return null;
};

const submitConfirmation = async () => {
    if (isSubmitting.value) return;

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
            body: JSON.stringify({ transactionType: 'Enrollment' }),
        });

        const data = await response.json();

        if (response.ok) {
            const normalizedMessage = (data?.message || '').toLowerCase();
            if (normalizedMessage === 'added') {
                toast.success(
                    'Enrollment confirmation submitted successfully.',
                );
            } else if (normalizedMessage === 'exists') {
                toast.warning('Enrollment confirmation already exists.');
            } else {
                toast.success(
                    data?.message || 'Enrollment confirmation submitted.',
                );
            }
        } else {
            toast.error(data?.message || 'Unable to submit confirmation.');
            console.log('Enrollment confirmation error:', data);
        }
    } catch (error) {
        toast.error('Unable to submit confirmation.');
        console.log('Enrollment confirmation error:', error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Student Academic Registration" />

    <div class="flex h-full flex-1 flex-col bg-slate-50/60 dark:bg-slate-950">
        <div class="border-b border-slate-200 bg-white px-4 py-5 dark:border-white/10 dark:bg-slate-950 lg:px-8">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <GraduationCap class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">
                            Enrollment
                        </p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">
                            Student Academic Registration
                        </h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                            Review the enrollment declaration and submit your confirmation for the active registration period.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[420px]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Student
                        </p>
                        <p class="mt-1 truncate text-sm font-bold text-slate-950 dark:text-white">
                            {{ user?.name || 'Authenticated Student' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Status
                        </p>
                        <p class="mt-1 flex items-center gap-2 text-sm font-bold text-emerald-800 dark:text-emerald-200">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            Ready for confirmation
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,1fr)_360px] lg:p-8">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                <AlertTriangle class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">
                                    Registration Declaration
                                </h2>
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    Review these statements before submitting your enrollment confirmation.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex w-fit items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
                            Required
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-white/10">
                    <div class="px-5 py-5">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Student Responsibilities
                        </h3>

                        <div class="mt-4 grid gap-4 xl:grid-cols-3">
                            <div
                                v-for="item in policyItems"
                                :key="item.title"
                                class="flex gap-3"
                            >
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                    <component :is="item.icon" class="size-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-950 dark:text-white">
                                        {{ item.title }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                                <ClipboardCheck class="size-4" />
                            </div>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Confirmation Statements
                            </h3>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(item, index) in confirmationItems"
                                :key="item"
                                class="flex gap-3 rounded-lg bg-slate-50 px-4 py-3 dark:bg-white/5"
                            >
                                <div class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                    <CheckCircle2 class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                        Declaration {{ index + 1 }}
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-300">
                                        {{ item }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">
                                Submit Confirmation
                            </h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Finalize your enrollment intent.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm leading-6 text-slate-700 dark:text-slate-300">
                            By submitting, you confirm that you understand the enrollment declaration and agree to proceed with the academic registration process.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-emerald-500 dark:hover:bg-emerald-400 dark:hover:text-emerald-950"
                        :disabled="isSubmitting"
                        @click="submitConfirmation"
                    >
                        <LoaderCircle
                            v-if="isSubmitting"
                            class="size-4 animate-spin"
                        />
                        <CheckCircle2 v-else class="size-4" />
                        {{ isSubmitting ? 'Submitting confirmation' : 'Submit Confirmation' }}
                    </button>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Processing Notes
                    </h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-slate-400"></span>
                            Confirmation is saved once per enrollment period.
                        </div>
                        <div class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-slate-400"></span>
                            If a record already exists, the system will notify you.
                        </div>
                        <div class="flex gap-3 text-sm text-slate-600 dark:text-slate-400">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-slate-400"></span>
                            Contact the Registrar for enrollment record corrections.
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
