<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import LegalFooter from '@/components/LegalFooter.vue';
import TermsAcceptanceModal from '@/components/TermsAcceptanceModal.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    hideHeader?: boolean;
    hideFooter?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    hideHeader: false,
    hideFooter: false,
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="w-full overflow-x-hidden">
            <AppSidebarHeader v-if="!hideHeader" :breadcrumbs="breadcrumbs" />
            <div class="flex w-full min-h-[calc(100svh-4rem)] flex-col">
                <div class="flex-1 w-full">
                    <slot />
                </div>
                <LegalFooter v-if="!hideFooter" />
            </div>
        </AppContent>
        <Toaster />
        <TermsAcceptanceModal />
    </AppShell>
</template>
