<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Info } from 'lucide-vue-next';

interface Props {
    show: boolean;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default' | 'outline' | 'secondary' | 'ghost' | 'link';
    loading?: boolean;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
    loading: false,
    compact: false,
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Dialog :open="show" @update:open="(val) => !val && emit('close')">
        <DialogContent :class="[compact ? 'sm:max-w-[350px] p-4' : 'sm:max-w-[425px] p-6']">
            <DialogHeader :class="{ 'p-0': compact }">
                <div class="flex items-start gap-4 text-left">
                    <div :class="[
                        'shrink-0 flex items-center justify-center rounded-full',
                        compact ? 'h-8 w-8' : 'h-10 w-10 mt-0.5',
                        variant === 'destructive' ? 'bg-red-50 text-red-600 dark:bg-red-500/10' : 'bg-blue-50 text-blue-600 dark:bg-blue-500/10'
                    ]">
                        <AlertTriangle v-if="variant === 'destructive'" :class="compact ? 'h-4 w-4' : 'h-5 w-5'" />
                        <Info v-else :class="compact ? 'h-4 w-4' : 'h-5 w-5'" />
                    </div>
                    <div class="grid gap-1">
                        <DialogTitle :class="[compact ? 'text-sm' : 'text-lg', 'font-bold']">{{ title }}</DialogTitle>
                        <DialogDescription :class="[compact ? 'text-xs' : 'text-sm', 'text-slate-500 dark:text-slate-400']">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter :class="[compact ? 'mt-4' : 'mt-6', 'flex flex-col-reverse gap-2 sm:flex-row sm:justify-end']">
                <Button variant="outline" class="h-9 px-4 text-xs font-bold" @click="emit('close')" :disabled="loading">
                    {{ cancelText }}
                </Button>
                <Button :variant="variant" class="h-9 gap-1.5 px-4 text-xs font-bold" @click="emit('confirm')" :disabled="loading">
                    <slot name="confirm-icon"></slot>
                    {{ confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
