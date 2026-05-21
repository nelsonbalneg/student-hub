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
    Eye,
    Globe,
    Users as UsersIcon,
    UserCircle,
    ShieldAlert,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import * as categoryRoutes from '@/routes/faqs/manage/categories';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string;
    icon: string;
    color: string;
    sort_order: number;
    visibility: 'public' | 'students' | 'employees' | 'admin';
    is_active: boolean;
    faqs_count?: number;
    created_at: string;
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
    filters: {
        search?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const modal = ref<{ type: 'create' | 'edit' | 'delete'; category?: Category } | null>(null);

const form = useForm({
    name: '',
    description: '',
    icon: 'HelpCircle',
    color: '#10b981',
    sort_order: 0,
    visibility: 'public' as Category['visibility'],
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
    form.description = category.description || '';
    form.icon = category.icon || 'HelpCircle';
    form.color = category.color || '#10b981';
    form.sort_order = category.sort_order || 0;
    form.visibility = category.visibility || 'public';
    form.is_active = !!category.is_active;
    modal.value = { type: 'edit', category };
};

const submit = () => {
    if (modal.value?.type === 'edit') {
        form.patch(categoryRoutes.update.url(modal.value.category!.id), {
            onSuccess: () => (modal.value = null),
            preserveScroll: true,
            preserveState: true,
        });
    } else {
        form.post(categoryRoutes.store.url(), {
            onSuccess: () => {
                modal.value = null;
                form.reset();
            },
            preserveScroll: true,
            preserveState: true,
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
            preserveState: true,
        });
    }
};

let searchTimeout: any;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            categoryRoutes.index.url(),
            { search: value, per_page: props.filters.per_page },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 400);
});

