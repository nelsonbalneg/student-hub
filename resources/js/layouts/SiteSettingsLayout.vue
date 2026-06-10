<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SiteSettingsSidebar from '@/components/SiteSettingsSidebar.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <div
        class="flex min-h-[calc(100vh-4rem)] bg-slate-50/50 p-3 sm:p-4 lg:h-[calc(100vh-4rem)] lg:overflow-hidden lg:p-6 dark:bg-slate-900/50"
    >
        <div
            class="flex w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:flex-row dark:border-white/10 dark:bg-slate-950"
        >
            <SiteSettingsSidebar />
            <main
                class="no-scrollbar flex min-w-0 flex-1 flex-col overflow-y-auto"
            >
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
