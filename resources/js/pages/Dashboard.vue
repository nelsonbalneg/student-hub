<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { edit as editProfileRoute } from '@/routes/profile';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    ChevronDown,
    CheckCircle2,
    Database,
    Megaphone,
    Pin,
    Server,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { format } from 'date-fns';

type Campus = {
    id: number | null;
    name: string | null;
    tenant_id: string | null;
};

type ActiveSemester = Record<string, unknown>;

type DashboardAnnouncement = {
    id: number;
    title: string;
    summary?: string | null;
    content?: string | null;
    priority: 'low' | 'normal' | 'high' | 'urgent' | string;
    is_pinned?: boolean;
    published_at?: string | null;
    created_at?: string | null;
    category: {
        name: string;
        color?: string | null;
    };
};

const props = defineProps<{
    campus: Campus;
    activeSemesters: {
        data: ActiveSemester[];
        error: string | null;
    };
    announcements: DashboardAnnouncement[];
    studentProfile?: {
        studentPicture?: string | null;
        firstName?: string | null;
        middlename?: string | null;
        lastName?: string | null;
    };
}>();

const showAnnouncements = ref(false);
const selectedAnnouncement = ref<DashboardAnnouncement | null>(null);

const openAnnouncement = (announcement: DashboardAnnouncement) => {
    selectedAnnouncement.value = announcement;
};

const closeAnnouncement = () => {
    selectedAnnouncement.value = null;
};

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

const page = usePage();
const currentUser = computed(
    () =>
        (
            page.props.auth as {
                user?: { name?: string; avatar?: string | null };
            }
        ).user ?? {},
);
const profileFullName = computed(() => {
    const parts = [
        props.studentProfile?.firstName,
        props.studentProfile?.middlename,
        props.studentProfile?.lastName,
    ]
        .map((v) => String(v ?? '').trim())
        .filter((v) => v.length > 0);
    return parts.join(' ').trim();
});
const currentUserName = computed(
    () => profileFullName.value || currentUser.value?.name || 'Student',
);
const currentUserAvatar = computed(() => {
    const fromProfile = String(
        props.studentProfile?.studentPicture ?? '',
    ).trim();
    const raw = fromProfile;
    if (!raw) return null;
    if (raw.startsWith('data:image')) return raw;
    if (/^https?:\/\//i.test(raw)) return raw;
    return `data:image/jpeg;base64,${raw}`;
});

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const can = (permission?: string | string[]): boolean => {
    if (!permission) {
        return true;
    }

    const requiredPermissions = Array.isArray(permission)
        ? permission
        : [permission];

    return (
        roles.value.includes('Super Admin') ||
        requiredPermissions.some((requiredPermission) =>
            permissions.value.includes(requiredPermission),
        )
    );
};

const statusCards = computed(() => {
    const cards = [
        {
            label: 'Active Terms',
            value: activeTermCount.value,
            detail:
                activeTermCount.value === 1 ? 'Current semester' : 'Semesters',
            icon: CalendarDays,
            tone: 'blue',
            permission: null,
        },
        {
            label: 'Campus Link',
            value: campusLinked.value ? 'Linked' : 'Missing',
            detail: props.campus.name || 'SSO campus context',
            icon: Building2,
            tone: campusLinked.value ? 'emerald' : 'amber',
            permission: 'dashboard.view-campus-identity',
        },
        {
            label: 'Tenant',
            value: props.campus.tenant_id || '-',
            detail: 'Identity scope',
            icon: Database,
            tone: 'slate',
            permission: 'dashboard.view-campus-identity',
        },
        {
            label: 'Academic API',
            value: integrationState.value.label,
            detail: 'Active semester feed',
            icon: Server,
            tone: integrationState.value.tone,
            permission: 'dashboard.view-integration-health',
        },
    ];

    return cards.filter((card) => can(card.permission));
});

const priorityClass = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
        case 'high':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300';
        case 'low':
            return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default:
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }
};

