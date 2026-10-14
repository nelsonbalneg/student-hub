<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarPlus,
    CheckCircle2,
    Pencil,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroySchedules,
    store as storeSchedules,
} from '@/routes/site-settings/physical-fitness/configuration/test-schedules';

type ScheduleStatus = 'not_scheduled' | 'upcoming' | 'open' | 'closed';
type Phase = 'pre' | 'post';
type PhaseWindow = {
    opens_on: string | null;
    closes_on: string | null;
    status: ScheduleStatus;
};

export type ScheduleRow = {
    site_academic_term_id: number;
    site_campus_id: number;
    campus_name: string | null;
    school_year: string;
    semester: string;
    term_status: string | null;
    pre: PhaseWindow;
    post: PhaseWindow;
    is_scheduled: boolean;
    updated_at: string | null;
};

const props = defineProps<{
    rows: ScheduleRow[];
    today: string;
    canManage: boolean;
}>();

const phases: Array<{ key: Phase; label: string }> = [
    { key: 'pre', label: 'Pre-test' },
    { key: 'post', label: 'Post-test' },
];

const statusMeta: Record<ScheduleStatus, { label: string; classes: string }> = {
    open: {
        label: 'Open',
        classes:
            'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    },
    upcoming: {
        label: 'Upcoming',
        classes:
            'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30',
    },
    closed: {
        label: 'Closed',
        classes:
            'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-white/5 dark:text-slate-300 dark:ring-white/15',
    },
    not_scheduled: {
        label: 'Not Scheduled',
        classes:
            'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    },
};

const selectClass =
    'h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:focus:ring-blue-500/20';

// ---------- Formatting helpers (dates are calendar dates; format in UTC to avoid day shifts) ----------
const dateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
});
const formatDate = (value: string | null) =>
    value ? dateFormatter.format(new Date(`${value}T00:00:00Z`)) : '';
const dayCount = (opens: string | null, closes: string | null) => {
    if (!opens || !closes) {
        return 0;
    }

    return (
        Math.round(
            (Date.parse(`${closes}T00:00:00Z`) -
                Date.parse(`${opens}T00:00:00Z`)) /
                86_400_000,
        ) + 1
    );
};
const termLabel = (row: Pick<ScheduleRow, 'school_year' | 'semester'>) =>
    `${row.school_year}, ${row.semester}`;
const periodKey = (row: Pick<ScheduleRow, 'school_year' | 'semester'>) =>
    `${row.school_year}|${row.semester}`;
const statusFor = (opens: string, closes: string): ScheduleStatus => {
    if (!opens || !closes) {
        return 'not_scheduled';
    }

    if (props.today < opens) {
        return 'upcoming';
    }

    return props.today > closes ? 'closed' : 'open';
};

// ---------- Filters ----------
const filterCampusId = ref('');
const filterPeriod = ref('');

const campuses = computed(() =>
    [
        ...new Map(
            props.rows.map((row) => [
                String(row.site_campus_id),
                {
                    id: String(row.site_campus_id),
                    name: row.campus_name ?? `Campus #${row.site_campus_id}`,
                },
            ]),
        ).values(),
    ].sort((a, b) => a.name.localeCompare(b.name)),
);

const periods = computed(() => [
    ...new Map(
        props.rows
            .filter(
                (row) =>
                    !filterCampusId.value ||
                    String(row.site_campus_id) === filterCampusId.value,
            )
            .map((row) => [
                periodKey(row),
                { key: periodKey(row), label: termLabel(row) },
            ]),
    ).values(),
]);

watch(filterCampusId, () => {
    if (
        filterPeriod.value &&
        !periods.value.some((period) => period.key === filterPeriod.value)
    ) {
        filterPeriod.value = '';
    }
});

