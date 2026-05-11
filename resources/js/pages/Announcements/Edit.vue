<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Save,
    Megaphone,
    Calendar,
    Pin,
    Eye,
    Target,
    Settings,
    FileText,
    Trash2,
    X,
    Loader2,
    Download,
    Plus,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import TiptapEditor from '@/components/TiptapEditor.vue';
import * as announcementRoutes from '@/routes/announcements';

interface Category {
    id: number;
    name: string;
}

interface Role {
    id: number;
    name: string;
}

interface Attachment {
    id: number;
    original_name: string;
    file_path: string;
    file_size: number;
}

interface Announcement {
    id: number;
    title: string;
    summary: string;
    content: string;
    category_id: number;
    priority: string;
    visibility: string;
    is_pinned: boolean;
    publish_at: string;
    status: string;
    attachments: Attachment[];
    targets: any[];
}

interface Props {
    announcement: Announcement;
    categories: Category[];
    roles: Role[];
}

const props = defineProps<Props>();

const form = useForm({
    title: props.announcement.title,
    summary: props.announcement.summary || '',
    content: props.announcement.content,
    category_id: props.announcement.category_id,
    priority: props.announcement.priority,
    visibility: props.announcement.visibility,
    is_pinned: !!props.announcement.is_pinned,
    publish_at: props.announcement.publish_at || '',
    send_notification: false,
    status: props.announcement.status,
    targets: props.announcement.targets.map(t => ({
        role_id: t.role_id || null,
        campus_id: t.campus_id || null,
        office_id: t.office_id || null,
        year_level: t.year_level || null,
    })),
    attachments: [] as File[],
    remove_attachments: [] as number[],
});

const addTarget = () => {
    form.targets.push({
        role_id: '',
        campus_id: '',
        office_id: '',
        year_level: '',
    });
};

const removeTarget = (index: number) => {
    form.targets.splice(index, 1);
};

const handleFileSelect = (e: Event) => {
    const files = (e.target as HTMLInputElement).files;
    if (files) {
        for (let i = 0; i < files.length; i++) {
            form.attachments.push(files[i]);
        }
    }
};

const removeNewAttachment = (index: number) => {
    form.attachments.splice(index, 1);
};

const removeExistingAttachment = (id: number) => {
    form.remove_attachments.push(id);
};

const isRemoved = (id: number) => form.remove_attachments.includes(id);

