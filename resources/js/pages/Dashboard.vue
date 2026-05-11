<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Building2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Database,
    Megaphone,
    Pin,
    Server,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { format } from 'date-fns';

type Campus = {
    id: number | null;
    name: string | null;
    tenant_id: string | null;
};

type ActiveSemester = Record<string, unknown>;

const props = defineProps<{
    campus: Campus;
    activeSemesters: {
        data: ActiveSemester[];
        error: string | null;
    };
    announcements: any[];
}>();

const labelFor = (semester: ActiveSemester): string => {
    const fields = [
        'semesterName',
        'semester_name',
        'description',
        'name',
        'schoolYear',
        'school_year',
        'academicYear',
        'academic_year',
        'term',
    ];

    return (
        fields
            .map((field) => semester[field])
            .filter(Boolean)
            .join(' - ') || 'Active Semester'
    );
};

const detailFor = (semester: ActiveSemester): string => {
    const fields = ['semesterId', 'semester_id', 'id', 'code'];

    return fields
        .map((field) => semester[field])
        .filter(Boolean)
        .join(' / ');
};

const activeTermCount = computed(() => props.activeSemesters.data.length);
const campusLinked = computed(() => Boolean(props.campus.id));
const integrationState = computed(() => {
    if (props.activeSemesters.error) {
        return {
            label: 'Attention',
            tone: 'amber',
            message: props.activeSemesters.error,
        };
    }

    return {
        label: 'Operational',
        tone: 'emerald',
        message: 'Academic service responded successfully.',
    };
});

