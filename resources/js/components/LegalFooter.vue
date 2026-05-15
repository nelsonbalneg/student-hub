<script setup lang="ts">
import { ref } from 'vue';
import LegalDocumentDrawer from '@/components/LegalDocumentDrawer.vue';

type LegalType = 'terms' | 'cookie_policy' | 'privacy_policy';

const drawer = ref<InstanceType<typeof LegalDocumentDrawer> | null>(null);

const links: { type: LegalType; label: string }[] = [
    { type: 'terms', label: 'Terms and Conditions' },
    { type: 'cookie_policy', label: 'Cookie Policy' },
    { type: 'privacy_policy', label: 'Privacy Policy' },
];
</script>

<template>
    <footer class="sticky bottom-0 z-20 border-t border-slate-200 bg-white/85 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-950/85">
        <div class="flex flex-col items-center justify-between gap-2 text-xs text-slate-500 sm:flex-row dark:text-slate-400">
            <p>© {{ new Date().getFullYear() }} Student Hub</p>
            <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                <button
                    v-for="link in links"
                    :key="link.type"
                    type="button"
                    class="font-medium text-slate-600 transition hover:text-emerald-600 dark:text-slate-300 dark:hover:text-emerald-300"
                    @click="drawer?.openDocument(link.type)"
                >
                    {{ link.label }}
                </button>
            </nav>
        </div>
    </footer>

    <LegalDocumentDrawer ref="drawer" />
</template>
