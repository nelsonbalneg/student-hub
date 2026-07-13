<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Check,
    ChevronLeft,
    HeartPulse,
    Home,
    Settings,
} from 'lucide-vue-next';
import { computed } from 'vue';

type ActiveItem =
    | 'executive'
    | 'comparative'
    | 'pft-result'
    | 'parq'
    | 'settings';

const props = defineProps<{
    active: ActiveItem;
    campusId?: string | number;
    termId?: string | number;
}>();

const page = usePage();

const analyticsQuery = computed(() => {
    const params = new URLSearchParams();

    if (props.campusId) {
        params.set('campus_id', String(props.campusId));
    }

    if (props.termId) {
        params.set('term_id', String(props.termId));
    }

    const query = params.toString();

    return query ? `?${query}` : '';
});

const user = computed(() => page.props.auth?.user);
const siteSettings = computed(() => page.props.siteSettings as Record<string, string | null> | undefined);
const brandName = computed(() => siteSettings.value?.fitness_intelligence_name || 'Fitness Intelligence');
const brandTagline = computed(() => siteSettings.value?.fitness_intelligence_tagline || 'Physical Fitness & Health Assessment Analytics');
const brandLogoUrl = computed(() => siteSettings.value?.fitness_intelligence_logo_url || null);

const navItemClass = (item: ActiveItem) => [
    'sidebar-link flex items-center gap-2.5 rounded-lg px-3 py-2.5 transition-colors',
    props.active === item
        ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/25 dark:bg-blue-500 dark:shadow-blue-500/20'
        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-blue-500/15 dark:hover:text-white',
];
</script>

<template>
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-60 -translate-x-full border-r border-slate-200 bg-white text-slate-900 shadow-sm transition-transform duration-300 lg:static lg:translate-x-0 dark:border-white/10 dark:bg-navy-950 dark:text-white"
    >
        <div class="flex h-full flex-col">
            <div class="flex items-center gap-2.5 border-b border-slate-200 px-4 py-4 dark:border-white/10">
                <div class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-white text-blue-600 ring-1 ring-slate-200 dark:bg-white dark:text-blue-600 dark:ring-white/15">
                    <img
                        v-if="brandLogoUrl"
                        :src="brandLogoUrl"
                        :alt="`${brandName} logo`"
                        class="h-full w-full object-cover"
                    />
                    <span v-else class="text-sm font-black">USM</span>
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-bold">{{ brandName }}</h1>
                    <p class="line-clamp-2 text-[11px] leading-4 text-slate-500 dark:text-slate-300">{{ brandTagline }}</p>
                </div>
            </div>

            <nav class="scrollbar-thin flex-1 space-y-1 overflow-y-auto px-2.5 py-4 text-sm">
                <Link href="/dashboard" class="sidebar-link mb-3 flex items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-100 hover:text-slate-950 dark:border-blue-400/20 dark:bg-blue-500/10 dark:text-slate-100 dark:hover:bg-blue-500/20 dark:hover:text-white">
                    <ChevronLeft class="h-4 w-4" /> Back to Main
                </Link>
                <div class="mb-3 border-t border-slate-200 dark:border-blue-400/20"></div>

                <Link

                    :href="`/admin/reporting/pft-result/analytics${analyticsQuery}`"
                    :class="navItemClass('executive')"
                >
                    <Home class="h-4 w-4" /> Dashboard
                </Link>


                <Link

                    :href="`/admin/reporting/pft-result/analytics/comparative${analyticsQuery}`"
                    :class="navItemClass('comparative')"
                >
                    <BarChart3 class="h-4 w-4" /> Comparative Analytics
                </Link>


                <Link

                    href="/admin/reporting/pft-result"
                    :class="navItemClass('pft-result')"
                >
                    <HeartPulse class="h-4 w-4" /> PFT Result
                </Link>


                <Link

                    href="/admin/reporting/pft-parq"
                    :class="navItemClass('parq')"
                >
                    <Check class="h-4 w-4" /> PAR-Q &amp; Clearance
                </Link>

                <Link
                    href="/admin/site-settings/physical-fitness/configuration"
                    :class="navItemClass('settings')"
                >
                    <Settings class="h-4 w-4" /> Settings
                </Link>
            </nav>

            <div class="space-y-2 p-2.5">
                <div v-if="user" class="rounded-xl border border-slate-200 bg-slate-50 p-3 ring-1 ring-slate-100 dark:border-white/10 dark:bg-white/10 dark:ring-white/10">
                    <div class="flex items-center gap-2.5">
                        <div class="grid h-9 w-9 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white dark:bg-white dark:text-navy-900">
                            {{ user.name.substring(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-300">Administrator</p>
                            <p class="mt-0.5 text-[11px] text-emerald-600 dark:text-emerald-300">Online</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
