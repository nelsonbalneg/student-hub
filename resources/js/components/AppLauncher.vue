<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    BookOpen,
    Building2,
    CalendarDays,
    ClipboardCheck,
    Clock3,
    Dumbbell,
    FileCheck,
    FileSignature,
    FileText,
    Grid3X3,
    GraduationCap,
    Heart,
    HelpCircle,
    LayoutDashboard,
    Leaf,
    Megaphone,
    Network,
    NotebookTabs,
    Search,
    Settings,
    ShieldCheck,
    Trophy,
    UserRound,
    UsersRound,
    X,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { index as classSchedule } from '@/routes/academic/class-schedule';
import {
    create as createAnnouncement,
    index as announcements,
} from '@/routes/announcements';
import { index as announcementCategories } from '@/routes/announcements/categories';
import {
    accountabilitiesCenter,
    myClearance,
} from '@/routes/clearance';
import { index as clearanceUpdates } from '@/routes/clearance/updates';
import { index as curriculum } from '@/routes/curriculum';
import { studentAcademicRegistration } from '@/routes/enrollment';
import { index as faqs } from '@/routes/faqs';
import { index as grades } from '@/routes/grades';
import { index as internetAccounts } from '@/routes/internet-accounts';
import { myCarbonFootprint } from '@/routes/reporting';
import {
    index as societies,
    mySocieties,
    registration as societyRegistration,
} from '@/routes/societies';
import { index as societyAnnouncements } from '@/routes/societies/announcements';
import { index as societyEvents } from '@/routes/societies/events';
import { index as evaluation } from '@/routes/student/evaluation';
import { index as studentProfile } from '@/routes/student-profile';

type LauncherItem = {
    title: string;
    description: string;
    group: string;
    href: string;
    icon: LucideIcon;
    iconClass: string;
    permission?: string | string[];
    roles?: string[];
};

const RECENT_STORAGE_KEY = 'studenthub_app_launcher_recents';
const MAX_RECENTS = 6;

const page = usePage();
const searchQuery = ref('');
const recentHrefs = ref<string[]>([]);

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const profileUrl = studentProfile().url;