// Scheduled rows are always listed; selecting a specific term also lists campuses not yet scheduled for it.
const visibleRows = computed(() =>
    props.rows
        .filter(
            (row) =>
                !filterCampusId.value ||
                String(row.site_campus_id) === filterCampusId.value,
        )
        .filter(
            (row) =>
                !filterPeriod.value || periodKey(row) === filterPeriod.value,
        )
        .filter((row) => row.is_scheduled || filterPeriod.value !== '')
        .sort(
            (a, b) =>
                b.school_year.localeCompare(a.school_year) ||
                a.semester.localeCompare(b.semester) ||
                (a.campus_name ?? '').localeCompare(b.campus_name ?? ''),
        ),
);

const hasFilters = computed(
    () => filterCampusId.value !== '' || filterPeriod.value !== '',
);
const clearFilters = () => {
    filterCampusId.value = '';
    filterPeriod.value = '';
};

// ---------- Success feedback ----------
const successMessage = ref('');
const highlightedTermId = ref<number | null>(null);
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;

const showSuccess = (message: string, termId: number) => {
    successMessage.value = message;
    highlightedTermId.value = termId;
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
        successMessage.value = '';
        highlightedTermId.value = null;
    }, 6000);
};

onBeforeUnmount(() => clearTimeout(feedbackTimer));

const deleteRow = ref<ScheduleRow | null>(null);
const deleteForm = useForm({});
const deleteSchedule = () => {
    if (!deleteRow.value || deleteForm.processing) {
return;
}

    const termId = deleteRow.value.site_academic_term_id;
    deleteForm.delete(destroySchedules.url(termId), {
        preserveScroll: true,
        only: ['scheduleRows', 'scheduleToday', 'flash', 'errors'],
        onSuccess: () => {
            deleteRow.value = null;
            showSuccess(
                'Schedules and related Physical Fitness data deleted.',
                termId,
            );
        },
    });
};
const openDelete = (row: ScheduleRow) => {
    deleteForm.clearErrors();
    deleteRow.value = row;
};

// ---------- Set / Edit schedule dialog ----------
type DateField =
    | 'pre_opens_on'
    | 'pre_closes_on'
    | 'post_opens_on'
    | 'post_closes_on';
const opensField = (phase: Phase) => `${phase}_opens_on` as DateField;
const closesField = (phase: Phase) => `${phase}_closes_on` as DateField;

const dialogOpen = ref(false);
const isEditing = ref(false);
const formCampusId = ref('');
const form = useForm({
    site_academic_term_id: '' as number | '',
    pre_opens_on: '',
    pre_closes_on: '',
    post_opens_on: '',
    post_closes_on: '',
});

const formTerms = computed(() =>
    props.rows.filter(
        (row) => String(row.site_campus_id) === formCampusId.value,
    ),
);
const selectedRow = computed(() =>
    props.rows.find(
        (row) =>
            row.site_academic_term_id === Number(form.site_academic_term_id),
    ),
);
const willUpdateExisting = computed(() =>
    Boolean(selectedRow.value?.is_scheduled),
);

const fillFromRow = (row: ScheduleRow | undefined) => {
    for (const { key } of phases) {
        form[opensField(key)] = row?.[key].opens_on ?? '';
        form[closesField(key)] = row?.[key].closes_on ?? '';
    }

    form.clearErrors();
};

const resetDialog = () => {
    form.reset();
    form.clearErrors();
    formCampusId.value = '';
    isEditing.value = false;
};

const openCreate = () => {
    resetDialog();
    formCampusId.value =
        filterCampusId.value ||
        (campuses.value.length === 1 ? campuses.value[0].id : '');

    // Preselect the term when the filters already identify exactly one campus term.
    if (formCampusId.value && filterPeriod.value) {
        const match = formTerms.value.find(
            (row) => periodKey(row) === filterPeriod.value,
        );

        if (match) {
            form.site_academic_term_id = match.site_academic_term_id;
            fillFromRow(match);
        }
    }

    dialogOpen.value = true;
};

const openEdit = (row: ScheduleRow) => {
    resetDialog();
    isEditing.value = true;
    formCampusId.value = String(row.site_campus_id);
    form.site_academic_term_id = row.site_academic_term_id;
    fillFromRow(row);
    dialogOpen.value = true;
};

const onCampusChange = () => {
    form.site_academic_term_id = '';
    fillFromRow(undefined);
};

