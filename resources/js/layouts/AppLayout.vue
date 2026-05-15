<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        hideHeader?: boolean;
        hideFooter?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        hideHeader: false,
        hideFooter: false,
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="props.breadcrumbs" :hide-header="props.hideHeader" :hide-footer="props.hideFooter">
        <slot />
    </AppLayout>
</template>
