<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Check,
    ChevronLeft,
    HeartPulse,
    Home,
    Moon,
    Settings,
    Sun,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

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
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

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

const toggleTheme = () => {
    updateAppearance(isDark.value ? 'light' : 'dark');
};

const navItemClass = (item: ActiveItem) => [
    'sidebar-link flex items-center gap-3 rounded-xl px-4 py-3 transition-colors',
    props.active === item
        ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/25 dark:bg-blue-500 dark:shadow-blue-500/20'
        : 'text-blue-800 hover:bg-blue-50 hover:text-blue-950 dark:text-blue-100 dark:hover:bg-blue-500/15 dark:hover:text-white',
];
</script>

<template>
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-slate-200 bg-white text-slate-900 shadow-sm transition-transform duration-300 lg:static lg:translate-x-0 dark:border-white/10 dark:bg-navy-950 dark:text-white"
    >
        <div class="flex h-full flex-col">
            <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-5 dark:border-white/10">
                <div class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-600 text-white ring-1 ring-blue-600/15 dark:bg-white/10 dark:ring-white/15">
                    <span class="text-lg font-black">USM</span>
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold">Fitness Intelligence</h1>
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-300">Physical Fitness &amp; Health<br />Assessment Analytics</p>
                </div>
            </div>

            <nav class="scrollbar-thin flex-1 space-y-1 overflow-y-auto px-3 py-5 text-sm">
                <Link href="/dashboard" class="sidebar-link mb-3 flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 font-semibold text-blue-800 transition-colors hover:border-blue-200 hover:bg-blue-100 hover:text-blue-950 dark:border-blue-400/20 dark:bg-blue-500/10 dark:text-blue-100 dark:hover:bg-blue-500/20 dark:hover:text-white">
                    <ChevronLeft class="h-4 w-4" /> Back to Main
                </Link>
                <div class="mb-3 border-t border-blue-100 dark:border-blue-400/20"></div>

                <Link

                    :href="`/admin/reporting/pft-result/analytics${analyticsQuery}`"
                    :class="navItemClass('executive')"
                >
                    <Home class="h-4 w-4" /> Executive Dashboard
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

            <div class="space-y-3 p-3">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/10 dark:text-slate-100 dark:hover:bg-white/15"
                    :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                    @click="toggleTheme"
                >
                    <span class="flex items-center gap-2">
                        <Sun v-if="isDark" class="h-4 w-4" />
                        <Moon v-else class="h-4 w-4" />
                        {{ isDark ? 'Light theme' : 'Dark theme' }}
                    </span>
                    <span
                        class="flex h-6 w-11 items-center rounded-full bg-slate-300 p-1 transition dark:bg-blue-500"
                        :class="isDark ? 'justify-end' : 'justify-start'"
                    >
                        <span class="h-4 w-4 rounded-full bg-white shadow-sm"></span>
                    </span>
                </button>

                <div v-if="user" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 ring-1 ring-slate-100 dark:border-white/10 dark:bg-white/10 dark:ring-white/10">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-full bg-blue-600 font-bold text-white dark:bg-white dark:text-navy-900">
                            {{ user.name.substring(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <p class="font-semibold">{{ user.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-300">Administrator</p>
                            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-300">Online</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
