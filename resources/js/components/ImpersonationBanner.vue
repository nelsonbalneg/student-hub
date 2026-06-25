<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { stop as stopImpersonationRoute } from '@/routes/user-management/impersonation';

const page = usePage();
const impersonation = computed(() => page.props.auth?.impersonation ?? null);

const stopImpersonation = () => {
    router.post(stopImpersonationRoute.url(), {}, { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="impersonation?.active"
        class="border-b border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100"
    >
        <div
            class="mx-auto flex min-h-10 flex-col gap-2 px-4 py-2 text-xs sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-2">
                <ShieldAlert class="h-4 w-4 shrink-0" />
                <span>
                    You are impersonating
                    <span class="font-medium">
                        {{ impersonation.user?.name }}
                    </span>
                    <span
                        v-if="impersonation.impersonator?.name"
                        class="text-amber-700 dark:text-amber-200/80"
                    >
                        from {{ impersonation.impersonator.name }}
                    </span>
                </span>
            </div>
            <button
                class="inline-flex h-7 items-center justify-center rounded-lg border border-amber-300 bg-white px-3 text-[11px] font-medium text-amber-900 shadow-sm transition hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-900/50"
                @click="stopImpersonation"
            >
                Return to my account
            </button>
        </div>
    </div>
</template>
