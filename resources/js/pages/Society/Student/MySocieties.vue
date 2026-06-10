<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
defineProps<{ memberships: any[] }>();
</script>

<template>
    <Head title="My Societies" />
    <div class="min-h-screen bg-slate-50 px-6 py-8 dark:bg-slate-950">
        <div class="mx-auto max-w-6xl space-y-4">
            <h1 class="text-xl font-black text-slate-950 dark:text-white">
                My Societies
            </h1>
            <div class="grid gap-4 md:grid-cols-3">
                <Link
                    v-for="membership in memberships"
                    :key="membership.id"
                    :href="`/societies/${membership.society.id}`"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="font-black text-slate-950 dark:text-white">
                        {{
                            membership.society.full_name ??
                            membership.society.name
                        }}
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-500">
                        {{ membership.status }}
                    </p>
                </Link>
            </div>
        </div>
    </div>
</template>
