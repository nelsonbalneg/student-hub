<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Plus,
    Search,
    Trash2,
    Eye,
    Edit2,
    HelpCircle,
    RefreshCcw,
    X,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Loader2,
    Star,
    Globe,
    Users,
    UserCircle,
    ShieldAlert,
    CheckCircle2,
    Archive,
    SlidersHorizontal,
    MessageSquare,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import * as faqRoutes from '@/routes/faqs/manage/faqs';
import { format } from 'date-fns';

interface Category {
    id: number;
    name: string;
    color: string;
}

interface Faq {
    id: number;
    question: string;
    summary: string;
    category: Category;
    is_featured: boolean;
    visibility: string;
    status: 'draft' | 'published' | 'archived';
    view_count: number;
    helpful_count: number;
    not_helpful_count: number;
    published_at: string;
    created_at: string;
    creator?: { name: string };
}

interface Props {
    faqs: {
        data: Faq[];
        links: any[];
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
    };
    categories: { data: Category[] };
    filters: {
        search?: string;
        category_id?: string;
        status?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status || '',
    category_id: props.filters.category_id || '',
});

const deleteId = ref<number | null>(null);

const activeFilterCount = computed(() => {
    return Object.entries(filters.value).filter(([, value]) => Boolean(value))
        .length;
});

