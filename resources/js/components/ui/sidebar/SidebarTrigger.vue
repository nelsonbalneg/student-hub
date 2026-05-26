<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronsLeft, ChevronsRight } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:border-emerald-400/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300', props.class)"
    @click="toggleSidebar"
  >
    <ChevronsRight v-if="isMobile || state === 'collapsed'" class="size-4" />
    <ChevronsLeft v-else class="size-4" />
    <span class="sr-only">Toggle sidebar</span>
  </Button>
</template>
