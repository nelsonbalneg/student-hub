<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileQuestion,
    FolderOpen,
    Share2,
    Tag,
    ThumbsDown,
    ThumbsUp,
} from 'lucide-vue-next';
import { format } from 'date-fns';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

interface Faq {
    id: number;
    question: string;
    answer: string;
    summary: string;
    category: { id: number; name: string; color: string; slug: string };
    tags: string[];
    view_count: number;
    helpful_count: number;
    not_helpful_count: number;
    published_at: string;
}

interface Props {
    faq: { data: Faq };
    relatedFaqs: { data: Faq[] };
}

const props = defineProps<Props>();

const feedbackSubmitted = ref(false);
const feedbackForm = useForm({
    is_helpful: true,
    feedback: '',
});

const publishedDate = computed(() =>
    format(new Date(props.faq.data.published_at), 'MMM dd, yyyy'),
);

const submitFeedback = (helpful: boolean) => {
    if (feedbackForm.processing) return;

    feedbackForm.is_helpful = helpful;
    feedbackForm.post(`/faqs/${props.faq.data.id}/feedback`, {
        preserveScroll: true,
        onSuccess: () => (feedbackSubmitted.value = true),
    });
};

const copyLink = async () => {
    await navigator.clipboard.writeText(window.location.href);
    toast.success('FAQ link copied.');
};
</script>

<template>
    <Head :title="faq.data.question" />

    <div class="flex min-h-screen flex-col bg-slate-50/70 dark:bg-slate-950">
        <section
            class="border-b border-slate-200 bg-white px-4 py-5 lg:px-8 dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <FileQuestion class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <Link
                            href="/faqs"
                            class="inline-flex items-center gap-1.5 text-xs font-bold tracking-[0.16em] text-emerald-700 uppercase transition hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                        >
                            <ChevronLeft class="size-3.5" />
                            Knowledge Base
                        </Link>
                        <h1
                            class="mt-2 max-w-5xl text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ faq.data.question }}
                        </h1>
                        <p
                            class="mt-2 max-w-4xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            {{ faq.data.summary }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="copyLink"
                    class="inline-flex h-10 w-fit items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                >
                    <Share2 class="size-4" />
                    Share
                </button>
            </div>
        </section>

        <main
            class="grid w-full flex-1 gap-5 p-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-8"
        >
            <article
                class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{
                                backgroundColor: faq.data.category.color,
                            }"
                        ></span>
                        <Link
                            :href="`/faqs?category=${faq.data.category.slug}`"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300"
                        >
                            {{ faq.data.category.name }}
                        </Link>
                    </div>

                    <div
                        class="flex items-center gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Calendar class="size-3.5" />
                            {{ publishedDate }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Eye class="size-3.5" />
                            {{ faq.data.view_count }} views
                        </span>
                    </div>
                </div>

                <div class="px-5 py-6 sm:px-8">
                    <div
                        class="prose prose-slate prose-headings:font-bold prose-h2:mt-8 prose-h2:text-xl prose-p:text-sm prose-p:leading-7 prose-a:text-emerald-700 prose-a:no-underline hover:prose-a:underline prose-ul:text-sm prose-li:leading-7 prose-img:rounded-lg prose-strong:text-slate-950 dark:prose-invert dark:prose-a:text-emerald-300 dark:prose-strong:text-white max-w-none"
                        v-html="faq.data.answer"
                    ></div>
                </div>

                <div
                    class="border-t border-slate-200 px-5 py-5 sm:px-8 dark:border-white/10"
                >
                    <div
                        v-if="!feedbackSubmitted"
                        class="flex flex-col gap-4 rounded-lg bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-white/5"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                Was this article helpful?
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Your feedback helps improve StudentHub support
                                content.
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-9 gap-2 rounded-lg font-bold hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                                :disabled="feedbackForm.processing"
                                @click="submitFeedback(true)"
                            >
                                <ThumbsUp class="size-4" />
                                Yes
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-9 gap-2 rounded-lg font-bold hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                                :disabled="feedbackForm.processing"
                                @click="submitFeedback(false)"
                            >
                                <ThumbsDown class="size-4" />
                                No
                            </Button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex items-center gap-3 rounded-lg bg-emerald-50 p-4 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200"
                    >
                        <CheckCircle2 class="size-5 shrink-0" />
                        <div>
                            <h2 class="text-sm font-bold">Feedback received</h2>
                            <p
                                class="text-sm text-emerald-700/80 dark:text-emerald-100/80"
                            >
                                Thank you. Your response was recorded.
                            </p>
                        </div>
                    </div>
                </div>
            </article>

            <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
                <section
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Article Details
                        </h2>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-white/10">
                        <div class="flex items-center gap-3 px-4 py-3">
                            <Calendar class="size-4 text-slate-400" />
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wide text-slate-400 uppercase"
                                >
                                    Last Updated
                                </p>
                                <p
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ publishedDate }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-3">
                            <Eye class="size-4 text-slate-400" />
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wide text-slate-400 uppercase"
                                >
                                    Views
                                </p>
                                <p
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ faq.data.view_count }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-3">
                            <FolderOpen class="size-4 text-slate-400" />
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wide text-slate-400 uppercase"
                                >
                                    Category
                                </p>
                                <Link
                                    :href="`/faqs?category=${faq.data.category.slug}`"
                                    class="text-sm font-semibold text-emerald-700 transition hover:text-emerald-800 dark:text-emerald-300"
                                >
                                    {{ faq.data.category.name }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="faq.data.tags && faq.data.tags.length > 0"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Tag
                            class="size-4 text-slate-500 dark:text-slate-400"
                        />
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Tags
                        </h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="tag in faq.data.tags"
                            :key="tag"
                            class="inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-white/10 dark:text-slate-300"
                        >
                            #{{ tag }}
                        </span>
                    </div>
                </section>

                <section
                    v-if="relatedFaqs.data.length > 0"
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Related Articles
                        </h2>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-white/10">
                        <Link
                            v-for="related in relatedFaqs.data"
                            :key="related.id"
                            :href="`/faqs/view/${related.id}`"
                            class="group flex items-start justify-between gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <span
                                class="line-clamp-2 text-sm leading-6 font-semibold text-slate-700 transition group-hover:text-emerald-700 dark:text-slate-300 dark:group-hover:text-emerald-300"
                            >
                                {{ related.question }}
                            </span>
                            <ChevronRight
                                class="mt-1 size-4 shrink-0 text-slate-400 transition group-hover:text-emerald-600"
                            />
                        </Link>
                    </div>
                </section>

                <Link
                    href="/faqs"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                >
                    Browse all FAQs
                    <ArrowRight class="size-4" />
                </Link>
            </aside>
        </main>
    </div>
</template>
