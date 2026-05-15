<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardCheck,
    Heart,
    GraduationCap,
    FileText,
    Palette,
    Settings2,
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

const can = (permission?: string) => {
    if (!permission) {
        return true;
    }

    return (
        roles.value.includes('Super Admin') ||
        permissions.value.includes(permission)
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
    },
    {
        name: 'CCD Cares',
        href: '/admin/site-settings/ccd-cares',
        icon: Heart,
        description: 'Student support configurations',
    },
    {
        name: 'Grade Viewing',
        href: '/admin/site-settings/grade-viewing',
        icon: GraduationCap,
        description: 'Results visibility control',
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
        class="flex w-72 flex-col border-r border-slate-200 bg-white dark:border-white/5 dark:bg-slate-950"
    >
        <div
            class="flex h-16 items-center border-b border-slate-100 px-6 dark:border-white/5"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex size-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 ring-1 ring-sky-500/20"
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

        <nav class="flex-1 space-y-1 p-3">
            <Link
                v-for="tab in tabs"
                :key="tab.name"
                v-show="can(tab.permission)"
                :href="tab.href"
                :class="[
                    'group flex items-start gap-3 rounded-xl p-3 transition-all',
                    isActive(tab.href)
                        ? 'bg-sky-500/5 text-sky-600 ring-1 ring-sky-500/10 dark:bg-sky-500/10'
                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-100',
                ]"
            >
                <div
                    :class="[
                        'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                        isActive(tab.href)
                            ? 'bg-sky-500/10 text-sky-600'
                            : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200 group-hover:text-slate-600 dark:bg-white/5 dark:group-hover:bg-white/10 dark:group-hover:text-slate-300',
                    ]"
                >
                    <component :is="tab.icon" class="size-4" />
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-[13px] leading-none font-bold">{{
                        tab.name
                    }}</span>
                    <span
                        class="text-[11px] leading-none font-medium text-slate-400 group-hover:text-slate-500 dark:text-slate-500"
                        >{{ tab.description }}</span
                    >
                </div>
            </Link>
        </nav>

        <div class="border-t border-slate-100 p-4 dark:border-white/5">
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
