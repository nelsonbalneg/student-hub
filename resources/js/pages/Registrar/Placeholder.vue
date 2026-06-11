<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';

const props = defineProps<{
    title: string;
    description: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Registrar', href: '/admin/registrar/report-of-grades' },
        ],
    },
});
</script>

<template>
    <Head :title="props.title" />

    <div class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950">
        <div>
            <h1 class="text-base font-bold text-slate-900 dark:text-white">
                {{ props.title }}
            </h1>
            <p class="text-xs text-slate-500">{{ props.description }}</p>
        </div>

        <div
            class="flex min-h-[320px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center dark:border-white/10 dark:bg-slate-950"
        >
            <FileText class="h-10 w-10 text-slate-300" />
            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                Coming soon
            </p>
            <p class="mt-1 max-w-md text-xs text-slate-500">
                This Registrar tool is reserved for a future version.
            </p>
        </div>
    </div>
</template>
