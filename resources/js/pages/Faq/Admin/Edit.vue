<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    HelpCircle,
    Save,
    X,
    Loader2,
    Eye,
    Globe,
    Users,
    UserCircle,
    ShieldAlert,
    CheckCircle2,
    Archive,
    FileText,
    Settings2,
    Layout,
    Star,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import * as faqRoutes from '@/routes/faqs/manage/faqs';

interface Category {
    id: number;
    name: string;
}

interface Faq {
    id: number;
    faq_category_id: number;
    question: string;
    answer: string;
    summary: string;
    tags: string[];
    keywords: string[];
    is_featured: boolean;
    status: string;
    visibility: string;
    sort_order: number;
}

interface Props {
    faq: { data: Faq };
    categories: { data: Category[] };
}

const props = defineProps<Props>();

const form = useForm({
    faq_category_id: props.faq.data.faq_category_id,
    question: props.faq.data.question,
    answer: props.faq.data.answer,
    summary: props.faq.data.summary || '',
    tags: props.faq.data.tags || [],
    keywords: props.faq.data.keywords || [],
    is_featured: !!props.faq.data.is_featured,
    status: props.faq.data.status,
    visibility: props.faq.data.visibility,
    sort_order: props.faq.data.sort_order,
});

const tagInput = ref('');
const keywordInput = ref('');
const deleteModal = ref(false);

const addTag = () => {
    if (tagInput.value && !form.tags.includes(tagInput.value)) {
        form.tags.push(tagInput.value);
        tagInput.value = '';
    }
};

const removeTag = (tag: string) => {
    form.tags = form.tags.filter(t => t !== tag);
};

const addKeyword = () => {
    if (keywordInput.value && !form.keywords.includes(keywordInput.value)) {
        form.keywords.push(keywordInput.value);
        keywordInput.value = '';
    }
};

const removeKeyword = (keyword: string) => {
    form.keywords = form.keywords.filter(k => k !== keyword);
};

const submit = () => {
    form.patch(faqRoutes.update.url(props.faq.data.id));
};

const deleteArticle = () => {
    router.delete(faqRoutes.destroy.url(props.faq.data.id));
};
</script>

