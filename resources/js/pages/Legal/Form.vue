<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Save } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { Button } from '@/components/ui/button';
import { index, store, update } from '@/routes/legal';

type LegalType = { value: string; label: string };
type LegalDocument = {
    id: number;
    type: string;
    title: string;
    slug?: string | null;
    content: string;
    version?: string | null;
    is_active: boolean;
};

const props = defineProps<{
    types: LegalType[];
    document?: LegalDocument | null;
    mode: 'create' | 'edit';
}>();

const form = useForm({
    type: props.document?.type ?? 'terms',
    title: props.document?.title ?? '',
    slug: props.document?.slug ?? '',
    content: props.document?.content ?? '',
    version: props.document?.version ?? '1.0',
    is_active: props.document?.is_active ?? false,
});

const legalInputClass =
    'h-10 w-full rounded-lg border !border-slate-300 !bg-white px-3 text-sm !font-normal !text-slate-950 shadow-xs transition outline-none placeholder:!text-slate-400 focus:!border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:!border-white/10 dark:!bg-slate-900 dark:!text-slate-100 dark:placeholder:!text-slate-500 dark:focus:!border-emerald-400 dark:focus:ring-emerald-500/20';

const submit = () => {
    const options = {
        preserveScroll: true,
        preserveState: true,
    };

    if (props.mode === 'edit' && props.document) {
        form.put(update.url(props.document.id), options);
        return;
    }

    form.post(store.url(), options);
};
</script>

<template>
    <form class="grid gap-5 lg:grid-cols-[1fr_320px]" @submit.prevent="submit">
        <section
            class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="border-b border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <h2
                    class="text-sm font-bold tracking-wide text-slate-900 uppercase dark:text-white"
                >
                    Document Content
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Store the approved legal text in the database for public
                    display and acceptance tracking.
                </p>
            </div>

            <div class="grid gap-4 p-5">
                <label
                    class="grid gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Title
                    <input
                        v-model="form.title"
                        :class="legalInputClass"
                        placeholder="Terms and Conditions"
                    />
                    <InputError :message="form.errors.title" />
                </label>

                <label
                    class="grid gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Slug
                    <input
                        v-model="form.slug"
                        :class="legalInputClass"
                        placeholder="Auto-generated when left blank"
                    />
                    <InputError :message="form.errors.slug" />
                </label>

                <div
                    class="grid gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Content
                    <TiptapEditor
                        v-model="form.content"
                        placeholder="Write the approved legal document content..."
                    />
                    <InputError :message="form.errors.content" />
                </div>
            </div>
        </section>

        <aside
            class="h-fit rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="border-b border-slate-200 px-5 py-4 dark:border-white/10"
            >
                <h2
                    class="text-sm font-bold tracking-wide text-slate-900 uppercase dark:text-white"
                >
                    Publication
                </h2>
            </div>

            <div class="grid gap-4 p-5">
                <label
                    class="grid gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Type
                    <select v-model="form.type" :class="legalInputClass">
                        <option
                            v-for="type in types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.type" />
                </label>

                <label
                    class="grid gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Version
                    <input
                        v-model="form.version"
                        :class="legalInputClass"
                        placeholder="1.0"
                    />
                    <InputError :message="form.errors.version" />
                </label>

                <label
                    class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-emerald-600"
                    />
                    <span>
                        <span class="block font-semibold"
                            >Activate after saving</span
                        >
                        <span
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            This will deactivate any other active document of
                            the same type.
                        </span>
                    </span>
                </label>

                <div class="flex flex-col gap-2 pt-2">
                    <Button
                        type="submit"
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        :disabled="form.processing"
                    >
                        <Save class="mr-2 size-4" />
                        {{
                            mode === 'create'
                                ? 'Create Document'
                                : 'Save Changes'
                        }}
                    </Button>
                    <Link :href="index.url()">
                        <Button type="button" variant="outline" class="w-full">
                            <ArrowLeft class="mr-2 size-4" />
                            Back to Legal
                        </Button>
                    </Link>
                </div>

                <div
                    class="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200"
                >
                    <CheckCircle2 class="mb-2 size-4" />
                    Terms acceptance is version-aware. Activating a new Terms
                    version requires users to accept it again.
                </div>
            </div>
        </aside>
    </form>
</template>
