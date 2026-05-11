<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search,
    MessageSquare,
    ChevronRight,
    HelpCircle,
    Star,
    ArrowRight,
    Tag,
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

const applyFilters = () => {
    router.get(
        faqRoutes.index.url(),
        {
            search: search.value,
            category: activeCategory.value,
        },
        { preserveState: true, preserveScroll: true, replace: true }
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
        { preserveState: true, preserveScroll: true }
    );
};

const clearSearch = () => {
    search.value = '';
};
</script>

<template>
    <Head title="Knowledge Base & FAQs" />

    <div class="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
        <!-- Hero Search Section -->
        <section class="relative overflow-hidden bg-emerald-600 py-16 px-4 dark:bg-emerald-900/40">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-emerald-400/20 via-transparent to-transparent"></div>
            <div class="relative mx-auto max-w-4xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-50 mb-6 backdrop-blur-sm border border-emerald-400/20">
                    <HelpCircle class="size-3.5" />
                    How can we help you today?
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Knowledge Base</h1>
                <p class="mt-4 text-lg font-medium text-emerald-50/80">Search our frequently asked questions for instant answers to your inquiries.</p>
                
                <div class="relative mt-10 mx-auto max-w-2xl">
                    <div class="group relative">
                        <Search class="absolute top-1/2 left-5 size-5 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Type keywords like 'enrollment', 'grades', or 'password'..."
                            class="h-16 w-full rounded-2xl border-0 bg-white pl-14 pr-14 text-base font-medium text-slate-900 shadow-2xl shadow-emerald-900/20 focus:ring-4 focus:ring-emerald-500/20 focus:outline-none dark:bg-slate-900 dark:text-white dark:shadow-none"
                        />
                        <button v-if="search" @click="clearSearch" class="absolute top-1/2 right-5 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                            <X class="size-5" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <main class="flex w-full flex-1 flex-col gap-8 px-6 py-8 lg:flex-row">
            <!-- Sidebar: Categories -->
            <aside class="w-full shrink-0 lg:w-64">
                <div class="sticky top-24 space-y-6">
                    <div>
                        <h2 class="mb-4 text-xs font-bold uppercase tracking-widest text-slate-400">Categories</h2>
                        <div class="space-y-1">
                            <button
                                @click="selectCategory(null)"
                                :class="['flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition-all', !activeCategory ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200 dark:shadow-none' : 'text-slate-600 hover:bg-white hover:text-emerald-600 dark:text-slate-400 dark:hover:bg-white/5']"
                            >
                                <LayoutGrid class="size-4" />
                                All Topics
                            </button>
                            <button
                                v-for="cat in categories.data"
                                :key="cat.id"
                                @click="selectCategory(cat.slug)"
                                :class="['flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition-all', activeCategory === cat.slug ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200 dark:shadow-none' : 'text-slate-600 hover:bg-white hover:text-emerald-600 dark:text-slate-400 dark:hover:bg-white/5']"
                            >
                                <div class="h-2 w-2 rounded-full" :style="{ backgroundColor: cat.color }"></div>
                                {{ cat.name }}
                            </button>
                        </div>
                    </div>

                    <!-- Contact Support Widget -->
                    <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-xl">
                        <MessageSquare class="size-8 text-emerald-400 mb-4" />
                        <h3 class="text-lg font-bold mb-2">Still need help?</h3>
                        <p class="text-xs text-slate-400 mb-4">Our support team is available during office hours to assist you.</p>
                        <Button class="w-full bg-emerald-500 hover:bg-emerald-600 text-white border-0 font-bold">
                            Contact Support
                        </Button>
                    </div>
                </div>
            </aside>

            <!-- Content Area -->
            <div class="flex-1 space-y-10">
                <!-- Featured FAQs (Only on landing) -->
                <section v-if="!activeCategory && !search && featuredFaqs.data.length > 0">
                    <div class="flex items-center gap-2 mb-6">
                        <Star class="size-5 text-amber-500 fill-amber-500" />
                        <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">Featured Questions</h2>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Link
                            v-for="faq in featuredFaqs.data"
                            :key="faq.id"
                            :href="`/faqs/view/${faq.id}`"
                            class="group flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 transition-all hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:hover:border-emerald-500/30"
                        >
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-600 dark:bg-emerald-500/10">{{ faq.category.name }}</span>
                                </div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-emerald-600 transition-colors">{{ faq.question }}</h3>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 line-clamp-2">{{ faq.summary }}</p>
                            </div>
                            <div class="mt-6 flex items-center text-xs font-bold text-emerald-600 group-hover:gap-2 transition-all">
                                Read Article
                                <ArrowRight class="ml-1 size-3.5" />
                            </div>
                        </Link>
                    </div>
                </section>

                <!-- Results List -->
                <section>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <BookOpen class="size-5 text-emerald-600" />
                            <h2 class="text-xl font-extrabold text-slate-900 dark:text-white">
                                {{ search ? 'Search Results' : (activeCategory ? categories.data.find(c => c.slug === activeCategory)?.name : 'All Articles') }}
                            </h2>
                        </div>
                        <p class="text-xs font-bold text-slate-400">{{ faqs.data.length }} Articles found</p>
                    </div>

                    <div v-if="faqs.data.length > 0" class="space-y-3">
                        <Link
                            v-for="faq in faqs.data"
                            :key="faq.id"
                            :href="`/faqs/view/${faq.id}`"
                            class="group block rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-500/5 dark:border-white/10 dark:bg-slate-900 dark:hover:border-emerald-500/30"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: faq.category.color }"></div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 group-hover:text-emerald-500 transition-colors">{{ faq.category.name }}</span>
                                    </div>
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white line-clamp-1 group-hover:text-emerald-600 transition-colors">{{ faq.question }}</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 line-clamp-1">{{ faq.summary }}</p>
                                </div>
                                <div class="mt-1 rounded-full bg-slate-50 p-2 text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-600 transition-all dark:bg-white/5">
                                    <ChevronRight class="size-5" />
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-200 py-24 px-4 text-center dark:border-white/10">
                        <div class="h-20 w-20 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-6 dark:bg-white/5">
                            <Search class="size-10" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">No results found</h3>
                        <p class="mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">We couldn't find any articles matching your search criteria. Try using different keywords or browse categories.</p>
                        <Button @click="clearSearch" variant="outline" class="mt-8 font-bold">
                            Clear all filters
                        </Button>
                    </div>
                </section>
            </div>
        </main>

        <!-- Footer Accent -->
        <footer class="border-t border-slate-200 bg-white py-12 dark:border-white/5 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-4 text-center">
                <p class="text-sm font-medium text-slate-500">StudentHub Knowledge Base &copy; 2026. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
