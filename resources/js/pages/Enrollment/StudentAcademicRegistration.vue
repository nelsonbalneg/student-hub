<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { ref } from 'vue';

const isSubmitting = ref(false);

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
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
            },
            body: JSON.stringify({ transactionType: 'Enrollment' }),
        });

        const data = await response.json();

        if (response.ok) {
            const normalizedMessage = (data?.message || '').toLowerCase();
            if (normalizedMessage === 'added') {
                toast.success('Enrollment confirmation submitted successfully.');
            } else if (normalizedMessage === 'exists') {
                toast.warning('Enrollment confirmation already exists.');
            } else {
                toast.success(data?.message || 'Enrollment confirmation submitted.');
            }
            console.log('Enrollment confirmation response:', data);
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

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section
            class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">
                Student Academic Registration
            </h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                This page is ready for the Student Academic Registration module.
            </p>

            <ul class="mt-4 list-disc space-y-3 pl-5 text-sm text-slate-700 dark:text-slate-300">
                <li>
                    This is an important reminder about <b class="text-red-600 dark:text-red-400">providing accurate</b> and
                    <b class="text-red-600 dark:text-red-400"> truthful information</b> in all profiles and submissions within our academic institution
                    systems. Ensuring the integrity of your data is essential for maintaining
                    the quality and credibility of our educational environment.
                </li>
                <li>
                    Providing incorrect data <b class="text-red-600 dark:text-red-400">violates</b> our
                    academic policies and can lead to
                    significant issues, including compromised academic integrity and
                    administrative complications.
                </li>
                <li>
                    <b class="text-red-600 dark:text-red-400">Please be aware that taking screenshots of the
                        data in this system is prohibited.</b> Sensitive information must remain secure.
                </li>
            </ul>

            <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <h2 class="text-sm font-bold text-emerald-800 dark:text-emerald-200">
                    Enrollment Confirmation
                </h2>

                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-emerald-900 dark:text-emerald-100">
                    <li>
                        I confirm to enroll at the University of Southern Mindanao for the next semester
                    </li>
                    <li>
                        I have reviewed and understood the course offerings, schedules, and academic
                        policies.
                    </li>
                    <li>
                        I agree to comply with all the institution's guidelines, deadlines, and financial
                        obligations associated with my enrollment.
                    </li>
                </ul>

                <button
                    type="button"
                    class="mt-4 inline-flex h-9 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700"
                    :disabled="isSubmitting"
                    @click="submitConfirmation"
                >
                    {{ isSubmitting ? 'SUBMITTING...' : 'SUBMIT CONFIRMATION' }}
                </button>
            </div>
        </section>
    </div>
</template>
