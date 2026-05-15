<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, FileText, Loader2, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { acceptTerms } from '@/routes/legal';

type LegalDocument = {
    id: number;
    type: string;
    title: string;
    content: string;
    version?: string | null;
    published_at_human?: string | null;
};

const page = usePage();
const checked = ref(false);
const processing = ref(false);

const requiredTerms = computed<LegalDocument | null>(() => {
    const legal = page.props.legal as { requiredTerms?: LegalDocument | null } | undefined;

    return legal?.requiredTerms ?? null;
});

const accept = () => {
    if (!requiredTerms.value || !checked.value) {
        return;
    }

    processing.value = true;

    router.post(
        acceptTerms.url(),
        {
            legal_document_id: requiredTerms.value.id,
            type: 'terms',
            accepted: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="Boolean(requiredTerms)">
        <DialogContent
            :show-close-button="false"
            class="max-h-[92vh] max-w-3xl overflow-hidden border-slate-200 p-0 shadow-2xl dark:border-white/10"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
        >
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-slate-900">
                <DialogHeader class="space-y-2">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                        <ShieldCheck class="size-4" />
                        Required acceptance
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ requiredTerms?.title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="requiredTerms?.version">Version {{ requiredTerms.version }}</span>
                        <span v-if="requiredTerms?.version && requiredTerms?.published_at_human"> · </span>
                        <span v-if="requiredTerms?.published_at_human">Published {{ requiredTerms.published_at_human }}</span>
                    </DialogDescription>
                </DialogHeader>
            </div>

            <div class="max-h-[56vh] overflow-y-auto px-5 py-5">
                <article
                    v-if="requiredTerms"
                    class="prose prose-sm max-w-none text-slate-700 dark:prose-invert dark:text-slate-200"
                    v-html="requiredTerms.content"
                />
                <div v-else class="grid min-h-44 place-items-center text-sm text-slate-500">
                    <FileText class="size-8" />
                </div>
            </div>

            <DialogFooter class="border-t border-slate-200 bg-white px-5 py-4 dark:border-white/10 dark:bg-slate-950">
                <div class="flex w-full flex-col gap-4">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200">
                        <input
                            v-model="checked"
                            type="checkbox"
                            class="mt-0.5 size-4 accent-emerald-600"
                        />
                        <span>I have read and agree to the Terms and Conditions.</span>
                    </label>
                    <Button
                        class="w-full bg-emerald-600 text-white hover:bg-emerald-700"
                        :disabled="!checked || processing"
                        @click="accept"
                    >
                        <Loader2 v-if="processing" class="mr-2 size-4 animate-spin" />
                        <CheckCircle2 v-else class="mr-2 size-4" />
                        Accept and continue
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
