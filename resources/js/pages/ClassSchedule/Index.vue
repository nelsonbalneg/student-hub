<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    CalendarDays,
    Clock3,
    Download,
    FlaskConical,
    GraduationCap,
    LibraryBig,
    MapPin,
    RefreshCw,
    UserRound,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

type Student = {
    name: string;
    student_no: string | null;
    campus_name: string | null;
    tenant_id: string | null;
};

type ActiveTerm = {
    term_id: string | null;
    name: string | null;
};

type ScheduleSession = {
    schedule: string | null;
    room: string | null;
};

type ClassScheduleRecord = {
    subject_id: string;
    schedule_id: string | null;
    subject_code: string;
    subject_title: string;
    section: string;
    faculty_name: string | null;
    class_size: string | number | null;
    sessions: ScheduleSession[];
};

const props = defineProps<{
    student: Student;
    activeTerm: ActiveTerm;
    classSchedule: {
        data: ClassScheduleRecord[];
        error: string | null;
    };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Home', href: '/' },
        { title: 'Class Schedule', href: '/academic/class-schedule' },
    ],
});

const refreshing = ref(false);
const downloadingCor = ref(false);
const page = usePage();

const schedules = computed(() => props.classSchedule.data ?? []);
const subjectCount = computed(() => schedules.value.length);
const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);
const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);
const canDownloadCor = computed(
    () =>
        roles.value.includes('Super Admin') ||
        roles.value.includes('Student') ||
        permissions.value.includes('download cor'),
);

const parseClock = (
    hour: string,
    minute: string | undefined,
    meridiem: string | undefined,
    fallbackMeridiem?: string,
): number => {
    let parsedHour = Number(hour);
    const parsedMinute = Number(minute ?? 0);
    const period = (meridiem || fallbackMeridiem || '').toUpperCase();

    if (period === 'PM' && parsedHour < 12) {
        parsedHour += 12;
    }

    if (period === 'AM' && parsedHour === 12) {
        parsedHour = 0;
    }

    return parsedHour * 60 + parsedMinute;
};

const dayCount = (schedule: string): number => {
    const prefix = schedule
        .split(/\d{1,2}(?::\d{2})?\s*(?:AM|PM)?/i)[0]
        ?.toUpperCase()
        .replace(/[^A-Z]/g, '');

    if (!prefix) {
        return 1;
    }

    const longMatches = prefix.match(/MON|TUE|WED|THU|FRI|SAT|SUN/g);

    if (longMatches?.length) {
        return longMatches.length;
    }

    let compact = prefix;
    let count = 0;

    for (const token of ['SUN', 'SAT', 'TH', 'M', 'T', 'W', 'F', 'S']) {
        const matches = compact.match(new RegExp(token, 'g'));
        if (!matches) {
            continue;
        }

        count += matches.length;
        compact = compact.replace(new RegExp(token, 'g'), '');
    }

    return count > 0 ? count : 1;
};