const navigatePage = (url: string | null) => {
    if (url) {
        router.get(url, { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
    }
};

const getVisibilityIcon = (visibility: string) => {
    switch (visibility) {
        case 'public': return Globe;
        case 'students': return UsersIcon;
        case 'employees': return UserCircle;
        case 'admin': return ShieldAlert;
        default: return Eye;
    }
};

const getVisibilityColor = (visibility: string) => {
    switch (visibility) {
        case 'public': return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10';
        case 'students': return 'text-blue-600 bg-blue-50 dark:bg-blue-500/10';
        case 'employees': return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10';
        case 'admin': return 'text-rose-600 bg-rose-50 dark:bg-rose-500/10';
        default: return 'text-slate-600 bg-slate-50 dark:bg-white/5';
    }
};
</script>

<template>
    <Head title="FAQ Categories" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 uppercase shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <FolderTree class="size-3.5 text-emerald-600" />
                        Knowledge Base Taxonomy
                    </div>
                    <h1 class="mt-3 text-2xl font-bold tracking-normal text-slate-950 dark:text-white">FAQ Categories</h1>
                    <p class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400">
                        Manage groupings for your frequently asked questions to help users navigate content efficiently.
                    </p>
                </div>

                <Button @click="openCreate" class="h-10 rounded-md px-4 bg-emerald-600 hover:bg-emerald-700 text-white border-0">
                    <Plus class="mr-2 size-4" />
                    Add Category
                </Button>
            </div>
        </section>

        <!-- Main Content -->
        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <!-- Toolbar -->
            <div class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Category Directory</h2>
                    <p class="text-xs font-medium text-slate-500">
                        Showing <span class="font-bold text-slate-700 dark:text-slate-200">{{ categories.meta.from ?? 0 }}-{{ categories.meta.to ?? 0 }}</span> of <span class="font-bold text-slate-700 dark:text-slate-200">{{ categories.meta.total }}</span>
                    </p>
                </div>

                <div class="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center">
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search categories..."
                            class="h-9 w-full rounded-md border border-slate-200 bg-white pr-8 pl-8 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <button v-if="search" @click="search = ''" class="absolute top-1/2 right-2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-white">
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
            <div class="relative min-h-0 w-full flex-1 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 w-[250px]">Category</th>
                            <th class="px-4 py-3">Visibility</th>
                            <th class="px-4 py-3 text-center">Order</th>
                            <th class="px-4 py-3 text-center">FAQs</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="category in categories.data" :key="category.id" class="hover:bg-slate-50/80 dark:hover:bg-white/5 transition-colors group">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-black/5 dark:border-white/10" :style="{ backgroundColor: category.color || '#10b981', color: '#fff' }">
                                        <component :is="category.icon === 'Folder' ? FolderOpen : FolderTree" class="size-4" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ category.name }}</span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 line-clamp-1">{{ category.description || 'No description' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <div :class="['p-1 rounded-md', getVisibilityColor(category.visibility)]">
                                        <component :is="getVisibilityIcon(category.visibility)" class="size-3" />
                                    </div>
                                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 capitalize">{{ category.visibility }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ category.sort_order }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex h-5 items-center justify-center rounded-full bg-slate-100 px-2 text-[10px] font-bold text-slate-600 dark:bg-white/5 dark:text-slate-400">
                                    {{ category.faqs_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span 
                                    class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                    :class="category.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200'"
                                >
                                    {{ category.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 hover:bg-slate-100 dark:hover:bg-white/10">
                                            <MoreHorizontal class="size-4 text-slate-500" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-44">
                                        <DropdownMenuItem @click="openEdit(category)">
                                            <Edit2 class="mr-2 size-4" />
                                            Edit Details
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem @click="confirmDelete(category)" class="text-destructive hover:bg-red-50 dark:hover:bg-red-500/10">
                                            <Trash2 class="mr-2 size-4" />
                                            Delete Category
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                        <tr v-if="categories.data.length === 0">
                            <td colspan="6" class="p-12 text-center">
                                <div class="flex flex-col items-center justify-center gap-3 opacity-50">
                                    <FolderOpen class="size-10 text-slate-300 dark:text-slate-600" />
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">No categories found</p>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Initialize your knowledge base by creating a category.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="categories.meta.last_page > 1" class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                <p class="text-xs font-medium text-slate-500">
                    Page {{ categories.meta.current_page }} of {{ categories.meta.last_page }}
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
                        :disabled="!categories.links[categories.meta.current_page - 1]?.url"
                        @click="navigatePage(categories.links[categories.meta.current_page - 1]?.url)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="categories.meta.current_page === categories.meta.last_page"
                        @click="navigatePage(categories.links[categories.meta.current_page + 1]?.url)"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        class="inline-flex size-8 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10"
                        :disabled="categories.meta.current_page === categories.meta.last_page"
                        @click="navigatePage(categories.links[categories.links.length - 1]?.url)"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="modal && (modal.type === 'create' || modal.type === 'edit')" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-xl border bg-card p-6 shadow-2xl dark:bg-slate-900 animate-in fade-in zoom-in duration-200">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                        <FolderTree class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ modal.type === 'create' ? 'Add FAQ Category' : 'Edit Category Details' }}</h2>
                        <p class="text-xs text-slate-500">Classify related questions for easier navigation.</p>
                    </div>
                </div>
                <Button variant="ghost" size="icon" @click="modal = null" class="h-8 w-8">
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Category Name</label>
                        <input 
                            v-model="form.name" 
                            class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                            placeholder="e.g. Enrollment Process" 
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Visibility</label>
                        <select v-model="form.visibility" class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm">
                            <option value="public">Public (All Users)</option>
                            <option value="students">Students Only</option>
                            <option value="employees">Employees Only</option>
                            <option value="admin">Administrators Only</option>
                        </select>
                        <InputError :message="form.errors.visibility" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Sort Order</label>
                        <input 
                            v-model="form.sort_order" 
                            type="number"
                            class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Theme Color</label>
                        <div class="flex gap-2">
                            <input v-model="form.color" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-slate-200 p-1 dark:border-white/10 dark:bg-slate-950 shadow-sm" />
                            <input v-model="form.color" class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-mono text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" />
                        </div>
                        <InputError :message="form.errors.color" />
                    </div>

                    <div class="flex items-center gap-3 pt-6 pl-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <div class="relative flex items-center">
                                <input type="checkbox" v-model="form.is_active" class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-slate-200 bg-white transition-all checked:bg-emerald-600 dark:border-white/10 dark:bg-slate-950 shadow-sm" />
                                <X v-if="!form.is_active" class="absolute left-1/2 top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 text-slate-400" />
                                <CheckCircle2 v-else class="absolute left-1/2 top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 text-white" />
                            </div>
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300 group-hover:text-emerald-600 transition-colors">Active Category</span>
                        </label>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Description</label>
                    <textarea 
                        v-model="form.description" 
                        class="flex min-h-[80px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm resize-none" 
                        placeholder="What kind of questions will be in this category?"
                    ></textarea>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <Button type="button" variant="ghost" @click="modal = null" class="font-bold text-slate-600">Cancel</Button>
                    <Button type="submit" :disabled="form.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white border-0 font-bold px-6 shadow-lg shadow-emerald-200 dark:shadow-none">
                        <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        {{ modal.type === 'create' ? 'Create Category' : 'Apply Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div v-if="modal && modal.type === 'delete'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-xl border bg-card p-6 shadow-2xl dark:bg-slate-900 animate-in fade-in zoom-in duration-200">
            <div class="flex items-center gap-3 text-destructive mb-4">
                <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-500/10 flex items-center justify-center">
                    <Trash2 class="size-5" />
                </div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Delete Category</h2>
            </div>
            <p class="text-sm text-muted-foreground mb-6">
                Are you sure you want to delete <strong>{{ modal.category?.name }}</strong>? If this category has associated FAQs, deletion may be blocked. This action cannot be reversed.
            </p>
            <div class="flex justify-end gap-3">
                <Button variant="ghost" @click="modal = null" class="font-bold text-slate-600">Cancel</Button>
                <Button variant="destructive" @click="deleteCategory" class="font-bold px-6 shadow-lg shadow-red-200 dark:shadow-none">Confirm Deletion</Button>
            </div>
        </div>
    </div>
</template>
