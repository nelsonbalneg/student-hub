<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import {
    Building2,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FilePlus2,
    Pencil,
    Search,
    Send,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    society?: any | null;
    registeredSocieties?: any[];
    activeTerm?: any | null;
}>();

const showForm = ref(Boolean(props.society));
const search = ref('');
const registrationBlocked = ref(false);
const deleteDialog = ref({
    show: false,
    title: 'Delete Society Registration',
    description: '',
    confirmText: 'Delete Society',
    loading: false,
    society: null as any | null,
});

const form = useForm({
    full_name: props.society?.full_name ?? props.society?.name ?? '',
    abbreviation: props.society?.abbreviation ?? props.society?.acronym ?? '',
    category: props.society?.category ?? props.society?.society_type ?? '',
    college_unit: props.society?.college_unit ?? props.society?.college ?? '',
    description: props.society?.description ?? '',
    facebook_page_url: props.society?.facebook_page_url ?? '',
});

const societies = computed(() => props.registeredSocieties ?? []);
const hasRegisteredSociety = computed(() => societies.value.length > 0);

const filteredSocieties = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return societies.value;
    }

    return societies.value.filter((item) =>
        [
            item.full_name,
            item.name,
            item.abbreviation,
            item.acronym,
            item.category,
            item.society_type,
            item.college_unit,
            item.college,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query)),
    );
});

const latestAccreditation = (item: any) =>
    item.accreditation_requests?.[0] ?? null;

const activeTermAccreditation = (item: any) => {
    if (!props.activeTerm) {
        return null;
    }

    return (
        item.accreditation_requests?.find(
            (request: any) =>
                request.semester === props.activeTerm.semester &&
                request.school_year === props.activeTerm.school_year,
        ) ?? null
    );
};

const displayStatus = (item: any) => {
    const accreditation = latestAccreditation(item);

    if (accreditation) {
        return accreditation.status;
    }

    return item.status ?? 'draft';
};

const isSocietyPublished = (item: any) => {
    const normalized = String(item.status ?? '').toLowerCase();

    return normalized !== 'draft';
};

const statusClass = (status: string) => {
    const normalized = String(status ?? '').toLowerCase();

    if (normalized === 'approved' || normalized === 'accredited') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (normalized === 'returned') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300';
    }

    if (normalized === 'rejected') {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
    }

    if (normalized === 'submitted' || normalized === 'under_review') {
        return 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-500/10 dark:text-indigo-300';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
};

const resetForNewSociety = () => {
    if (hasRegisteredSociety.value && !props.society?.id) {
        registrationBlocked.value = true;
        showForm.value = false;
        return;
    }

    registrationBlocked.value = false;
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    if (props.society?.id) {
        form.patch(`/societies/manage/${props.society.id}/profile`);
        return;
    }

    form.post('/societies/registration');
};

const canDeleteSociety = (item: any) =>
    !item.accreditation_requests?.some(
        (request: any) => request.status !== 'draft',
    );

const publishSociety = (item: any) => {
    router.patch(
        `/societies/manage/${item.id}/publish`,
        {},
        {
            preserveScroll: true,
        },
    );
};

const destroySociety = (item: any) => {
    const name = item.full_name ?? item.name ?? 'this society';

    deleteDialog.value = {
        show: true,
        title: 'Delete Society Registration',
        description: `Delete ${name}? This will remove the draft registration and related draft records. This action cannot be undone.`,
        confirmText: 'Delete Society',
        loading: false,
        society: item,
    };
};

const confirmDeleteSociety = () => {
    if (!deleteDialog.value.society) {
        return;
    }

    router.delete(`/societies/${deleteDialog.value.society.id}`, {
        preserveScroll: true,
        onStart: () => {
            deleteDialog.value.loading = true;
        },
        onFinish: () => {
            deleteDialog.value.loading = false;
            deleteDialog.value.show = false;
            deleteDialog.value.society = null;
        },
    });
};
</script>