<template>
    <Head :title="`Edit Article: ${form.question}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <Link :href="faqRoutes.index.url()" class="inline-flex h-7 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-xs font-bold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                            <ChevronLeft class="mr-1 size-3.5" />
                            Back to Articles
                        </Link>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                            <Edit2 class="size-6" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-normal text-slate-950 dark:text-white">Edit FAQ Article</h1>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Modify help article content and settings.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button variant="ghost" @click="deleteModal = true" class="font-bold text-destructive hover:bg-red-50 dark:hover:bg-red-500/10">Delete</Button>
                    <div class="h-6 w-px bg-slate-200 dark:bg-white/10 mx-1"></div>
                    <Button variant="ghost" @click="router.visit(faqRoutes.index.url())" class="font-bold text-slate-600">Cancel</Button>
                    <Button @click="submit" :disabled="form.processing" class="h-10 rounded-md px-6 bg-emerald-600 hover:bg-emerald-700 text-white border-0 font-bold shadow-lg shadow-emerald-200 dark:shadow-none">
                        <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                        <Save class="mr-2 size-4" />
                        Update Article
                    </Button>
                </div>
            </div>
        </section>

        <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Question & Editor -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-2 mb-6 border-b border-slate-100 pb-4 dark:border-white/5">
                        <FileText class="size-4 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Article Content</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Question / Heading</label>
                            <input 
                                v-model="form.question" 
                                class="flex h-12 w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-lg font-bold text-slate-900 placeholder:font-normal focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                                placeholder="What is the question being answered?" 
                            />
                            <InputError :message="form.errors.question" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Short Summary</label>
                            <textarea 
                                v-model="form.summary" 
                                class="flex min-h-[80px] w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm resize-none" 
                                placeholder="Briefly summarize the answer..."
                            ></textarea>
                            <InputError :message="form.errors.summary" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Detailed Answer</label>
                            <div class="min-h-[400px]">
                                <TiptapEditor v-model="form.answer" />
                            </div>
                            <InputError :message="form.errors.answer" />
                        </div>
                    </div>
                </div>

                <!-- Tags & Keywords -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-2 mb-6 border-b border-slate-100 pb-4 dark:border-white/5">
                        <Search class="size-4 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Search Optimization</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Tags</label>
                            <div class="flex gap-2">
                                <input 
                                    v-model="tagInput" 
                                    @keydown.enter.prevent="addTag"
                                    class="flex h-9 flex-1 rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                                    placeholder="Add tag..." 
                                />
                                <Button type="button" size="sm" @click="addTag" class="bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300">Add</Button>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                <div v-for="tag in form.tags" :key="tag" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    {{ tag }}
                                    <button type="button" @click="removeTag(tag)" class="hover:text-emerald-900 transition-colors">
                                        <X class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Keywords (Hidden)</label>
                            <div class="flex gap-2">
                                <input 
                                    v-model="keywordInput" 
                                    @keydown.enter.prevent="addKeyword"
                                    class="flex h-9 flex-1 rounded-md border border-slate-200 bg-white px-3 py-1 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                                    placeholder="Add keyword..." 
                                />
                                <Button type="button" size="sm" @click="addKeyword" class="bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300">Add</Button>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                <div v-for="kw in form.keywords" :key="kw" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-white/10 dark:text-slate-400">
                                    {{ kw }}
                                    <button type="button" @click="removeKeyword(kw)" class="hover:text-slate-900 transition-colors">
                                        <X class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings -->
            <div class="space-y-6">
                <!-- Publishing & Taxonomy -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center gap-2 mb-6 border-b border-slate-100 pb-4 dark:border-white/5">
                        <Settings2 class="size-4 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Publication Settings</h2>
                    </div>

                    <div class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Article Category</label>
                            <select v-model="form.faq_category_id" class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm">
                                <option value="">Select Category...</option>
                                <option v-for="cat in categories.data" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <InputError :message="form.errors.faq_category_id" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Status</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button 
                                    type="button" 
                                    @click="form.status = 'draft'"
                                    :class="['flex items-center justify-center gap-2 rounded-lg border p-2.5 text-xs font-bold transition-all', form.status === 'draft' ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/10' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950']"
                                >
                                    <FileText class="size-3.5" />
                                    Draft
                                </button>
                                <button 
                                    type="button" 
                                    @click="form.status = 'published'"
                                    :class="['flex items-center justify-center gap-2 rounded-lg border p-2.5 text-xs font-bold transition-all', form.status === 'published' ? 'border-emerald-400 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950']"
                                >
                                    <Globe class="size-3.5" />
                                    Published
                                </button>
                                <button 
                                    type="button" 
                                    @click="form.status = 'archived'"
                                    :class="['flex items-center justify-center gap-2 rounded-lg border p-2.5 text-xs font-bold transition-all col-span-2', form.status === 'archived' ? 'border-slate-400 bg-slate-100 text-slate-700 dark:bg-white/10' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950']"
                                >
                                    <Archive class="size-3.5" />
                                    Archived
                                </button>
                            </div>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Audience Visibility</label>
                            <select v-model="form.visibility" class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm">
                                <option value="public">Public (Everyone)</option>
                                <option value="students">Students Only</option>
                                <option value="employees">Employees Only</option>
                                <option value="admin">Administrators Only</option>
                            </select>
                            <InputError :message="form.errors.visibility" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Display Order</label>
                            <input 
                                v-model="form.sort_order" 
                                type="number"
                                class="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-emerald-400 focus:outline-none dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 shadow-sm" 
                            />
                            <InputError :message="form.errors.sort_order" />
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-3 cursor-pointer group p-3 rounded-lg border border-slate-100 bg-slate-50/50 hover:bg-emerald-50/50 hover:border-emerald-200 transition-all dark:border-white/5 dark:bg-white/5">
                                <div class="relative flex items-center">
                                    <input type="checkbox" v-model="form.is_featured" class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-slate-200 bg-white transition-all checked:bg-emerald-600 dark:border-white/10 dark:bg-slate-950 shadow-sm" />
                                    <Star v-if="form.is_featured" class="absolute left-1/2 top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 text-white fill-white" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">Feature this Article</span>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400">Pin to the top of the FAQ landing page.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Delete Confirmation Modal -->
        <div v-if="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border bg-card p-6 shadow-2xl dark:bg-slate-900 animate-in fade-in zoom-in duration-200">
                <div class="flex items-center gap-3 text-destructive mb-4">
                    <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-500/10 flex items-center justify-center">
                        <Trash2 class="size-5" />
                    </div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Delete Article</h2>
                </div>
                <p class="text-sm text-muted-foreground mb-6">
                    Are you sure you want to delete this FAQ article? This action is permanent and cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <Button variant="ghost" @click="deleteModal = false" class="font-bold text-slate-600">Cancel</Button>
                    <Button variant="destructive" @click="deleteArticle" class="font-bold px-6 shadow-lg shadow-red-200 dark:shadow-none">Delete Permanently</Button>
                </div>
            </div>
        </div>
    </div>
</template>