const menuItems: LauncherItem[] = [
    {
        title: 'Dashboard',
        description: 'Overview, announcements, and active term status.',
        group: 'Overview',
        href: dashboard().url,
        icon: LayoutDashboard,
        iconClass: 'bg-sky-500 text-white',
        permission: 'dashboard.view',
    },
    {
        title: 'Grades',
        description: 'View grades, GPA, and faculty evaluation requirements.',
        group: 'Academic',
        href: grades().url,
        icon: GraduationCap,
        iconClass: 'bg-emerald-500 text-white',
        permission: 'grades.view',
    },
    {
        title: 'Curriculum',
        description: 'Browse subjects, units, and prerequisites.',
        group: 'Academic',
        href: curriculum().url,
        icon: NotebookTabs,
        iconClass: 'bg-violet-500 text-white',
        permission: 'curriculum.view',
    },
    {
        title: 'Class Schedule',
        description: 'Open current lecture and laboratory schedules.',
        group: 'Academic',
        href: classSchedule().url,
        icon: CalendarDays,
        iconClass: 'bg-amber-500 text-white',
        permission: 'view class schedule',
        roles: ['Student', 'Super Admin'],
    },
    {
        title: 'Academic Registration',
        description: 'Manage student academic registration.',
        group: 'Enrollment',
        href: studentAcademicRegistration().url,
        icon: FileSignature,
        iconClass: 'bg-indigo-500 text-white',
    },
    ...[
        ['Personal', 'personal', UserRound],
        ['Academic Profile', 'academic', GraduationCap],
        ['Family', 'family', UsersRound],
        ['Education', 'education', BookOpen],
        ['Documents', 'documents', FileText],
        ['Awards', 'achievements', Trophy],
        ['Trainings', 'trainings', CalendarDays],
        ['Socio-Economic', 'socio', Activity],
        ['Physical Fitness Test', 'physical-fitness-test', Dumbbell],
        ['CCD Cares', 'ccd-cares', Heart],
    ].map(
        ([title, hash, icon], index): LauncherItem => ({
            title: String(title),
            description: `Open the ${String(title).toLowerCase()} tab in your student profile.`,
            group: 'Student Profile',
            href: `${profileUrl}#${String(hash)}`,
            icon: icon as LucideIcon,
            iconClass: [
                'bg-cyan-500 text-white',
                'bg-blue-500 text-white',
                'bg-fuchsia-500 text-white',
                'bg-teal-500 text-white',
                'bg-slate-600 text-white',
                'bg-yellow-500 text-white',
                'bg-orange-500 text-white',
                'bg-lime-600 text-white',
                'bg-rose-500 text-white',
                'bg-pink-500 text-white',
            ][index],
            permission: 'student-profile.view',
        }),
    ),
    {
        title: 'Internet Account',
        description: 'Request and manage campus internet access.',
        group: 'Student Services',
        href: internetAccounts().url,
        icon: Network,
        iconClass: 'bg-blue-600 text-white',
        permission: [
            'internet-accounts.view',
            'internet-accounts.manage',
            'internet-accounts.approve',
            'internet-accounts.cancel',
            'internet-accounts.delete',
        ],
    },
    {
        title: 'My Evaluation',
        description: 'Open your available student evaluations.',
        group: 'Student Services',
        href: evaluation().url,
        icon: ClipboardCheck,
        iconClass: 'bg-rose-500 text-white',
        permission: ['evaluation.view', 'evaluation.submit-intent'],
    },
    {
        title: 'Evaluation Management',
        description: 'Review and manage evaluation requests.',
        group: 'Administration',
        href: '/admin/evaluations',
        icon: ClipboardCheck,
        iconClass: 'bg-rose-700 text-white',
        permission: 'evaluation.manage-requests',
    },
    {
        title: 'All Announcements',
        description: 'Browse published campus announcements.',
        group: 'Announcements',
        href: announcements().url,
        icon: Megaphone,
        iconClass: 'bg-orange-500 text-white',
        permission: ['announcement.view', 'announcements.view'],
    },
    {
        title: 'Create Announcement',
        description: 'Publish a new announcement.',
        group: 'Announcements',
        href: createAnnouncement().url,
        icon: Megaphone,
        iconClass: 'bg-orange-600 text-white',
        permission: ['announcement.create', 'announcements.create'],
    },
    {
        title: 'Announcement Categories',
        description: 'Manage announcement categories.',
        group: 'Announcements',
        href: announcementCategories().url,
        icon: NotebookTabs,
        iconClass: 'bg-orange-700 text-white',
        permission: [
            'announcement.manage-categories',
            'announcements.manage-categories',
        ],
    },
    {
        title: 'My Clearance',
        description: 'Track your student clearance application.',
        group: 'Clearance',
        href: myClearance().url,
        icon: FileCheck,
        iconClass: 'bg-emerald-600 text-white',
        permission: 'clearance-application.view',
    },
    {
        title: 'Accountabilities Center',
        description: 'Review clearance accountabilities.',
        group: 'Clearance',
        href: accountabilitiesCenter().url,
        icon: ShieldCheck,
        iconClass: 'bg-emerald-700 text-white',
        permission: 'clearance-update.view',
    },
    {
        title: 'Clearance Updates',
        description: 'View and manage clearance update periods.',
        group: 'Clearance',
        href: clearanceUpdates().url,
        icon: FileCheck,
        iconClass: 'bg-green-700 text-white',
        permission: 'clearance-update.view',
    },
    {
        title: 'Browse Societies',
        description: 'Discover accredited student societies.',
        group: 'Societies',
        href: societies().url,
        icon: UsersRound,
        iconClass: 'bg-fuchsia-500 text-white',
        permission: 'society.view',
    },
    {
        title: 'My Societies',
        description: 'Open societies you joined or manage.',
        group: 'Societies',
        href: mySocieties().url,
        icon: UsersRound,
        iconClass: 'bg-purple-500 text-white',
        permission: 'society.view',
    },
    {
        title: 'Society Registration',
        description: 'Create or update a society registration.',
        group: 'Societies',
        href: societyRegistration().url,
        icon: FileSignature,
        iconClass: 'bg-purple-600 text-white',
        permission: ['society.create', 'society.update'],
    },
    {
        title: 'Society Events',
        description: 'Browse upcoming society events.',
        group: 'Societies',
        href: societyEvents().url,
        icon: CalendarDays,
        iconClass: 'bg-purple-700 text-white',
        permission: 'society.view',
    },
    {
        title: 'Society Announcements',
        description: 'Read announcements from student societies.',
        group: 'Societies',
        href: societyAnnouncements().url,
        icon: Megaphone,
        iconClass: 'bg-fuchsia-700 text-white',
        permission: 'society.view',
    },
    {
        title: 'My Carbon Footprint',
        description: 'Review your personal sustainability activity.',
        group: 'Reporting',
        href: myCarbonFootprint().url,
        icon: Leaf,
        iconClass: 'bg-green-600 text-white',
        permission: 'reporting.carbon_footprint.user_view',
    },
    {
        title: 'Registrar Student Profile',
        description: 'Open registrar student academic records.',
        group: 'Registrar',
        href: '/admin/registrar/report-of-grades',
        icon: GraduationCap,
        iconClass: 'bg-blue-700 text-white',
        permission: [
            'registrar.student-profile.view',
            'registrar.report-of-grades.view',
            'registrar.tag-graduating-student.view',
        ],
    },
    ...[
        ['Society Dashboard', '/admin/societies/dashboard', Building2],
        ['Society Applications', '/admin/societies/applications', FileText],
        ['Accredited Societies', '/admin/societies/accredited', ShieldCheck],
        ['Society Reports', '/admin/societies/reports', BarChart3],
    ].map(
        ([title, href, icon], index): LauncherItem => ({
            title: String(title),
            description: `Open ${String(title).toLowerCase()}.`,
            group: 'Society Administration',
            href: String(href),
            icon: icon as LucideIcon,
            iconClass: 'bg-indigo-700 text-white',
            permission:
                index === 1
                    ? 'society.review_accreditation'
                    : index === 3
                      ? 'society.view_reports'
                      : ['society.review_accreditation', 'society.view_reports'],
        }),
    ),
    ...[
        ['Reporting Overview', '/admin/reporting/overview', Activity, 'reporting.overview.view'],
        ['PFT Results', '/admin/reporting/pft-result', Dumbbell, 'reporting.pft_result.view'],
        ['Audit Logs', '/admin/reporting/audit-logs', FileText, 'reporting.audit_logs.view'],
        ['Carbon Footprint', '/admin/reporting/carbon-footprint', Leaf, 'reporting.carbon_footprint.view'],
        ['System Logs', '/system/logs', FileText, 'system.logs.view'],
    ].map(
        ([title, href, icon, permission]): LauncherItem => ({
            title: String(title),
            description: `Open ${String(title).toLowerCase()}.`,
            group: 'Reporting',
            href: String(href),
            icon: icon as LucideIcon,
            iconClass: 'bg-slate-700 text-white',
            permission: String(permission),
            roles:
                title === 'System Logs'
                    ? ['System Administrator', 'Super Admin']
                    : undefined,
        }),
    ),
    ...[
        ['User Management', '/user-management', UsersRound, 'users.view'],
        ['Roles & Permissions', '/user-management/roles', ShieldCheck, ['roles.view', 'permissions.view']],
        ['Reference Lookups', '/admin/reference-lookups', BookOpen, 'roles.view'],
        ['Site Settings', '/admin/site-settings/campuses', Settings, 'site-settings.view'],
        ['Student Profile Settings', '/admin/site-settings/student-profile', UserRound, ['site-settings.student-profile.view', 'site-settings.view']],
        ['Legal Documents', '/legal', FileText, 'legal.view'],
    ].map(
        ([title, href, icon, permission]): LauncherItem => ({
            title: String(title),
            description: `Open ${String(title).toLowerCase()}.`,
            group: 'Settings',
            href: String(href),
            icon: icon as LucideIcon,
            iconClass: 'bg-slate-600 text-white',
            permission: permission as string | string[],
        }),
    ),
    ...[
        ['FAQ Management', '/faqs/manage/faqs', HelpCircle, 'faq.create'],
        ['FAQ Categories', '/faqs/manage/categories', NotebookTabs, 'faq-category.view'],
        ['FAQ Analytics', '/faqs/manage/analytics', BarChart3, 'faq.analytics.view'],
    ].map(
        ([title, href, icon, permission]): LauncherItem => ({
            title: String(title),
            description: `Open ${String(title).toLowerCase()}.`,
            group: 'FAQ Administration',
            href: String(href),
            icon: icon as LucideIcon,
            iconClass: 'bg-cyan-700 text-white',
            permission: String(permission),
        }),
    ),
    {
        title: 'FAQs',
        description: 'Find answers to frequently asked questions.',
        group: 'Support',
        href: faqs().url,
        icon: HelpCircle,
        iconClass: 'bg-sky-600 text-white',
        permission: 'faq.view',
    },
];

