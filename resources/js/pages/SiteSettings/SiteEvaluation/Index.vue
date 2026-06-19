<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Edit3,
    Eye,
    MessageSquareHeart,
    Plus,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import * as periodRoutes from '@/routes/site-settings/site-evaluation/periods';
import { results as resultsRoute } from '@/routes/site-settings/site-evaluation';

type Period = {
    id: number;
    evaluation_template_id: number;
    title: string;
    description: string | null;
    start_date: string;
    end_date: string;
    max_skips: number;
    status: 'draft' | 'active' | 'closed';
    submissions_count: number;
    dismissals_count: number;
    skips_count: number;
    template: { id: number; name: string };
};

const props = defineProps<{
    periods: Period[];
    templates: Array<{
        id: number;
        name: string;
        description: string | null;
    }>;
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const modalOpen = ref(false);
const editingPeriod = ref<Period | null>(null);
const pendingDelete = ref<Period | null>(null);
const deleteForm = useForm({});
const form = useForm({
    evaluation_template_id: 0,
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    max_skips: 1,
    status: 'draft' as Period['status'],
});

const openCreate = () => {
    editingPeriod.value = null;
    form.reset();
    form.clearErrors();
    form.evaluation_template_id =
        props.templates.find(
            (template) => template.name === 'Student Portal System Evaluation',
        )?.id ??
        props.templates[0]?.id ??
        0;
    form.status = 'draft';
    form.max_skips = 1;
    modalOpen.value = true;
};

const openEdit = (period: Period) => {
    editingPeriod.value = period;
    form.evaluation_template_id = period.evaluation_template_id;
    form.title = period.title;
    form.description = period.description ?? '';
    form.start_date = period.start_date;
    form.end_date = period.end_date;
    form.max_skips = period.max_skips;
    form.status = period.status;
    form.clearErrors();
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    editingPeriod.value = null;
    form.clearErrors();
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: closeModal };

    if (editingPeriod.value) {
        form.patch(periodRoutes.update.url(editingPeriod.value.id), options);
        return;
    }

    form.post(periodRoutes.store.url(), options);
};

const destroyPeriod = () => {
    if (!pendingDelete.value) return;

    deleteForm.delete(periodRoutes.destroy.url(pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
    });
};

const statusClass = (status: Period['status']) =>
    status === 'active'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
        : status === 'closed'
          ? 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'
          : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
</script>

<template>
    <Head title="Site Settings - Site Evaluation" />

    <SiteSettingsLayout>
        <div class="space-y-6 p-5 sm:p-7 lg:p-10">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <MessageSquareHeart class="size-6 text-emerald-600" />
                        <h1 class="text-2xl font-bold">Site Evaluation</h1>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        Schedule an optional portal evaluation for authenticated
                        users.
                    </p>
                </div>
                <button
                    v-if="can.create"
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="templates.length === 0"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Evaluation Period
                </button>
            </header>

            <div
                v-if="templates.length === 0"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Create and activate an evaluation template first.
            </div>

            <div
                v-if="periods.length === 0"
                class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-white/15"
            >
                <CalendarDays class="size-10 text-slate-300" />
                <h2 class="mt-3 text-base font-bold">
                    No site evaluation periods
                </h2>
                <p class="mt-1 max-w-md text-sm text-slate-500">
                    Add a date range and activate it when the optional feedback
                    drawer should appear.
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:border-white/10 dark:bg-white/[0.04]"
                        >
                            <tr>
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3">Template</th>
                                <th class="px-4 py-3">Schedule</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Responses</th>
                                <th class="px-4 py-3">Skip limit</th>
                                <th class="px-4 py-3">Skips</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr
                                v-for="period in periods"
                                :key="period.id"
                                class="hover:bg-slate-50/70 dark:hover:bg-white/[0.03]"
                            >
                                <td class="px-4 py-4">
                                    <p class="font-bold">{{ period.title }}</p>
                                    <p
                                        class="mt-1 max-w-xs truncate text-xs text-slate-500"
                                    >
                                        {{
                                            period.description ||
                                            'No description'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-xs font-semibold">
                                    {{ period.template.name }}
                                </td>
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    {{ period.start_date }}–{{
                                        period.end_date
                                    }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="rounded-full px-2 py-1 text-[10px] font-bold uppercase"
                                        :class="statusClass(period.status)"
                                    >
                                        {{ period.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-bold">
                                    {{ period.submissions_count }}
                                </td>
                                <td class="px-4 py-4 font-bold">
                                    {{ period.max_skips }} per user
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold">
                                        {{ period.skips_count }}
                                    </p>
                                    <p class="text-[10px] text-slate-500">
                                        {{ period.dismissals_count }} users
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Link
                                            :href="
                                                resultsRoute.url({
                                                    query: {
                                                        period_id: period.id,
                                                    },
                                                })
                                            "
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                            title="View results"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                        <button
                                            v-if="can.update"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10"
                                            @click="openEdit(period)"
                                        >
                                            <Edit3 class="size-4" />
                                        </button>
                                        <button
                                            v-if="can.delete"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                            @click="pendingDelete = period"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <header
                    class="flex items-start justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10"
                >
                    <div>
                        <h2 class="text-base font-bold">
                            {{
                                editingPeriod
                                    ? 'Edit Evaluation Period'
                                    : 'Add Evaluation Period'
                            }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            Only one active site evaluation is shown at a time.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                        @click="closeModal"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <form class="space-y-4 p-5" @submit.prevent="submit">
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Title</span>
                        <input
                            v-model="form.title"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        />
                        <small class="text-red-600">{{
                            form.errors.title
                        }}</small>
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Description</span>
                        <textarea
                            v-model="form.description"
                            class="min-h-20 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        />
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Evaluation template</span>
                        <select
                            v-model="form.evaluation_template_id"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        >
                            <option
                                v-for="template in templates"
                                :key="template.id"
                                :value="template.id"
                            >
                                {{ template.name }}
                            </option>
                        </select>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-xs font-bold">
                            <span>Start date</span>
                            <input
                                v-model="form.start_date"
                                type="date"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                            />
                        </label>
                        <label class="grid gap-1.5 text-xs font-bold">
                            <span>End date</span>
                            <input
                                v-model="form.end_date"
                                type="date"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                            />
                        </label>
                    </div>
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Maximum skips per user</span>
                        <input
                            v-model.number="form.max_skips"
                            type="number"
                            min="1"
                            max="20"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        />
                        <span class="text-[11px] font-normal text-slate-500">
                            The invitation returns on later visits until this
                            limit is reached.
                        </span>
                        <small class="text-red-600">{{
                            form.errors.max_skips
                        }}</small>
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Status</span>
                        <select
                            v-model="form.status"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                        >
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </label>
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Period' }}
                    </button>
                </form>
            </div>
        </div>

        <ConfirmationModal
            :show="pendingDelete !== null"
            title="Delete evaluation period?"
            :description="`Delete ${pendingDelete?.title ?? 'this period'}? This cannot be undone.`"
            confirm-text="Delete"
            variant="danger"
            :loading="deleteForm.processing"
            @close="pendingDelete = null"
            @confirm="destroyPeriod"
        />
    </SiteSettingsLayout>
</template>
