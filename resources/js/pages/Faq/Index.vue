<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    CircleHelp,
    FileQuestion,
    Filter,
    MessageSquare,
    Search,
    ArrowRight,
    Sparkles,
    X,
    LayoutGrid,
    BookOpen,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import * as faqRoutes from '@/routes/faqs/index';

interface Category {
    id: number;
    name: string;
    slug: string;
    icon: string;
    color: string;
    description: string;
}

interface Faq {
    id: number;
    question: string;
    summary: string;
    category: { name: string; color: string };
    is_featured: boolean;
    published_at: string;
}

interface Props {
    faqs: {
        data: Faq[];
        meta: any;
    };
    categories: { data: Category[] };
    featuredFaqs: { data: Faq[] };
    filters: {
        search?: string;
        category?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const activeCategory = computed(() => props.filters.category || null);
const activeCategoryName = computed(() => {
    if (!activeCategory.value) {
        return 'All Topics';
    }

    return (
        props.categories.data.find(
            (category) => category.slug === activeCategory.value,
        )?.name || 'Selected Topic'
    );
});

const articleCount = computed(
    () => props.faqs.meta?.total ?? props.faqs.data.length,
);

const applyFilters = () => {
    router.get(
        faqRoutes.index.url(),
        {
            search: search.value,
            category: activeCategory.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimeout: any;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

const selectCategory = (slug: string | null) => {
    router.get(
        faqRoutes.index.url(),
        {
            search: search.value,
            category: slug,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const clearSearch = () => {
    search.value = '';
};
</script>

<template>
    <Head title="Knowledge Base & FAQs" />

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
                        <CircleHelp class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-xs font-bold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            Student Support
                        </p>
                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                        >
                            Knowledge Base
                        </h1>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            Find official answers for enrollment, grades,
                            accounts, academic services, and StudentHub
                            workflows.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[420px]">
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Articles
                        </p>
                        <p
                            class="mt-1 text-sm font-bold text-slate-950 dark:text-white"
                        >
                            {{ articleCount }} available
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Current View
                        </p>
                        <p
                            class="mt-1 truncate text-sm font-bold text-slate-950 dark:text-white"
                        >
                            {{ activeCategoryName }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <main
            class="grid w-full flex-1 gap-5 p-4 lg:grid-cols-[280px_minmax(0,1fr)] lg:p-8"
        >
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <div class="flex items-center gap-2">
                            <Filter
                                class="size-4 text-slate-500 dark:text-slate-400"
                            />
                            <h2
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                Browse Topics
                            </h2>
                        </div>
                    </div>

                    <div class="p-2">
                        <button
                            @click="selectCategory(null)"
                            :class="[
                                'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition',
                                !activeCategory
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white',
                            ]"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <LayoutGrid class="size-4 shrink-0" />
                                <span class="truncate">All Topics</span>
                            </span>
                            <ChevronRight class="size-4 shrink-0 opacity-50" />
                        </button>

                        <button
                            v-for="cat in categories.data"
                            :key="cat.id"
                            @click="selectCategory(cat.slug)"
                            :class="[
                                'mt-1 flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold transition',
                                activeCategory === cat.slug
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white',
                            ]"
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :style="{ backgroundColor: cat.color }"
                                ></span>
                                <span class="truncate">{{ cat.name }}</span>
                            </span>
                            <ChevronRight class="size-4 shrink-0 opacity-50" />
                        </button>
                    </div>
                </div>

                <div
                    class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                        >
                            <MessageSquare class="size-4" />
                        </div>
                        <div>
                            <h3
                                class="text-sm font-bold text-slate-950 dark:text-white"
                            >
                                Need Assistance?
                            </h3>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Contact support during office hours.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="space-y-5">
                <div
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="border-b border-slate-200 p-4 dark:border-white/10"
                    >
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search FAQs by keyword, service, or issue..."
                                class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 pr-11 pl-11 text-sm font-medium text-slate-950 transition outline-none placeholder:text-slate-400 focus:border-emerald-300 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-emerald-500/40 dark:focus:bg-slate-950"
                            />
                            <button
                                v-if="search"
                                type="button"
                                @click="clearSearch"
                                class="absolute top-1/2 right-3 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-white"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <BookOpen class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <h2
                                    class="truncate text-base font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        search
                                            ? 'Search Results'
                                            : activeCategoryName
                                    }}
                                </h2>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ articleCount }} matching articles
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="search || activeCategory"
                            type="button"
                            @click="
                                selectCategory(null);
                                clearSearch();
                            "
                            class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        >
                            Clear filters
                        </button>
                    </div>
                </div>

                <section
                    v-if="
                        !activeCategory &&
                        !search &&
                        featuredFaqs.data.length > 0
                    "
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            <Sparkles class="size-4" />
                        </div>
                        <div>
                            <h2
                                class="text-base font-bold text-slate-950 dark:text-white"
                            >
                                Featured Questions
                            </h2>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Frequently referenced help articles.
                            </p>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-200 dark:divide-white/10">
                        <Link
                            v-for="faq in featuredFaqs.data"
                            :key="faq.id"
                            :href="`/faqs/view/${faq.id}`"
                            class="group flex items-start justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <div class="min-w-0">
                                <div class="mb-2 flex items-center gap-2">
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor: faq.category.color,
                                        }"
                                    ></span>
                                    <span
                                        class="text-xs font-bold tracking-wide text-slate-400 uppercase dark:text-slate-500"
                                    >
                                        {{ faq.category.name }}
                                    </span>
                                </div>
                                <h3
                                    class="line-clamp-1 text-sm font-bold text-slate-950 transition group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-300"
                                >
                                    {{ faq.question }}
                                </h3>
                                <p
                                    class="mt-1 line-clamp-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ faq.summary }}
                                </p>
                            </div>
                            <ArrowRight
                                class="mt-1 size-4 shrink-0 text-slate-400 transition group-hover:text-emerald-600"
                            />
                        </Link>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900/60"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200"
                            >
                                <FileQuestion class="size-4" />
                            </div>
                            <div>
                                <h2
                                    class="text-base font-bold text-slate-950 dark:text-white"
                                >
                                    Articles
                                </h2>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Select an article to view the complete
                                    answer.
                                </p>
                            </div>
                        </div>
                        <p
                            class="hidden text-xs font-bold tracking-wide text-slate-400 uppercase sm:block"
                        >
                            {{ faqs.data.length }} shown
                        </p>
                    </div>

                    <div
                        v-if="faqs.data.length > 0"
                        class="divide-y divide-slate-200 dark:divide-white/10"
                    >
                        <Link
                            v-for="faq in faqs.data"
                            :key="faq.id"
                            :href="`/faqs/view/${faq.id}`"
                            class="group flex items-start justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <div class="min-w-0">
                                <div class="mb-2 flex items-center gap-3">
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            backgroundColor: faq.category.color,
                                        }"
                                    ></span>
                                    <span
                                        class="text-xs font-bold tracking-wide text-slate-400 uppercase transition group-hover:text-emerald-600 dark:text-slate-500"
                                    >
                                        {{ faq.category.name }}
                                    </span>
                                </div>
                                <h3
                                    class="line-clamp-1 text-sm font-bold text-slate-950 transition group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-300"
                                >
                                    {{ faq.question }}
                                </h3>
                                <p
                                    class="mt-1 line-clamp-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {{ faq.summary }}
                                </p>
                            </div>
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition group-hover:bg-emerald-50 group-hover:text-emerald-600 dark:group-hover:bg-emerald-500/10 dark:group-hover:text-emerald-300"
                            >
                                <ChevronRight class="size-4" />
                            </div>
                        </Link>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center px-4 py-20 text-center"
                    >
                        <div
                            class="mb-5 flex size-14 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5"
                        >
                            <Search class="size-7" />
                        </div>
                        <h3
                            class="text-base font-bold text-slate-950 dark:text-white"
                        >
                            No results found
                        </h3>
                        <p
                            class="mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            No FAQ articles match the current keyword or topic.
                            Try another term or clear your filters.
                        </p>
                        <Button
                            @click="clearSearch"
                            variant="outline"
                            class="mt-6 font-bold"
                        >
                            Clear all filters
                        </Button>
                    </div>
                </section>
            </section>
        </main>
    </div>
</template>
