<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'rounded-2xl border bg-card text-card-foreground shadow-premium transition-all duration-300',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
