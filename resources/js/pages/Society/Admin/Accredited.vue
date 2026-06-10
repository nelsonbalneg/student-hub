<script setup lang="ts">
import Applications from './Applications.vue';
defineProps<{ societies?: any; filters?: any }>();
</script>

<template>
    <div
        class="min-h-screen bg-slate-50/50 px-6 py-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="border-b border-slate-200 pb-5 dark:border-slate-800">
                <p
                    class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                >
                    Registry
                </p>
                <h1 class="text-xl font-black text-slate-950 dark:text-white">
                    Approved Societies
                </h1>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <a
                    v-for="society in societies.data"
                    :key="society.id"
                    :href="`/admin/societies/${society.id}/profile`"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="font-black text-slate-950 dark:text-white">
                        {{ society.full_name ?? society.name }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ society.abbreviation ?? society.acronym }} ·
                        {{ society.status ?? 'registered' }}
                    </p>
                </a>
            </div>
        </div>
    </div>
</template>
