<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ThumbsUp,
    ThumbsDown,
    Share2,
    Calendar,
    Eye,
    MessageSquare,
    HelpCircle,
    ArrowRight,
    Search,
    CheckCircle2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { format } from 'date-fns';

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

const submitFeedback = (helpful: boolean) => {
    feedbackForm.is_helpful = helpful;
    feedbackForm.post(`/faqs/${props.faq.data.id}/feedback`, {
        preserveScroll: true,
        onSuccess: () => (feedbackSubmitted.value = true),
    });
};

const copyLink = () => {
    navigator.clipboard.writeText(window.location.href);
    // Could add a toast here
};
</script>

<template>
    <Head :title="faq.data.question" />

    <div class="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
        <!-- Navigation Header -->
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-white/10 dark:bg-slate-900/80">
            <div class="flex w-full h-12 items-center justify-between px-6">
                <Link href="/faqs" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-emerald-600 transition-colors dark:text-slate-400 dark:hover:text-white">
                    <ChevronLeft class="size-3.5" />
                    Back to Knowledge Base
                </Link>
                <div class="flex items-center gap-3">
                    <button @click="copyLink" class="inline-flex h-8 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300">
                        <Share2 class="size-3.5" />
                        Share Link
                    </button>
                </div>
            </div>
        </header>

        <main class="w-full flex-1 px-6 py-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Sidebar: Info & Related -->
                <aside class="order-2 lg:order-1 lg:col-span-3 space-y-6">
                    <!-- Article Meta -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">Article Details</h3>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <Calendar class="size-4 text-slate-400 mt-0.5" />
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Last Updated</span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ format(new Date(faq.data.published_at), 'MMM dd, yyyy') }}</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <Eye class="size-4 text-slate-400 mt-0.5" />
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Total Views</span>
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ faq.data.view_count }} times</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <HelpCircle class="size-4 text-slate-400 mt-0.5" />
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Category</span>
                                    <Link :href="`/faqs?category=${faq.data.category.slug}`" class="text-xs font-bold text-emerald-600 hover:underline">
                                        {{ faq.data.category.name }}
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-if="faq.data.tags && faq.data.tags.length > 0" class="mt-6 pt-6 border-t border-slate-100 dark:border-white/5">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Related Tags</h3>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="tag in faq.data.tags" :key="tag" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 dark:bg-white/5 dark:text-slate-400">
                                    #{{ tag }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Related Articles -->
                    <div v-if="relatedFaqs.data.length > 0" class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 pl-1">Related Articles</h3>
                        <div class="space-y-2">
                            <Link
                                v-for="related in relatedFaqs.data"
                                :key="related.id"
                                :href="`/faqs/view/${related.id}`"
                                class="group flex flex-col rounded-xl border border-transparent p-3 hover:border-slate-200 hover:bg-white transition-all dark:hover:border-white/10 dark:hover:bg-slate-900"
                            >
                                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 group-hover:text-emerald-600 transition-colors line-clamp-2">{{ related.question }}</span>
                                <span class="mt-1 flex items-center text-[10px] font-bold text-emerald-600/0 group-hover:text-emerald-600 transition-all">
                                    Read more <ArrowRight class="ml-1 size-3" />
                                </span>
                            </Link>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <article class="order-1 lg:order-2 lg:col-span-9">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900 sm:p-8">
                        <div class="flex items-center gap-2 mb-6">
                            <div class="h-2 w-2 rounded-full" :style="{ backgroundColor: faq.data.category.color }"></div>
                            <span class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ faq.data.category.name }}</span>
                        </div>
                        
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-3xl lg:text-4xl">
                            {{ faq.data.question }}
                        </h1>

                        <div class="mt-4 text-base font-medium text-slate-500 dark:text-slate-400 leading-relaxed italic border-l-4 border-emerald-500 pl-4 bg-slate-50/50 py-3 rounded-r-xl dark:bg-white/5">
                            {{ faq.data.summary }}
                        </div>

                        <!-- Article Content -->
                        <div class="prose prose-slate mt-8 max-w-none dark:prose-invert prose-headings:font-extrabold prose-h2:text-xl prose-h2:mt-8 prose-p:text-sm prose-p:leading-relaxed prose-a:text-emerald-600 prose-a:no-underline hover:prose-a:underline prose-img:rounded-xl prose-strong:text-slate-900 dark:prose-strong:text-white">
                            <div v-html="faq.data.answer"></div>
                        </div>

                        <!-- Feedback Section -->
                        <div class="mt-16 border-t border-slate-100 pt-12 dark:border-white/5">
                            <div v-if="!feedbackSubmitted" class="flex flex-col items-center justify-center text-center">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Was this article helpful?</h3>
                                <div class="mt-6 flex items-center gap-4">
                                    <button 
                                        @click="submitFeedback(true)"
                                        class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-white p-5 px-8 transition-all hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700 dark:border-white/10 dark:bg-slate-950 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 group"
                                    >
                                        <ThumbsUp class="size-6 text-slate-400 group-hover:text-emerald-600" />
                                        <span class="text-xs font-bold">Yes, it helped!</span>
                                    </button>
                                    <button 
                                        @click="submitFeedback(false)"
                                        class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-white p-5 px-8 transition-all hover:border-rose-500 hover:bg-rose-50 hover:text-rose-700 dark:border-white/10 dark:bg-slate-950 dark:hover:bg-rose-500/10 dark:hover:text-rose-400 group"
                                    >
                                        <ThumbsDown class="size-6 text-slate-400 group-hover:text-rose-600" />
                                        <span class="text-xs font-bold">No, not really</span>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center text-center animate-in fade-in zoom-in duration-500">
                                <div class="h-16 w-16 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 mb-4 dark:bg-emerald-500/10">
                                    <CheckCircle2 class="size-8" />
                                </div>
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Thanks for your feedback!</h3>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your input helps us improve the knowledge base for everyone.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Search Box -->
                    <div class="mt-8 rounded-3xl bg-slate-900 p-8 text-white">
                        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-xl font-bold">Didn't find what you needed?</h3>
                                <p class="text-sm text-slate-400 mt-1">Try searching another topic or contact our support.</p>
                            </div>
                            <div class="relative w-full max-w-sm">
                                <Search class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-500" />
                                <input
                                    type="text"
                                    placeholder="Search again..."
                                    class="h-12 w-full rounded-xl border-0 bg-white/10 pl-11 pr-4 text-sm font-medium text-white placeholder:text-slate-500 focus:bg-white/20 focus:ring-0 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </main>
    </div>
</template>
