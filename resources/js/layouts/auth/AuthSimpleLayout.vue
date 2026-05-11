<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <AppLogo class="mb-4 scale-125" />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1
                            class="text-2xl font-bold tracking-tight text-foreground"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-center text-base text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl border bg-card p-8 shadow-premium">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
