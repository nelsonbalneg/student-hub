<script setup lang="ts">
import { FileText, Loader2, ShieldCheck, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { show as showPublicLegal } from '@/routes/legal/public';

type LegalType = 'terms' | 'cookie_policy' | 'privacy_policy';
type LegalDocument = {
    id: number;
    type: LegalType;
    title: string;
    content: string;
    version?: string | null;
    published_at_human?: string | null;
};

const labels: Record<LegalType, string> = {
    terms: 'Terms and Conditions',
    cookie_policy: 'Cookie Policy',
    privacy_policy: 'Privacy Policy',
};

const open = ref(false);
const loading = ref(false);
const error = ref('');
const document = ref<LegalDocument | null>(null);

const title = computed(() => document.value?.title ?? 'Legal Document');

const openDocument = async (type: LegalType) => {
    open.value = true;
    loading.value = true;
    error.value = '';
    document.value = null;

    try {
        const response = await fetch(showPublicLegal.url(type), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Document unavailable');
        }

        const payload = await response.json();
        document.value = payload.document;
    } catch {
        error.value = `${labels[type]} is not available yet.`;
    } finally {
        loading.value = false;
    }
};

defineExpose({ openDocument });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="w-full overflow-y-auto border-slate-200 bg-white p-0 sm:max-w-xl dark:border-white/10 dark:bg-slate-950"
        >
            <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-slate-950/95">
                <SheetHeader class="space-y-1 pr-8 text-left">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                        <ShieldCheck class="size-3.5" />
                        Legal
                    </div>
                    <SheetTitle class="text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ title }}
                    </SheetTitle>
                    <SheetDescription class="text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="document?.version">Version {{ document.version }}</span>
                        <span v-if="document?.version && document?.published_at_human"> · </span>
                        <span v-if="document?.published_at_human">Published {{ document.published_at_human }}</span>
                    </SheetDescription>
                </SheetHeader>
            </div>

            <div class="px-5 py-5">
                <div v-if="loading" class="flex min-h-60 items-center justify-center gap-2 text-sm text-slate-500">
                    <Loader2 class="size-4 animate-spin" />
                    Loading document
                </div>

                <div v-else-if="error" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ error }}
                </div>

                <article
                    v-else-if="document"
                    class="prose prose-sm max-w-none text-slate-700 dark:prose-invert dark:text-slate-200"
                    v-html="document.content"
                />

                <div v-else class="grid min-h-60 place-items-center text-center text-sm text-slate-500">
                    <div>
                        <FileText class="mx-auto mb-3 size-10 text-slate-300 dark:text-slate-700" />
                        Select a legal document to preview.
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