const hoursFromSchedule = (schedule: string | null): number => {
    if (!schedule) {
        return 0;
    }

    const normalized = schedule.replace(/\s+/g, ' ').trim();
    const match = normalized.match(
        /(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?\s*[-–]\s*(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?/i,
    );

    if (!match) {
        return 0;
    }

    const [, startHour, startMinute, startMeridiem, endHour, endMinute, endMeridiem] = match;
    const start = parseClock(startHour, startMinute, startMeridiem, endMeridiem);
    let end = parseClock(endHour, endMinute, endMeridiem, startMeridiem);

    if (end <= start) {
        end += 12 * 60;
    }

    return ((end - start) / 60) * dayCount(normalized);
};

const lectureHours = computed(() =>
    schedules.value.reduce(
        (total, schedule) => total + hoursFromSchedule(schedule.sessions[0]?.schedule ?? null),
        0,
    ),
);

const labHours = computed(() =>
    schedules.value.reduce(
        (total, schedule) => total + hoursFromSchedule(schedule.sessions[1]?.schedule ?? null),
        0,
    ),
);

const refresh = () => {
    refreshing.value = true;
    router.reload({
        onFinish: () => {
            refreshing.value = false;
        },
    });
};

const filenameFromDisposition = (disposition: string | null): string => {
    const fallback = `COR-${props.student.student_no ?? 'student'}-${props.activeTerm.term_id ?? 'term'}.pdf`;

    if (!disposition) {
        return fallback;
    }

    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utfMatch?.[1]) {
        return decodeURIComponent(utfMatch[1]);
    }

    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match?.[1] ?? fallback;
};

const downloadCOR = async () => {
    if (downloadingCor.value) {
        return;
    }

    downloadingCor.value = true;

    try {
        const response = await fetch('/academic/cor/download', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/pdf',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            let message = 'Unable to download your COR right now.';

            try {
                const data = await response.json();
                message = data?.message || message;
            } catch {
                // Keep the friendly fallback message.
            }

            toast.error(message);
            return;
        }

        const blob = await response.blob();

        if (!blob.size) {
            toast.error('The COR file is empty. Please try again later.');
            return;
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filenameFromDisposition(response.headers.get('Content-Disposition'));
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        toast.success('COR download started.');
    } catch {
        toast.error('Unable to download your COR right now. Please try again later.');
    } finally {
        downloadingCor.value = false;
    }
};

const valueOrDash = (value: string | number | null | undefined): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    return String(value);
};

const facultyName = (value: string | null): string =>
    value && value.trim().length > 0 ? value : 'TBA';

const formatHours = (hours: number): string => {
    if (!Number.isFinite(hours) || hours <= 0) {
        return '0';
    }
    return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
};
</script>

<template>
    <Head title="Class Schedule" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 lg:gap-5 lg:p-6">

        <!-- Page Header -->
        <div class="flex flex-col gap-1">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-slate-950 dark:text-white">Class Schedule</h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        Your enrolled subjects for
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ activeTerm.name || 'the current term' }}</span>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <button
                        v-if="canDownloadCor"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20 sm:px-3.5"
                        :disabled="downloadingCor"
                        @click="downloadCOR"
                    >
                        <RefreshCw v-if="downloadingCor" class="size-3.5 animate-spin" />
                        <Download v-else class="size-3.5" />
                        {{ downloadingCor ? 'Downloading…' : 'Download COR' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 sm:px-3.5"
                        :disabled="refreshing"
                        @click="refresh"
                    >
                        <RefreshCw class="size-3.5" :class="{ 'animate-spin': refreshing }" />
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-3 dark:border-white/10 dark:bg-slate-950">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 sm:size-9 dark:bg-sky-500/10 dark:text-sky-400">
                    <LibraryBig class="size-4" />
                </span>
                <div>
                    <p class="text-[9px] font-medium text-slate-500 uppercase sm:text-[11px] dark:text-slate-400">Courses</p>
                    <p class="text-lg font-bold text-slate-950 sm:text-xl dark:text-white">{{ subjectCount }}</p>
                </div>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-3 dark:border-white/10 dark:bg-slate-950">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 sm:size-9 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <Clock3 class="size-4" />
                </span>
                <div>
                    <p class="text-[9px] font-medium text-slate-500 uppercase sm:text-[11px] dark:text-slate-400">Lec Hours</p>
                    <p class="text-lg font-bold text-slate-950 sm:text-xl dark:text-white">{{ formatHours(lectureHours) }}</p>
                </div>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-3 dark:border-white/10 dark:bg-slate-950">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 sm:size-9 dark:bg-violet-500/10 dark:text-violet-400">
                    <FlaskConical class="size-4" />
                </span>
                <div>
                    <p class="text-[9px] font-medium text-slate-500 uppercase sm:text-[11px] dark:text-slate-400">Lab Hours</p>
                    <p class="text-lg font-bold text-slate-950 sm:text-xl dark:text-white">{{ formatHours(labHours) }}</p>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid min-h-0 gap-5 xl:grid-cols-[1fr_280px]">

            <!-- Schedule Table -->
            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <CalendarDays class="size-4 text-slate-400" />
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">Current Classes</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-white/10 dark:text-slate-400">
                            {{ subjectCount }}
                        </span>
                    </div>
                </div>

                <!-- Skeleton loader -->
                <div v-if="refreshing && !schedules.length" class="divide-y divide-slate-100 dark:divide-white/10">
                    <div v-for="i in 4" :key="i" class="flex items-center gap-4 px-5 py-4">
                        <div class="h-8 w-24 animate-pulse rounded-md bg-slate-100 dark:bg-white/5" />
                        <div class="flex-1 space-y-2">
                            <div class="h-4 w-2/3 animate-pulse rounded bg-slate-100 dark:bg-white/5" />
                            <div class="h-3 w-1/3 animate-pulse rounded bg-slate-100 dark:bg-white/5" />
                        </div>
                    </div>
                </div>

                <template v-else>
                    <!-- Error notice -->
                    <div
                        v-if="classSchedule.error"
                        class="mx-5 mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-400/30 dark:bg-amber-500/10"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <p class="font-bold text-amber-800 dark:text-amber-300">Schedule notice</p>
                            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">{{ classSchedule.error }}</p>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="!schedules.length"
                        class="flex min-h-[340px] flex-col items-center justify-center gap-3 p-8 text-center"
                    >
                        <span class="flex size-14 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                            <CalendarDays class="size-6 text-slate-300 dark:text-slate-600" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-700 dark:text-white">No class schedule found</h3>
                            <p class="mt-1 max-w-xs text-xs text-slate-400 dark:text-slate-500">
                                No matched class schedule is available for your enrolled subjects this term.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="schedules.length"
                        class="divide-y divide-slate-100 md:hidden dark:divide-white/10"
                    >
                        <article
                            v-for="schedule in schedules"
                            :key="`${schedule.subject_id}-${schedule.schedule_id}`"
                            class="space-y-2.5 p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center rounded-md bg-slate-900 px-2 py-0.5 text-[9px] font-bold text-white dark:bg-white dark:text-slate-950">
                                            {{ schedule.subject_code }}
                                        </span>
                                        <span class="text-[9px] font-bold text-slate-500 dark:text-slate-400">
                                            Section {{ valueOrDash(schedule.section) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs font-semibold leading-4 text-slate-800 dark:text-slate-100">
                                        {{ schedule.subject_title }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400">
                                <UserRound class="size-3 shrink-0" />
                                <span class="truncate">{{ facultyName(schedule.faculty_name) }}</span>
                            </div>

                            <div
                                v-if="schedule.sessions.length"
                                class="grid gap-1.5"
                            >
                                <div
                                    v-for="(session, sessionIndex) in schedule.sessions"
                                    :key="sessionIndex"
                                    class="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 rounded-md bg-slate-50 px-2.5 py-2 dark:bg-white/5"
                                >
                                    <span
                                        class="row-span-2 mt-0.5 inline-flex h-4 items-center rounded-full px-1.5 text-[8px] font-bold"
                                        :class="
                                            sessionIndex === 0
                                                ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                                                : 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                                        "
                                    >
                                        {{ sessionIndex === 0 ? 'Lec' : 'Lab' }}
                                    </span>
                                    <span class="flex min-w-0 items-center gap-1 text-[10px] font-semibold text-slate-700 dark:text-slate-200">
                                        <Clock3 class="size-3 shrink-0 text-slate-400" />
                                        <span class="truncate">{{ valueOrDash(session.schedule) }}</span>
                                    </span>
                                    <span class="flex min-w-0 items-center gap-1 text-[10px] text-slate-500 dark:text-slate-400">
                                        <MapPin class="size-3 shrink-0 text-slate-400" />
                                        <span class="truncate">{{ valueOrDash(session.room) }}</span>
                                    </span>
                                </div>
                            </div>
                            <div
                                v-else
                                class="rounded-md bg-slate-50 px-2.5 py-2 text-[10px] font-medium text-slate-400 dark:bg-white/5 dark:text-slate-500"
                            >
                                Schedule and room TBA
                            </div>
                        </article>
                    </div>

                    <!-- Schedule Table -->
                    <div
                        v-if="schedules.length"
                        class="hidden overflow-x-auto md:block"
                    >
                        <table class="min-w-full text-left text-xs">
                            <thead class="border-b border-slate-100 bg-slate-50/80 dark:border-white/10 dark:bg-white/[0.03]">
                                <tr>
                                    <th class="px-5 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Code</th>
                                    <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Subject</th>
                                    <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Section</th>
                                    <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Faculty</th>
                                    <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Schedule</th>
                                    <th class="px-3 py-3 text-[11px] font-bold text-slate-400 uppercase dark:text-slate-500">Room</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <template
                                    v-for="schedule in schedules"
                                    :key="`${schedule.subject_id}-${schedule.schedule_id}`"
                                >
                                    <!-- Single row if only one session OR show first session -->
                                    <tr
                                        class="group transition hover:bg-slate-50/80 dark:hover:bg-white/[0.03]"
                                        :class="{ 'border-b-0': schedule.sessions.length > 1 }"
                                    >
                                        <td
                                            class="px-5 py-3.5"
                                            :rowspan="Math.max(schedule.sessions.length, 1)"
                                        >
                                            <span class="inline-flex items-center rounded-md bg-slate-900 px-2.5 py-1 text-[11px] font-bold text-white dark:bg-white dark:text-slate-950">
                                                {{ schedule.subject_code }}
                                            </span>
                                        </td>
                                        <td
                                            class="max-w-[220px] px-3 py-3.5"
                                            :rowspan="Math.max(schedule.sessions.length, 1)"
                                        >
                                            <p class="font-semibold text-slate-800 dark:text-slate-100 leading-snug">
                                                {{ schedule.subject_title }}
                                            </p>
                                        </td>
                                        <td
                                            class="px-3 py-3.5"
                                            :rowspan="Math.max(schedule.sessions.length, 1)"
                                        >
                                            <span class="font-semibold text-slate-600 dark:text-slate-300">
                                                {{ valueOrDash(schedule.section) }}
                                            </span>
                                        </td>
                                        <td
                                            class="max-w-[160px] px-3 py-3.5"
                                            :rowspan="Math.max(schedule.sessions.length, 1)"
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <UserRound class="size-3 shrink-0 text-slate-400" />
                                                <span class="truncate font-medium text-slate-600 dark:text-slate-300">
                                                    {{ facultyName(schedule.faculty_name) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- First session (or TBA) -->
                                        <td class="px-3 py-3.5" v-if="!schedule.sessions.length">
                                            <span class="text-slate-400 dark:text-slate-500">TBA</span>
                                        </td>
                                        <td class="px-3 py-3.5" v-else>
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">Lec</span>
                                                <span class="flex items-center gap-1 font-medium text-slate-700 dark:text-slate-200">
                                                    <Clock3 class="size-3 shrink-0 text-slate-400" />
                                                    {{ valueOrDash(schedule.sessions[0]?.schedule) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3.5" v-if="!schedule.sessions.length">
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        </td>
                                        <td class="px-3 py-3.5" v-else>
                                            <div class="flex items-center gap-1 font-medium text-slate-600 dark:text-slate-300">
                                                <MapPin class="size-3 shrink-0 text-slate-400" />
                                                {{ valueOrDash(schedule.sessions[0]?.room) }}
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Additional sessions (Lab etc.) -->
                                    <tr
                                        v-for="(session, sIndex) in schedule.sessions.slice(1)"
                                        :key="sIndex"
                                        class="transition hover:bg-slate-50/80 dark:hover:bg-white/[0.03]"
                                    >
                                        <td class="px-3 py-3 border-t border-slate-100 dark:border-white/10">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Lab</span>
                                                <span class="flex items-center gap-1 font-medium text-slate-700 dark:text-slate-200">
                                                    <Clock3 class="size-3 shrink-0 text-slate-400" />
                                                    {{ valueOrDash(session.schedule) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 border-t border-slate-100 dark:border-white/10">
                                            <div class="flex items-center gap-1 font-medium text-slate-600 dark:text-slate-300">
                                                <MapPin class="size-3 shrink-0 text-slate-400" />
                                                {{ valueOrDash(session.room) }}
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </section>

            <!-- Sidebar -->
            <aside class="flex flex-col gap-4">

                <!-- Student Info Card -->
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-white/10">
                        <h3 class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400">Student</h3>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-sm font-bold text-white shadow-sm">
                                {{ student.name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-950 dark:text-white">{{ student.name }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500">Student Account</p>
                            </div>
                        </div>

                        <dl class="mt-4 space-y-1 text-xs">
                            <div class="flex items-center justify-between py-1.5">
                                <dt class="text-slate-400 dark:text-slate-500">Student No.</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ student.student_no || '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 py-1.5 dark:border-white/10">
                                <dt class="text-slate-400 dark:text-slate-500">Campus</dt>
                                <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ student.campus_name || '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <!-- Active Term Card -->
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-white/10">
                        <h3 class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400">Active Term</h3>
                    </div>
                    <div class="p-4">
                        <div class="flex items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <GraduationCap class="size-4" />
                            </span>
                            <div>
                                <p class="text-sm font-bold text-slate-950 dark:text-white">
                                    {{ activeTerm.name || 'No active term' }}
                                </p>

                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-1.5">
                            <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                            <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">Currently Enrolled</span>
                        </div>
                    </div>
                </section>

                <!-- Legend Card -->
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 px-4 py-3 dark:border-white/10">
                        <h3 class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400">Legend</h3>
                    </div>
                    <div class="p-4 space-y-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">Lec</span>
                            <span class="text-slate-500 dark:text-slate-400">Lecture session</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Lab</span>
                            <span class="text-slate-500 dark:text-slate-400">Laboratory session</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 border-t border-slate-100 pt-2 dark:border-white/10">
                            <BookOpen class="size-3.5 text-slate-400" />
                            <span class="text-slate-500 dark:text-slate-400">TBA = To be announced</span>
                        </div>
                    </div>
                </section>

            </aside>
        </div>
    </div>
</template>
