<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    BookOpen,
    FileSignature,
    ClipboardCheck,
    Wifi,
    GraduationCap,
    HelpCircle,
    LayoutGrid,
    Megaphone,
    MessageSquareQuote,
    Settings,
    ShieldCheck,
    Sparkles,
    User,
    Users,
    FileCheck,
    FileText,
    Building2,
    Leaf,
    Dumbbell,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as legalIndex } from '@/routes/legal';
import type { NavItem } from '@/types';

const page = usePage();

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const hasRole = (role?: string | string[]): boolean => {
    if (!role) {
        return false;
    }

    const requiredRoles = Array.isArray(role) ? role : [role];

    return requiredRoles.some((requiredRole) =>
        roles.value.includes(requiredRole),
    );
};

const can = (
    permission?: string | string[],
    role?: string | string[],
): boolean => {
    if (!permission && !role) {
        return true;
    }

    const requiredPermissions = Array.isArray(permission)
        ? permission
        : permission
            ? [permission]
            : [];

    return (
        roles.value.includes('Super Admin') ||
        hasRole(role) ||
        requiredPermissions.some((requiredPermission) =>
            permissions.value.includes(requiredPermission),
        )
    );
};

// Feature status helpers — reads from cached systemFeatures prop shared by HandleInertiaRequests
const systemFeatures = computed(() => (page.props.systemFeatures as Record<string, string>) || {});
const featureStatus = (routeName?: string) => routeName ? (systemFeatures.value[routeName] ?? 'active') : 'active';
const featureVisible = (routeName?: string) => featureStatus(routeName) !== 'disabled';
const featureMaintenance = (routeName?: string) => featureStatus(routeName) === 'maintenance';

const visibleItems = (items: NavItem[]): NavItem[] =>
    items
        .map((item) => ({
            ...item,
            badge: item.routeName && featureMaintenance(item.routeName) ? 'Maintenance' : item.badge,
            items: item.items ? visibleItems(item.items) : undefined,
        }))
        .filter(
            (item) =>
                can(item.permission, item.roles) &&
                (!item.routeName || featureVisible(item.routeName)) &&
                (!item.items || item.items.length > 0),
        );

const firstVisibleHref = (items: NavItem[]): NavItem['href'] | string => {
    for (const item of items) {
        if (item.items?.length) {
            return firstVisibleHref(item.items);
        }

        return item.href;
    }

    return '#';
};

const overviewNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        permission: 'dashboard.view',
        routeName: 'dashboard',
    },
    {
        title: 'Academic',
        href: '/grades',
        icon: GraduationCap,
        items: [
            {
                title: 'Grades',
                href: '/grades',
                // icon: Archive,
                permission: 'grades.view',
                routeName: 'grades.index',
            },
            {
                title: 'Curriculum',
                href: '/curriculum',
                // icon: BookOpen,
                permission: 'curriculum.view',
                routeName: 'curriculum.index',
            },
            {
                title: 'Class Schedule',
                href: '/academic/class-schedule',
                // icon: CalendarDays,
                permission: 'view class schedule',
                roles: ['Student', 'Super Admin'],
                routeName: 'academic.class-schedule.index',
            },
        ],
    },
    {
        title: 'Enrollment',
        href: '/student-academic-registration',
        icon: FileSignature,
        items: [
            {
                title: 'Student Academic Registration',
                href: '/student-academic-registration',
                routeName: 'enrollment.student-academic-registration',
            },
        ],
    },
    {
        title: 'Profile',
        href: '/student-profile',
        icon: User,
        permission: 'student-profile.view',
        routeName: 'student-profile.index',
    },
    {
        title: 'Internet Account',
        href: '/internet-accounts',
        icon: Wifi,
        permission: [
            'internet-accounts.view',
            'internet-accounts.manage',
            'internet-accounts.approve',
            'internet-accounts.cancel',
            'internet-accounts.delete',
        ],
        routeName: 'internet-accounts.index',
    },
    {
        title: 'Evaluation',
        href: '/student/evaluation',
        icon: ClipboardCheck,
        permission: 'evaluation.view',
        items: [
            {
                title: 'My Evaluation',
                href: '/student/evaluation',
                permission: ['evaluation.view', 'evaluation.submit-intent'],
                routeName: 'student.evaluation.index',
            },
            {
                title: 'Management',
                href: '/admin/evaluations',
                permission: 'evaluation.manage-requests',
                routeName: 'admin.evaluations.index',
            },
        ],
    },
    {
        title: 'Announcement',
        href: '/announcements',
        icon: Megaphone,
        permission: ['announcement.view', 'announcements.view'],
        items: [
            {
                title: 'All Announcements',
                href: '/announcements',
                permission: ['announcement.view', 'announcements.view'],
                routeName: 'announcements.index',
            },
            {
                title: 'Create Announcement',
                href: '/announcements/create',
                permission: ['announcement.create', 'announcements.create'],
                routeName: 'announcements.create',
            },
            {
                title: 'Categories',
                href: '/announcements/categories',
                permission: [
                    'announcement.manage-categories',
                    'announcements.manage-categories',
                ],
                routeName: 'announcements.categories.index',
            },
        ],
    },
    {
        title: 'Clearance',
        href: '/student-services/clearance/my-clearance',
        icon: FileCheck,
        permission: ['clearance-application.view', 'clearance-update.view'],
        items: [
            {
                title: 'My Clearance',
                href: '/student-services/clearance/my-clearance',
                permission: 'clearance-application.view',
                routeName: 'clearance.my-clearance',
            },
            {
                title: 'Accountabilities Center',
                href: '/student-services/clearance/accountabilities-center',
                permission: 'clearance-update.view',
                routeName: 'clearance.accountabilities-center',
            },
            {
                title: 'Manage Clearance',
                href: '/student-services/clearance/updates',
                permission: 'clearance-update.view',
                routeName: 'clearance.updates.index',
            },
        ],
    },
    {
        title: 'Societies',
        href: '/societies',
        icon: Users,
        permission: 'society.view',
        items: [
            {
                title: 'Browse Societies',
                href: '/societies',
                permission: 'society.view',
                routeName: 'societies.index',
            },
            {
                title: 'My Societies',
                href: '/societies/my-societies',
                permission: 'society.view',
                routeName: 'societies.my-societies',
            },
            {
                title: 'Registration',
                href: '/societies/registration',
                permission: ['society.create', 'society.update'],
                routeName: 'societies.registration',
            },
            {
                title: 'Society Events',
                href: '/societies/events',
                permission: 'society.view',
                routeName: 'societies.events.index',
            },
            {
                title: 'Announcements',
                href: '/societies/announcements',
                permission: 'society.view',
                routeName: 'societies.announcements.index',
            },
        ],
    },
    {
        title: 'My Carbon Footprint',
        href: '/my-carbon-footprint',
        icon: Leaf,
        permission: 'reporting.carbon_footprint.user_view',
        routeName: 'reporting.my-carbon-footprint',
    },
];

const siteAdministrationNavItems: NavItem[] = [
    {
        title: 'Society Management',
        href: '/admin/societies/dashboard',
        icon: Building2,
        permission: ['society.review_accreditation', 'society.view_reports'],
        items: [
            {
                title: 'Dashboard',
                href: '/admin/societies/dashboard',
                routeName: 'admin.societies.dashboard',
            },
            {
                title: 'Applications',
                href: '/admin/societies/applications',
                permission: 'society.review_accreditation',
                routeName: 'admin.societies.applications.index',
            },
            {
                title: 'Accredited Societies',
                href: '/admin/societies/accredited',
                routeName: 'admin.societies.accredited.index',
            },
            {
                title: 'Reports',
                href: '/admin/societies/reports',
                permission: 'society.view_reports',
                routeName: 'admin.societies.reports.index',
            },
        ],
    },
    {
        title: 'Reporting',
        href: '/admin/reporting/overview',
        icon: BarChart3,
        permission: 'reporting.view',
        items: [
            {
                title: 'Overview',
                href: '/admin/reporting/overview',
                icon: Activity,
                permission: 'reporting.overview.view',
                routeName: 'reporting.overview.index',
            },
            {
                title: 'PFT Result',
                href: '/admin/reporting/pft-result',
                icon: Dumbbell,
                permission: 'reporting.pft_result.view',
                routeName: 'admin.reporting.pft-result.index',
            },
            {
                title: 'Audit Logs',
                href: '/admin/reporting/audit-logs',
                icon: FileText,
                permission: 'reporting.audit_logs.view',
                routeName: 'reporting.audit-logs.index',
            },
            {
                title: 'Carbon Footprint',
                href: '/admin/reporting/carbon-footprint',
                icon: Leaf,
                permission: 'reporting.carbon_footprint.view',
                routeName: 'reporting.carbon-footprint.index',
            },
            {
                title: 'System Logs',
                href: '/system/logs',
                icon: FileText,
                permission: 'system.logs.view',
                roles: ['Super Admin', 'System Administrator'],
                routeName: 'system.logs.index',
            },
        ],
    },
    {
        title: 'Settings',
        href: '/user-management',
        icon: Settings,
        items: [
            {
                title: 'User Management',
                href: '/user-management',
                icon: Users,
                permission: 'users.view',
                routeName: 'user-management.index',
            },
            {
                title: 'Roles & Permissions',
                href: '/user-management/roles',
                icon: ShieldCheck,
                permission: ['roles.view', 'permissions.view'],
                routeName: 'user-management.roles.index',
            },
            {
                title: 'Reference Lookups',
                href: '/admin/reference-lookups',
                icon: BookOpen,
                permission: 'roles.view',
                routeName: 'admin.reference-lookups.index',
            },
            {
                title: 'Site Settings',
                href: '/admin/site-settings/campuses',
                icon: Building2,
                permission: 'site-settings.view',
                routeName: 'site-settings.campuses.index',
            },
            {
                title: 'Student Profile',
                href: '/admin/site-settings/student-profile',
                icon: User,
                permission: [
                    'site-settings.student-profile.view',
                    'site-settings.view',
                ],
                routeName: 'site-settings.student-profile.index',
            },
            {
                title: 'Feature Management',
                href: '/settings/feature-management',
                icon: ShieldCheck,
                permission: 'features.view',
                routeName: 'settings.feature-management.index',
            },
            {
                title: 'Legal',
                href: legalIndex(),
                icon: FileText,
                permission: 'legal.view',
                routeName: 'legal.index',
            },
        ],
    },
    {
        title: 'FAQ Management',
        href: '#',
        icon: HelpCircle,
        permission: 'faq.create',
        items: [
            {
                title: 'All FAQs',
                href: '/faqs/manage/faqs',
                permission: 'faq.create',
                routeName: 'faqs.manage.faqs.index',
            },
            {
                title: 'Categories',
                href: '/faqs/manage/categories',
                permission: 'faq-category.view',
                routeName: 'faqs.manage.categories.index',
            },
            {
                title: 'Analytics',
                href: '/faqs/manage/analytics',
                permission: 'faq.analytics.view',
                routeName: 'faqs.manage.analytics',
            },
        ],
    },
];