const canAccess = (item: LauncherItem): boolean => {
    if (roles.value.includes('Super Admin')) {
        return true;
    }

    if (item.roles?.some((role) => roles.value.includes(role))) {
        return true;
    }

    if (!item.permission) {
        return true;
    }

    const required = Array.isArray(item.permission)
        ? item.permission
        : [item.permission];

    return required.some((permission) =>
        permissions.value.includes(permission),
    );
};

const visibleItems = computed(() => menuItems.filter(canAccess));

const filteredItems = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (!query) {
        return visibleItems.value;
    }

    return visibleItems.value.filter((item) =>
        [item.title, item.description, item.group]
            .join(' ')
            .toLowerCase()
            .includes(query),
    );
});

const groupedItems = computed(() => {
    const groups = new Map<string, LauncherItem[]>();

    filteredItems.value.forEach((item) => {
        groups.set(item.group, [...(groups.get(item.group) ?? []), item]);
    });

    return Array.from(groups.entries()).map(([title, items]) => ({
        title,
        items,
    }));
});

const recentItems = computed(() =>
    recentHrefs.value
        .map((href) => visibleItems.value.find((item) => item.href === href))
        .filter((item): item is LauncherItem => Boolean(item)),
);

const persistRecents = () => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(
            RECENT_STORAGE_KEY,
            JSON.stringify(recentHrefs.value),
        );
    }
};

