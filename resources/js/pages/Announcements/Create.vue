<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    Calendar,
    CheckCircle2,
    Clock3,
    Eye,
    FileText,
    Loader2,
    Megaphone,
    Paperclip,
    Pin,
    Plus,
    Send,
    Settings,
    ShieldCheck,
    Target,
    Trash2,
    UploadCloud,
    Users,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';
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

interface Props {
    categories: Category[];
    roles: Role[];
    campuses: any[];
    offices: any[];
}

const props = defineProps<Props>();

const form = useForm({
    title: '',
    summary: '',
    content: '',
    category_id: '',
    priority: 'normal',
    visibility: 'public',
    is_pinned: false,
    publish_at: '',
    send_notification: false,
    status: 'published',
    targets: [] as any[],
    attachments: [] as File[],
});

const priorities = [
    {
        value: 'low',
        label: 'Low',
        class: 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300',
    },
    {
        value: 'normal',
        label: 'Normal',
        class: 'border-sky-200 text-sky-700 dark:border-sky-400/30 dark:text-sky-200',
    },
    {
        value: 'high',
        label: 'High',
        class: 'border-amber-200 text-amber-700 dark:border-amber-400/30 dark:text-amber-200',
    },
    {
        value: 'urgent',
        label: 'Urgent',
        class: 'border-red-200 text-red-700 dark:border-red-400/30 dark:text-red-200',
    },
];

const selectedCategory = computed(() =>
    props.categories.find(
        (category) => category.id === Number(form.category_id),
    ),
);
const publicationMode = computed(() =>
    form.publish_at ? 'Scheduled' : 'Immediate',
);
const audienceMode = computed(() =>
    form.targets.length > 0
        ? `${form.targets.length} rule${form.targets.length === 1 ? '' : 's'}`
        : 'Public',
);
const attachmentSize = computed(() =>
    form.attachments.reduce((total, file) => total + file.size, 0),
);
const completionScore = computed(() => {
    return [
        Boolean(form.title.trim()),
        Boolean(form.category_id),
        Boolean(form.content.trim()),
        Boolean(form.summary.trim()),
    ].filter(Boolean).length;
});

