<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import LegalFooter from '@/components/LegalFooter.vue';
import SiteEvaluationDrawer from '@/components/SiteEvaluationDrawer.vue';
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
            <ImpersonationBanner />
            <AppSidebarHeader v-if="!hideHeader" :breadcrumbs="breadcrumbs" />
            <div class="flex min-h-[calc(100svh-4rem)] w-full flex-col">
                <div class="w-full flex-1">
                    <slot />
                </div>
                <LegalFooter v-if="!hideFooter" />
            </div>
        </AppContent>
        <Toaster />
        <TermsAcceptanceModal />
        <SiteEvaluationDrawer />
    </AppShell>
</template>
