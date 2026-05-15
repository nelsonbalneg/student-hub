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
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex min-h-[calc(100svh-4rem)] flex-col">
                <div class="flex-1">
                    <slot />
                </div>
                <LegalFooter />
            </div>
        </AppContent>
        <Toaster />
        <TermsAcceptanceModal />
    </AppShell>
</template>
