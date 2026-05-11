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
}

const props = withDefaults(defineProps<Props>(), {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'default',
    loading: false,
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Dialog :open="show" @update:open="(val) => !val && emit('close')">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <div class="flex items-start gap-4 text-left">
                    <div :class="[
                        'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        variant === 'destructive' ? 'bg-red-50 text-red-600 dark:bg-red-500/10' : 'bg-blue-50 text-blue-600 dark:bg-blue-500/10'
                    ]">
                        <AlertTriangle v-if="variant === 'destructive'" class="h-5 w-5" />
                        <Info v-else class="h-5 w-5" />
                    </div>
                    <div class="grid gap-1.5">
                        <DialogTitle class="text-lg font-bold">{{ title }}</DialogTitle>
                        <DialogDescription class="text-sm text-slate-500 dark:text-slate-400">
                            {{ description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
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
