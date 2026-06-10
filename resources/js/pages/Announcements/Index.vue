<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Plus,
    Search,
    Trash2,
    Eye,
    Edit2,
    Megaphone,
    Calendar,
    Pin,
    AlertCircle,
    CheckCircle2,
    Archive,
    SlidersHorizontal,
    RefreshCcw,
    X,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Loader2,
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
import * as announcementRoutes from '@/routes/announcements';
import { format } from 'date-fns';

interface Category {
    id: number;
    name: string;
    color: string;
}

interface Announcement {
    id: number;
    uuid: string;
    title: string;
    summary: string;
    category: Category;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    visibility: string;
    status: 'draft' | 'scheduled' | 'published' | 'archived';
    is_pinned: boolean;
    publish_at: string;
    published_at: string;
    publication_date: string;
    created_at: string;
    attachments_count: number;
    creator?: { name: string };
}

interface Props {
    announcements: {
        data: Announcement[];
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
    categories: Category[];
    filters: any;
    isSuperAdmin: boolean;
    can: {
        createAnnouncements: boolean;
        editAnnouncements: boolean;
        publishAnnouncements: boolean;
        archiveAnnouncements: boolean;
        deleteAnnouncements: boolean;
    };
}

const props = defineProps<Props>();
const showAdminColumns = computed(() => props.isSuperAdmin);
const canManageActions = computed(
    () =>
        props.can.editAnnouncements ||
        props.can.publishAnnouncements ||
        props.can.archiveAnnouncements ||
        props.can.deleteAnnouncements,
);
const tableColumnCount = computed(() => (showAdminColumns.value ? 8 : 4));

const search = ref(props.filters.search || '');
const filterOpen = ref(false);
const filters = ref({
    status: props.filters.status || '',
    category_id: props.filters.category_id || '',
    priority: props.filters.priority || '',
});

const deleteId = ref<number | null>(null);

const activeFilterCount = computed(() => {
    return Object.entries(filters.value)
        .filter(([key]) => showAdminColumns.value || key === 'category_id')
        .filter(([, value]) => Boolean(value)).length;
});

const applyFilters = () => {
    router.get(
        announcementRoutes.index.url(),
        {
            search: search.value,
            ...filters.value,
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
        priority: '',
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

const deleteAnnouncement = () => {
    if (deleteId.value) {
        router.delete(announcementRoutes.destroy.url(deleteId.value), {
            onSuccess: () => (deleteId.value = null),
            preserveScroll: true,
        });
    }
};

const publish = (id: number) => {
    router.patch(
        announcementRoutes.publish.url(id),
        {},
        { preserveScroll: true },
    );
};

const archive = (id: number) => {
    router.patch(
        announcementRoutes.archive.url(id),
        {},
        { preserveScroll: true },
    );
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200';
        case 'high':
            return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-400/30 dark:bg-orange-500/10 dark:text-orange-200';
        case 'normal':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-500/10 dark:text-blue-200';
        case 'low':
            return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-600';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'published':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200';
        case 'scheduled':
            return 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-400/30 dark:bg-purple-500/10 dark:text-purple-200';
        case 'draft':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200';
        case 'archived':
            return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default:
            return 'border-slate-200 bg-slate-50 text-slate-600';
    }
};
</script>

<template>
    <Head title="Announcements" />

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
                        <Megaphone class="size-3.5 text-sky-600" />
                        Bulletin Board
                    </div>
                    <h1
                        class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white"
                    >
                        Announcements
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        {{
                            isSuperAdmin
                                ? 'Manage system-wide announcements, advisories, and notices for all users.'
                                : 'Browse published advisories and notices shared with your account.'
                        }}
                    </p>
                </div>

                <Link
                    v-if="can.createAnnouncements"
                    :href="announcementRoutes.create.url()"
                >
                    <Button class="h-10 rounded-md px-4">
                        <Plus class="mr-2 size-4" />
                        Create Announcement
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
                        All Announcements
                    </h2>
                    <p class="text-xs font-medium text-slate-500">
                        Showing
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ announcements.meta.from ?? 0 }}-{{
                                announcements.meta.to ?? 0
                            }}</span
                        >
                        of
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ announcements.meta.total }}</span
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
                            placeholder="Search by title, summary, or content..."
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
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
                                ? 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'
                        "
                    >
                        <SlidersHorizontal class="size-3.5" />
                        Filters
                        <span
                            v-if="activeFilterCount > 0"
                            class="inline-flex size-4 items-center justify-center rounded-full bg-sky-600 text-[9px] font-bold text-white"
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
                        class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                        :class="
                            showAdminColumns
                                ? 'sm:grid-cols-3'
                                : 'sm:grid-cols-1'
                        "
                    >
                        <label
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Category
                            <select
                                v-model="filters.category_id"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All Categories</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </label>
                        <label
                            v-if="showAdminColumns"
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Status
                            <select
                                v-model="filters.status"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </label>
                        <label
                            v-if="showAdminColumns"
                            class="grid gap-1 text-xs font-bold text-slate-600 dark:text-slate-300"
                        >
                            Priority
                            <select
                                v-model="filters.priority"
                                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-xs font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </label>
                    </div>
                </Transition>
            </div>

            <!-- Table Content -->
            <div class="relative min-h-0 w-full flex-1 overflow-auto">
                <table
                    class="w-full text-sm"
                    :class="
                        showAdminColumns ? 'min-w-[1120px]' : 'min-w-[720px]'
                    "
                >
                    <thead
                        class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                    >
                        <tr>
                            <th
                                class="px-4 py-3"
                                :class="
                                    showAdminColumns ? 'w-[330px]' : 'w-[46%]'
                                "
                            >
                                Announcement
                            </th>
                            <th class="px-4 py-3">Category</th>
                            <th
                                v-if="showAdminColumns"
                                class="px-4 py-3 text-center"
                            >
                                Priority
                            </th>
                            <th v-if="showAdminColumns" class="px-4 py-3">
                                Target
                            </th>
                            <th
                                v-if="showAdminColumns"
                                class="px-4 py-3 text-center"
                            >
                                Status
                            </th>
                            <th v-if="showAdminColumns" class="px-4 py-3">
                                Created By
                            </th>
                            <th class="px-4 py-3">Publication Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-white/10"
                    >
                        <tr
                            v-for="item in announcements.data"
                            :key="item.id"
                            class="group transition-colors hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center gap-2">
                                        <Pin
                                            v-if="item.is_pinned"
                                            class="size-3 shrink-0 fill-sky-500 text-sky-500"
                                        />
                                        <span
                                            class="line-clamp-1 font-bold text-slate-900 dark:text-white"
                                            >{{ item.title }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        <span
                                            v-if="item.attachments_count > 0"
                                            class="flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-sky-600 dark:bg-white/10 dark:text-sky-400"
                                        >
                                            <AlertCircle class="size-2.5" />
                                            {{ item.attachments_count }} files
                                        </span>
                                    </div>
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
                            <td
                                v-if="showAdminColumns"
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase',
                                        getPriorityColor(item.priority),
                                    ]"
                                >
                                    {{ item.priority }}
                                </span>
                            </td>
                            <td v-if="showAdminColumns" class="px-4 py-3">
                                <span
                                    class="text-xs font-semibold text-slate-600 capitalize dark:text-slate-300"
                                >
                                    {{ item.visibility.replace('_', ' ') }}
                                </span>
                            </td>
                            <td
                                v-if="showAdminColumns"
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase',
                                        getStatusColor(item.status),
                                    ]"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td v-if="showAdminColumns" class="px-4 py-3">
                                <span
                                    class="text-xs font-semibold text-slate-600 dark:text-slate-300"
                                    >{{ item.creator?.name ?? 'System' }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex flex-col text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        v-if="item.published_at"
                                        class="text-emerald-600 dark:text-emerald-400"
                                        >{{
                                            showAdminColumns
                                                ? 'Published: '
                                                : ''
                                        }}{{
                                            format(
                                                new Date(item.published_at),
                                                'MMM d, yyyy',
                                            )
                                        }}</span
                                    >
                                    <span v-else-if="item.publish_at"
                                        >{{ showAdminColumns ? 'Sched: ' : ''
                                        }}{{
                                            format(
                                                new Date(item.publish_at),
                                                'MMM d, yyyy',
                                            )
                                        }}</span
                                    >
                                    <span v-else
                                        >{{ showAdminColumns ? 'Created: ' : ''
                                        }}{{
                                            format(
                                                new Date(item.publication_date),
                                                'MMM d, yyyy',
                                            )
                                        }}</span
                                    >
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
                                                router.visit(
                                                    announcementRoutes.show.url(
                                                        item.id,
                                                    ),
                                                )
                                            "
                                        >
                                            <Eye class="mr-2 size-4" />
                                            View Details
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="can.editAnnouncements"
                                            @click="
                                                router.visit(
                                                    announcementRoutes.edit.url(
                                                        item.id,
                                                    ),
                                                )
                                            "
                                        >
                                            <Edit2 class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="canManageActions"
                                        />
                                        <DropdownMenuItem
                                            v-if="
                                                can.publishAnnouncements &&
                                                item.status !== 'published'
                                            "
                                            @click="publish(item.id)"
                                            class="text-emerald-600"
                                        >
                                            <CheckCircle2 class="mr-2 size-4" />
                                            Publish Now
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="
                                                can.archiveAnnouncements &&
                                                item.status === 'published'
                                            "
                                            @click="archive(item.id)"
                                        >
                                            <Archive class="mr-2 size-4" />
                                            Archive
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator
                                            v-if="can.deleteAnnouncements"
                                        />
                                        <DropdownMenuItem
                                            v-if="can.deleteAnnouncements"
                                            @click="confirmDelete(item.id)"
                                            class="text-destructive hover:bg-red-50 dark:hover:bg-red-500/10"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                        <tr v-if="announcements.data.length === 0">
                            <td
                                :colspan="tableColumnCount"
                                class="p-12 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-3 opacity-50"
                                >
                                    <Megaphone
                                        class="size-10 text-slate-300 dark:text-slate-600"
                                    />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No announcements found
                                    </p>
                                    <p
                                        class="mx-auto max-w-xs text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Try adjusting your filters or create
                                        your first announcement to reach your
                                        audience.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="announcements.meta.last_page > 1"
                class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <p class="text-xs font-medium text-slate-500">
                    Page {{ announcements.meta.current_page }} of
                    {{ announcements.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="announcements.meta.current_page === 1"
                        @click="navigatePage(announcements.links[0]?.url)"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            !announcements.links[
                                announcements.meta.current_page - 1
                            ]?.url
                        "
                        @click="
                            navigatePage(
                                announcements.links[
                                    announcements.meta.current_page - 1
                                ]?.url,
                            )
                        "
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            announcements.meta.current_page ===
                            announcements.meta.last_page
                        "
                        @click="
                            navigatePage(
                                announcements.links[
                                    announcements.meta.current_page + 1
                                ]?.url,
                            )
                        "
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            announcements.meta.current_page ===
                            announcements.meta.last_page
                        "
                        @click="
                            navigatePage(
                                announcements.links[
                                    announcements.links.length - 1
                                ]?.url,
                            )
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
                    Delete Announcement
                </h2>
            </div>
            <p class="mb-6 text-sm text-muted-foreground">
                Are you sure you want to delete this announcement? This will
                permanently remove the record and all associated attachments.
                This action cannot be undone.
            </p>
            <div class="flex justify-end gap-3">
                <Button variant="outline" @click="deleteId = null"
                    >Cancel</Button
                >
                <Button variant="destructive" @click="deleteAnnouncement"
                    >Delete Permanently</Button
                >
            </div>
        </div>
    </div>
</template>