const applyFilters = () => {
    router.get(
        faqRoutes.index.url(),
        {
            search: search.value,
            ...filters.value,
            per_page: props.filters.per_page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimeout: any;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

watch(filters, applyFilters, { deep: true });

const clearFilters = () => {
    search.value = '';
    filters.value = {
        status: '',
        category_id: '',
    };
};

const navigatePage = (url?: string | null) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const confirmDelete = (id: number) => {
    deleteId.value = id;
};

const deleteFaq = () => {
    if (deleteId.value) {
        router.delete(faqRoutes.destroy.url(deleteId.value), {
            onSuccess: () => (deleteId.value = null),
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'published':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200';
        case 'draft':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200';
        case 'archived':
            return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-600';
    }
};

const getVisibilityIcon = (visibility: string) => {
    switch (visibility) {
        case 'public':
            return Globe;
        case 'students':
            return Users;
        case 'employees':
            return UserCircle;
        case 'admin':
            return ShieldAlert;
        default:
            return Eye;
    }
};
</script>

<template>
    <Head title="Manage FAQs" />

    <div
        class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 lg:p-6 dark:bg-slate-950"
    >
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <div
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 uppercase shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <HelpCircle class="size-3.5 text-emerald-600" />
                        Knowledge Base Admin
                    </div>
                    <h1
                        class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white"
                    >
                        Frequently Asked Questions
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Create and manage help articles to provide self-service
                        support for students and employees.
                    </p>
                </div>

                <Link :href="faqRoutes.create.url()">
                    <Button
                        class="h-10 rounded-md border-0 bg-emerald-600 px-4 text-white hover:bg-emerald-700"
                    >
                        <Plus class="mr-2 size-4" />
                        Create FAQ
                    </Button>
                </Link>
            </div>
        </section>

        <!-- Table Section -->
        <section
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <!-- Table Toolbar -->
            <div
                class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 dark:border-white/10"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <h2
                        class="text-sm font-bold text-slate-950 dark:text-white"
                    >
                        All Help Articles
                    </h2>
                    <p class="text-xs font-medium text-slate-500">
                        Showing
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ faqs.meta.from ?? 0 }}-{{
                                faqs.meta.to ?? 0
                            }}</span
                        >
                        of
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ faqs.meta.total }}</span
                        >
                    </p>
                </div>

                <div
                    class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center"
                >
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by question or content..."
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute top-1/2 right-2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-white"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <button
                        @click="filterOpen = !filterOpen"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-xs font-bold transition"
                        :class="
                            filterOpen || activeFilterCount > 0
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'
                        "
                    >
                        <SlidersHorizontal class="size-3.5" />
                        Filters
                        <span
                            v-if="activeFilterCount > 0"
                            class="inline-flex size-4 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <button
                        @click="clearFilters"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                    >
                        <RefreshCcw class="size-3.5" />
                        Reset
                    </button>
                </div>

                <!-- Filters Panel -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="-translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="-translate-y-2 opacity-0"
                >
                    <div
                        v-if="filterOpen"
                        class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5"
                    >
                        <label
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Category
                            <select
                                v-model="filters.category_id"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All Categories</option>
                                <option
                                    v-for="cat in categories.data"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </label>
                        <label
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Status
                            <select
                                v-model="filters.status"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </label>
                    </div>
                </Transition>
            </div>

            <!-- Table Content -->
            <div class="relative min-h-0 w-full flex-1 overflow-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead
                        class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                    >
                        <tr>
                            <th class="w-[400px] px-4 py-3">Question</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-center">Visibility</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Engagement</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-white/10"
                    >
                        <tr
                            v-for="item in faqs.data"
                            :key="item.id"
                            class="group transition-colors hover:bg-slate-50/80 dark:hover:bg-white/5"
                        >
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-2">
                                        <Star
                                            v-if="item.is_featured"
                                            class="size-3 shrink-0 fill-amber-500 text-amber-500"
                                        />
                                        <span
                                            class="line-clamp-1 font-bold text-slate-900 dark:text-white"
                                            >{{ item.question }}</span
                                        >
                                    </div>
                                    <span
                                        class="line-clamp-1 text-[10px] text-slate-500 dark:text-slate-400"
                                        >{{
                                            item.summary ||
                                            'No summary provided'
                                        }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 w-2 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                item.category.color,
                                        }"
                                    ></div>
                                    <span
                                        class="text-xs font-medium text-slate-700 dark:text-slate-300"
                                        >{{ item.category.name }}</span
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div
                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600 uppercase dark:border-white/10 dark:text-slate-400"
                                >
                                    <component
                                        :is="getVisibilityIcon(item.visibility)"
                                        class="size-3"
                                    />
                                    {{ item.visibility }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase',
                                        getStatusColor(item.status),
                                    ]"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div
                                    class="flex items-center justify-center gap-3 text-[10px] font-bold"
                                >
                                    <div class="flex flex-col items-center">
                                        <Eye
                                            class="mb-0.5 size-3 text-slate-400"
                                        />
                                        <span
                                            class="text-slate-600 dark:text-slate-300"
                                            >{{ item.view_count }}</span
                                        >
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <MessageSquare
                                            class="mb-0.5 size-3 text-emerald-400"
                                        />
                                        <span
                                            class="text-emerald-600 dark:text-emerald-400"
                                            >{{ item.helpful_count }}</span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 hover:bg-slate-100 dark:hover:bg-white/10"
                                        >
                                            <MoreHorizontal
                                                class="size-4 text-slate-500"
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-48"
                                    >
                                        <DropdownMenuItem
                                            @click="
                                                navigatePage(
                                                    faqRoutes.edit.url(item.id),
                                                )
                                            "
                                        >
                                            <Edit2 class="mr-2 size-4" />
                                            Edit Article
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="
                                                router.visit(
                                                    `/faqs/view/${item.id}`,
                                                )
                                            "
                                        >
                                            <Eye class="mr-2 size-4" />
                                            Preview Public
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            @click="confirmDelete(item.id)"
                                            class="text-destructive hover:bg-red-50 dark:hover:bg-red-500/10"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete Article
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                        <tr v-if="faqs.data.length === 0">
                            <td colspan="6" class="p-12 text-center">
                                <div
                                    class="flex flex-col items-center justify-center gap-3 opacity-50"
                                >
                                    <HelpCircle
                                        class="size-10 text-slate-300 dark:text-slate-600"
                                    />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No FAQ articles found
                                    </p>
                                    <p
                                        class="mx-auto max-w-xs text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Try adjusting your search or filters to
                                        find what you're looking for.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="faqs.meta.last_page > 1"
                class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <p class="text-xs font-medium text-slate-500">
                    Page {{ faqs.meta.current_page }} of
                    {{ faqs.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="faqs.meta.current_page === 1"
                        @click="navigatePage(faqs.links[0]?.url)"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="!faqs.links[faqs.meta.current_page - 1]?.url"
                        @click="
                            navigatePage(
                                faqs.links[faqs.meta.current_page - 1]?.url,
                            )
                        "
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            faqs.meta.current_page === faqs.meta.last_page
                        "
                        @click="
                            navigatePage(
                                faqs.links[faqs.meta.current_page + 1]?.url,
                            )
                        "
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            faqs.meta.current_page === faqs.meta.last_page
                        "
                        @click="
                            navigatePage(faqs.links[faqs.links.length - 1]?.url)
                        "
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
        v-if="deleteId"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md animate-in rounded-xl border bg-card p-6 shadow-2xl duration-200 fade-in zoom-in dark:bg-slate-900"
        >
            <div class="mb-4 flex items-center gap-3 text-destructive">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-500/10"
                >
                    <Trash2 class="size-5" />
                </div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                    Delete Article
                </h2>
            </div>
            <p class="mb-6 text-sm text-muted-foreground">
                Are you sure you want to delete this FAQ article? This action is
                permanent and cannot be undone.
            </p>
            <div class="flex justify-end gap-3">
                <Button
                    variant="ghost"
                    @click="deleteId = null"
                    class="font-bold text-slate-600"
                    >Cancel</Button
                >
                <Button
                    variant="destructive"
                    @click="deleteFaq"
                    class="px-6 font-bold shadow-lg shadow-red-200 dark:shadow-none"
                    >Delete Permanently</Button
                >
            </div>
        </div>
    </div>
</template>