const statusCards = computed(() => [
    {
        label: 'Active Terms',
        value: activeTermCount.value,
        detail: activeTermCount.value === 1 ? 'Current semester' : 'Semesters',
        icon: CalendarDays,
        tone: 'blue',
    },
    {
        label: 'Campus Link',
        value: campusLinked.value ? 'Linked' : 'Missing',
        detail: props.campus.name || 'SSO campus context',
        icon: Building2,
        tone: campusLinked.value ? 'emerald' : 'amber',
    },
    {
        label: 'Tenant',
        value: props.campus.tenant_id || '-',
        detail: 'Identity scope',
        icon: Database,
        tone: 'slate',
    },
    {
        label: 'Academic API',
        value: integrationState.value.label,
        detail: 'Active semester feed',
        icon: Server,
        tone: integrationState.value.tone,
    },
]);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section
            class="flex flex-col gap-3 border-b border-slate-200 pb-4 lg:flex-row lg:items-center lg:justify-between dark:border-white/10"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex size-8 items-center justify-center rounded-lg bg-sky-50 text-sky-600 ring-1 ring-sky-100 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20"
                    >
                        <Activity class="size-4" />
                    </span>
                    <div>
                        <h1
                            class="text-lg font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            Dashboard
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Academic operations and SSO context overview.
                        </p>
                    </div>
                </div>
            </div>
            <div
                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300"
            >
                <Clock3 class="size-3.5 text-slate-400" />
                <span class="font-medium">Academic Year</span>
                <span class="font-bold text-slate-900 dark:text-white"
                    >2023-2024</span
                >
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in statusCards"
                :key="card.label"
                class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-500"
                        >
                            {{ card.label }}
                        </p>
                        <p
                            class="mt-1 truncate text-lg font-bold text-slate-950 dark:text-white"
                        >
                            {{ card.value }}
                        </p>
                        <p
                            class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ card.detail }}
                        </p>
                    </div>
                    <div
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg"
                        :class="{
                            'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300':
                                card.tone === 'blue',
                            'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300':
                                card.tone === 'emerald',
                            'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300':
                                card.tone === 'amber',
                            'bg-slate-100 text-slate-600 dark:bg-white/[0.06] dark:text-slate-300':
                                card.tone === 'slate',
                        }"
                    >
                        <component :is="card.icon" class="size-4" />
                    </div>
                </div>
            </div>
        </section>

        <section class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[1fr_360px]">
            <div
                class="min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Current Term Status
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Active semester records from the academic service.
                        </p>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-bold"
                        :class="
                            activeSemesters.error
                                ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                        "
                    >
                        <component
                            :is="
                                activeSemesters.error
                                    ? AlertTriangle
                                    : CheckCircle2
                            "
                            class="size-3"
                        />
                        {{ activeSemesters.error ? 'Needs Review' : 'Live' }}
                    </span>
                </div>

                <div
                    v-if="activeSemesters.error"
                    class="m-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ activeSemesters.error }}</span>
                </div>

                <div
                    v-else-if="!activeSemesters.data.length"
                    class="grid place-items-center px-4 py-16 text-center"
                >
                    <div>
                        <CalendarDays
                            class="mx-auto size-8 text-slate-300 dark:text-slate-700"
                        />
                        <p
                            class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
                        >
                            No active semester
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            No active semester was detected for this campus.
                        </p>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                    >
                        <thead class="bg-slate-50/80 dark:bg-white/[0.03]">
                            <tr>
                                <th class="table-head">Term</th>
                                <th class="table-head">Reference</th>
                                <th class="table-head">Status</th>
                                <th class="table-head text-right">Source</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-50 dark:divide-white/5"
                        >
                            <tr
                                v-for="(
                                    semester, index
                                ) in activeSemesters.data"
                                :key="index"
                                class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                            >
                                <td class="px-4 py-3">
                                    <p
                                        class="max-w-xl truncate text-xs font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ labelFor(semester) }}
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    {{ detailFor(semester) || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                    >
                                        <CheckCircle2 class="size-3" />
                                        Active
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-[11px] text-slate-500"
                                >
                                    Academic API
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid content-start gap-4">
                <!-- Announcements Widget -->
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Latest Announcements</h2>
                            <p class="text-[10px] text-slate-500">Stay updated with the latest news.</p>
                        </div>
                        <Megaphone class="h-4 w-4 text-sky-500" />
                    </div>
                    <div class="divide-y divide-slate-50 dark:divide-white/5">
                        <div v-for="ann in announcements" :key="ann.id" class="p-3 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="mt-1">
                                    <Pin v-if="ann.is_pinned" class="h-3 w-3 text-sky-500 fill-sky-500" />
                                    <div v-else class="h-2 w-2 rounded-full mt-1" :style="{ backgroundColor: ann.category.color }"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <Link :href="'/announcements/' + ann.id" class="text-xs font-semibold text-slate-900 dark:text-white hover:underline line-clamp-1">
                                        {{ ann.title }}
                                    </Link>
                                    <p class="text-[10px] text-slate-500 line-clamp-1 mt-0.5">{{ ann.summary || 'View details...' }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] font-medium px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-400">
                                            {{ ann.category.name }}
                                        </span>
                                        <span class="text-[9px] text-slate-400">{{ format(new Date(ann.published_at || ann.created_at), 'MMM d') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="announcements.length === 0" class="p-8 text-center text-[11px] text-slate-400">
                            No announcements yet.
                        </div>
                    </div>
                    <div class="p-2 border-t border-slate-50 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01]">
                        <Link href="/announcements" class="block w-full text-center text-[10px] font-bold text-sky-600 hover:text-sky-700 dark:text-sky-400 uppercase tracking-wider">
                            View All Announcements
                        </Link>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="border-b border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <h2
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Campus Identity
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            SSO-linked organizational context.
                        </p>
                    </div>
                    <dl class="divide-y divide-slate-100 dark:divide-white/10">
                        <div class="grid grid-cols-[120px_1fr] gap-3 px-4 py-3">
                            <dt class="text-xs font-medium text-slate-500">
                                Campus
                            </dt>
                            <dd
                                class="truncate text-right text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                {{ campus.name || 'Not linked' }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-[120px_1fr] gap-3 px-4 py-3">
                            <dt class="text-xs font-medium text-slate-500">
                                Campus ID
                            </dt>
                            <dd
                                class="text-right font-mono text-xs text-slate-900 dark:text-white"
                            >
                                {{ campus.id || '-' }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-[120px_1fr] gap-3 px-4 py-3">
                            <dt class="text-xs font-medium text-slate-500">
                                Tenant ID
                            </dt>
                            <dd
                                class="truncate text-right font-mono text-xs text-slate-900 dark:text-white"
                            >
                                {{ campus.tenant_id || '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="border-b border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <h2
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Integration Health
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Service connectivity summary.
                        </p>
                    </div>
                    <div class="space-y-3 p-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    activeSemesters.error
                                        ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'
                                        : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300'
                                "
                            >
                                <component
                                    :is="
                                        activeSemesters.error
                                            ? AlertTriangle
                                            : CheckCircle2
                                    "
                                    class="size-4"
                                />
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{ integrationState.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                                >
                                    {{ integrationState.message }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="rounded-lg bg-slate-50 p-3 dark:bg-white/[0.03]"
                        >
                            <p
                                class="text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                            >
                                Endpoint
                            </p>
                            <p
                                class="mt-1 truncate font-mono text-[11px] text-slate-600 dark:text-slate-300"
                            >
                                ActiveSemesters / active-only
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.table-head {
    @apply px-4 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}
</style>
