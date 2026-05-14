<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Edit, ShieldCheck, Trash2, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { activate, deactivate, destroy, edit, index } from '@/routes/legal';

type LegalDocument = {
    id: number;
    type: string;
    title: string;
    slug: string;
    content: string;
    version?: string | null;
    is_active: boolean;
    published_at_human?: string | null;
    updated_at?: string | null;
    acceptances_count: number;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
};

const props = defineProps<{
    document: LegalDocument;
    can: {
        edit: boolean;
        delete: boolean;
        activate: boolean;
    };
}>();

const confirmAction = ref<null | 'delete' | 'activate' | 'deactivate'>(null);

const typeLabel = (type: string) =>
    ({
        terms: 'Terms and Conditions',
        cookie_policy: 'Cookie Policy',
        privacy_policy: 'Privacy Policy',
    })[type] ?? type;

const runAction = () => {
    if (confirmAction.value === 'delete') {
        router.delete(destroy.url(props.document.id));
        return;
    }

    if (confirmAction.value === 'activate') {
        router.patch(activate.url(props.document.id), {}, {
            preserveScroll: true,
            onSuccess: () => (confirmAction.value = null),
        });
    }

    if (confirmAction.value === 'deactivate') {
        router.patch(deactivate.url(props.document.id), {}, {
            preserveScroll: true,
            onSuccess: () => (confirmAction.value = null),
        });
    }
};
</script>

<template>
    <Head :title="document.title" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <header class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                        Settings · Legal
                    </p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">
                        {{ document.title }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ typeLabel(document.type) }} · {{ document.slug }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="index.url()">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 size-4" />
                            Back
                        </Button>
                    </Link>
                    <Link v-if="can.edit" :href="edit.url(document.id)">
                        <Button variant="outline">
                            <Edit class="mr-2 size-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button
                        v-if="can.activate && !document.is_active"
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="confirmAction = 'activate'"
                    >
                        <ShieldCheck class="mr-2 size-4" />
                        Activate
                    </Button>
                    <Button
                        v-if="can.activate && document.is_active"
                        variant="outline"
                        @click="confirmAction = 'deactivate'"
                    >
                        <XCircle class="mr-2 size-4" />
                        Deactivate
                    </Button>
                    <Button
                        v-if="can.delete"
                        class="bg-red-600 text-white hover:bg-red-700"
                        @click="confirmAction = 'delete'"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Delete
                    </Button>
                </div>
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Status</p>
                <p class="mt-2 inline-flex items-center gap-1 text-sm font-bold" :class="document.is_active ? 'text-emerald-600' : 'text-slate-500'">
                    <CheckCircle2 v-if="document.is_active" class="size-4" />
                    <XCircle v-else class="size-4" />
                    {{ document.is_active ? 'Active' : 'Inactive' }}
                </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Version</p>
                <p class="mt-2 text-sm font-bold text-slate-950 dark:text-white">{{ document.version || 'Unversioned' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Published</p>
                <p class="mt-2 text-sm font-bold text-slate-950 dark:text-white">{{ document.published_at_human || '-' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Acceptances</p>
                <p class="mt-2 text-sm font-bold text-slate-950 dark:text-white">{{ document.acceptances_count }}</p>
            </div>
        </section>

        <article class="rounded-lg border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-slate-950">
            <div class="prose prose-sm max-w-none text-slate-700 dark:prose-invert dark:text-slate-200" v-html="document.content" />
        </article>
    </div>

    <div v-if="confirmAction" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-slate-950">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                {{ confirmAction === 'delete' ? 'Delete document?' : confirmAction === 'activate' ? 'Activate document?' : 'Deactivate document?' }}
            </h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ confirmAction === 'delete' ? 'This action cannot be undone.' : confirmAction === 'activate' ? 'This will deactivate any other active document of the same type.' : 'This document will no longer appear in public legal drawers.' }}
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="confirmAction = null">Cancel</Button>
                <Button
                    :class="confirmAction === 'delete' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                    @click="runAction"
                >
                    {{ confirmAction === 'delete' ? 'Delete' : confirmAction === 'activate' ? 'Activate' : 'Deactivate' }}
                </Button>
            </div>
        </div>
    </div>
</template>
