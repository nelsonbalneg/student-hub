<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    CheckCircle2,
    Clock3,
    Download,
    Edit2,
    Eye,
    FileText,
    History,
    Megaphone,
    Paperclip,
    Pin,
    ShieldCheck,
    Target,
    User,
    Users,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { format } from 'date-fns';
import * as announcementRoutes from '@/routes/announcements';

interface Attachment {
    id: number;
    original_name: string;
    file_name: string;
    file_path: string;
    mime_type: string;
    file_size: number;
}

interface Announcement {
    id: number;
    title: string;
    summary: string;
    content: string;
    priority: string;
    visibility: string;
    status: string;
    is_pinned: boolean;
    publish_at: string;
    published_at: string;
    publication_date: string;
    created_at: string;
    category: { name: string; color: string };
    creator?: { name: string };
    attachments: Attachment[];
    targets?: any[];
}

interface Props {
    announcement: Announcement;
    isSuperAdmin: boolean;
    can: {
        editAnnouncements: boolean;
    };
}

const props = defineProps<Props>();

const formatBytes = (bytes: number): string => {
    if (!bytes) {
        return '0 KB';
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const attachmentUrl = (attachment: Attachment): string => `/storage/${attachment.file_path}`;

const getPriorityStyles = (priority: string) => {
    switch (priority) {
        case 'urgent': return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200';
        case 'high': return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200';
        case 'normal': return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200';
        case 'low': return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default: return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
    }
};

const getStatusStyles = (status: string) => {
    switch (status) {
        case 'published': return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200';
        case 'scheduled': return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200';
        case 'draft': return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200';
        case 'archived': return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
        default: return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
    }
};
</script>

<template>
    <Head :title="announcement.title" />

    <div class="flex h-full flex-1 flex-col bg-slate-50/60 dark:bg-slate-950">
        <section class="border-b border-slate-200 bg-white/95 px-4 py-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-950/95 lg:px-6">
            <div class="flex w-full flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <Link :href="announcementRoutes.index.url()" class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white">
                        <ArrowLeft class="size-4" />
                    </Link>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold uppercase text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                                <Megaphone class="size-3.5 text-sky-600" />
                                Announcement
                            </span>
                            <span class="inline-flex items-center rounded-md border px-2 py-1 text-[10px] font-bold uppercase" :style="{ borderColor: announcement.category.color + '40', backgroundColor: announcement.category.color + '12', color: announcement.category.color }">
                                {{ announcement.category.name }}
                            </span>
                            <span v-if="announcement.is_pinned" class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold uppercase text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200">
                                <Pin class="size-3.5 fill-sky-600" />
                                Pinned
                            </span>
                        </div>

                        <h1 class="mt-2 max-w-4xl text-2xl font-bold tracking-normal text-slate-950 dark:text-white lg:text-3xl">
                            {{ announcement.title }}
                        </h1>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span v-if="isSuperAdmin && announcement.creator" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 dark:border-white/10 dark:bg-white/5">
                                <User class="size-3.5 text-sky-600" />
                                {{ announcement.creator.name }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 dark:border-white/10 dark:bg-white/5">
                                <Clock3 class="size-3.5 text-amber-600" />
                                {{ format(new Date(announcement.publication_date), isSuperAdmin ? 'MMM d, yyyy h:mm a' : 'MMM d, yyyy') }}
                            </span>
                            <span v-if="announcement.attachments.length > 0" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 dark:border-white/10 dark:bg-white/5">
                                <Paperclip class="size-3.5 text-violet-600" />
                                {{ announcement.attachments.length }} attachment{{ announcement.attachments.length === 1 ? '' : 's' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div v-if="isSuperAdmin" class="grid grid-cols-2 gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-white/10 dark:bg-white/5">
                        <div class="rounded-md bg-white px-3 py-2 text-center shadow-sm dark:bg-slate-900">
                            <p class="text-[10px] font-bold uppercase text-slate-400">Status</p>
                            <p class="text-xs font-bold capitalize text-slate-800 dark:text-slate-100">{{ announcement.status }}</p>
                        </div>
                        <div class="rounded-md bg-white px-3 py-2 text-center shadow-sm dark:bg-slate-900">
                            <p class="text-[10px] font-bold uppercase text-slate-400">Priority</p>
                            <p class="text-xs font-bold capitalize text-slate-800 dark:text-slate-100">{{ announcement.priority }}</p>
                        </div>
                    </div>

                    <Link v-if="can.editAnnouncements" :href="announcementRoutes.edit.url(announcement.id)">
                        <Button class="h-10 rounded-md px-5 font-bold shadow-sm">
                            <Edit2 class="mr-2 size-4" />
                            Edit
                        </Button>
                    </Link>
                </div>
            </div>
        </section>

        <main class="grid w-full grid-cols-1 gap-5 p-4 lg:grid-cols-12 lg:p-6">
            <div class="space-y-5" :class="isSuperAdmin ? 'lg:col-span-8' : 'lg:col-span-12'">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-md border px-2.5 py-1 text-[10px] font-bold uppercase" :style="{ borderColor: announcement.category.color + '40', backgroundColor: announcement.category.color + '12', color: announcement.category.color }">
                                {{ announcement.category.name }}
                            </span>
                            <span v-if="isSuperAdmin" :class="['inline-flex items-center rounded-md border px-2.5 py-1 text-[10px] font-bold uppercase', getPriorityStyles(announcement.priority)]">
                                {{ announcement.priority }}
                            </span>
                            <span v-if="isSuperAdmin" :class="['inline-flex items-center rounded-md border px-2.5 py-1 text-[10px] font-bold uppercase', getStatusStyles(announcement.status)]">
                                {{ announcement.status }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                            <Eye class="size-4" />
                            Read-only view
                        </div>
                    </div>

                    <div class="p-5 sm:p-7 lg:p-9">
                        <div v-if="announcement.summary" class="mb-7 rounded-lg border border-sky-100 bg-sky-50/80 p-5 text-sm font-semibold leading-7 text-slate-700 dark:border-sky-400/20 dark:bg-sky-500/10 dark:text-slate-200">
                            {{ announcement.summary }}
                        </div>

                        <article class="prose prose-slate max-w-none prose-headings:font-bold prose-h1:tracking-normal prose-h2:tracking-normal prose-a:text-sky-600 prose-img:rounded-lg prose-table:text-sm dark:prose-invert" v-html="announcement.content"></article>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-md border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200">
                                <Paperclip class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-950 dark:text-white">Attachments</h2>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ announcement.attachments.length }} related document{{ announcement.attachments.length === 1 ? '' : 's' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 p-5 xl:grid-cols-2">
                        <div v-if="announcement.attachments.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center dark:border-white/10 dark:bg-white/5 xl:col-span-2">
                            <FileText class="mx-auto size-7 text-slate-300 dark:text-slate-600" />
                            <p class="mt-2 text-xs font-bold uppercase text-slate-400">No attached files</p>
                        </div>

                        <div v-for="attachment in announcement.attachments" :key="attachment.id" class="group flex flex-col gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-white text-slate-500 shadow-sm dark:bg-slate-900">
                                    <FileText class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ attachment.original_name }}</p>
                                    <p class="text-[10px] font-bold uppercase text-slate-500">{{ formatBytes(attachment.file_size) }} · {{ attachment.mime_type.split('/')[1] }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a :href="attachmentUrl(attachment)" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-sky-500/10 dark:hover:text-sky-200">
                                    <Eye class="size-4" />
                                    Open
                                </a>
                                <a :href="attachmentUrl(attachment)" download class="inline-flex size-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-sky-500/10 dark:hover:text-sky-200">
                                    <Download class="size-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside v-if="isSuperAdmin" class="space-y-5 lg:col-span-4">
                <div class="sticky top-6 space-y-5">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                                    <ShieldCheck class="size-4" />
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Publishing State</h2>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Administrative metadata</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 p-5">
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Status</p>
                                <p class="mt-1 text-sm font-bold capitalize text-slate-900 dark:text-white">{{ announcement.status }}</p>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Priority</p>
                                <p class="mt-1 text-sm font-bold capitalize text-slate-900 dark:text-white">{{ announcement.priority }}</p>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Visibility</p>
                                <p class="mt-1 text-sm font-bold capitalize text-slate-900 dark:text-white">{{ announcement.visibility.replace('_', ' ') }}</p>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="text-[10px] font-bold uppercase text-slate-400">Pinned</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white">{{ announcement.is_pinned ? 'Yes' : 'No' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200">
                                    <History class="size-4" />
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Lifecycle</h2>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Timeline and publication dates</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="relative space-y-6 before:absolute before:bottom-2 before:left-[7px] before:top-2 before:w-[2px] before:bg-slate-100 dark:before:bg-white/10">
                                <div class="relative pl-7">
                                    <div class="absolute left-0 top-1 size-3.5 rounded-full border-4 border-white bg-sky-500 shadow-sm dark:border-slate-950"></div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">Draft Created</p>
                                    <p class="mt-0.5 text-[10px] font-medium text-slate-500">{{ format(new Date(announcement.created_at), 'MMMM d, yyyy') }}</p>
                                </div>
                                <div v-if="announcement.published_at" class="relative pl-7">
                                    <div class="absolute left-0 top-1 size-3.5 rounded-full border-4 border-white bg-emerald-500 shadow-sm dark:border-slate-950"></div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">Published Live</p>
                                    <p class="mt-0.5 text-[10px] font-medium text-slate-500">{{ format(new Date(announcement.published_at), 'MMMM d, yyyy') }}</p>
                                </div>
                                <div v-if="announcement.publish_at && !announcement.published_at" class="relative pl-7">
                                    <div class="absolute left-0 top-1 size-3.5 rounded-full border-4 border-white bg-violet-500 shadow-sm dark:border-slate-950"></div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">Scheduled</p>
                                    <p class="mt-0.5 text-[10px] font-medium text-slate-500">{{ format(new Date(announcement.publish_at), 'MMMM d, yyyy') }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-md border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200">
                                    <Target class="size-4" />
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-950 dark:text-white">Audience</h2>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ announcement.targets?.length ?? 0 }} targeting rule{{ (announcement.targets?.length ?? 0) === 1 ? '' : 's' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 p-5">
                            <div v-if="!announcement.targets || announcement.targets.length === 0" class="flex items-center gap-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-400 shadow-sm dark:bg-slate-900">
                                    <Users class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Public audience</p>
                                    <p class="text-[10px] font-medium text-slate-500">No targeting rules configured.</p>
                                </div>
                            </div>

                            <div v-for="(target, index) in announcement.targets" :key="index" class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <p class="mb-2 text-[10px] font-bold uppercase text-slate-400">Rule {{ index + 1 }}</p>
                                <div class="space-y-1">
                                    <div v-if="target.role" class="flex items-center justify-between gap-3 text-[11px] font-medium">
                                        <span class="font-bold uppercase text-slate-500">Role</span>
                                        <span class="truncate font-bold text-slate-900 dark:text-white">{{ target.role.name }}</span>
                                    </div>
                                    <div v-if="target.campus_id" class="flex items-center justify-between gap-3 text-[11px] font-medium">
                                        <span class="font-bold uppercase text-slate-500">Campus</span>
                                        <span class="truncate font-bold text-slate-900 dark:text-white">{{ target.campus_id }}</span>
                                    </div>
                                    <div v-if="target.office_id" class="flex items-center justify-between gap-3 text-[11px] font-medium">
                                        <span class="font-bold uppercase text-slate-500">Office</span>
                                        <span class="truncate font-bold text-slate-900 dark:text-white">{{ target.office_id }}</span>
                                    </div>
                                    <div v-if="target.year_level" class="flex items-center justify-between gap-3 text-[11px] font-medium">
                                        <span class="font-bold uppercase text-slate-500">Year Level</span>
                                        <span class="font-bold text-slate-900 dark:text-white">{{ target.year_level }} Year</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </main>
    </div>
</template>