const postedDate = (announcement: DashboardAnnouncement) => {
    const date = announcement.published_at || announcement.created_at;

    if (!date) {
        return '-';
    }

    return format(new Date(date), 'MMM d, yyyy');
};

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
                <div class="flex items-center gap-3">
                    <div
                        class="inline-flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900"
                    >
                        <img
                            v-if="currentUserAvatar"
                            :src="currentUserAvatar"
                            :alt="currentUserName"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            {{ currentUserName.charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <div>
                        <h1
                            class="text-lg font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            Dashboard
                        </h1>
                        <p
                            class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                        >
                            {{ currentUserName }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Academic operations and SSO context overview.
                        </p>
                    </div>
                </div>
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
                    <div>
                        <template v-if="activeSemesters.error.includes('campus') || activeSemesters.error.includes('tenant') || !campus.id || !campus.tenant_id">
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                                Account Setup Incomplete
                            </p>
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                Your account is missing required information (Campus or Tenant assignment).
                            </p>
                            <p class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">
                                Please update your Campus in your profile. If you cannot update it, contact your Registrar or System Administrator to complete your account setup.
                            </p>
                            <div class="mt-3 text-xs font-semibold">
                                <Link
                                    :href="editProfileRoute.url()"
                                    class="inline-flex items-center text-amber-950 underline hover:text-amber-900 dark:text-amber-200 dark:hover:text-white"
                                >
                                    → Go to <strong class="font-bold">Profile</strong> to update your Campus.
                                </Link>
                            </div>
                        </template>
                        <template v-else>
                            <span>{{ activeSemesters.error }}</span>
                        </template>
                    </div>
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
                <div
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/10"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <Megaphone class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <h2
                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                >
                                    Announcements
                                </h2>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Quick updates posted for students.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-8 shrink-0 items-center justify-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 text-[11px] font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                            :aria-expanded="showAnnouncements"
                            @click="showAnnouncements = !showAnnouncements"
                        >
                            {{
                                showAnnouncements
                                    ? 'Hide Announcements'
                                    : 'Show Announcements'
                            }}
                            <ChevronDown
                                class="size-3.5 transition-transform duration-200"
                                :class="{ 'rotate-180': showAnnouncements }"
                            />
                        </button>
                    </div>

                    <Transition name="announcement-panel">
                        <div
                            v-if="showAnnouncements"
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <div
                                v-if="announcements.length === 0"
                                class="grid place-items-center px-4 py-10 text-center"
                            >
                                <div>
                                    <Megaphone
                                        class="mx-auto size-8 text-slate-300 dark:text-slate-700"
                                    />
                                    <p
                                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No active announcements
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        New student announcements will appear
                                        here.
                                    </p>
                                </div>
                            </div>

                            <article
                                v-for="ann in announcements"
                                v-else
                                :key="ann.id"
                                class="p-0"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 p-3 text-left transition-colors hover:bg-emerald-50/40 focus:bg-emerald-50/60 focus:outline-none dark:hover:bg-emerald-500/5 dark:focus:bg-emerald-500/10"
                                    @click="openAnnouncement(ann)"
                                >
                                    <span
                                        class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
                                        :class="
                                            ann.is_pinned
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-300'
                                        "
                                    >
                                        <Pin
                                            v-if="ann.is_pinned"
                                            class="size-3.5 fill-current"
                                        />
                                        <Megaphone v-else class="size-3.5" />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h3
                                                class="min-w-0 flex-1 text-sm font-bold text-slate-950 dark:text-white"
                                            >
                                                {{ ann.title }}
                                            </h3>
                                            <span
                                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                :class="
                                                    priorityClass(ann.priority)
                                                "
                                            >
                                                {{ ann.priority }}
                                            </span>
                                        </div>

                                        <div
                                            class="mt-1 flex flex-wrap items-center gap-2 text-[11px]"
                                        >
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 font-semibold"
                                                :style="{
                                                    backgroundColor:
                                                        (ann.category.color ||
                                                            '#059669') + '18',
                                                    color:
                                                        ann.category.color ||
                                                        '#047857',
                                                }"
                                            >
                                                {{ ann.category.name }}
                                            </span>
                                            <span
                                                class="font-medium text-slate-400"
                                            >
                                                {{ postedDate(ann) }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                ann.summary ||
                                                'No announcement summary provided.'
                                            }}
                                        </p>
                                    </div>
                                </button>
                            </article>
                        </div>
                    </Transition>
                </div>

                <div
                    v-if="can('dashboard.view-campus-identity')"
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
                    v-if="can('dashboard.view-integration-health')"
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

        <Transition name="drawer-fade">
            <div
                v-if="selectedAnnouncement"
                class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[2px]"
                @click="closeAnnouncement"
            />
        </Transition>

        <Transition name="drawer-slide">
            <aside
                v-if="selectedAnnouncement"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
                role="dialog"
                aria-modal="true"
                aria-labelledby="announcement-drawer-title"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-white/10"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :style="{
                                    backgroundColor:
                                        (selectedAnnouncement.category.color ||
                                            '#059669') + '18',
                                    color:
                                        selectedAnnouncement.category.color ||
                                        '#047857',
                                }"
                            >
                                {{ selectedAnnouncement.category.name }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                :class="
                                    priorityClass(selectedAnnouncement.priority)
                                "
                            >
                                {{ selectedAnnouncement.priority }}
                            </span>
                            <span
                                v-if="selectedAnnouncement.is_pinned"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <Pin class="size-3 fill-current" />
                                Pinned
                            </span>
                        </div>
                        <h2
                            id="announcement-drawer-title"
                            class="mt-3 text-lg leading-snug font-bold text-slate-950 dark:text-white"
                        >
                            {{ selectedAnnouncement.title }}
                        </h2>
                        <p
                            class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Posted {{ postedDate(selectedAnnouncement) }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                        aria-label="Close announcement details"
                        @click="closeAnnouncement"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <div
                        v-if="selectedAnnouncement.summary"
                        class="mb-5 rounded-lg border border-emerald-100 bg-emerald-50/70 p-4 text-sm leading-6 font-semibold text-slate-700 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-slate-200"
                    >
                        {{ selectedAnnouncement.summary }}
                    </div>

                    <article
                        v-if="selectedAnnouncement.content"
                        class="announcement-content max-w-none"
                        v-html="selectedAnnouncement.content"
                    />

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center dark:border-white/10 dark:bg-white/5"
                    >
                        <Megaphone
                            class="mx-auto size-7 text-slate-300 dark:text-slate-700"
                        />
                        <p
                            class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                        >
                            No additional details
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            This announcement only has a summary.
                        </p>
                    </div>
                </div>
            </aside>
        </Transition>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.table-head {
    @apply px-4 py-2 text-left text-[10px] font-bold tracking-wide text-slate-500 uppercase;
}

.announcement-content {
    @apply text-xs leading-6 text-slate-700 dark:text-slate-200;
}

.announcement-content :deep(*) {
    color: #334155 !important;
    font-size: 0.75rem !important;
    line-height: 1.5rem !important;
}

.dark .announcement-content :deep(*) {
    color: #e2e8f0 !important;
}

.announcement-content :deep(p) {
    @apply my-2 text-xs leading-6;
}

.announcement-content :deep(ol),
.announcement-content :deep(ul) {
    @apply my-2 space-y-1 pl-5 text-xs leading-6;
}

.announcement-content :deep(ol) {
    @apply list-decimal;
}

.announcement-content :deep(ul) {
    @apply list-disc;
}

.announcement-content :deep(li) {
    @apply pl-1 text-xs leading-6;
}

.announcement-content :deep(strong),
.announcement-content :deep(b) {
    @apply font-bold;
    color: #0f172a !important;
}

.dark .announcement-content :deep(strong),
.dark .announcement-content :deep(b) {
    color: #ffffff !important;
}

.announcement-content :deep(a) {
    @apply font-semibold underline-offset-2 hover:underline;
    color: #047857 !important;
}

.dark .announcement-content :deep(a) {
    color: #6ee7b7 !important;
}

.announcement-content :deep(h1),
.announcement-content :deep(h2),
.announcement-content :deep(h3) {
    @apply mt-4 mb-2 font-bold;
    color: #0f172a !important;
    font-size: 0.875rem !important;
}

.dark .announcement-content :deep(h1),
.dark .announcement-content :deep(h2),
.dark .announcement-content :deep(h3) {
    color: #ffffff !important;
}

.announcement-panel-enter-active,
.announcement-panel-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.announcement-panel-enter-from,
.announcement-panel-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
    transition: opacity 180ms ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
    opacity: 0;
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.drawer-slide-enter-from,
.drawer-slide-leave-to {
    opacity: 0;
    transform: translateX(24px);
}
</style>
