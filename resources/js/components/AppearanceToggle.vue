<script setup lang="ts">
import { computed } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');
const label = computed(() =>
    isDark.value ? 'Switch to light mode' : 'Switch to dark mode',
);

function toggleAppearance() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-10 rounded-xl border border-border/60 bg-background/80 text-muted-foreground shadow-sm hover:bg-muted hover:text-foreground"
                    :aria-label="label"
                    @click="toggleAppearance"
                >
                    <Sun v-if="isDark" class="size-5" />
                    <Moon v-else class="size-5" />
                </Button>
            </TooltipTrigger>
            <TooltipContent side="bottom">
                <p>{{ label }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
