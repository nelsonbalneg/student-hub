<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileText,
    MoreHorizontal,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    activate,
    create,
    deactivate,
    destroy,
    edit,
    index,
    show,
} from '@/routes/legal';

type PageLink = { url: string | null; label: string; active: boolean };
type LegalDocument = {
    id: number;
    type: string;
    title: string;
    slug: string;
    version?: string | null;
    is_active: boolean;
    published_at_human?: string | null;
    updated_at?: string | null;
    acceptances_count: number;
    creator?: { id: number; name: string } | null;
};

const props = defineProps<{
    documents: {
        data: LegalDocument[];
        links: PageLink[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
        };
    };
    filters: {
        type?: string;
        status?: string;
    };
    types: { value: string; label: string }[];
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        activate: boolean;
    };
}>();

const typeFilter = ref(props.filters.type ?? '');
const statusFilter = ref(props.filters.status ?? '');
const pendingAction = ref<null | { type: 'delete' | 'activate' | 'deactivate'; document: LegalDocument }>(null);

const activeDocuments = computed(() => props.documents.data.filter((document) => document.is_active).length);

const typeLabel = (value: string) => props.types.find((type) => type.value === value)?.label ?? value;

const applyFilters = () => {
    router.get(
        index.url(),
        {
            type: typeFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const navigatePage = (url: string | null) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const runPendingAction = () => {
    if (!pendingAction.value) return;

    if (pendingAction.value.type === 'delete') {
        router.delete(destroy.url(pendingAction.value.document.id), {
            preserveScroll: true,
            onSuccess: () => (pendingAction.value = null),
        });
        return;
    }

    const actionUrl = pendingAction.value.type === 'deactivate'
        ? deactivate.url(pendingAction.value.document.id)
        : activate.url(pendingAction.value.document.id);

    router.patch(actionUrl, {}, {
        preserveScroll: true,
        onSuccess: () => (pendingAction.value = null),
    });
};
</script>

<template>
    <Head title="Legal Documents" />

    <div class="flex h-full flex-1 flex-col gap-3 bg-slate-50/60 p-3 dark:bg-slate-950 lg:p-4">
        <header class="border-b border-slate-200 pb-3 dark:border-white/10">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">
                        Settings
                    </p>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-950 dark:text-white">
                        Legal Documents
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage active Terms, Cookie Policy, and Privacy Policy versions.
                    </p>
                </div>
                <Link v-if="can.create" :href="create.url()">
                    <Button class="h-8 bg-emerald-600 px-3 text-xs text-white hover:bg-emerald-700">
                        <Plus class="mr-2 size-4" />
                        New Document
                    </Button>
                </Link>
            </div>
        </header>

        <section class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Documents</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">{{ documents.meta.total }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Active on page</p>
                <p class="mt-1 text-xl font-bold text-emerald-600">{{ activeDocuments }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase text-slate-400">Document types</p>
                <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">{{ types.length }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select v-model="typeFilter" class="legal-filter" @change="applyFilters">
                        <option value="">All types</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <select v-model="statusFilter" class="legal-filter" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <p class="text-xs font-medium text-slate-500">
                    Page {{ documents.meta.current_page }} of {{ documents.meta.last_page }}
                </p>
            </div>

            <div v-if="documents.data.length === 0" class="grid min-h-52 place-items-center p-6 text-center">
                <div>
                    <FileText class="mx-auto mb-2 size-10 text-slate-300 dark:text-slate-700" />
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">No legal documents yet</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Create the first Terms, Cookie Policy, or Privacy Policy document.
                    </p>
                </div>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-3 py-2 text-left font-bold">Document</th>
                            <th class="px-3 py-2 text-left font-bold">Type</th>
                            <th class="px-3 py-2 text-left font-bold">Version</th>
                            <th class="px-3 py-2 text-left font-bold">Status</th>
                            <th class="px-3 py-2 text-left font-bold">Acceptances</th>
                            <th class="px-3 py-2 text-right font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr v-for="document in documents.data" :key="document.id" class="hover:bg-slate-50/80 dark:hover:bg-white/[0.03]">
                            <td class="px-3 py-2">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ document.title }}</div>
                                <div class="text-xs text-slate-500">{{ document.slug }}</div>
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ typeLabel(document.type) }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                    {{ document.version || 'Unversioned' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold"
                                    :class="document.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'"
                                >
                                    <CheckCircle2 v-if="document.is_active" class="size-3" />
                                    <XCircle v-else class="size-3" />
                                    {{ document.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ document.acceptances_count }}</td>
                            <td class="px-3 py-2 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="size-7">
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-44">
                                        <Link :href="show.url(document.id)">
                                            <DropdownMenuItem>
                                                <Eye class="mr-2 size-4" />
                                                View
                                            </DropdownMenuItem>
                                        </Link>
                                        <Link v-if="can.edit" :href="edit.url(document.id)">
                                            <DropdownMenuItem>
                                                <Pencil class="mr-2 size-4" />
                                                Edit
                                            </DropdownMenuItem>
                                        </Link>
                                        <DropdownMenuItem
                                            v-if="can.activate && !document.is_active"
                                            @click="pendingAction = { type: 'activate', document }"
                                        >
                                            <ShieldCheck class="mr-2 size-4" />
                                            Activate
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="can.activate && document.is_active"
                                            @click="pendingAction = { type: 'deactivate', document }"
                                        >
                                            <XCircle class="mr-2 size-4" />
                                            Deactivate
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator v-if="can.delete" />
                                        <DropdownMenuItem
                                            v-if="can.delete"
                                            class="text-red-600"
                                            @click="pendingAction = { type: 'delete', document }"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="documents.meta.last_page > 1" class="flex items-center justify-end gap-1 border-t border-slate-200 p-2 dark:border-white/10">
                <button class="legal-page-btn" :disabled="!documents.links[0]?.url" @click="navigatePage(documents.links[0]?.url)">
                    <ChevronLeft class="size-4" />
                </button>
                <button class="legal-page-btn" :disabled="!documents.links[documents.links.length - 1]?.url" @click="navigatePage(documents.links[documents.links.length - 1]?.url)">
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </section>
    </div>

    <div v-if="pendingAction" class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-2xl dark:bg-slate-950">
            <h2 class="text-lg font-bold text-slate-950 dark:text-white">
                {{ pendingAction.type === 'delete' ? 'Delete document?' : pendingAction.type === 'activate' ? 'Activate document?' : 'Deactivate document?' }}
            </h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                <template v-if="pendingAction.type === 'delete'">
                    This permanently deletes {{ pendingAction.document.title }}.
                </template>
                <template v-else>
                    {{ pendingAction.type === 'activate'
                        ? `This makes ${pendingAction.document.title} the only active ${typeLabel(pendingAction.document.type)}.`
                        : `This removes ${pendingAction.document.title} from public legal drawers and required acceptance checks.` }}
                </template>
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="pendingAction = null">Cancel</Button>
                <Button
                    :class="pendingAction.type === 'delete' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                    @click="runPendingAction"
                >
                    {{ pendingAction.type === 'delete' ? 'Delete' : pendingAction.type === 'activate' ? 'Activate' : 'Deactivate' }}
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

.legal-filter {
    @apply h-8 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 outline-none focus:border-emerald-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200;
    color-scheme: light;
    background-color: #ffffff;
    color: #334155;
}

.legal-filter option {
    background-color: #ffffff;
    color: #334155;
}

.dark .legal-filter {
    color-scheme: dark;
    background-color: #0f172a;
    color: #e2e8f0;
}

.dark .legal-filter option {
    background-color: #0f172a;
    color: #e2e8f0;
}

.legal-page-btn {
    @apply inline-flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-300 dark:hover:bg-white/10;
}
</style>
