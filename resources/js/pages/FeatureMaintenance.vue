<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldOff, Home, RefreshCw, Clock } from 'lucide-vue-next';

defineProps({
    featureName: {
        type: String,
        default: 'This feature',
    },
    maintenanceMessage: {
        type: String,
        default: 'This feature is currently under maintenance. Please try again later.',
    },
});
</script>

<template>
    <Head :title="`${featureName} — Unavailable`" />

    <div class="flex h-full flex-1 flex-col">
        <!-- Header bar -->
        <div class="border-b border-slate-200 bg-white px-6 py-4 dark:border-white/10 dark:bg-slate-950">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                >
                    <ShieldOff class="size-5" />
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ featureName }}
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        This feature is currently unavailable
                    </p>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex flex-1 flex-col items-center justify-center p-6 lg:p-10">
            <div class="w-full max-w-xl space-y-8 text-center">
                <!-- Status icon -->
                <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-amber-50 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/20">
                    <Clock class="size-10 text-amber-500 dark:text-amber-400" />
                </div>

                <!-- Status heading -->
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                        Temporarily Unavailable
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white lg:text-3xl">
                        {{ featureName }} is currently closed
                    </h2>
                </div>

                <!-- Message -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 text-left shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        {{ maintenanceMessage }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-center gap-3">
                    <Link
                        href="/dashboard"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 active:scale-[0.98] dark:bg-slate-700 dark:hover:bg-slate-600"
                    >
                        <Home class="size-4" />
                        Back to Dashboard
                    </Link>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-[0.98] dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="$router?.go(0) ?? window.location.reload()"
                    >
                        <RefreshCw class="size-4" />
                        Retry
                    </button>
                </div>

                <!-- Footer note -->
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    Contact your administrator if you believe this is an error.
                </p>
            </div>
        </div>
    </div>
</template>
