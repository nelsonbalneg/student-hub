<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Plus,
    Search,
    Trash2,
    X,
    FolderTree,
    Edit2,
    FolderOpen,
    RefreshCcw,
    Loader2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import * as categoryRoutes from '@/routes/announcements/categories';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Category {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string;
    description: string;
    is_active: boolean;
}

interface Props {
    categories: {
        data: Category[];
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
}

const props = defineProps<Props>();

const search = ref('');
const modal = ref<{
    type: 'create' | 'edit' | 'delete';
    category?: Category;
} | null>(null);

const form = useForm({
    name: '',
    color: '#3b82f6',
    icon: 'Folder',
    description: '',
    is_active: true,
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    modal.value = { type: 'create' };
};

const openEdit = (category: Category) => {
    form.clearErrors();
    form.name = category.name;
    form.color = category.color || '#3b82f6';
    form.icon = category.icon || 'Folder';
    form.description = category.description || '';
    form.is_active = !!category.is_active;
    modal.value = { type: 'edit', category };
};

const submit = () => {
    if (modal.value?.type === 'edit') {
        form.patch(categoryRoutes.update.url(modal.value.category!.id), {
            onSuccess: () => (modal.value = null),
            preserveScroll: true,
        });
    } else {
        form.post(categoryRoutes.store.url(), {
            onSuccess: () => {
                modal.value = null;
                form.reset();
            },
            preserveScroll: true,
        });
    }
};

const confirmDelete = (category: Category) => {
    modal.value = { type: 'delete', category };
};

const deleteCategory = () => {
    if (modal.value?.category) {
        router.delete(categoryRoutes.destroy.url(modal.value.category.id), {
            onSuccess: () => (modal.value = null),
            preserveScroll: true,
        });
    }
};

let searchTimeout: any;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            categoryRoutes.index.url(),
            { search: value },
            { preserveState: true, replace: true },
        );
    }, 400);
});

const navigatePage = (url: string | null) => {
    if (url) {
        router.get(
            url,
            { search: search.value },
            { preserveState: true, replace: true },
        );
    }
};
</script>