// Picking a term that already has schedules loads them, so saving updates instead of duplicating.
const onTermChange = () => fillFromRow(selectedRow.value);

const closeDialog = (open: boolean) => {
    if (!open && !form.processing) {
        dialogOpen.value = false;
    }
};

// ---------- Client-side guidance (server validation remains authoritative) ----------
const clientErrors = computed(() => {
    const errors: Partial<Record<DateField, string>> = {};

    if (
        form.pre_opens_on &&
        form.pre_closes_on &&
        form.pre_closes_on < form.pre_opens_on
    ) {
        errors.pre_closes_on =
            'The Pre-test closing date must be on or after its opening date.';
    }

    if (
        form.post_opens_on &&
        form.post_closes_on &&
        form.post_closes_on < form.post_opens_on
    ) {
        errors.post_closes_on =
            'The Post-test closing date must be on or after its opening date.';
    }

    if (
        form.pre_closes_on &&
        form.post_opens_on &&
        form.post_opens_on <= form.pre_closes_on
    ) {
        errors.post_opens_on =
            'The Post-test must open after the Pre-test closes. Periods cannot overlap, including boundary dates.';
    }

    return errors;
});

const fieldError = (field: DateField) =>
    form.errors[field] ?? clientErrors.value[field];
const generalErrors = computed(() =>
    Object.entries(form.errors)
        .filter(
            ([field]) =>
                ![
                    'pre_opens_on',
                    'pre_closes_on',
                    'post_opens_on',
                    'post_closes_on',
                ].includes(field),
        )
        .map(([, message]) => message),
);

const canSubmit = computed(
    () =>
        props.canManage &&
        Boolean(form.site_academic_term_id) &&
        Boolean(
            form.pre_opens_on &&
            form.pre_closes_on &&
            form.post_opens_on &&
            form.post_closes_on,
        ) &&
        Object.keys(clientErrors.value).length === 0 &&
        !form.processing,
);

watch(
    () => [
        form.pre_opens_on,
        form.pre_closes_on,
        form.post_opens_on,
        form.post_closes_on,
    ],
    () => {
        if (form.hasErrors) {
            form.clearErrors(
                'pre_opens_on',
                'pre_closes_on',
                'post_opens_on',
                'post_closes_on',
            );
        }
    },
);

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    const termId = Number(form.site_academic_term_id);
    const campusId = formCampusId.value;
    const period = selectedRow.value ? periodKey(selectedRow.value) : '';

    form.post(storeSchedules.url(), {
        preserveScroll: true,
        preserveState: true,
        // Partial reload: only the schedule table data is refreshed after saving.
        only: ['scheduleRows', 'scheduleToday', 'flash', 'errors'],
        onSuccess: (page) => {
            const flash = (
                page.props as { flash?: { toast?: { message?: string } } }
            ).flash;
            dialogOpen.value = false;
            resetDialog();

            // Make sure the saved row is visible under the current filters.
            if (filterCampusId.value && filterCampusId.value !== campusId) {
                filterCampusId.value = '';
            }

            if (filterPeriod.value && filterPeriod.value !== period) {
                filterPeriod.value = '';
            }

            showSuccess(
                flash?.toast?.message ??
                    'Pre-test and Post-test schedules saved.',
                termId,
            );
            nextTick(() =>
                document
                    .getElementById(`schedule-row-${termId}`)
                    ?.focus({ preventScroll: false }),
            );
        },
    });
};
</script>

