<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{ society: any; is_member?: boolean; membership_status?: string }>();
</script>

<template>
    <Head :title="society.full_name ?? society.name" />
    <div class="min-h-screen bg-slate-50 px-6 py-8 dark:bg-slate-950">
        <div class="mx-auto max-w-5xl rounded-lg border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-black uppercase tracking-wider text-sky-600">{{ society.abbreviation ?? society.acronym }}</p>
            <h1 class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ society.full_name ?? society.name }}</h1>
            <p class="mt-3 text-slate-600 dark:text-slate-300">{{ society.description ?? society.mission ?? 'No society description provided.' }}</p>
            <div class="mt-6 flex gap-3">
                <button v-if="!is_member" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white dark:bg-sky-600" @click="router.post(`/societies/${society.id}/join`)">Join Society</button>
                <span v-else class="rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">{{ membership_status }}</span>
                <Link href="/societies" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">Back</Link>
            </div>
        </div>
    </div>
</template>