const registrarNavItems: NavItem[] = [
    {
        title: 'Registrar',
        href: '/admin/registrar/report-of-grades',
        icon: FileText,
        permission: 'registrar.view',
        items: [
            {
                title: 'Student Profile',
                href: '/admin/registrar/report-of-grades',
                icon: User,
                permission: [
                    'registrar.student-profile.view',
                    'registrar.report-of-grades.view',
                    'registrar.tag-graduating-student.view',
                ],
                routeName: 'admin.registrar.report-of-grades.index',
            },
        ],
    },
];

const visibleOverviewNavItems = computed(() => visibleItems(overviewNavItems));
const visibleRegistrarNavItems = computed(() =>
    visibleItems(registrarNavItems),
);
const visibleSiteAdministrationNavItems = computed(() =>
    visibleItems(siteAdministrationNavItems),
);
const homeHref = computed(() =>
    firstVisibleHref(visibleOverviewNavItems.value),
);

const footerNavItems: NavItem[] = [
    {
        title: 'FAQs',
        href: '/faqs',
        icon: MessageSquareQuote,
        permission: 'faq.view',
        routeName: 'faqs.index',
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar"
        class="border-r border-slate-200/80 bg-white dark:border-white/5 dark:bg-[#0f172a]">
        <SidebarHeader class="px-3 pt-4 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child
                        class="h-12 flex-1 rounded-xl px-1 hover:bg-transparent active:bg-transparent">
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarSeparator class="mt-3 bg-slate-100 dark:bg-white/5" />
        </SidebarHeader>

        <SidebarContent class="gap-0 overflow-y-auto px-2 pb-4">
            <NavMain label="Overview" :items="visibleOverviewNavItems" />
            <NavMain v-if="visibleRegistrarNavItems.length > 0" label="Registrar" :items="visibleRegistrarNavItems" />
            <NavMain v-if="visibleSiteAdministrationNavItems.length > 0" label="Site Administration"
                :items="visibleSiteAdministrationNavItems" />
        </SidebarContent>

        <SidebarFooter
            class="mt-auto gap-3 border-t border-slate-100 bg-white p-3 dark:border-white/5 dark:bg-[#0f172a]">
            <SidebarMenu class="gap-1">
                <SidebarMenuItem v-for="item in footerNavItems" :key="item.title"
                    v-show="can(item.permission, item.roles)">
                    <SidebarMenuButton as-child :tooltip="item.title"
                        class="h-9 rounded-lg px-2.5 text-[13px] font-semibold tracking-tight text-slate-600 transition-all hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-white/[0.05] dark:hover:text-slate-100">
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <Sparkles v-if="item.title === 'Help Center'"
                                class="ml-auto size-3.5 text-slate-300 dark:text-slate-600" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
