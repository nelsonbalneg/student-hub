<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground/60 selection:bg-primary/20 selection:text-foreground h-11 w-full min-w-0 rounded-xl border border-input bg-background px-4 py-2 text-sm shadow-sm transition-all duration-200 outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
      'focus:border-primary focus:ring-4 focus:ring-primary/10',
      'aria-invalid:border-destructive aria-invalid:ring-destructive/10',
      'dark:bg-muted/30 dark:border-border dark:focus:border-primary',
      props.class,
    )"
  >
</template>
