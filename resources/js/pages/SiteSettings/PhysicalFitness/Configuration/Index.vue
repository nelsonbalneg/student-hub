<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { 
    Layers, 
    Settings, 
    ChevronRight, 
    ArrowUpRight 
} from 'lucide-vue-next';
import { computed } from 'vue';
import { 
    index as configIndex 
} from '@/routes/site-settings/physical-fitness/configuration';

const props = defineProps<{
    activeTab: string;
}>();

const activeTab = computed(() => props.activeTab);

// Since I don't have the full structure of the routes, 
// I will assume the configuration exists and we are just implementing the UI first.
</script>

<template>
    <Head title="Physical Fitness Configuration" />

    <SiteSettingsLayout>
        <div class="space-y-5 p-4 lg:p-6">
            <div class="flex flex-components items-center justify-between gap-4 border-b border-slate-200 pb-5 dark:border-white/10">
                <div>
                    <p class="text-[11px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300">
                        Physical Fitness
                    </p>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                        Configuration
                    </h1>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-[220px_1fr]">
                <aside class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition"
                        :class="activeTab === 'configuration' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5'"
                        @click="router.get(configIndex.url())"
                    >
                        <Layers class="size-4" />
                        <span class="text-sm font-bold">Configuration</span>
                    </br>
                    </button>
                    <button
                        type="button"
                        class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-3 text-left transition"
                        :class="activeTab === 'settings' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-white/5'"
                        @click="router.get(configIndex.url({ query: { tab: 'settings' } }))"
                    >
                        <Settings class="size-4" />
                        <span class="text-sm font-bold">Settings</span>
                    </button>
                </aside>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div v-if="activeTab === 'configuration'" class="p-6 text-center">
                        <div class="flex justify-center mb-4">
                            <Layers class="size-12 text-slate-300" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-950 dark:text-white">Configuration View</h3>
                        <p class="text-sm text-slate-500">Select a configuration option from the sidebar.</p>
                    </div>
                    <div v-else class="p-6 text-center">
                         <div class="flex justify-center mb-4">
                            <Settings class="size-12 text-slate-300" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-950 dark:text-white">Settings View</h3>
                        <p class="text-sm text-slate-500">Switch to the configuration tab to see elements.</p>
                    </div>
                </section>
            </div>
        </div>
    </SiteSettingsLayout>
</template>
