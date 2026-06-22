<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    FileText,
    ArrowRight,
    X,
    Clock3,
    Plus,
    ClipboardCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    apply as applyForClearance,
    show as showClearance,
} from '@/routes/clearance';

const props = defineProps<{
    activeSemester: any;
    activeUpdates: any[];
    myClearances: any[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student Services', href: '#' },
            {
                title: 'My Clearance',
                href: '/student-services/clearance/my-clearance',
            },
        ],
    },
});

const applyModalOpen = ref(false);
const applyingUpdateId = ref<number | null>(null);

const applicationForUpdate = (updateId: number) =>
    props.myClearances.find(
        (clearance) => clearance.clearance_update.id === updateId,
    );

const availableApplicationCount = computed(
    () =>
        props.activeUpdates.filter((update) => !applicationForUpdate(update.id))
            .length,
);
const availableUpdates = computed(() =>
    props.activeUpdates.filter((update) => !applicationForUpdate(update.id)),
);

const apply = (updateId: number) => {
    router.post(
        applyForClearance.url(updateId),
        {},
        {
            preserveScroll: true,
            onStart: () => (applyingUpdateId.value = updateId),
            onFinish: () => (applyingUpdateId.value = null),
        },
    );
};

const expandedUpdates = ref<number[]>([]);

const toggleOffices = (id: number) => {
    if (expandedUpdates.value.includes(id)) {
        expandedUpdates.value = expandedUpdates.value.filter((i) => i !== id);
    } else {
        expandedUpdates.value.push(id);
    }
};

const statusColor = (s: string) => {
    switch (s) {
        case 'cleared':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'not_cleared':
            return 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400';
        case 'with_accountability':
            return 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400';
        case 'pending_review':
            return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400';
        case 'completed':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        default:
            return 'text-slate-600 bg-slate-50 dark:bg-white/5 dark:text-slate-400';
    }
};

const getOfficeStatus = (clearance: any, officeId: number) => {
    const updateOffice = clearance?.clearance_update?.offices?.find(
        (office: any) => office.office?.id === officeId,
    );

    if (!updateOffice?.finalized_at) {
        return { cleared: false, processing: true, accountabilities: [] };
    }

    let accs = clearance.clearance_update?.accountabilities;

    if (accs && !Array.isArray(accs) && accs.data) {
        accs = accs.data;
    }

    if (!accs || !Array.isArray(accs)) {
        return { cleared: true, processing: false };
    }

    const accountabilities = accs.filter(
        (acc: any) => acc.office?.id === officeId && acc.status === 'pending',
    );

    if (accountabilities.length === 0) {
        return { cleared: true, processing: false };
    }

    return { cleared: false, processing: false, accountabilities };
};