const formatBytes = (bytes: number): string => {
    if (!bytes) {
        return '0 KB';
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const addTarget = () => {
    form.targets.push({
        role_id: null,
        campus_id: null,
        office_id: null,
        year_level: null,
    });
};

const removeTarget = (index: number) => {
    form.targets.splice(index, 1);
};

const handleFileSelect = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (!files) {
        return;
    }

    for (let index = 0; index < files.length; index++) {
        form.attachments.push(files[index]);
    }

    (event.target as HTMLInputElement).value = '';
};

const removeAttachment = (index: number) => {
    form.attachments.splice(index, 1);
};

const submit = () => {
    form.status = form.publish_at ? 'scheduled' : 'published';
    form.post(announcementRoutes.store.url(), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create Announcement" />

    <div class="flex h-full flex-1 flex-col bg-slate-50/60 dark:bg-slate-950">
        <section
            class="border-b border-slate-200 bg-white/95 px-4 py-4 shadow-sm backdrop-blur lg:px-6 dark:border-white/10 dark:bg-slate-950/95"
        >
            <div
                class="flex w-full flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="announcementRoutes.index.url()"
                        class="group inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        <ArrowLeft class="size-4" />
                    </Link>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                            >
                                <Megaphone class="size-3.5 text-sky-600" />
                                Announcement Desk
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 uppercase dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                            >
                                <ShieldCheck class="size-3.5" />
                                Authorized
                            </span>
                        </div>
                        <h1
                            class="mt-2 truncate text-xl font-bold tracking-normal text-slate-950 sm:text-2xl dark:text-white"
                        >
                            Create Announcement
                        </h1>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div
                        class="grid grid-cols-3 gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="rounded-md bg-white px-3 py-2 text-center shadow-sm dark:bg-slate-900"
                        >
                            <p
                                class="text-[10px] font-bold text-slate-400 uppercase"
                            >
                                Mode
                            </p>
                            <p
                                class="text-xs font-bold text-slate-800 dark:text-slate-100"
                            >
                                {{ publicationMode }}
                            </p>
                        </div>
                        <div
                            class="rounded-md bg-white px-3 py-2 text-center shadow-sm dark:bg-slate-900"
                        >
                            <p
                                class="text-[10px] font-bold text-slate-400 uppercase"
                            >
                                Audience
                            </p>
                            <p
                                class="text-xs font-bold text-slate-800 dark:text-slate-100"
                            >
                                {{ audienceMode }}
                            </p>
                        </div>
                        <div
                            class="rounded-md bg-white px-3 py-2 text-center shadow-sm dark:bg-slate-900"
                        >
                            <p
                                class="text-[10px] font-bold text-slate-400 uppercase"
                            >
                                Ready
                            </p>
                            <p
                                class="text-xs font-bold text-slate-800 dark:text-slate-100"
                            >
                                {{ completionScore }}/4
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            @click="
                                router.visit(announcementRoutes.index.url())
                            "
                            class="h-10 rounded-md px-4 font-bold"
                        >
                            Cancel
                        </Button>
                        <Button
                            @click="submit"
                            :disabled="form.processing"
                            class="h-10 rounded-md px-5 font-bold shadow-sm"
                        >
                            <Send v-if="!form.processing" class="mr-2 size-4" />
                            <Loader2 v-else class="mr-2 size-4 animate-spin" />
                            Publish
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <main class="grid w-full grid-cols-1 gap-5 p-4 lg:grid-cols-12 lg:p-6">
            <div class="space-y-5 lg:col-span-8">
                <section
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-md border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200"
                            >
                                <FileText class="size-4" />
                            </div>
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Message
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Title, summary, and full announcement body
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <div class="space-y-2">
                            <label
                                class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                >Headline</label
                            >
                            <input
                                v-model="form.title"
                                class="h-12 w-full rounded-md border border-slate-200 bg-white px-4 text-base font-bold text-slate-950 transition outline-none placeholder:font-medium placeholder:text-slate-400 focus:border-sky-400 focus:ring-4 focus:ring-sky-100 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-500/10"
                                placeholder="Enter announcement title"
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-5">
                            <div class="space-y-2 md:col-span-3">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                    >Executive Summary</label
                                >
                                <textarea
                                    v-model="form.summary"
                                    class="min-h-[104px] w-full resize-y rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 transition outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-4 focus:ring-sky-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-sky-500/10"
                                    placeholder="Short summary shown in announcement lists"
                                ></textarea>
                                <InputError :message="form.errors.summary" />
                            </div>

                            <div
                                class="rounded-lg border border-slate-200 bg-slate-50 p-4 md:col-span-2 dark:border-white/10 dark:bg-white/5"
                            >
                                <div class="flex items-center gap-2">
                                    <Eye class="size-4 text-slate-500" />
                                    <p
                                        class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                    >
                                        Preview
                                    </p>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <p
                                        class="line-clamp-2 text-sm font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ form.title || 'Announcement title' }}
                                    </p>
                                    <p
                                        class="line-clamp-3 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            form.summary ||
                                            'Summary appears here once entered.'
                                        }}
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center gap-2 pt-1"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                                        >
                                            {{
                                                selectedCategory?.name ??
                                                'Category'
                                            }}
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                            :class="
                                                priorities.find(
                                                    (priority) =>
                                                        priority.value ===
                                                        form.priority,
                                                )?.class
                                            "
                                        >
                                            {{ form.priority }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                >Detailed Message</label
                            >
                            <div
                                class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <TiptapEditor v-model="form.content" />
                            </div>
                            <InputError :message="form.errors.content" />
                        </div>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-9 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200"
                            >
                                <Target class="size-4" />
                            </div>
                            <div>
                                <h2
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Audience Targeting
                                </h2>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ audienceMode }}
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addTarget"
                            class="h-8 rounded-md text-xs font-bold"
                        >
                            <Plus class="mr-1.5 size-3.5" />
                            Add Rule
                        </Button>
                    </div>

                    <div class="space-y-3 p-5">
                        <div
                            v-if="form.targets.length === 0"
                            class="flex items-center gap-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-5 dark:border-white/10 dark:bg-white/5"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-md bg-white text-slate-400 shadow-sm dark:bg-slate-900"
                            >
                                <Users class="size-5" />
                            </div>
                            <div>
                                <p
                                    class="text-sm font-bold text-slate-800 dark:text-slate-100"
                                >
                                    Public announcement
                                </p>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Visible to all users with announcement
                                    access.
                                </p>
                            </div>
                        </div>

                        <div
                            v-for="(target, index) in form.targets"
                            :key="index"
                            class="relative rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <p
                                    class="text-xs font-bold text-slate-500 uppercase"
                                >
                                    Rule {{ index + 1 }}
                                </p>
                                <button
                                    type="button"
                                    @click="removeTarget(index)"
                                    class="inline-flex size-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                <label class="space-y-1.5">
                                    <span
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Role</span
                                    >
                                    <select
                                        v-model="target.role_id"
                                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-800 outline-none focus:border-sky-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">Any Role</option>
                                        <option
                                            v-for="role in roles"
                                            :key="role.id"
                                            :value="role.id"
                                        >
                                            {{ role.name }}
                                        </option>
                                    </select>
                                </label>
                                <label class="space-y-1.5">
                                    <span
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Campus</span
                                    >
                                    <select
                                        v-model="target.campus_id"
                                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-800 outline-none focus:border-sky-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">Any Campus</option>
                                        <option
                                            v-for="campus in campuses"
                                            :key="campus.id"
                                            :value="campus.id"
                                        >
                                            {{ campus.name }}
                                        </option>
                                    </select>
                                </label>
                                <label class="space-y-1.5">
                                    <span
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Office</span
                                    >
                                    <select
                                        v-model="target.office_id"
                                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-800 outline-none focus:border-sky-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">Any Office</option>
                                        <option
                                            v-for="office in offices"
                                            :key="office.id"
                                            :value="office.id"
                                        >
                                            {{ office.name }}
                                        </option>
                                    </select>
                                </label>
                                <label class="space-y-1.5">
                                    <span
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Year Level</span
                                    >
                                    <select
                                        v-model="target.year_level"
                                        class="h-9 w-full rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-slate-800 outline-none focus:border-sky-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                    >
                                        <option value="">Any Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:col-span-4">
                <div class="sticky top-6 space-y-5">
                    <section
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-md border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200"
                                >
                                    <Settings class="size-4" />
                                </div>
                                <div>
                                    <h2
                                        class="text-sm font-bold text-slate-950 dark:text-white"
                                    >
                                        Publication
                                    </h2>
                                    <p
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ publicationMode }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5 p-5">
                            <div class="space-y-2">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                    >Category</label
                                >
                                <select
                                    v-model="form.category_id"
                                    class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-900 transition outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-sky-500/10"
                                >
                                    <option value="">Select category</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.category_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                    >Priority</label
                                >
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="priority in priorities"
                                        :key="priority.value"
                                        type="button"
                                        @click="form.priority = priority.value"
                                        class="h-9 rounded-md border text-[10px] font-bold uppercase transition"
                                        :class="
                                            form.priority === priority.value
                                                ? `${priority.class} bg-slate-50 shadow-sm dark:bg-white/10`
                                                : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-white/5'
                                        "
                                    >
                                        {{ priority.label }}
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label
                                    class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300"
                                    >Schedule</label
                                >
                                <div class="relative">
                                    <Calendar
                                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        type="datetime-local"
                                        v-model="form.publish_at"
                                        class="h-10 w-full rounded-md border border-slate-200 bg-white pr-3 pl-9 text-xs font-medium text-slate-900 transition outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:focus:ring-sky-500/10"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <label
                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                                >
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="flex size-8 items-center justify-center rounded-md bg-white text-sky-600 shadow-sm dark:bg-slate-900"
                                        >
                                            <Pin
                                                :class="[
                                                    'size-4',
                                                    form.is_pinned
                                                        ? 'fill-sky-600'
                                                        : '',
                                                ]"
                                            />
                                        </span>
                                        <span>
                                            <span
                                                class="block text-xs font-bold text-slate-800 dark:text-slate-100"
                                                >Pin to Top</span
                                            >
                                            <span
                                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                                >Featured placement</span
                                            >
                                        </span>
                                    </span>
                                    <input
                                        type="checkbox"
                                        v-model="form.is_pinned"
                                        class="size-5 rounded border-slate-300 text-sky-600 dark:border-white/20 dark:bg-slate-900"
                                    />
                                </label>

                                <label
                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                                >
                                    <span class="flex items-center gap-3">
                                        <span
                                            class="flex size-8 items-center justify-center rounded-md bg-white text-emerald-600 shadow-sm dark:bg-slate-900"
                                        >
                                            <Bell class="size-4" />
                                        </span>
                                        <span>
                                            <span
                                                class="block text-xs font-bold text-slate-800 dark:text-slate-100"
                                                >Send Notification</span
                                            >
                                            <span
                                                class="block text-[10px] font-bold text-slate-400 uppercase"
                                                >Email and in-app alert</span
                                            >
                                        </span>
                                    </span>
                                    <input
                                        type="checkbox"
                                        v-model="form.send_notification"
                                        class="size-5 rounded border-slate-300 text-emerald-600 dark:border-white/20 dark:bg-slate-900"
                                    />
                                </label>
                            </div>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-md border border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-400/30 dark:bg-violet-500/10 dark:text-violet-200"
                                >
                                    <Paperclip class="size-4" />
                                </div>
                                <div>
                                    <h2
                                        class="text-sm font-bold text-slate-950 dark:text-white"
                                    >
                                        Attachments
                                    </h2>
                                    <p
                                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ form.attachments.length }} files,
                                        {{ formatBytes(attachmentSize) }}
                                    </p>
                                </div>
                            </div>
                            <label
                                class="inline-flex h-8 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[10px] font-bold text-slate-600 uppercase transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-white/10"
                            >
                                <UploadCloud class="size-3.5" />
                                Add
                                <input
                                    type="file"
                                    multiple
                                    @change="handleFileSelect"
                                    class="hidden"
                                />
                            </label>
                        </div>

                        <div class="space-y-2 p-4">
                            <div
                                v-if="form.attachments.length === 0"
                                class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-5 text-center dark:border-white/10 dark:bg-white/5"
                            >
                                <FileText
                                    class="mx-auto size-6 text-slate-300 dark:text-slate-600"
                                />
                                <p
                                    class="mt-2 text-[10px] font-bold text-slate-400 uppercase"
                                >
                                    No attachments
                                </p>
                            </div>

                            <div
                                v-for="(file, index) in form.attachments"
                                :key="index"
                                class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-white text-slate-500 shadow-sm dark:bg-slate-900"
                                    >
                                        <FileText class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-xs font-bold text-slate-800 dark:text-slate-100"
                                        >
                                            {{ file.name }}
                                        </p>
                                        <p
                                            class="text-[10px] font-medium text-slate-500"
                                        >
                                            {{ formatBytes(file.size) }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removeAttachment(index)"
                                    class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div class="grid grid-cols-2 gap-2">
                            <div
                                class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                            >
                                <div
                                    class="flex items-center gap-2 text-slate-500"
                                >
                                    <Clock3 class="size-3.5" />
                                    <p class="text-[10px] font-bold uppercase">
                                        Timing
                                    </p>
                                </div>
                                <p
                                    class="mt-1 text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{ publicationMode }}
                                </p>
                            </div>
                            <div
                                class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5"
                            >
                                <div
                                    class="flex items-center gap-2 text-slate-500"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    <p class="text-[10px] font-bold uppercase">
                                        Status
                                    </p>
                                </div>
                                <p
                                    class="mt-1 text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    {{
                                        completionScore === 4
                                            ? 'Ready'
                                            : 'Drafting'
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </main>
    </div>
</template>