const rememberItem = (item: LauncherItem) => {
    recentHrefs.value = [
        item.href,
        ...recentHrefs.value.filter((href) => href !== item.href),
    ].slice(0, MAX_RECENTS);
    persistRecents();
};

const removeRecent = (href: string) => {
    recentHrefs.value = recentHrefs.value.filter((item) => item !== href);
    persistRecents();
};

const clearRecents = () => {
    recentHrefs.value = [];
    persistRecents();
};

onMounted(() => {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(RECENT_STORAGE_KEY) ?? '[]',
        );

        if (Array.isArray(stored)) {
            recentHrefs.value = stored
                .filter((href): href is string => typeof href === 'string')
                .slice(0, MAX_RECENTS);
        }
    } catch {
        recentHrefs.value = [];
    }
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex size-10 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="Open apps"
                title="Apps"
            >
                <Grid3X3 class="size-5" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="10"
            class="max-h-[min(78vh,44rem)] w-[min(26rem,calc(100vw-1rem))] overflow-y-auto rounded-3xl p-3"
        >
            <div class="sticky top-0 z-10 bg-popover px-1 pt-1 pb-3">
                <div class="px-2 pb-3">
                    <p class="text-lg font-bold text-foreground">Menu</p>
                    <p class="text-xs text-muted-foreground">
                        Search all modules and pages available to you.
                    </p>
                </div>

                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search menu"
                        class="h-10 w-full rounded-full border-0 bg-muted pr-9 pl-10 text-sm text-foreground outline-none ring-0 placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/30"
                        @click.stop
                        @keydown.stop
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-2 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground"
                        aria-label="Clear search"
                        @click.stop="searchQuery = ''"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <div
                    v-if="!searchQuery && recentItems.length"
                    class="mt-3 rounded-2xl border border-border/70 bg-background/70 p-2"
                >
                    <div class="flex items-center justify-between px-1 pb-1.5">
                        <div class="flex items-center gap-1.5">
                            <Clock3 class="size-3.5 text-muted-foreground" />
                            <p class="text-xs font-bold text-foreground">
                                Recent
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-[11px] font-semibold text-primary hover:underline"
                            @click.stop="clearRecents"
                        >
                            Clear
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-1">
                        <DropdownMenuItem
                            v-for="item in recentItems"
                            :key="item.href"
                            as-child
                            class="h-auto p-0 focus:bg-transparent"
                        >
                            <div class="group/recent relative min-w-0">
                                <Link
                                    :href="item.href"
                                    class="flex min-w-0 items-center gap-2 rounded-xl px-2 py-2 pr-7 hover:bg-muted"
                                    @click="rememberItem(item)"
                                >
                                    <span
                                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
                                        :class="item.iconClass"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="size-3.5"
                                        />
                                    </span>
                                    <span
                                        class="truncate text-[11px] font-semibold"
                                    >
                                        {{ item.title }}
                                    </span>
                                </Link>
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-1 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground opacity-70 hover:bg-background hover:text-foreground group-hover/recent:opacity-100"
                                    aria-label="Remove recent item"
                                    @click.stop.prevent="
                                        removeRecent(item.href)
                                    "
                                >
                                    <X class="size-3" />
                                </button>
                            </div>
                        </DropdownMenuItem>
                    </div>
                </div>
            </div>

            <div
                v-if="groupedItems.length"
                class="space-y-4 px-1 pb-1"
            >
                <section
                    v-for="group in groupedItems"
                    :key="group.title"
                    class="space-y-1"
                >
                    <h3
                        class="px-2 text-[11px] font-bold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.title }}
                    </h3>
                    <DropdownMenuItem
                        v-for="item in group.items"
                        :key="item.href"
                        as-child
                        class="h-auto p-0 focus:bg-transparent"
                    >
                        <Link
                            :href="item.href"
                            class="group flex items-start gap-3 rounded-2xl px-2 py-2.5 transition hover:bg-muted focus:bg-muted"
                            @click="rememberItem(item)"
                        >
                            <span
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md"
                                :class="item.iconClass"
                            >
                                <component :is="item.icon" class="size-4.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-semibold text-foreground"
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    class="mt-0.5 block text-[11px] leading-4 text-muted-foreground"
                                >
                                    {{ item.description }}
                                </span>
                            </span>
                        </Link>
                    </DropdownMenuItem>
                </section>
            </div>

            <div
                v-else
                class="grid min-h-32 place-items-center px-4 text-center"
            >
                <div>
                    <Search
                        class="mx-auto size-7 text-muted-foreground/50"
                    />
                    <p class="mt-2 text-sm font-semibold text-foreground">
                        No menu results
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Try a different module or page name.
                    </p>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
