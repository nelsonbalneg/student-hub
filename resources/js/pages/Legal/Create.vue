<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Form from './Form.vue';

type LegalType = { value: string; label: string };

defineProps<{
    types: LegalType[];
}>();
</script>

<template>
    <Head title="Create Legal Document" />

    <div
        class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 lg:p-6 dark:bg-slate-950"
    >
        <header class="border-b border-slate-200 pb-5 dark:border-white/10">
            <p
                class="text-xs font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300"
            >
                Settings · Legal
            </p>
            <h1
                class="mt-2 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
            >
                Create Legal Document
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Publish versioned legal content for terms, privacy, and cookie
                notices.
            </p>
        </header>

        <Form :types="types" mode="create" />
    </div>
</template>
