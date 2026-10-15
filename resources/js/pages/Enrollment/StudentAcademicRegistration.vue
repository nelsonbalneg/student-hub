<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    Eye,
    GraduationCap,
    Loader2,
    RefreshCw,
    Send,
    CheckCircle2,
    Clock3,
    BookOpen,
    Layers,
    CircleHelp,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import {
    records as sarStatus,
    submitConfirmation as submitSar,
} from '@/actions/App/Http/Controllers/EnrollmentController';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

interface SarRecord {
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
    checkedAt?: string | null;
    can_apply: boolean;
    registration_open: boolean;
    verification_error: string | null;
}
const page = usePage();
const user = computed(() => page.props.auth?.user);
const statusData = ref<SarRecord | null>(null);
const statusLoading = ref(true);
const statusError = ref('');
const records = ref<SarRecord[]>([]);
const isSubmitting = ref(false);
const detailsOpen = ref(false);
const applicationOpen = ref(false);
const agreed = ref(false);
const selectedTransactionType = ref<'Regular' | 'Irregular' | ''>('');
const isRecordSubmitted = (record: SarRecord) =>
    record.submitted ||
    ['submitted', 'enrolled'].includes(
        String(record.status ?? '').toLowerCase(),
    );
const isRecordEnrolled = (record: SarRecord) =>
    String(record.status ?? '')
        .trim()
        .toLowerCase() === 'enrolled';
const registrationStatusLabel = (record: SarRecord) =>
    isRecordEnrolled(record)
        ? 'Enrolled'
        : isRecordSubmitted(record)
          ? 'Already Submitted'
          : 'Not Submitted';
const isAlreadySubmitted = computed(
    () => !!statusData.value && isRecordSubmitted(statusData.value),
);
const openDetails = (row: SarRecord) => {
    statusData.value = row;
    detailsOpen.value = true;
};
const formatDate = (raw: string | null | undefined) => {
    if (!raw) return '-';
    const date = new Date(raw);
    return Number.isNaN(date.getTime())
        ? raw
        : new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(date);
};
const recordRows = computed<Array<[string, string | number]>>(() => [
    [
        'Student ID Number',
        String(
            statusData.value?.studentNo || user.value?.student_number || '-',
        ),
    ],
    ['Student Name', statusData.value?.studentName || user.value?.name || '-'],
    ['Campus', statusData.value?.campusName || '-'],
    ['Academic Term', statusData.value?.term || '-'],
    ['Term ID', statusData.value?.termId ?? '-'],
    ['Type', statusData.value?.transactionType || '-'],
    ['Program', statusData.value?.programName || '-'],
    ['Curriculum', statusData.value?.curriculum || '-'],
    ['SAR ID', statusData.value?.sarId ?? '-'],
    [
        'Status',
        statusData.value?.status ||
            (isAlreadySubmitted.value ? 'Submitted' : 'Not submitted'),
    ],
    ['Date Created', formatDate(statusData.value?.dateCreated)],
    ['Date Updated', formatDate(statusData.value?.updatedAt)],
]);
const loadStatus = async () => {
    if (isSubmitting.value) return;
    statusLoading.value = true;
    statusError.value = '';
    try {
        const response = await fetch(sarStatus.url(), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const result = await response.json();
        if (!response.ok || !Array.isArray(result?.data))
            throw new Error(
                result?.message ||
                    'Unable to load registration status. Please try again.',
            );
        records.value = result.data;
        if (statusData.value) {
            const updated = records.value.find(
                (row) =>
                    String(row.termId) === String(statusData.value?.termId),
            );
            statusData.value = updated ?? null;
            if (!updated) {
                detailsOpen.value = false;
                applicationOpen.value = false;
            }
        }
    } catch (error) {
        statusError.value =
            error instanceof Error
                ? error.message
                : 'Unable to load registration status. Please try again.';
    } finally {
        statusLoading.value = false;
    }
};
const openApplication = (row: SarRecord) => {
    if (
        statusLoading.value ||
        statusError.value ||
        !row.can_apply ||
        isRecordSubmitted(row)
    )
        return;
    statusData.value = row;
    agreed.value = false;
    selectedTransactionType.value = '';
    applicationOpen.value = true;
};
const closeApplication = (open = false) => {
    if (open || isSubmitting.value) return;
    applicationOpen.value = false;
};
const getCookie = (name: string) => {
    const parts = `; ${document.cookie}`.split(`; ${name}=`);
    return parts.length === 2
        ? (parts.pop()?.split(';').shift() ?? null)
        : null;
};
const submitConfirmation = async () => {
    if (
        isSubmitting.value ||
        isAlreadySubmitted.value ||
        !statusData.value?.can_apply ||
        !agreed.value ||
        !selectedTransactionType.value
    )
        return;
    isSubmitting.value = true;
    let succeeded = false;
    try {
        const token = getCookie('XSRF-TOKEN');
        const response = await fetch(submitSar.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
            },
            body: JSON.stringify({
                transactionType: selectedTransactionType.value,
                term_id: String(statusData.value?.termId),
            }),
        });
        const result = await response.json();
        if (!response.ok)
            throw new Error(
                result?.message || 'Unable to submit confirmation.',
            );
        if (statusData.value) {
            statusData.value.submitted = true;
            statusData.value.status = 'Submitted';
            statusData.value.transactionType = selectedTransactionType.value;
        }
        applicationOpen.value = false;
        succeeded = true;
        toast.success(
            String(result?.message ?? '').toLowerCase() === 'exists'
                ? 'Enrollment confirmation already exists.'
                : 'Enrollment confirmation submitted successfully.',
        );
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Unable to submit confirmation.',
        );
    } finally {
        isSubmitting.value = false;
    }
    if (succeeded) await loadStatus();
};
onMounted(loadStatus);
</script>

