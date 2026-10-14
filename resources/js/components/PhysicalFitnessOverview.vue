<script setup lang="ts">
import {
    ChartColumnIncreasing,
    FileCheck,
    HeartPulse,
    CalendarDays,
    ArrowRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

type Term = {
    id: number;
    term_id: string;
    school_year: string;
    semester: string;
    status: string;
};
const props = defineProps<{
    terms: Term[];
    termId: string;
    phase: 'pre' | 'post';
    required: number;
    completed: number;
    started: boolean;
    availability: 'Not Scheduled' | 'Upcoming' | 'Open' | 'Closed';
    opensOn?: string;
    closesOn?: string;
    canFillUp: boolean;
    canSubmit: boolean;
    parqOpen: boolean;
}>();
const emit = defineEmits<{
    term: [id: string];
    phase: [phase: 'pre' | 'post'];
    fill: [];
    summary: [];
    analytics: [];
    parq: [];
    health: [];
}>();
const term = computed(() =>
    props.terms.find((term) => term.term_id === props.termId),
);
const pending = computed(() => Math.max(0, props.required - props.completed));
const percentage = computed(() =>
    props.required ? Math.round((props.completed / props.required) * 100) : 0,
);
const completion = computed(() =>
    props.required > 0 && pending.value === 0
        ? 'Completed'
        : props.started
          ? 'In Progress'
          : 'Not Started',
);
const phaseLabel = computed(() =>
    props.phase === 'pre' ? 'Pre-test' : 'Post-test',
);
const canEnter = computed(
    () =>
        props.canFillUp &&
        props.canSubmit &&
        props.availability === 'Open' &&
        pending.value > 0,
);
const dateLabel = (date?: string) =>
    date
        ? new Intl.DateTimeFormat('en', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          }).format(new Date(`${date.slice(0, 10)}T12:00:00`))
        : '';
const message = computed(() => {
    if (!props.canFillUp || !props.canSubmit) {
        return 'Your account has read-only access. You can review your completed assessment results.';
    }

    if (props.availability === 'Not Scheduled') {
        return 'This assessment is not currently scheduled for data entry.';
    }

    if (props.availability === 'Upcoming') {
        return `Data entry opens on ${dateLabel(props.opensOn)}.`;
    }

    if (props.availability === 'Closed') {
        return 'The submission period has ended. Your saved results remain available to view.';
    }

    if (pending.value === 0) {
        return 'All required tests are complete. Review your assessment summary below.';
    }

    return 'Data entry is open. Health and PAR-Q clearance requirements are checked before you begin.';
});
</script>