<template>
    <Head title="Announcement Categories" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 lg:p-6">
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <div
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 uppercase shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    >
                        <FolderTree class="size-3.5 text-sky-600" />
                        Taxonomy Management
                    </div>
                    <h1
                        class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white"
                    >
                        Announcement Categories
                    </h1>
                    <p
                        class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Organize your announcements into meaningful categories
                        with distinct colors and descriptors.
                    </p>
                </div>

                <Button @click="openCreate" class="h-10 rounded-md px-4">
                    <Plus class="mr-2 size-4" />
                    Add Category
                </Button>
            </div>
        </section>

        <!-- Main Content -->
        <section
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <!-- Toolbar -->
            <div
                class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 dark:border-white/10"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <h2
                        class="text-sm font-bold text-slate-950 dark:text-white"
                    >
                        Active Categories
                    </h2>
                    <p class="text-xs font-medium text-slate-500">
                        Showing
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ categories.meta.from ?? 0 }}-{{
                                categories.meta.to ?? 0
                            }}</span
                        >
                        of
                        <span
                            class="font-bold text-slate-700 dark:text-slate-200"
                            >{{ categories.meta.total }}</span
                        >
                    </p>
                </div>

                <div
                    class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center"
                >
                    <div class="relative max-w-md flex-1">
                        <Search
                            class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name or description..."
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
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
                        @click="search = ''"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                    >
                        <RefreshCcw class="size-3.5" />
                        Reset
                    </button>
                </div>
            </div>

            <!-- Table Content -->
            <div class="relative w-full overflow-auto">
                <table class="w-full text-sm">
                    <thead
                        class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-3">Category Name</th>
                            <th class="px-4 py-3">Color Code</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="w-[400px] px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-white/10"
                    >
                        <tr
                            v-for="category in categories.data"
                            :key="category.id"
                            class="group transition-colors hover:bg-slate-50 dark:hover:bg-white/5"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-3 w-3 rounded-full border border-black/5 dark:border-white/10"
                                        :style="{
                                            backgroundColor:
                                                category.color || '#3b82f6',
                                        }"
                                    ></div>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white"
                                        >{{ category.name }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                {{ category.color || '#3b82f6' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                    :class="
                                        category.is_active
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                                            : 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </td>
                            <td
                                class="truncate px-4 py-3 text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    category.description ||
                                    'No description provided.'
                                }}
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
                                        class="w-40"
                                    >
                                        <DropdownMenuItem
                                            @click="openEdit(category)"
                                        >
                                            <Edit2 class="mr-2 size-4" />
                                            Edit Category
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            @click="confirmDelete(category)"
                                            class="text-destructive hover:bg-red-50 dark:hover:bg-red-500/10"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                        <tr v-if="categories.data.length === 0">
                            <td colspan="5" class="p-12 text-center">
                                <div
                                    class="flex flex-col items-center justify-center gap-3 opacity-50"
                                >
                                    <FolderOpen
                                        class="size-10 text-slate-300 dark:text-slate-600"
                                    />
                                    <p
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        No categories found
                                    </p>
                                    <p
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Add your first category to start
                                        organizing announcements.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div
                v-if="categories.meta.last_page > 1"
                class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <p class="text-xs font-medium text-slate-500">
                    Page {{ categories.meta.current_page }} of
                    {{ categories.meta.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="categories.meta.current_page === 1"
                        @click="navigatePage(categories.links[0]?.url)"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            !categories.links[categories.meta.current_page - 1]
                                ?.url
                        "
                        @click="
                            navigatePage(
                                categories.links[
                                    categories.meta.current_page - 1
                                ]?.url,
                            )
                        "
                    >
                        <ChevronLeft class="size-4" />
                    </button>

                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            categories.meta.current_page ===
                            categories.meta.last_page
                        "
                        @click="
                            navigatePage(
                                categories.links[
                                    categories.meta.current_page + 1
                                ]?.url,
                            )
                        "
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="
                            categories.meta.current_page ===
                            categories.meta.last_page
                        "
                        @click="
                            navigatePage(
                                categories.links[categories.links.length - 1]
                                    ?.url,
                            )
                        "
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Create/Edit Modal -->
    <div
        v-if="modal && (modal.type === 'create' || modal.type === 'edit')"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md animate-in rounded-xl border bg-card p-6 shadow-2xl duration-200 fade-in zoom-in dark:bg-slate-900"
        >
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-500/10"
                    >
                        <FolderTree class="size-5" />
                    </div>
                    <div>
                        <h2
                            class="text-lg font-bold text-slate-900 dark:text-white"
                        >
                            {{
                                modal.type === 'create'
                                    ? 'Add Category'
                                    : 'Edit Category'
                            }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            Classification for announcements.
                        </p>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    @click="modal = null"
                    class="h-8 w-8"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div class="space-y-2">
                    <label
                        class="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                        >Category Name</label
                    >
                    <input
                        v-model="form.name"
                        class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="e.g. Academic Advisory"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                            >Theme Color</label
                        >
                        <div class="flex gap-2">
                            <input
                                v-model="form.color"
                                type="color"
                                class="h-10 w-12 cursor-pointer rounded border border-slate-200 p-1 dark:border-white/10 dark:bg-slate-950"
                            />
                            <input
                                v-model="form.color"
                                class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                            />
                        </div>
                        <InputError :message="form.errors.color" />
                    </div>
                    <div class="flex items-end pb-2">
                        <label
                            class="group flex cursor-pointer items-center gap-2"
                        >
                            <div class="relative flex items-center">
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-slate-200 bg-white transition-all checked:bg-sky-600 dark:border-white/10 dark:bg-slate-950"
                                />
                                <X
                                    v-if="!form.is_active"
                                    class="absolute top-1/2 left-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 text-slate-400"
                                />
                                <CheckCircle2
                                    v-else
                                    class="absolute top-1/2 left-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 text-white"
                                />
                            </div>
                            <span
                                class="text-sm font-bold text-slate-700 transition-colors group-hover:text-sky-600 dark:text-slate-300"
                                >Active Status</span
                            >
                        </label>
                    </div>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                        >Description</label
                    >
                    <textarea
                        v-model="form.description"
                        class="flex min-h-[100px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="Briefly describe the purpose of this category..."
                    ></textarea>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="modal = null"
                        class="font-bold"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="font-bold shadow-sky-200 dark:shadow-none"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 size-4 animate-spin"
                        />
                        {{
                            modal.type === 'create'
                                ? 'Create Category'
                                : 'Save Changes'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div
        v-if="modal && modal.type === 'delete'"
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
                    Delete Category
                </h2>
            </div>
            <p class="mb-6 text-sm text-muted-foreground">
                Are you sure you want to delete the category
                <strong>{{ modal.category?.name }}</strong
                >? This action cannot be undone and may affect announcements
                currently using this category.
            </p>
            <div class="flex justify-end gap-3">
                <Button
                    variant="ghost"
                    @click="modal = null"
                    class="font-bold text-slate-600"
                    >Cancel</Button
                >
                <Button
                    variant="destructive"
                    @click="deleteCategory"
                    class="font-bold"
                    >Delete permanently</Button
                >
            </div>
        </div>
    </div>
</template>
