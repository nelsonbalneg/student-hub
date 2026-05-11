<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-20 shrink-0 items-center justify-between gap-2 border-b border-border/50 bg-background/80 px-8 backdrop-blur-md transition-all"
    >
        <div class="flex items-center gap-4">
            <SidebarTrigger class="-ml-2 h-10 w-10 hover:bg-muted" />
            <div class="mx-2 hidden h-6 w-px bg-border/50 md:block" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-4">
            <AppearanceTabs />
        </div>
    </header>
</template>