<template>
    <Head title="Society Registration" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <section
            class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                    >
                        <Building2 class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-bold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            USM-OSA-F06-Rev.02.2025.05.05
                        </p>
                        <h1
                            class="truncate text-lg font-black text-slate-900 dark:text-white"
                        >
                            Society Registration
                        </h1>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Register a society, monitor status, and renew
                            accreditation every semester.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="hasRegisteredSociety && !society?.id"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 text-xs font-bold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 disabled:shadow-none dark:shadow-none dark:disabled:bg-white/10 dark:disabled:text-slate-500"
                    @click="resetForNewSociety"
                >
                    <FilePlus2 class="size-4" />
                    Register New Society
                </button>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-4">
                <div
                    v-if="registrationBlocked"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                >
                    You already registered a society. Open Accreditation on the
                    existing society for the active semester instead of
                    registering it again.
                </div>

                <section
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 p-4 md:flex-row md:items-center md:justify-between dark:border-white/10"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                Your Registered Societies
                            </h2>
                            <p
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Check the society you registered and open
                                renewal per semester.
                            </p>
                        </div>
                        <div class="relative w-full md:w-72">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search society"
                                class="h-9 w-full rounded-md border-slate-200 bg-slate-50 pr-3 pl-9 text-xs font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[920px] text-sm">
                            <thead
                                class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Society</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">College / Unit</th>
                                    <th class="px-4 py-3">Latest Term</th>
                                    <th class="px-4 py-3">Active Term</th>
                                    <th class="px-4 py-3 text-center">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <tr
                                    v-for="item in filteredSocieties"
                                    :key="item.id"
                                    class="hover:bg-slate-50/80 dark:hover:bg-white/5"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-black text-slate-700 dark:bg-white/5 dark:text-slate-200"
                                            >
                                                {{
                                                    item.abbreviation ??
                                                    item.acronym ??
                                                    'SOC'
                                                }}
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate font-bold text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        item.full_name ??
                                                        item.name
                                                    }}
                                                </p>
                                                <p
                                                    class="truncate text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        item.facebook_page_url ??
                                                        'No Facebook page linked'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            item.category ??
                                            item.society_type ??
                                            '-'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            item.college_unit ??
                                            item.college ??
                                            '-'
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                                    >
                                        <template
                                            v-if="latestAccreditation(item)"
                                        >
                                            {{
                                                latestAccreditation(item)
                                                    .semester
                                            }}
                                            ·
                                            {{
                                                latestAccreditation(item)
                                                    .school_year
                                            }}
                                        </template>
                                        <template v-else>
                                            No application yet
                                        </template>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                                    >
                                        <template v-if="activeTerm">
                                            <span
                                                v-if="
                                                    activeTermAccreditation(
                                                        item,
                                                    )
                                                "
                                                class="text-emerald-700 dark:text-emerald-300"
                                            >
                                                Applied ·
                                                {{ activeTerm.semester }}
                                                {{ activeTerm.school_year }}
                                                <span v-if="activeTerm.term_id">
                                                    · Term ID
                                                    {{
                                                        activeTerm.term_id
                                                    }}</span
                                                >
                                            </span>
                                            <span v-else>
                                                {{ activeTerm.semester }} ·
                                                {{ activeTerm.school_year }}
                                                <span v-if="activeTerm.term_id">
                                                    · Term ID
                                                    {{
                                                        activeTerm.term_id
                                                    }}</span
                                                >
                                            </span>
                                        </template>
                                        <template v-else>
                                            No active campus term
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase"
                                            :class="
                                                statusClass(displayStatus(item))
                                            "
                                        >
                                            {{
                                                displayStatus(item).replace(
                                                    '_',
                                                    ' ',
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Link
                                                :href="`/societies/manage/${item.id}/profile`"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                                            >
                                                <Pencil class="size-3.5" />
                                                Edit
                                            </Link>
                                            <button
                                                v-if="!isSocietyPublished(item)"
                                                type="button"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-3 text-[11px] font-bold text-white transition hover:bg-emerald-700"
                                                title="Publish registration before accreditation"
                                                @click="publishSociety(item)"
                                            >
                                                <Send class="size-3.5" />
                                                Publish
                                            </button>
                                            <Link
                                                v-else
                                                :href="`/societies/manage/${item.id}/accreditation`"
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md bg-slate-900 px-3 text-[11px] font-bold text-white transition hover:bg-indigo-700 dark:bg-white dark:text-slate-950"
                                                title="Open accreditation application"
                                            >
                                                <ClipboardList
                                                    class="size-3.5"
                                                />
                                                {{
                                                    activeTermAccreditation(
                                                        item,
                                                    )
                                                        ? 'View Accreditation'
                                                        : 'Accreditation'
                                                }}
                                            </Link>
                                            <button
                                                type="button"
                                                :disabled="
                                                    !canDeleteSociety(item)
                                                "
                                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 text-[11px] font-bold text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20 dark:disabled:border-white/10 dark:disabled:bg-white/5 dark:disabled:text-slate-500"
                                                :title="
                                                    canDeleteSociety(item)
                                                        ? 'Delete society registration'
                                                        : 'Society with accreditation history cannot be deleted'
                                                "
                                                @click="destroySociety(item)"
                                            >
                                                <Trash2 class="size-3.5" />
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredSocieties.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-12 text-center"
                                    >
                                        <div
                                            class="mx-auto flex max-w-sm flex-col items-center gap-2"
                                        >
                                            <ClipboardList
                                                class="size-8 text-slate-300 dark:text-slate-700"
                                            />
                                            <p
                                                class="text-sm font-bold text-slate-700 dark:text-slate-200"
                                            >
                                                No registered society found
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Use the button above to register
                                                your first society.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <form
                    v-if="showForm && (!hasRegisteredSociety || society?.id)"
                    class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    @submit.prevent="submit"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{
                                    society?.id
                                        ? 'Update Society Profile'
                                        : 'Register New Society'
                                }}
                            </h2>
                            <p
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Complete the organization information used by
                                OSA during accreditation.
                            </p>
                        </div>
                        <span
                            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-500 uppercase dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                        >
                            {{ society?.status ?? 'Draft' }}
                        </span>
                    </div>

                    <div class="grid gap-4 p-4 lg:grid-cols-12">
                        <label class="space-y-1.5 lg:col-span-6">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Full Society Name
                            </span>
                            <input
                                v-model="form.full_name"
                                placeholder="e.g. Society of Computer Scientists"
                                class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                            <p
                                v-if="form.errors.full_name"
                                class="text-xs font-semibold text-red-600"
                            >
                                {{ form.errors.full_name }}
                            </p>
                        </label>

                        <label class="space-y-1.5 lg:col-span-2">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Abbreviation
                            </span>
                            <input
                                v-model="form.abbreviation"
                                placeholder="SOCS"
                                class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium uppercase dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </label>

                        <label class="space-y-1.5 lg:col-span-4">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Category / Type
                            </span>
                            <select
                                v-model="form.category"
                                class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            >
                                <option value="">Select category</option>
                                <option>Academic Organization</option>
                                <option>College-Based Organization</option>
                                <option>University-Wide Organization</option>
                                <option>Religious Organization</option>
                                <option>Cultural Organization</option>
                                <option>Sports Organization</option>
                                <option>Special Interest Organization</option>
                            </select>
                        </label>

                        <label class="space-y-1.5 lg:col-span-6">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                College / Unit
                            </span>
                            <input
                                v-model="form.college_unit"
                                placeholder="e.g. College of Computing Studies"
                                class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </label>

                        <label class="space-y-1.5 lg:col-span-6">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Facebook Page URL
                            </span>
                            <input
                                v-model="form.facebook_page_url"
                                placeholder="https://facebook.com/your-society"
                                class="h-10 w-full rounded-md border-slate-200 bg-slate-50 px-3 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                            <p
                                v-if="form.errors.facebook_page_url"
                                class="text-xs font-semibold text-red-600"
                            >
                                {{ form.errors.facebook_page_url }}
                            </p>
                        </label>

                        <label class="space-y-1.5 lg:col-span-12">
                            <span
                                class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Description
                            </span>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                placeholder="Briefly describe the purpose, scope, and membership of the society."
                                class="w-full rounded-md border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        </label>
                    </div>

                    <div
                        class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-950/95"
                    >
                        <p
                            class="hidden text-[11px] font-medium text-slate-500 sm:block dark:text-slate-400"
                        >
                            After saving and publishing, open Accreditation to
                            submit the semester application.
                        </p>
                        <div class="ml-auto flex gap-2">
                            <button
                                type="button"
                                class="h-9 rounded-md border border-slate-200 px-4 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5"
                                @click="showForm = false"
                            >
                                Close
                            </button>
                            <button
                                :disabled="form.processing"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 text-xs font-bold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                <CheckCircle2 class="size-4" />
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : society?.id
                                          ? 'Update Society'
                                          : 'Save Society'
                                }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="space-y-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-slate-50 text-slate-700 dark:bg-white/5 dark:text-slate-200"
                        >
                            <CalendarClock class="size-5" />
                        </div>
                        <div>
                            <h3
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                Semester Accreditation
                            </h3>
                            <p
                                class="text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Accreditation uses the active term configured
                                for your campus.
                            </p>
                        </div>
                    </div>
                    <div
                        class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-white/5 dark:text-slate-300"
                    >
                        <p v-if="activeTerm">
                            Active term:
                            <strong
                                >{{ activeTerm.semester }}
                                {{ activeTerm.school_year }}</strong
                            >
                            for {{ activeTerm.campus_name }}.
                            <span v-if="activeTerm.term_id"
                                >Term ID:
                                <strong>{{ activeTerm.term_id }}</strong
                                >.</span
                            >
                        </p>
                        <p v-else>
                            No active term is configured for your campus.
                        </p>
                        <p>
                            Each society can keep yearly and semestral
                            accreditation history.
                        </p>
                        <p>
                            Approved applications are locked unless OSA reopens
                            them.
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <h3
                        class="text-xs font-bold text-slate-900 dark:text-white"
                    >
                        Quick Count
                    </h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-md bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[10px] font-bold text-slate-500 uppercase"
                            >
                                Registered
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-slate-900 dark:text-white"
                            >
                                {{ societies.length }}
                            </p>
                        </div>
                        <div class="rounded-md bg-slate-50 p-3 dark:bg-white/5">
                            <p
                                class="text-[10px] font-bold text-slate-500 uppercase"
                            >
                                Needs Accreditation
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-slate-900 dark:text-white"
                            >
                                {{
                                    activeTerm
                                        ? societies.filter(
                                              (item) =>
                                                  isSocietyPublished(item) &&
                                                  !activeTermAccreditation(
                                                      item,
                                                  ),
                                          ).length
                                        : 0
                                }}
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <ConfirmationModal
            :show="deleteDialog.show"
            :title="deleteDialog.title"
            :description="deleteDialog.description"
            :confirm-text="
                deleteDialog.loading ? 'Deleting...' : deleteDialog.confirmText
            "
            cancel-text="Keep Society"
            variant="destructive"
            :loading="deleteDialog.loading"
            compact
            @close="deleteDialog.show = false"
            @confirm="confirmDeleteSociety"
        >
            <template #confirm-icon>
                <Trash2 class="size-3.5" />
            </template>
        </ConfirmationModal>
    </div>
</template>
