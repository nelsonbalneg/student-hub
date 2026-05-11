<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Archive,
    BookOpen,
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
    Building2,
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
import type { NavItem } from '@/types';

const page = usePage();

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

const visibleItems = (items: NavItem[]): NavItem[] =>
    items
        .map((item) => ({
            ...item,
            items: item.items ? visibleItems(item.items) : undefined,
        }))
        .filter(
            (item) =>
                can(item.permission) && (!item.items || item.items.length > 0),
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
            },
            {
                title: 'Curriculum',
                href: '/curriculum',
                // icon: BookOpen,
                permission: 'curriculum.view',
            },
        ],
    },
    {
        title: 'Profile',
        href: '/student-profile',
        icon: User,
        permission: 'student-profile.view',
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
            },
            {
                title: 'Management',
                href: '/admin/evaluations',
                permission: 'evaluation.manage-requests',
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
            },
            {
                title: 'Create Announcement',
                href: '/announcements/create',
                permission: ['announcement.create', 'announcements.create'],
            },
            {
                title: 'Categories',
                href: '/announcements/categories',
                permission: ['announcement.manage-categories', 'announcements.manage-categories'],
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
            },
            {
                title: 'Accountabilities Center',
                href: '/student-services/clearance/accountabilities-center',
                permission: 'clearance-update.view',
            },
            {
                title: 'Clearance Updates',
                href: '/student-services/clearance/updates',
                permission: 'clearance-update.view',
            },
        ],
    },
];

const siteAdministrationNavItems: NavItem[] = [
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
            },
            {
                title: 'Roles & Permissions',
                href: '/user-management/roles',
                icon: ShieldCheck,
                permission: ['roles.view', 'permissions.view'],
            },
            {
                title: 'Reference Lookups',
                href: '/admin/reference-lookups',
                icon: BookOpen,
                permission: 'roles.view',
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
            },
            {
                title: 'Categories',
                href: '/faqs/manage/categories',
                permission: 'faq-category.view',
            },
            {
                title: 'Analytics',
                href: '/faqs/manage/analytics',
                permission: 'faq.analytics.view',
            },
        ],
    },
];

const visibleOverviewNavItems = computed(() => visibleItems(overviewNavItems));
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
    },
    // {
    //     title: 'Help Center',
    //     href: '#',
    //     icon: HelpCircle,
    // },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-slate-200/80 bg-white dark:border-white/5 dark:bg-[#0f172a]"
    >
        <SidebarHeader class="px-3 pt-4 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 flex-1 rounded-xl px-1 hover:bg-transparent active:bg-transparent"
                    >
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
            <NavMain
                v-if="visibleSiteAdministrationNavItems.length > 0"
                label="Site Administration"
                :items="visibleSiteAdministrationNavItems"
            />
        </SidebarContent>

        <SidebarFooter
            class="mt-auto gap-3 border-t border-slate-100 bg-white p-3 dark:border-white/5 dark:bg-[#0f172a]"
        >
            <SidebarMenu class="gap-1">
                <SidebarMenuItem
                    v-for="item in footerNavItems"
                    :key="item.title"
                    v-show="can(item.permission)"
                >
                    <SidebarMenuButton
                        as-child
                        :tooltip="item.title"
                        class="h-9 rounded-lg px-2.5 text-[13px] font-semibold tracking-tight text-slate-600 transition-all hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-white/[0.05] dark:hover:text-slate-100"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <Sparkles
                                v-if="item.title === 'Help Center'"
                                class="ml-auto size-3.5 text-slate-300 dark:text-slate-600"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
