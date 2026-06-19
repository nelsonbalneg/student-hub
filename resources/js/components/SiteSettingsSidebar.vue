<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardCheck,
    Heart,
    GraduationCap,
    FileText,
    Dumbbell,
    Palette,
    Settings2,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);
const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const can = (permission?: string | string[]) => {
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

const tabs = [
    {
        name: 'Campuses',
        href: '/admin/site-settings/campuses',
        icon: Building2,
        description: 'Manage campus identities',
    },
    {
        name: 'Evaluation',
        href: '/admin/site-settings/evaluation',
        icon: ClipboardCheck,
        description: 'Academic evaluation settings',
        permission: 'evaluation.templates.view',
    },
    {
        name: 'CCD Cares',
        href: '/admin/site-settings/ccd-cares',
        icon: Heart,
        description: 'Student support configurations',
        permission: 'evaluation.templates.view',
    },
    {
        name: 'Grade Viewing',
        href: '/admin/site-settings/grade-viewing',
        icon: GraduationCap,
        description: 'Results visibility control',
    },
    {
        name: 'Student Profile',
        href: '/admin/site-settings/student-profile',
        icon: UserRound,
        description: 'Awards and trainings',
        permission: [
            'site-settings.student-profile.view',
            'site-settings.view',
        ],
    },
    {
        name: 'Physical Fitness',
        href: '/admin/site-settings/physical-fitness/configuration',
        icon: Dumbbell,
        description: 'PFT master data',
        permission: ['pft.configuration.view', 'site-settings.view'],
    },
    {
        name: 'SAR',
        href: '/admin/site-settings/sar',
        icon: FileText,
        description: 'Student Admission Records',
    },
    {
        name: 'Site Settings',
        href: '/admin/site-settings/site-settings',
        icon: Palette,
        description: 'Branding and platform details',
        permission: 'site_settings.manage',
    },
];

const isActive = (href: string) => {
    return currentUrl.value.startsWith(href);
};
</script>

<template>
    <aside
        class="flex w-full shrink-0 flex-col border-b border-slate-200 bg-white lg:w-72 lg:border-r lg:border-b-0 dark:border-white/5 dark:bg-slate-950"
    >
        <div
            class="flex h-14 items-center border-b border-slate-100 px-4 sm:px-6 lg:h-16 dark:border-white/5"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300"
                >
                    <Settings2 class="size-4.5" />
                </div>
                <h2
                    class="text-sm font-bold tracking-tight text-slate-900 dark:text-white"
                >
                    Site Settings
                </h2>
            </div>
        </div>

        <nav
            class="flex gap-2 overflow-x-auto p-3 lg:block lg:flex-1 lg:space-y-1 lg:overflow-visible"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.name"
                v-show="can(tab.permission)"
                :href="tab.href"
                :class="[
                    'group flex min-w-[150px] items-start gap-3 rounded-xl p-3 transition-all lg:min-w-0',
                    isActive(tab.href)
                        ? 'bg-emerald-500/5 text-emerald-700 ring-1 ring-emerald-500/10 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-100',
                ]"
            >
                <div
                    :class="[
                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                        isActive(tab.href)
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200 group-hover:text-slate-600 dark:bg-white/5 dark:group-hover:bg-white/10 dark:group-hover:text-slate-300',
                    ]"
                >
                    <component :is="tab.icon" class="size-4" />
                </div>
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-[13px] leading-none font-bold">{{
                        tab.name
                    }}</span>
                    <span
                        class="hidden text-[11px] leading-none font-medium text-slate-400 group-hover:text-slate-500 sm:block dark:text-slate-500"
                        >{{ tab.description }}</span
                    >
                </div>
            </Link>
        </nav>

        <div
            class="hidden border-t border-slate-100 p-4 lg:block dark:border-white/5"
        >
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                <p
                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                >
                    System Version
                </p>
                <p
                    class="mt-0.5 text-xs font-medium text-nowrap text-slate-600 dark:text-slate-300"
                >
                    One USM Integrated System
                </p>
            </div>
        </div>
    </aside>
</template>
