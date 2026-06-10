<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';

defineProps<{
    title: string;
}>();
</script>

<template>
    <Head :title="`Site Settings - ${title}`" />

    <SiteSettingsLayout>
        <div class="flex-1 p-8 lg:p-12">
            <div
                class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-20 text-center dark:border-white/10 dark:bg-white/5"
            >
                <div
                    class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-white/10"
                >
                    <div
                        class="size-3 animate-pulse rounded-full bg-sky-500"
                    ></div>
                </div>
                <h1
                    class="mb-2 text-2xl font-bold text-slate-900 dark:text-white"
                >
                    {{ title }} Module
                </h1>
                <p class="mx-auto max-w-sm text-slate-500 dark:text-slate-400">
                    This module is currently under development. We are working
                    hard to bring you these settings soon.
                </p>
            </div>
        </div>
    </SiteSettingsLayout>
</template>