<template>
    <div class="space-y-4 font-normal">
        <section
            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-slate-900"
        >
            <div class="flex min-w-0 items-center gap-3">
                <CalendarDays
                    class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
                <div>
                    <p
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Academic Year & Semester
                    </p>
                    <p
                        v-if="terms.length === 1"
                        class="mt-1 text-base font-semibold text-slate-950 dark:text-white"
                    >
                        {{ term?.school_year }}, {{ term?.semester }}
                    </p>
                    <select
                        v-else
                        :value="termId"
                        aria-label="Academic year and semester"
                        class="mt-1 min-h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-500 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                        @change="
                            emit(
                                'term',
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option
                            v-for="item in terms"
                            :key="item.id"
                            :value="item.term_id"
                        >
                            {{ item.school_year }}, {{ item.semester }}
                        </option>
                    </select>
                </div>
            </div>
            <span
                v-if="term?.status?.toLowerCase() === 'active'"
                class="self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 sm:self-center dark:bg-emerald-500/10 dark:text-emerald-300"
                >Active Term</span
            >
        </section>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-live="polite">
            <div
                v-for="metric in [
                    { label: 'Required Tests', value: required },
                    { label: 'Completed Tests', value: completed },
                    { label: 'Pending Tests', value: pending },
                    { label: 'Completion', value: `${percentage}%` },
                ]"
                :key="metric.label"
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900"
            >
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ metric.label }}
                </p>
                <p
                    class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white"
                >
                    {{ metric.value }}
                </p>
            </div>
        </div>

        <div
            class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-white/5"
            role="group"
            aria-label="Assessment period"
        >
            <button
                v-for="period in ['pre', 'post'] as const"
                :key="period"
                type="button"
                :aria-pressed="phase === period"
                class="min-h-11 rounded-lg px-4 py-2 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                :class="
                    phase === period
                        ? 'bg-emerald-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-white/10'
                "
                @click="emit('phase', period)"
            >
                {{ period === 'pre' ? 'Pre-test' : 'Post-test' }}
            </button>
        </div>

        <section
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-white/10 dark:bg-slate-900"
            aria-live="polite"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3
                        class="text-base font-semibold text-slate-950 dark:text-white"
                    >
                        {{ phaseLabel }} Assessment
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Progress for the selected academic term
                    </p>
                </div>
                <span
                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200"
                    >{{ completion }}</span
                >
            </div>
            <div>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-slate-600 dark:text-slate-300"
                        >{{ completed }} of {{ required }} tests completed</span
                    ><span
                        class="font-semibold text-emerald-700 dark:text-emerald-300"
                        >{{ percentage }}%</span
                    >
                </div>
                <div
                    role="progressbar"
                    aria-label="Assessment completion"
                    :aria-valuenow="percentage"
                    :aria-valuemin="0"
                    :aria-valuemax="100"
                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"
                >
                    <div
                        class="h-full rounded-full bg-emerald-600 transition-all"
                        :style="{ width: `${percentage}%` }"
                    />
                </div>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >Availability</span
                    ><span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            availability === 'Open'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300'
                                : availability === 'Upcoming'
                                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                                  : 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-300'
                        "
                        >{{ availability }}</span
                    >
                </div>
                <p
                    v-if="opensOn && closesOn"
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                >
                    {{ dateLabel(opensOn) }} – {{ dateLabel(closesOn) }}
                </p>
                <p
                    class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-300"
                >
                    {{ message }}
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <Button
                    v-if="canEnter"
                    class="min-h-11 bg-emerald-600 text-white hover:bg-emerald-700"
                    @click="emit('fill')"
                    >{{ started ? 'Continue assessment' : 'Fill Up assessment'
                    }}<ArrowRight class="ml-2 size-4"
                /></Button>
                <Button
                    :variant="canEnter ? 'outline' : 'default'"
                    class="min-h-11"
                    :class="
                        !canEnter
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                            : ''
                    "
                    @click="emit('summary')"
                    >View Assessment Summary</Button
                >
            </div>
        </section>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                Health & Fitness Resources
            </h3>
            <div class="grid gap-2 sm:grid-cols-3">
                <button
                    v-for="resource in [
                        {
                            key: 'analytics',
                            icon: ChartColumnIncreasing,
                            label: 'View My Analytics',
                            description: 'Explore your fitness results.',
                        },
                        {
                            key: 'parq',
                            icon: FileCheck,
                            label: 'PAR-Q & Clearance',
                            description: 'Review readiness and clearance.',
                        },
                        {
                            key: 'health',
                            icon: HeartPulse,
                            label: 'Health Condition',
                            description: 'View your health submission.',
                        },
                    ]"
                    :key="resource.key"
                    type="button"
                    :disabled="resource.key === 'health' && !canFillUp"
                    class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-emerald-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-500 dark:border-white/10 dark:bg-slate-900 dark:hover:bg-white/5"
                    :class="
                        resource.key === 'parq' && parqOpen
                            ? 'ring-2 ring-emerald-500'
                            : ''
                    "
                    @click="
                        resource.key === 'analytics'
                            ? emit('analytics')
                            : resource.key === 'parq'
                              ? emit('parq')
                              : emit('health')
                    "
                >
                    <component
                        :is="resource.icon"
                        class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    /><span
                        ><span
                            class="block text-sm font-semibold text-slate-900 dark:text-white"
                            >{{ resource.label }}</span
                        ><span
                            class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                            >{{ resource.description }}</span
                        ></span
                    >
                </button>
            </div>
        </section>
    </div>
</template>