const formatDate = (date: string) => {
    if (!date) {
        return '';
    }

    return new Date(date).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const statusLabel = (status: string) => {
    if (status === 'pending_review') {
        return 'Processing';
    }

    if (status === 'with_accountability') {
        return 'With deficiencies';
    }

    return status.replaceAll('_', ' ');
};
</script>

<template>
    <Head title="My Clearance" />

    <div
        class="flex h-full flex-1 flex-col gap-6 bg-slate-50/30 p-6 dark:bg-slate-950"
    >
        <!-- Compact Enterprise Header -->
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div class="grid gap-0.5">
                <h1
                    class="text-base font-semibold tracking-wide text-slate-900 dark:text-slate-100"
                >
                    Clearance Portal
                </h1>
                <p
                    class="text-[10px] font-medium tracking-normal text-slate-400 dark:text-slate-500"
                >
                    Academic Year & Term Monitor
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div
                    v-if="activeSemester"
                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <Calendar class="h-4 w-4" />
                    </div>
                    <div class="grid gap-0">
                        <span
                            class="text-[8px] leading-none font-medium tracking-wider text-slate-400 uppercase dark:text-slate-500"
                        >
                            Active Session
                        </span>
                        <span
                            class="text-[11px] font-medium tracking-normal text-slate-700 dark:text-slate-200"
                        >
                            {{ activeSemester.academic_year }} •
                            {{ activeSemester.term }}
                        </span>
                    </div>
                </div>
                <Button
                    v-if="activeSemester"
                    class="h-11 gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-medium text-white shadow-sm hover:bg-emerald-700"
                    @click="applyModalOpen = true"
                >
                    <Plus class="size-4" />
                    Apply Clearance
                    <span
                        v-if="availableApplicationCount > 0"
                        class="rounded-full bg-white/20 px-1.5 py-0.5 text-[9px]"
                    >
                        {{ availableApplicationCount }}
                    </span>
                </Button>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <h2
                    class="text-sm font-semibold text-slate-900 dark:text-white"
                >
                    My Applications
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Track your submitted clearance applications and office
                    verification progress.
                </p>
            </div>

            <div
                class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-white/10 dark:bg-slate-900"
            >
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="border-b border-slate-100 bg-slate-50/50 text-[9px] font-medium tracking-wider text-slate-400 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-500"
                        >
                            <th class="px-6 py-3">Clearance</th>
                            <th class="px-6 py-3">Session & Reference</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-50 dark:divide-white/10"
                    >
                        <tr v-if="myClearances.length === 0">
                            <td colspan="4" class="px-6 py-14 text-center">
                                <div
                                    class="mx-auto flex max-w-md flex-col items-center gap-3"
                                >
                                    <div
                                        class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"
                                    >
                                        <ClipboardCheck class="size-5" />
                                    </div>
                                    <div class="grid gap-1">
                                        <p
                                            class="text-sm font-medium text-slate-800 dark:text-slate-100"
                                        >
                                            No clearance applications yet
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Select Apply Clearance to view
                                            available clearance periods.
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <template
                            v-for="clearance in myClearances"
                            :key="clearance.id"
                        >
                            <tr
                                class="group transition-colors hover:bg-slate-50/30 dark:hover:bg-white/5"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 shadow-sm transition-all group-hover:bg-emerald-600 group-hover:text-white dark:bg-emerald-500/10 dark:text-emerald-300"
                                        >
                                            <FileText class="h-4 w-4" />
                                        </div>
                                        <div class="grid gap-0">
                                            <p
                                                class="text-xs font-medium tracking-normal text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    clearance.clearance_update
                                                        .title
                                                }}
                                            </p>
                                            <p
                                                class="text-[9px] text-slate-500"
                                            >
                                                {{
                                                    clearance.clearance_update
                                                        .type?.name
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p
                                        class="text-[10px] font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ clearance.semester.academic_year }} •
                                        {{ clearance.semester.term }}
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono text-[9px] text-slate-400"
                                    >
                                        {{ clearance.reference_no }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full border px-2.5 py-1 text-[9px] font-medium capitalize',
                                            statusColor(clearance.status),
                                        ]"
                                    >
                                        {{ statusLabel(clearance.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="flex items-center justify-end gap-3"
                                    >
                                        <button
                                            class="text-[9px] font-medium text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-300"
                                            @click="toggleOffices(clearance.id)"
                                        >
                                            {{
                                                expandedUpdates.includes(
                                                    clearance.id,
                                                )
                                                    ? 'Hide offices'
                                                    : 'View offices'
                                            }}
                                        </button>
                                        <Link
                                            :href="
                                                showClearance.url(clearance.id)
                                            "
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-[10px] font-medium text-white hover:bg-emerald-700"
                                        >
                                            View details
                                            <ArrowRight class="size-3" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expandedUpdates.includes(clearance.id)">
                                <td
                                    colspan="4"
                                    class="bg-slate-50/50 px-6 py-0 dark:bg-white/[0.03]"
                                >
                                    <div
                                        class="grid animate-in grid-cols-4 gap-2 border-t border-slate-100 py-4 duration-300 fade-in slide-in-from-top-2 lg:grid-cols-6 dark:border-white/10"
                                    >
                                        <div
                                            v-for="off in clearance
                                                .clearance_update.offices"
                                            :key="off.id"
                                            class="group/node flex items-center justify-between rounded-xl border border-slate-200 bg-white p-2 transition-all hover:border-emerald-300 hover:shadow-md dark:border-white/10 dark:bg-slate-950 dark:hover:border-emerald-400/50"
                                        >
                                            <span
                                                class="truncate text-[9px] font-medium tracking-normal text-slate-500 dark:text-slate-300"
                                                >{{ off.office.name }}</span
                                            >
                                            <div
                                                v-if="
                                                    getOfficeStatus(
                                                        clearance,
                                                        off.office.id,
                                                    ).cleared
                                                "
                                            >
                                                <CheckCircle2
                                                    class="h-3 w-3 text-emerald-500"
                                                />
                                            </div>
                                            <Clock3
                                                v-else-if="
                                                    getOfficeStatus(
                                                        clearance,
                                                        off.office.id,
                                                    ).processing
                                                "
                                                class="h-3 w-3 text-blue-500"
                                            />
                                            <div
                                                v-else
                                                class="group relative flex items-center"
                                            >
                                                <X
                                                    class="h-3 w-3 text-red-500"
                                                />
                                                <div
                                                    class="invisible absolute top-6 right-0 z-50 w-48 rounded-xl border border-white/10 bg-slate-900 p-3 text-[9px] text-white shadow-2xl group-hover:visible"
                                                >
                                                    <p
                                                        class="mb-2 border-b border-white/5 pb-2 font-medium tracking-wide text-red-400"
                                                    >
                                                        Tagged Accountability:
                                                    </p>
                                                    <ul class="space-y-1.5">
                                                        <li
                                                            v-for="acc in getOfficeStatus(
                                                                clearance,
                                                                off.office.id,
                                                            ).accountabilities"
                                                            :key="acc.id"
                                                            class="flex items-start gap-1.5 leading-tight"
                                                        >
                                                            <span
                                                                class="text-red-500"
                                                                >•</span
                                                            >
                                                            {{ acc.title }}
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="grid gap-4 md:hidden">
                <div
                    v-if="myClearances.length === 0"
                    class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-12 text-center shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div
                        class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <ClipboardCheck class="size-5" />
                    </div>
                    <div class="grid gap-1">
                        <p
                            class="text-sm font-medium text-slate-800 dark:text-slate-100"
                        >
                            No clearance applications yet
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Tap Apply Clearance to begin an application.
                        </p>
                    </div>
                </div>
                <article
                    v-for="clearance in myClearances"
                    :key="clearance.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900"
                >
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div class="grid gap-0">
                            <h3
                                class="text-sm font-semibold tracking-normal text-slate-900 dark:text-slate-100"
                            >
                                {{ clearance.clearance_update.title }}
                            </h3>
                            <p
                                class="mt-0.5 font-mono text-[9px] text-slate-400"
                            >
                                {{ clearance.reference_no }}
                            </p>
                        </div>
                        <span
                            :class="[
                                'rounded-full border px-2 py-0.5 text-[8px] font-medium capitalize',
                                statusColor(clearance.status),
                            ]"
                        >
                            {{ statusLabel(clearance.status) }}
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div
                            class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-[10px] dark:bg-white/[0.03]"
                        >
                            <div>
                                <p class="text-slate-400">Session</p>
                                <p
                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                >
                                    {{ clearance.semester.academic_year }} •
                                    {{ clearance.semester.term }}
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-400">Applied</p>
                                <p
                                    class="mt-0.5 text-slate-700 dark:text-slate-200"
                                >
                                    {{ formatDate(clearance.applied_at) }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-slate-50 pt-4 dark:border-white/10"
                        >
                            <button
                                class="text-[9px] font-medium tracking-wide text-slate-400 hover:text-emerald-600 dark:text-slate-500 dark:hover:text-emerald-300"
                                @click="toggleOffices(clearance.id)"
                            >
                                {{
                                    expandedUpdates.includes(clearance.id)
                                        ? 'Hide Nodes'
                                        : 'Review Nodes'
                                }}
                            </button>
                            <Link
                                :href="showClearance.url(clearance.id)"
                                class="text-[10px] font-medium tracking-wide text-emerald-600 dark:text-emerald-300"
                            >
                                View Application
                            </Link>
                        </div>

                        <div
                            v-if="expandedUpdates.includes(clearance.id)"
                            class="grid animate-in grid-cols-2 gap-2 pt-2 fade-in slide-in-from-top-1"
                        >
                            <div
                                v-for="off in clearance.clearance_update
                                    .offices"
                                :key="off.id"
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2 dark:border-white/10 dark:bg-white/5"
                            >
                                <span
                                    class="truncate text-[9px] font-medium tracking-normal text-slate-600 dark:text-slate-300"
                                    >{{ off.office.name }}</span
                                >
                                <CheckCircle2
                                    v-if="
                                        getOfficeStatus(
                                            clearance,
                                            off.office.id,
                                        ).cleared
                                    "
                                    class="h-3 w-3 text-emerald-500"
                                />
                                <Clock3
                                    v-else-if="
                                        getOfficeStatus(
                                            clearance,
                                            off.office.id,
                                        ).processing
                                    "
                                    class="h-3 w-3 text-blue-500"
                                />
                                <X v-else class="h-3 w-3 text-red-500" />
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <Sheet v-model:open="applyModalOpen">
        <SheetContent
            side="right"
            class="flex h-full w-full flex-col gap-0 overflow-hidden border-slate-200 p-0 sm:max-w-xl dark:border-white/10"
        >
            <SheetHeader
                class="border-b border-slate-100 bg-emerald-50/60 py-4 pr-14 pl-5 text-left dark:border-white/10 dark:bg-emerald-500/5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        <ClipboardCheck class="size-5" />
                    </div>
                    <div>
                        <SheetTitle
                            class="text-base font-semibold text-slate-900 dark:text-white"
                        >
                            Apply for Clearance
                        </SheetTitle>
                        <SheetDescription class="mt-0.5 text-xs">
                            Select an available clearance for
                            {{ activeSemester?.academic_year }} •
                            {{ activeSemester?.term }}.
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto p-4">
                <div
                    v-if="availableUpdates.length === 0"
                    class="flex flex-col items-center gap-3 py-12 text-center"
                >
                    <FileText class="size-10 text-slate-300" />
                    <div>
                        <p
                            class="text-sm font-medium text-slate-800 dark:text-slate-200"
                        >
                            No new clearances available
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            You have already applied to all open clearance
                            periods for the active session.
                        </p>
                    </div>
                </div>

                <div v-else class="grid gap-3">
                    <article
                        v-for="update in availableUpdates"
                        :key="update.id"
                        class="rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-emerald-50/30 dark:border-white/10 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/5"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3
                                        class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ update.title }}
                                    </h3>
                                </div>
                                <p
                                    class="mt-1 line-clamp-2 text-xs text-slate-500"
                                >
                                    {{
                                        update.description ||
                                        'General semestral clearance'
                                    }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-400"
                                >
                                    <span>
                                        {{
                                            update.reference_code ||
                                            'Reference pending'
                                        }}
                                    </span>
                                    <span>
                                        Apply until
                                        {{ formatDate(update.end_date) }}
                                    </span>
                                </div>
                            </div>

                            <Button
                                class="h-9 shrink-0 gap-1.5 rounded-lg bg-emerald-600 px-4 text-xs font-medium text-white hover:bg-emerald-700"
                                :disabled="applyingUpdateId === update.id"
                                @click="apply(update.id)"
                            >
                                {{
                                    applyingUpdateId === update.id
                                        ? 'Applying...'
                                        : 'Apply'
                                }}
                                <ArrowRight
                                    v-if="applyingUpdateId !== update.id"
                                    class="size-3.5"
                                />
                            </Button>
                        </div>
                    </article>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