<template>
    <Head title="SAR" />
    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-4 p-3 sm:gap-5 sm:p-4 lg:p-6"
    >
        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-4 dark:border-white/10"
            >
                <div
                    class="flex min-w-0 flex-1 items-start gap-3 sm:items-center"
                >
                    <div
                        class="rounded-lg bg-sky-50 p-3 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                    >
                        <GraduationCap class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-base font-semibold sm:text-lg">
                            Student Academic Registration
                        </h1>
                        <p
                            class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                        >
                            View your registration or apply for the current
                            academic term.
                        </p>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full sm:w-auto"
                    :disabled="statusLoading || isSubmitting"
                    @click="loadStatus"
                    ><RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': statusLoading }"
                    />Refresh</Button
                >
            </header>
            <div
                v-if="statusError"
                role="alert"
                class="m-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
            >
                {{ statusError }} Use Refresh to try again.
            </div>
            <div
                class="space-y-4 bg-slate-50/60 p-3 sm:p-4 xl:hidden dark:bg-white/[0.02]"
            >
                <div
                    v-if="statusLoading"
                    role="status"
                    class="flex items-center justify-center gap-2 py-8 text-sm text-slate-500"
                >
                    <Loader2 class="size-4 animate-spin" />Loading
                    registration...
                </div>
                <div
                    v-else-if="!records.length"
                    class="rounded-lg border border-dashed border-slate-200 p-5 text-center text-sm leading-relaxed text-slate-500 dark:border-white/15"
                >
                    No registration terms are configured for your campus.
                    Registration is closed.
                </div>
                <article
                    v-for="row in statusLoading ? [] : records"
                    :key="String(row.termId)"
                    :aria-label="`Registration for ${row.term}`"
                    class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <header
                        class="space-y-1 border-b border-slate-100 p-4 dark:border-white/10"
                    >
                        <h2
                            class="text-base leading-relaxed font-semibold text-slate-950 dark:text-white"
                        >
                            {{ row.term }}
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            {{ row.campusName || '-' }}
                        </p>
                    </header>
                    <div class="space-y-4 p-4">
                        <dl class="grid min-w-0 grid-cols-2 gap-4 text-sm">
                            <div class="min-w-0">
                                <dt class="text-slate-500 dark:text-slate-400">
                                    ID Number
                                </dt>
                                <dd class="mt-1 font-medium break-words">
                                    {{ row.studentNo || '-' }}
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-500 dark:text-slate-400">
                                    Date Updated
                                </dt>
                                <dd class="mt-1 font-medium break-words">
                                    {{ formatDate(row.updatedAt) }}
                                </dd>
                            </div>
                        </dl>
                        <dl class="flex flex-wrap gap-x-6 gap-y-3">
                            <div class="space-y-1.5">
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Type
                                </dt>
                                <dd>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            row.transactionType?.toLowerCase() ===
                                            'regular'
                                                ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300'
                                                : row.transactionType?.toLowerCase() ===
                                                    'irregular'
                                                  ? 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300'
                                                  : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/15 dark:bg-white/5 dark:text-slate-300'
                                        "
                                    >
                                        <BookOpen
                                            v-if="
                                                row.transactionType?.toLowerCase() ===
                                                'regular'
                                            "
                                            aria-hidden="true"
                                        />
                                        <Layers
                                            v-else-if="
                                                row.transactionType?.toLowerCase() ===
                                                'irregular'
                                            "
                                            aria-hidden="true"
                                        />
                                        <CircleHelp v-else aria-hidden="true" />
                                        {{
                                            row.transactionType ||
                                            'Not selected'
                                        }}
                                    </Badge>
                                </dd>
                            </div>
                            <div class="space-y-1.5">
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Status
                                </dt>
                                <dd>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            isRecordSubmitted(row)
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
                                        "
                                    >
                                        <GraduationCap
                                            v-if="isRecordEnrolled(row)"
                                            aria-hidden="true"
                                        />
                                        <CheckCircle2
                                            v-else-if="isRecordSubmitted(row)"
                                            aria-hidden="true"
                                        />
                                        <Clock3 v-else aria-hidden="true" />
                                        {{ registrationStatusLabel(row) }}
                                    </Badge>
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="row.verification_error"
                            class="text-sm leading-relaxed text-red-600 dark:text-red-400"
                        >
                            {{ row.verification_error }}
                        </p>
                        <Button
                            v-if="isRecordSubmitted(row)"
                            type="button"
                            variant="outline"
                            class="min-h-11 w-full"
                            @click="openDetails(row)"
                            ><Eye class="size-4" />View Details</Button
                        >
                        <Button
                            v-else-if="row.registration_open"
                            type="button"
                            class="min-h-11 w-full"
                            :disabled="
                                !row.can_apply || !!statusError || isSubmitting
                            "
                            @click="openApplication(row)"
                            ><Send class="size-4" />Apply</Button
                        >
                        <p
                            v-else
                            class="rounded-lg bg-slate-100 px-3 py-3 text-center text-sm font-medium text-slate-600 dark:bg-white/5 dark:text-slate-400"
                        >
                            Registration Closed
                        </p>
                    </div>
                </article>
            </div>
            <div class="hidden overflow-x-auto xl:block">
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <caption class="sr-only">
                        Campus registration terms
                    </caption>
                    <thead
                        class="bg-slate-50 text-slate-600 dark:bg-white/5 dark:text-slate-300"
                    >
                        <tr>
                            <th
                                v-for="column in [
                                    'ID Number',
                                    'Campus',
                                    'Term',
                                    'Type',
                                    'Date Updated',
                                    'Status',
                                    'Action',
                                ]"
                                :key="column"
                                scope="col"
                                class="px-4 py-3 font-semibold"
                                :class="column === 'Action' ? 'text-right' : ''"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="statusLoading">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-slate-500"
                            >
                                <span
                                    class="inline-flex items-center gap-2"
                                    role="status"
                                    ><Loader2
                                        class="size-4 animate-spin"
                                    />Loading registration...</span
                                >
                            </td>
                        </tr>
                        <tr
                            v-for="row in statusLoading ? [] : records"
                            :key="String(row.termId)"
                            class="border-t border-slate-100 dark:border-white/10"
                        >
                            <td class="px-4 py-4 font-medium">
                                {{ row.studentNo || '-' }}
                            </td>
                            <td class="px-4 py-4">
                                {{ row.campusName || '-' }}
                            </td>
                            <td class="px-4 py-4">
                                {{ row.term }}
                            </td>
                            <td class="px-4 py-4">
                                <Badge
                                    variant="outline"
                                    :class="
                                        row.transactionType?.toLowerCase() ===
                                        'regular'
                                            ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300'
                                            : row.transactionType?.toLowerCase() ===
                                                'irregular'
                                              ? 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300'
                                              : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/15 dark:bg-white/5 dark:text-slate-300'
                                    "
                                >
                                    <BookOpen
                                        v-if="
                                            row.transactionType?.toLowerCase() ===
                                            'regular'
                                        "
                                        aria-hidden="true"
                                    />
                                    <Layers
                                        v-else-if="
                                            row.transactionType?.toLowerCase() ===
                                            'irregular'
                                        "
                                        aria-hidden="true"
                                    />
                                    <CircleHelp v-else aria-hidden="true" />
                                    {{ row.transactionType || 'Not selected' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-4">
                                {{ formatDate(row.updatedAt) }}
                            </td>
                            <td class="px-4 py-4">
                                <Badge
                                    variant="outline"
                                    :class="
                                        isRecordSubmitted(row)
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
                                    "
                                >
                                    <GraduationCap
                                        v-if="isRecordEnrolled(row)"
                                        aria-hidden="true"
                                    />
                                    <CheckCircle2
                                        v-else-if="isRecordSubmitted(row)"
                                        aria-hidden="true"
                                    />
                                    <Clock3 v-else aria-hidden="true" />
                                    {{ registrationStatusLabel(row) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <span
                                        v-if="row.verification_error"
                                        class="max-w-52 text-xs text-red-600 dark:text-red-400"
                                        >{{ row.verification_error }}</span
                                    >
                                    <Button
                                        v-if="isRecordSubmitted(row)"
                                        type="button"
                                        variant="outline"
                                        @click="openDetails(row)"
                                        ><Eye class="size-4" />View</Button
                                    >
                                    <Button
                                        v-else-if="row.registration_open"
                                        type="button"
                                        :disabled="
                                            !row.can_apply ||
                                            !!statusError ||
                                            isSubmitting
                                        "
                                        @click="openApplication(row)"
                                        ><Send class="size-4" />Apply</Button
                                    >
                                    <span v-else class="text-sm text-slate-500"
                                        >Closed</span
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!statusLoading && !records.length">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-slate-500"
                            >
                                No registration terms are configured for your
                                campus. Registration is closed.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Dialog v-model:open="detailsOpen">
            <DialogContent
                class="max-h-[90dvh] overflow-y-auto sm:max-w-[640px]"
            >
                <DialogTitle>SAR Registration Details</DialogTitle>
                <DialogDescription
                    >Your submitted registration for
                    {{
                        statusData?.term || 'the current term'
                    }}.</DialogDescription
                >
                <dl
                    class="grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 dark:border-white/10"
                >
                    <div v-for="[label, value] in recordRows" :key="label">
                        <dt class="text-sm text-slate-500 dark:text-slate-400">
                            {{ label }}
                        </dt>
                        <dd class="mt-1 text-sm font-medium break-words">
                            {{ value }}
                        </dd>
                    </div>
                </dl>
                <div class="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        @click="detailsOpen = false"
                        >Close</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
        <Dialog :open="applicationOpen" @update:open="closeApplication">
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-[580px]"
            >
                <header class="border-b p-5">
                    <DialogTitle>Apply for Academic Registration</DialogTitle
                    ><DialogDescription class="mt-2"
                        >{{ statusData?.campusName }} / {{ statusData?.term }}.
                        Confirm your enrollment and select a transaction
                        type.</DialogDescription
                    >
                </header>
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <section
                        class="space-y-3 rounded-lg border border-slate-200 p-4 text-sm leading-relaxed dark:border-white/10"
                    >
                        <h2 class="font-semibold">Enrollment Confirmation</h2>
                        <p>
                            I confirm my intent to enroll at the University of
                            Southern Mindanao for this academic term.
                        </p>
                        <p>
                            I have reviewed the course offerings, schedules, and
                            academic policies.
                        </p>
                        <p>
                            I agree to comply with institutional guidelines,
                            deadlines, and financial obligations.
                        </p>
                    </section>
                    <fieldset :disabled="isSubmitting" class="space-y-3">
                        <legend class="text-sm font-semibold">
                            Transaction Type
                        </legend>
                        <label
                            v-for="type in ['Regular', 'Irregular'] as const"
                            :key="type"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 focus-within:ring-2 focus-within:ring-emerald-500"
                            :class="
                                selectedTransactionType === type
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10'
                                    : 'border-slate-200 dark:border-white/10'
                            "
                            ><input
                                v-model="selectedTransactionType"
                                type="radio"
                                name="sar-transaction-type"
                                :value="type"
                                class="mt-1 accent-emerald-600"
                            /><span
                                ><span class="block text-sm font-medium">{{
                                    type
                                }}</span
                                ><span
                                    class="mt-1 block text-sm text-slate-600 dark:text-slate-300"
                                    >{{
                                        type === 'Regular'
                                            ? 'Full load based on advised curriculum'
                                            : 'Customized or partial load'
                                    }}</span
                                ></span
                            ></label
                        >
                    </fieldset>
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-white/10"
                        ><input
                            v-model="agreed"
                            type="checkbox"
                            :disabled="isSubmitting"
                            class="mt-1 size-4 accent-emerald-600"
                        /><span
                            >I have read and agree to the terms above.</span
                        ></label
                    >
                    <p
                        class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        Provide accurate information. Incorrect data may delay
                        enrollment. Do not share sensitive academic records
                        without authorization.
                    </p>
                </div>
                <footer
                    class="flex justify-end gap-3 border-t p-4 [&>button]:min-h-11 [&>button]:flex-1 sm:[&>button]:flex-none"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="closeApplication()"
                        >Cancel</Button
                    ><Button
                        type="button"
                        :disabled="
                            isSubmitting ||
                            !agreed ||
                            !selectedTransactionType ||
                            isAlreadySubmitted
                        "
                        @click="submitConfirmation"
                        ><Loader2
                            v-if="isSubmitting"
                            class="size-4 animate-spin"
                        />{{
                            isSubmitting
                                ? 'Submitting...'
                                : 'Submit Confirmation'
                        }}</Button
                    >
                </footer>
            </DialogContent>
        </Dialog>
    </div>
</template>