const submit = () => {
    if (form.publish_at && (form.status === 'published' || form.status === 'draft')) {
        form.status = 'scheduled';
    } else if (!form.publish_at && form.status === 'scheduled') {
        form.status = 'published';
    }

    form.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(announcementRoutes.update.url(props.announcement.id), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Edit Announcement" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- Header Section -->
        <section class="border-b border-slate-200 pb-5 dark:border-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 uppercase shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                        <Megaphone class="size-3.5 text-sky-600" />
                        Bulletin Board
                    </div>
                    <div class="mt-3 flex items-center gap-4">
                        <Link :href="announcementRoutes.index.url()" class="group">
                            <div class="h-9 w-9 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-colors dark:border-white/10 dark:hover:bg-white/5">
                                <ArrowLeft class="size-4 text-slate-600 group-hover:text-slate-900 dark:text-slate-400 dark:group-hover:text-white" />
                            </div>
                        </Link>
                        <h1 class="text-2xl font-bold tracking-normal text-slate-950 dark:text-white line-clamp-1">Edit: {{ announcement.title }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button variant="ghost" @click="router.visit(announcementRoutes.index.url())" class="font-bold text-slate-500">Discard Changes</Button>
                    <Button @click="submit" :disabled="form.processing" class="h-10 rounded-md px-6 font-bold shadow-sky-200 dark:shadow-none">
                        <Save v-if="!form.processing" class="mr-2 size-4" />
                        <Loader2 v-else class="mr-2 size-4 animate-spin" />
                        Save Changes
                    </Button>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Main Content Area -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Content Card -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950 overflow-hidden">
                    <div class="border-b border-slate-100 p-4 dark:border-white/5 bg-slate-50/50 dark:bg-white/5">
                        <h3 class="text-sm font-bold text-slate-950 dark:text-white flex items-center gap-2">
                            <FileText class="size-4 text-sky-600" />
                            Update Content
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Headline Title</label>
                            <input 
                                v-model="form.title" 
                                class="flex h-12 w-full rounded-md border border-slate-200 bg-white px-4 text-base font-bold text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100" 
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Executive Summary</label>
                            <textarea 
                                v-model="form.summary" 
                                class="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 focus:border-sky-400 focus:outline-none dark:border-white/10 dark:bg-slate-900 dark:text-slate-100" 
                            ></textarea>
                            <InputError :message="form.errors.summary" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Detailed Message</label>
                            <div class="rounded-md border border-slate-200 dark:border-white/10 overflow-hidden">
                                <TiptapEditor v-model="form.content" />
                            </div>
                            <InputError :message="form.errors.content" />
                        </div>
                    </div>
                </div>

                <!-- Audience Targeting -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 p-4 dark:border-white/5 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-950 dark:text-white flex items-center gap-2">
                            <Target class="size-4 text-emerald-600" />
                            Update Audience
                        </h3>
                        <Button type="button" variant="outline" size="sm" @click="addTarget" class="h-8 text-xs font-bold">
                            <Plus class="mr-1 size-3" />
                            Add Rule
                        </Button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div v-for="(target, index) in form.targets" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-3 p-4 rounded-xl border border-slate-100 bg-slate-50/50 dark:border-white/10 dark:bg-white/5 relative group">
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Role</label>
                                <select v-model="target.role_id" class="w-full h-8 rounded-md border border-slate-200 bg-white text-xs px-2 dark:border-white/10 dark:bg-slate-900">
                                    <option value="">Any Role</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Campus</label>
                                <select v-model="target.campus_id" class="w-full h-8 rounded-md border border-slate-200 bg-white text-xs px-2 dark:border-white/10 dark:bg-slate-900">
                                    <option value="">Any Campus</option>
                                    <!-- Campuses list would be here -->
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Office</label>
                                <select v-model="target.office_id" class="w-full h-8 rounded-md border border-slate-200 bg-white text-xs px-2 dark:border-white/10 dark:bg-slate-900">
                                    <option value="">Any Office</option>
                                    <!-- Offices list would be here -->
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Year Level</label>
                                <select v-model="target.year_level" class="w-full h-8 rounded-md border border-slate-200 bg-white text-xs px-2 dark:border-white/10 dark:bg-slate-900">
                                    <option value="">Any Year</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <button @click="removeTarget(index)" class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-red-100 text-red-600 border border-red-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                                <X class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Sidebar Area -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Publishing Options -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="border-b border-slate-100 p-4 dark:border-white/5">
                        <h3 class="text-sm font-bold text-slate-950 dark:text-white flex items-center gap-2">
                            <Settings class="size-4 text-amber-600" />
                            Publication Settings
                        </h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Category</label>
                            <select v-model="form.category_id" class="w-full h-10 rounded-md border border-slate-200 bg-white text-sm px-3 dark:border-white/10 dark:bg-slate-900">
                                <option value="">Select Category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Priority Level</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="p in ['low', 'normal', 'high', 'urgent']" :key="p" type="button" 
                                    @click="form.priority = p"
                                    :class="['h-9 rounded-md text-[10px] font-bold uppercase border transition-all', form.priority === p ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-500/20' : 'border-slate-100 bg-white text-slate-500 hover:bg-slate-50 dark:border-white/5 dark:bg-slate-900']">
                                    {{ p }}
                                </button>
                            </div>
                        </div>

                        <div class="pt-2 flex flex-col gap-3">
                            <label class="flex items-center justify-between p-3 rounded-lg border border-slate-100 bg-slate-50/30 dark:border-white/5 cursor-pointer hover:bg-slate-50 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-sky-100 dark:bg-sky-500/10 flex items-center justify-center text-sky-600 group-hover:scale-110 transition-transform">
                                        <Pin :class="['size-4', form.is_pinned ? 'fill-sky-600' : '']" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Pin to Top</p>
                                        <p class="text-[9px] text-slate-500 uppercase font-bold tracking-widest">Featured Status</p>
                                    </div>
                                </div>
                                <input type="checkbox" v-model="form.is_pinned" class="peer h-5 w-5 rounded border-slate-200 checked:bg-sky-600 transition-all dark:border-white/10 dark:bg-slate-900" />
                            </label>

                            <label class="flex items-center justify-between p-3 rounded-lg border border-slate-100 bg-slate-50/30 dark:border-white/5 cursor-pointer hover:bg-slate-50 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                                        <Eye class="size-4" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Resend Notification</p>
                                        <p class="text-[9px] text-slate-500 uppercase font-bold tracking-widest">Blast Alerts Again</p>
                                    </div>
                                </div>
                                <input type="checkbox" v-model="form.send_notification" class="peer h-5 w-5 rounded border-slate-200 checked:bg-emerald-600 transition-all dark:border-white/10 dark:bg-slate-900" />
                            </label>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider dark:text-slate-400">Schedule Date</label>
                            <div class="relative">
                                <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                                <input type="datetime-local" v-model="form.publish_at" class="w-full h-10 rounded-md border border-slate-200 bg-white text-xs pl-9 pr-3 dark:border-white/10 dark:bg-slate-900 dark:text-white focus:border-sky-400 focus:outline-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950 overflow-hidden">
                    <div class="border-b border-slate-100 p-4 dark:border-white/5 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-950 dark:text-white flex items-center gap-2">
                            <FileText class="size-4 text-violet-600" />
                            Manage Media
                        </h3>
                        <label class="h-8 px-3 rounded-md border border-slate-200 dark:border-white/10 flex items-center justify-center text-[10px] font-bold uppercase tracking-wider cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                            <Plus class="mr-1.5 size-3" />
                            Upload
                            <input type="file" multiple @change="handleFileSelect" class="hidden" />
                        </label>
                    </div>
                    <div class="p-4 space-y-4">
                        <!-- Existing Files -->
                        <div v-if="announcement.attachments.length > 0" class="space-y-2">
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest px-1">Currently Attached</p>
                            <div v-for="file in announcement.attachments" :key="file.id" 
                                :class="['flex items-center justify-between p-2 rounded-lg border transition-all', isRemoved(file.id) ? 'opacity-40 bg-slate-50 line-through grayscale' : 'border-slate-50 bg-slate-50/50 dark:border-white/5 dark:bg-white/5']">
                                <div class="flex items-center gap-2 min-w-0">
                                    <FileText class="size-3.5 text-slate-400 shrink-0" />
                                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">{{ file.original_name }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a v-if="!isRemoved(file.id)" :href="'/storage/' + file.file_path" download class="h-6 w-6 flex items-center justify-center text-slate-400 hover:text-sky-600">
                                        <Download class="size-3.5" />
                                    </a>
                                    <button @click="isRemoved(file.id) ? form.remove_attachments = form.remove_attachments.filter(id => id !== file.id) : removeExistingAttachment(file.id)" 
                                        :class="['h-6 w-6 flex items-center justify-center transition-colors', isRemoved(file.id) ? 'text-sky-600' : 'text-slate-400 hover:text-red-500']">
                                        <component :is="isRemoved(file.id) ? Plus : Trash2" class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- New Files -->
                        <div v-if="form.attachments.length > 0" class="space-y-2">
                            <p class="text-[9px] font-bold text-emerald-600 uppercase tracking-widest px-1">New Uploads</p>
                            <div v-for="(file, i) in form.attachments" :key="i" class="flex items-center justify-between p-2 rounded-lg border border-emerald-100 bg-emerald-50/20 dark:border-emerald-500/10">
                                <div class="flex items-center gap-2 min-w-0">
                                    <Plus class="size-3.5 text-emerald-500 shrink-0" />
                                    <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300 truncate">{{ file.name }}</span>
                                </div>
                                <button @click="removeNewAttachment(i)" class="h-6 w-6 text-slate-400 hover:text-red-500">
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        
                        <div v-if="announcement.attachments.length === 0 && form.attachments.length === 0" class="py-6 text-center">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">No attachments</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
