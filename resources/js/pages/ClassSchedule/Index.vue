<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    Clock3,
    DoorOpen,
    FlaskConical,
    LibraryBig,
    MapPin,
    Printer,
    RefreshCw,
    UserRound,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

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

    const [, startHour, startMinute, startMeridiem, endHour, endMinute, endMeridiem] =
        match;
    const start = parseClock(startHour, startMinute, startMeridiem, endMeridiem);
    let end = parseClock(endHour, endMinute, endMeridiem, startMeridiem);

    if (end <= start) {
        end += 12 * 60;
    }

    return ((end - start) / 60) * dayCount(normalized);
};

const lectureHours = computed(() =>
    schedules.value.reduce(
        (total, schedule) =>
            total + hoursFromSchedule(schedule.sessions[0]?.schedule ?? null),
        0,
    ),
);

const labHours = computed(() =>
    schedules.value.reduce(
        (total, schedule) =>
            total + hoursFromSchedule(schedule.sessions[1]?.schedule ?? null),
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
    value && value.trim().length > 0 ? value : '--';

const sessionLabel = (index: number): string => {
    if (index === 0) {
        return 'Lec';
    }

    if (index === 1) {
        return 'Lab';
    }

    return `Schedule ${index + 1}`;
};

const formatHours = (hours: number): string => {
    if (!Number.isFinite(hours) || hours <= 0) {
        return '0';
    }

    return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
};

const sessionAccentClass = (index: number): string => {
    if (index === 0) {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/20 dark:bg-sky-500/10 dark:text-sky-300';
    }

    if (index === 1) {
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/20 dark:bg-violet-500/10 dark:text-violet-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
};
</script>

<template>

    <Head title="Class Schedule" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                        <CalendarDays class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold text-slate-950 dark:text-white">
                            Class Schedule
                        </h1>
                        <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">
                            Enrolled subjects for the current academic
                            term.
                        </p>
                    </div>
                </div>

                <Button type="button" size="sm" class="h-8 gap-2 rounded-md text-xs font-bold" :disabled="refreshing"
                    @click="refresh">
                    <RefreshCw class="size-3.5" :class="{ 'animate-spin': refreshing }" />
                    Refresh
                </Button>
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <LibraryBig class="size-4 text-sky-600" />
                        Courses
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ subjectCount }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <Clock3 class="size-4 text-emerald-600" />
                        Lec Hours
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ formatHours(lectureHours) }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400">
                        <FlaskConical class="size-4 text-violet-600" />
                        Lab Hours
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">
                        {{ formatHours(labHours) }}
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
            <section
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                <div
                    class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                    <div>
                        <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                            Current Classes
                        </h2>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            {{ subjectCount }} matched sections
                        </p>
                    </div>
                    <button v-if="canDownloadCor" type="button"
                        class="inline-flex h-8 items-center gap-2 rounded-md border border-slate-200 px-3 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                        :disabled="downloadingCor" @click="downloadCOR">
                        <RefreshCw v-if="downloadingCor" class="size-3.5 animate-spin" />
                        <Printer v-else class="size-3.5" />
                        {{ downloadingCor ? 'Downloading...' : 'Download COR' }}
                    </button>
                </div>

                <div v-if="refreshing && !schedules.length" class="grid gap-3 p-4">
                    <div v-for="index in 4" :key="index"
                        class="h-24 animate-pulse rounded-lg bg-slate-100 dark:bg-white/5" />
                </div>

                <template v-else>
                    <div v-if="classSchedule.error"
                        class="m-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p class="font-bold">Schedule notice</p>
                            <p class="mt-0.5 text-xs font-medium">
                                {{ classSchedule.error }}
                            </p>
                        </div>
                    </div>

                    <div v-if="!schedules.length"
                        class="flex min-h-[320px] flex-col items-center justify-center gap-2 p-6 text-center">
                        <CalendarDays class="size-10 text-slate-300 dark:text-slate-700" />
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            No class schedule found
                        </h3>
                        <p class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400">
                            No matched class schedule is currently available for
                            your enrolled subjects in this term.
                        </p>
                    </div>

                    <div v-else class="grid gap-3 p-4">
                        <article v-for="schedule in schedules" :key="`${schedule.subject_id}-${schedule.schedule_id}`"
                            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:border-slate-300 hover:shadow-md dark:border-white/10 dark:bg-slate-950 dark:hover:border-white/20">
                            <div
                                class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-start sm:justify-between dark:border-white/10 dark:bg-white/[0.03]">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex h-7 items-center rounded-md bg-slate-900 px-2.5 text-xs font-bold text-white dark:bg-white dark:text-slate-950">
                                            {{ schedule.subject_code }}
                                        </span>
                                        <span
                                            class="inline-flex h-7 items-center rounded-md border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300">
                                            Section {{ valueOrDash(schedule.section) }}
                                        </span>
                                    </div>

                                    <h3 class="mt-2 text-base font-bold leading-snug text-slate-950 dark:text-white">
                                        {{ schedule.subject_title }}
                                    </h3>
                                </div>

                                <div
                                    class="flex min-w-0 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 sm:max-w-[300px] dark:border-white/10 dark:bg-slate-900 dark:text-slate-300">
                                    <UserRound class="size-3.5 shrink-0 text-slate-400" />
                                    <span class="min-w-0 truncate font-semibold">
                                        Faculty: {{ facultyName(schedule.faculty_name) }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid gap-2 p-4 md:grid-cols-2">
                                <div v-if="!schedule.sessions.length"
                                    class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center text-xs font-semibold text-slate-500 md:col-span-2 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                                    Schedule TBA
                                </div>
                                <div v-for="(session, index) in schedule.sessions" :key="index"
                                    class="rounded-lg border p-3" :class="sessionAccentClass(index)">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <p class="text-[11px] font-bold uppercase">
                                            {{ sessionLabel(index) }}
                                        </p>
                                        <span
                                            class="rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-300">
                                            {{ index === 0 ? 'Schedule 1' : index === 1 ? 'Schedule 2' : `Schedule
                                            ${index + 1}` }}
                                        </span>
                                    </div>

                                    <div
                                        class="grid gap-2 rounded-md bg-white/70 p-2 text-slate-700 dark:bg-slate-950/60 dark:text-slate-200">
                                        <div class="flex items-start gap-2">
                                            <Clock3 class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                                            <span class="font-bold leading-snug">
                                                {{ valueOrDash(session.schedule) }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <MapPin class="size-3.5 shrink-0 text-slate-400" />
                                            <span class="font-semibold">
                                                Room {{ valueOrDash(session.room) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </template>
            </section>

            <aside class="grid content-start gap-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            <UserRound class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-sm font-bold text-slate-950 dark:text-white">
                                {{ student.name }}
                            </h2>
                            <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">
                                Synced from One USM SSO
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-2 text-xs">
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5">
                            <dt class="font-bold text-slate-500 dark:text-slate-400">
                                Student No.
                            </dt>
                            <dd class="truncate font-bold text-slate-900 dark:text-white">
                                {{ student.student_no || '-' }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 dark:bg-white/5">
                            <dt class="font-bold text-slate-500 dark:text-slate-400">
                                Campus
                            </dt>
                            <dd class="truncate font-bold text-slate-900 dark:text-white">
                                {{ student.campus_name || '-' }}
                            </dd>
                        </div>
                    </dl>
                </section>

            </aside>
        </div>
    </div>
</template>