<template>
    <section
        aria-labelledby="pft-schedules-heading"
        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
    >
        <!-- Header -->
        <div
            class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between dark:border-white/10"
        >
            <div class="min-w-0">
                <h2
                    id="pft-schedules-heading"
                    class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                >
                    <CalendarClock
                        class="size-4 text-blue-600 dark:text-blue-400"
                        aria-hidden="true"
                    />
                    Pre-test &amp; Post-test Schedules
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Fill Up is available to students only within each test's
                    opening and closing dates (inclusive). Statuses use the
                    campus-local date:
                    <span
                        class="font-semibold text-slate-700 dark:text-slate-200"
                        >{{ formatDate(today) }}</span
                    >.
                </p>
            </div>
            <button
                v-if="canManage"
                type="button"
                class="inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-slate-950"
                :disabled="rows.length === 0"
                @click="openCreate"
            >
                <CalendarPlus class="size-4" aria-hidden="true" /> Set Schedule
            </button>
        </div>

        <!-- Filter bar -->
        <div
            class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-end dark:border-white/10 dark:bg-slate-900/30"
            role="search"
            aria-label="Filter schedules"
        >
            <div class="min-w-0 flex-1 space-y-1 sm:max-w-xs">
                <Label
                    for="schedule-filter-campus"
                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                    >Campus</Label
                >
                <select
                    id="schedule-filter-campus"
                    v-model="filterCampusId"
                    :class="selectClass"
                >
                    <option value="">All campuses</option>
                    <option
                        v-for="campus in campuses"
                        :key="campus.id"
                        :value="campus.id"
                    >
                        {{ campus.name }}
                    </option>
                </select>
            </div>
            <div class="min-w-0 flex-1 space-y-1 sm:max-w-xs">
                <Label
                    for="schedule-filter-term"
                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                    >Academic Year &amp; Term</Label
                >
                <select
                    id="schedule-filter-term"
                    v-model="filterPeriod"
                    :class="selectClass"
                >
                    <option value="">All terms</option>
                    <option
                        v-for="period in periods"
                        :key="period.key"
                        :value="period.key"
                    >
                        {{ period.label }}
                    </option>
                </select>
            </div>
            <button
                v-if="hasFilters"
                type="button"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                @click="clearFilters"
            >
                <X class="size-3.5" aria-hidden="true" /> Clear
            </button>
        </div>

        <!-- Success feedback -->
        <div aria-live="polite" role="status">
            <div
                v-if="successMessage"
                class="mx-5 mt-4 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
            >
                <CheckCircle2
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <p class="flex-1">{{ successMessage }}</p>
                <button
                    type="button"
                    class="rounded p-0.5 hover:bg-emerald-100 dark:hover:bg-emerald-500/20"
                    aria-label="Dismiss message"
                    @click="successMessage = ''"
                >
                    <X class="size-3.5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div class="relative p-5" :aria-busy="form.processing">
            <!-- Empty: no academic terms configured -->
            <div
                v-if="rows.length === 0"
                class="rounded-lg border border-dashed border-slate-300 px-6 py-10 text-center dark:border-white/15"
            >
                <CalendarClock
                    class="mx-auto size-8 text-slate-300 dark:text-slate-600"
                    aria-hidden="true"
                />
                <p
                    class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    No academic terms available
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Add campus academic terms in Site Settings before setting
                    Physical Fitness Test schedules.
                </p>
            </div>

            <!-- Empty: nothing matches -->
            <div
                v-else-if="visibleRows.length === 0"
                class="rounded-lg border border-dashed border-slate-300 px-6 py-10 text-center dark:border-white/15"
            >
                <CalendarClock
                    class="mx-auto size-8 text-slate-300 dark:text-slate-600"
                    aria-hidden="true"
                />
                <p
                    class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    {{
                        hasFilters
                            ? 'No schedules match these filters'
                            : 'No schedules yet'
                    }}
                </p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        hasFilters
                            ? 'Choose an academic year & term to see campuses that still need a schedule.'
                            : 'Set Pre-test and Post-test dates to open Fill Up for students.'
                    }}
                </p>
                <div class="mt-4 flex justify-center gap-2">
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white hover:bg-blue-700"
                        @click="openCreate"
                    >
                        <CalendarPlus class="size-4" aria-hidden="true" /> Set
                        Schedule
                    </button>
                </div>
            </div>

            <template v-else>
                <div
                    v-if="form.processing"
                    class="absolute inset-0 z-10 grid place-items-center rounded-b-xl bg-white/60 dark:bg-slate-950/60"
                >
                    <span
                        class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300"
                        ><Spinner class="size-4" /> Refreshing schedules…</span
                    >
                </div>

                <!-- Desktop table -->
                <div
                    class="hidden overflow-x-auto rounded-lg border border-slate-200 md:block dark:border-white/10"
                >
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">
                            Pre-test and Post-test schedules by campus and
                            academic term
                        </caption>
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold tracking-wide text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-2.5">Campus</th>
                                <th scope="col" class="px-4 py-2.5">
                                    Academic Year &amp; Term
                                </th>
                                <th
                                    v-for="phase in phases"
                                    :key="phase.key"
                                    scope="col"
                                    class="px-4 py-2.5"
                                >
                                    {{ phase.label }}
                                </th>
                                <th
                                    v-if="canManage"
                                    scope="col"
                                    class="px-4 py-2.5 text-right"
                                >
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/5"
                        >
                            <tr
                                v-for="row in visibleRows"
                                :id="`schedule-row-${row.site_academic_term_id}`"
                                :key="row.site_academic_term_id"
                                tabindex="-1"
                                class="align-top transition-colors outline-none focus-visible:bg-blue-50/60 dark:focus-visible:bg-blue-500/10"
                                :class="
                                    highlightedTermId ===
                                    row.site_academic_term_id
                                        ? 'bg-emerald-50/70 dark:bg-emerald-500/10'
                                        : 'hover:bg-slate-50/70 dark:hover:bg-white/[0.03]'
                                "
                            >
                                <td
                                    class="px-4 py-3 font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ row.campus_name ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-700 dark:text-slate-300"
                                >
                                    {{ termLabel(row) }}
                                    <span
                                        v-if="row.term_status === 'Active'"
                                        class="ml-1.5 inline-flex rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                        >Active term</span
                                    >
                                </td>
                                <td
                                    v-for="phase in phases"
                                    :key="phase.key"
                                    class="px-4 py-3"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset"
                                        :class="
                                            statusMeta[row[phase.key].status]
                                                .classes
                                        "
                                    >
                                        {{
                                            statusMeta[row[phase.key].status]
                                                .label
                                        }}
                                    </span>
                                    <p
                                        v-if="row[phase.key].opens_on"
                                        class="mt-1 whitespace-nowrap text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            formatDate(row[phase.key].opens_on)
                                        }}
                                        –
                                        {{
                                            formatDate(row[phase.key].closes_on)
                                        }}
                                    </p>
                                    <p
                                        v-if="row[phase.key].opens_on"
                                        class="text-xs text-slate-400"
                                    >
                                        {{
                                            dayCount(
                                                row[phase.key].opens_on,
                                                row[phase.key].closes_on,
                                            )
                                        }}
                                        day(s)
                                    </p>
                                </td>
                                <td
                                    v-if="canManage"
                                    class="px-4 py-3 text-right"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                        :aria-label="`${row.is_scheduled ? 'Edit' : 'Set'} schedule for ${row.campus_name ?? 'campus'}, ${termLabel(row)}`"
                                        @click="openEdit(row)"
                                    >
                                        <Pencil
                                            v-if="row.is_scheduled"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <CalendarPlus
                                            v-else
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ row.is_scheduled ? 'Edit' : 'Set' }}
                                    </button>
                                    <button
                                        v-if="row.is_scheduled"
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10"
                                        :aria-label="`Delete schedule for ${row.campus_name ?? 'campus'}, ${termLabel(row)}`"
                                        @click="openDelete(row)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <ul
                    class="space-y-3 md:hidden"
                    aria-label="Pre-test and Post-test schedules"
                >
                    <li
                        v-for="row in visibleRows"
                        :key="row.site_academic_term_id"
                        class="rounded-lg border p-4 transition-colors"
                        :class="
                            highlightedTermId === row.site_academic_term_id
                                ? 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                                : 'border-slate-200 dark:border-white/10'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ row.campus_name ?? '—' }}
                                </p>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ termLabel(row) }}
                                </p>
                            </div>
                            <button
                                v-if="canManage"
                                type="button"
                                class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                :aria-label="`${row.is_scheduled ? 'Edit' : 'Set'} schedule for ${row.campus_name ?? 'campus'}, ${termLabel(row)}`"
                                @click="openEdit(row)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                                {{ row.is_scheduled ? 'Edit' : 'Set' }}
                            </button>
                        </div>
                        <div v-if="canManage" class="mt-3 flex justify-end">
                            <button
                                v-if="row.is_scheduled"
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-200 px-2.5 text-xs font-semibold text-red-600 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10"
                                :aria-label="`Delete schedule for ${row.campus_name ?? 'campus'}, ${termLabel(row)}`"
                                @click="openDelete(row)"
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                                Delete
                            </button>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <div v-for="phase in phases" :key="phase.key">
                                <dt
                                    class="text-[10px] font-bold tracking-wide text-slate-400 uppercase"
                                >
                                    {{ phase.label }}
                                </dt>
                                <dd class="mt-1 space-y-1">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset"
                                        :class="
                                            statusMeta[row[phase.key].status]
                                                .classes
                                        "
                                    >
                                        {{
                                            statusMeta[row[phase.key].status]
                                                .label
                                        }}
                                    </span>
                                    <p
                                        v-if="row[phase.key].opens_on"
                                        class="text-xs text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            formatDate(row[phase.key].opens_on)
                                        }}
                                        –
                                        {{
                                            formatDate(row[phase.key].closes_on)
                                        }}
                                    </p>
                                </dd>
                            </div>
                        </dl>
                    </li>
                </ul>

                <p v-if="!filterPeriod" class="mt-3 text-xs text-slate-400">
                    Showing scheduled terms. Select an academic year &amp; term
                    to also list campuses without a schedule.
                </p>
            </template>
        </div>

        <Dialog
            :open="Boolean(deleteRow)"
            @update:open="
                (open) => {
                    if (!open && !deleteForm.processing) deleteRow = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete schedule and related data?</DialogTitle>
                    <DialogDescription v-if="deleteRow">
                        This permanently deletes both test schedules, all
                        Pre-test and Post-test results, health questionnaires,
                        PAR-Q records, privacy consents, and clearance uploads
                        for {{ deleteRow.campus_name }},
                        {{ termLabel(deleteRow) }}. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-for="error in deleteForm.errors"
                    :key="error"
                    role="alert"
                    class="text-sm text-red-600"
                >
                    {{ error }}
                </p>
                <DialogFooter>
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm"
                        :disabled="deleteForm.processing"
                        @click="deleteRow = null"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                        :disabled="deleteForm.processing"
                        @click="deleteSchedule"
                    >
                        <Spinner v-if="deleteForm.processing" class="size-4" />
                        {{
                            deleteForm.processing
                                ? 'Deleting�'
                                : 'Delete schedule and data'
                        }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Set / Edit dialog -->
        <Dialog :open="dialogOpen" @update:open="closeDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form class="space-y-5" novalidate @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{
                            isEditing || willUpdateExisting
                                ? 'Edit Schedule'
                                : 'Set Schedule'
                        }}</DialogTitle>
                        <DialogDescription>
                            Set the opening and closing dates for both tests.
                            Dates are inclusive; the Post-test must open after
                            the Pre-test closes.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="schedule-form-campus">Campus</Label>
                            <select
                                id="schedule-form-campus"
                                v-model="formCampusId"
                                :class="selectClass"
                                :disabled="isEditing || form.processing"
                                required
                                @change="onCampusChange"
                            >
                                <option value="" disabled>
                                    Select campus…
                                </option>
                                <option
                                    v-for="campus in campuses"
                                    :key="campus.id"
                                    :value="campus.id"
                                >
                                    {{ campus.name }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="schedule-form-term"
                                >Academic Year &amp; Term</Label
                            >
                            <select
                                id="schedule-form-term"
                                v-model="form.site_academic_term_id"
                                :class="selectClass"
                                :disabled="
                                    isEditing ||
                                    !formCampusId ||
                                    form.processing
                                "
                                :aria-invalid="
                                    Boolean(form.errors.site_academic_term_id)
                                "
                                required
                                @change="onTermChange"
                            >
                                <option value="" disabled>
                                    {{
                                        formCampusId
                                            ? 'Select term…'
                                            : 'Select campus first…'
                                    }}
                                </option>
                                <option
                                    v-for="term in formTerms"
                                    :key="term.site_academic_term_id"
                                    :value="term.site_academic_term_id"
                                >
                                    {{ termLabel(term)
                                    }}{{
                                        term.term_status === 'Active'
                                            ? ' (Active)'
                                            : ''
                                    }}{{
                                        term.is_scheduled ? ' — scheduled' : ''
                                    }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <p
                        v-if="!isEditing && willUpdateExisting"
                        class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                    >
                        This term already has schedules. Saved dates are loaded
                        below and will be updated — no duplicate schedule is
                        created.
                    </p>

                    <fieldset
                        :disabled="
                            !form.site_academic_term_id || form.processing
                        "
                        class="grid gap-4 disabled:opacity-60 sm:grid-cols-2"
                    >
                        <legend class="sr-only">Test windows</legend>
                        <div
                            v-for="phase in phases"
                            :key="phase.key"
                            class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-white/10"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                >
                                    {{ phase.label }}
                                </h3>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset"
                                    :class="
                                        statusMeta[
                                            statusFor(
                                                form[opensField(phase.key)],
                                                form[closesField(phase.key)],
                                            )
                                        ].classes
                                    "
                                >
                                    {{
                                        statusMeta[
                                            statusFor(
                                                form[opensField(phase.key)],
                                                form[closesField(phase.key)],
                                            )
                                        ].label
                                    }}
                                </span>
                            </div>
                            <div class="space-y-1">
                                <Label
                                    :for="`schedule-${phase.key}-opens`"
                                    class="text-xs"
                                    >Opening date</Label
                                >
                                <Input
                                    :id="`schedule-${phase.key}-opens`"
                                    v-model="form[opensField(phase.key)]"
                                    type="date"
                                    required
                                    :aria-invalid="
                                        Boolean(
                                            fieldError(opensField(phase.key)),
                                        )
                                    "
                                    :aria-describedby="
                                        fieldError(opensField(phase.key))
                                            ? `schedule-${phase.key}-opens-error`
                                            : undefined
                                    "
                                />
                                <p
                                    v-if="fieldError(opensField(phase.key))"
                                    :id="`schedule-${phase.key}-opens-error`"
                                    role="alert"
                                    class="text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ fieldError(opensField(phase.key)) }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <Label
                                    :for="`schedule-${phase.key}-closes`"
                                    class="text-xs"
                                    >Closing date</Label
                                >
                                <Input
                                    :id="`schedule-${phase.key}-closes`"
                                    v-model="form[closesField(phase.key)]"
                                    type="date"
                                    required
                                    :min="
                                        form[opensField(phase.key)] || undefined
                                    "
                                    :aria-invalid="
                                        Boolean(
                                            fieldError(closesField(phase.key)),
                                        )
                                    "
                                    :aria-describedby="
                                        fieldError(closesField(phase.key))
                                            ? `schedule-${phase.key}-closes-error`
                                            : undefined
                                    "
                                />
                                <p
                                    v-if="fieldError(closesField(phase.key))"
                                    :id="`schedule-${phase.key}-closes-error`"
                                    role="alert"
                                    class="text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ fieldError(closesField(phase.key)) }}
                                </p>
                            </div>
                        </div>
                    </fieldset>

                    <div
                        v-if="generalErrors.length"
                        role="alert"
                        class="space-y-1 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                    >
                        <p v-for="message in generalErrors" :key="message">
                            {{ message }}
                        </p>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Editing dates never changes or removes student results
                        already submitted.
                    </p>

                    <DialogFooter class="gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                            :disabled="form.processing"
                            @click="closeDialog(false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-600 px-4 text-sm font-bold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canSubmit"
                        >
                            <Spinner v-if="form.processing" class="size-4" />
                            {{
                                form.processing
                                    ? 'Saving…'
                                    : isEditing || willUpdateExisting
                                      ? 'Update Schedule'
                                      : 'Save Schedule'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
