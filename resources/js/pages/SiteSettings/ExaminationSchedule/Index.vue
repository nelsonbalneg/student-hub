<script setup lang="ts">
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import {
    Plus,
    Edit3,
    Trash2,
    CalendarDays,
    ClipboardList,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import * as examinationScheduleRoutes from '@/routes/site-settings/examination-schedules';

type Campus = { id: number; name: string };
type Term = { id: number; name: string; site_campus_id: number };
type Schedule = {
    id: number;
    title: string;
    academic_term_id: number;
    campus_id: number;
    start_date: string;
    end_date: string;
    status: 'Draft' | 'Published';
    description: string | null;
    campus: Campus;
    academic_term: Term;
};

const props = defineProps<{
    schedules: { data: Schedule[]; links: any[]; current_page: number };
    campuses: Campus[];
    terms: Term[];
}>();

const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const pendingDelete = ref<number | null>(null);
const isDeleting = ref(false);

const form = useForm({
    title: '',
    academic_term_id: '' as number | '',
    campus_id: '' as number | '',
    start_date: '',
    end_date: '',
    description: '',
    status: 'Draft' as 'Draft' | 'Published',
});

const filteredTerms = computed(() => {
    if (!form.campus_id) {
        return [];
    }

    return props.terms.filter(
        (term) => Number(term.site_campus_id) === Number(form.campus_id),
    );
});

const dateValue = (value: string | null | undefined) =>
    value ? value.substring(0, 10) : '';

watch(
    () => form.campus_id,
    (newValue) => {
        if (form.academic_term_id) {
            const term = props.terms.find(
                (item) => Number(item.id) === Number(form.academic_term_id),
            );

            if (term && Number(term.site_campus_id) !== Number(newValue)) {
                form.academic_term_id = '';
            }
        }
    },
);

watch(
    [() => form.campus_id, () => form.academic_term_id],
    ([campusId, termId]) => {
        if (campusId && termId && !editingId.value) {
            const campus = props.campuses.find(
                (item) => Number(item.id) === Number(campusId),
            );
            const term = props.terms.find(
                (item) => Number(item.id) === Number(termId),
            );

            if (campus && term) {
                form.title = `${term.name} Examination - ${campus.name}`;
            }
        }
    },
);

const openModal = (schedule?: Schedule) => {
    editingId.value = schedule?.id ?? null;
    form.title = schedule?.title ?? '';
    form.academic_term_id = schedule?.academic_term_id ?? '';
    form.campus_id = schedule?.campus_id ?? '';
    form.start_date = dateValue(schedule?.start_date);
    form.end_date = dateValue(schedule?.end_date);
    form.description = schedule?.description ?? '';
    form.status = schedule?.status ?? 'Draft';
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.patch(examinationScheduleRoutes.update.url(editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Examination schedule updated successfully.');
            },
        });

        return;
    }

    form.post(examinationScheduleRoutes.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Examination schedule created successfully.');
        },
    });
};

const confirmDelete = () => {
    if (!pendingDelete.value) {
        return;
    }

    isDeleting.value = true;
    router.delete(examinationScheduleRoutes.destroy.url(pendingDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
            toast.success('Examination schedule deleted successfully.');
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <SiteSettingsLayout>
        <Head title="Examination Schedules" />

        <div class="flex h-full flex-col p-4 sm:p-6 lg:p-8">
            <div
                class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        Examination Schedules
                    </h1>
                    <p
                        class="mt-1.5 text-sm text-slate-500 dark:text-slate-400"
                    >
                        Manage exam schedules and import subject data.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="openModal()"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                    >
                        <Plus class="size-4" />
                        Create Schedule
                    </button>
                </div>
            </div>

            <div
                class="flex-1 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900/50"
            >
                <table
                    class="min-w-full divide-y divide-slate-200 dark:divide-white/5"
                >
                    <thead class="bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Title
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Term & Campus
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Examination Period
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-4 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Status
                            </th>
                            <th scope="col" class="relative px-6 py-4">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 bg-white dark:divide-white/5 dark:bg-transparent"
                    >
                        <tr
                            v-for="schedule in schedules.data"
                            :key="schedule.id"
                            class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    <Link
                                        :href="
                                            examinationScheduleRoutes.show.url(
                                                schedule.id,
                                            )
                                        "
                                        class="hover:text-emerald-600 hover:underline"
                                    >
                                        {{ schedule.title }}
                                    </Link>
                                </div>
                                <div
                                    class="line-clamp-1 max-w-xs text-xs text-slate-500"
                                >
                                    {{ schedule.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm text-slate-900 dark:text-white"
                                >
                                    {{ schedule.academic_term.semester }} - A.Y.
                                    {{ schedule.academic_term.school_year }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ schedule.campus.campus_name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm text-slate-900 dark:text-white"
                                >
                                    {{ dateValue(schedule.start_date) }} —
                                    {{ dateValue(schedule.end_date) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        schedule.status === 'Published'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400'
                                            : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400',
                                    ]"
                                >
                                    {{ schedule.status }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <button
                                        @click="openModal(schedule)"
                                        class="text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400"
                                    >
                                        <Edit3 class="size-4" />
                                    </button>
                                    <button
                                        @click="pendingDelete = schedule.id"
                                        class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="schedules.data.length === 0">
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-sm text-slate-500"
                            >
                                No examination schedules found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Sheet
                :open="modalOpen"
                @update:open="
                    (open) => (open ? (modalOpen = true) : closeModal())
                "
            >
                <SheetContent
                    side="right"
                    class="flex h-full w-full flex-col gap-0 overflow-hidden border-slate-200 p-0 sm:max-w-xl dark:border-white/10"
                >
                    <SheetHeader
                        class="border-b border-slate-100 bg-emerald-50/60 py-5 pr-14 pl-6 text-left dark:border-white/10 dark:bg-emerald-500/5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                            >
                                <ClipboardList class="size-5" />
                            </div>
                            <div>
                                <SheetTitle
                                    class="text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        editingId
                                            ? 'Edit Examination Schedule'
                                            : 'Create Examination Schedule'
                                    }}
                                </SheetTitle>
                                <SheetDescription class="mt-1 text-xs">
                                    {{
                                        editingId
                                            ? 'Update the examination period and publishing status.'
                                            : 'Choose a campus and academic term, then define the examination period.'
                                    }}
                                </SheetDescription>
                            </div>
                        </div>
                    </SheetHeader>

                    <form
                        class="flex min-h-0 flex-1 flex-col"
                        @submit.prevent="submit"
                    >
                        <div class="flex-1 space-y-6 overflow-y-auto p-6">
                            <section class="space-y-4">
                                <div>
                                    <p
                                        class="text-xs font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        Academic session
                                    </p>
                                    <p
                                        class="mt-0.5 text-[11px] text-slate-500"
                                    >
                                        Terms are filtered automatically by the
                                        selected campus.
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <label
                                            for="schedule-campus"
                                            class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            Campus
                                        </label>
                                        <select
                                            id="schedule-campus"
                                            v-model="form.campus_id"
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                            required
                                        >
                                            <option value="" disabled>
                                                Select campus
                                            </option>
                                            <option
                                                v-for="campus in campuses"
                                                :key="campus.id"
                                                :value="campus.id"
                                            >
                                                {{ campus.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="form.errors.campus_id"
                                            class="text-xs text-rose-500"
                                        >
                                            {{ form.errors.campus_id }}
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <label
                                            for="schedule-term"
                                            class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            Academic term
                                        </label>
                                        <select
                                            id="schedule-term"
                                            v-model="form.academic_term_id"
                                            :disabled="
                                                !form.campus_id ||
                                                filteredTerms.length === 0
                                            "
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:disabled:bg-white/5"
                                            required
                                        >
                                            <option value="" disabled>
                                                {{
                                                    !form.campus_id
                                                        ? 'Select campus first'
                                                        : filteredTerms.length ===
                                                            0
                                                          ? 'No terms available'
                                                          : 'Select term'
                                                }}
                                            </option>
                                            <option
                                                v-for="term in filteredTerms"
                                                :key="term.id"
                                                :value="term.id"
                                            >
                                                {{ term.name }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="form.errors.academic_term_id"
                                            class="text-xs text-rose-500"
                                        >
                                            {{ form.errors.academic_term_id }}
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <div
                                class="border-t border-slate-100 dark:border-white/10"
                            ></div>

                            <section class="space-y-4">
                                <p
                                    class="text-xs font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Schedule details
                                </p>

                                <div class="grid gap-1.5">
                                    <label
                                        for="schedule-title"
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Title
                                    </label>
                                    <input
                                        id="schedule-title"
                                        v-model="form.title"
                                        type="text"
                                        placeholder="e.g. Midterm Examination Schedule"
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                        required
                                    />
                                    <p
                                        v-if="form.errors.title"
                                        class="text-xs text-rose-500"
                                    >
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <label
                                            for="schedule-start-date"
                                            class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            <CalendarDays class="size-3.5" />
                                            From date
                                        </label>
                                        <input
                                            id="schedule-start-date"
                                            v-model="form.start_date"
                                            type="date"
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.start_date"
                                            class="text-xs text-rose-500"
                                        >
                                            {{ form.errors.start_date }}
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <label
                                            for="schedule-end-date"
                                            class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            <CalendarDays class="size-3.5" />
                                            To date
                                        </label>
                                        <input
                                            id="schedule-end-date"
                                            v-model="form.end_date"
                                            type="date"
                                            :min="form.start_date || undefined"
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.end_date"
                                            class="text-xs text-rose-500"
                                        >
                                            {{ form.errors.end_date }}
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5 sm:col-span-2">
                                        <label
                                            for="schedule-status"
                                            class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                        >
                                            Status
                                        </label>
                                        <select
                                            id="schedule-status"
                                            v-model="form.status"
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                            required
                                        >
                                            <option value="Draft">Draft</option>
                                            <option value="Published">
                                                Published
                                            </option>
                                        </select>
                                        <p
                                            v-if="form.errors.status"
                                            class="text-xs text-rose-500"
                                        >
                                            {{ form.errors.status }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <label
                                        for="schedule-description"
                                        class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        Description
                                        <span class="text-slate-400"
                                            >(optional)</span
                                        >
                                    </label>
                                    <textarea
                                        id="schedule-description"
                                        v-model="form.description"
                                        rows="4"
                                        placeholder="Add instructions or notes about this examination schedule..."
                                        class="w-full resize-none rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-800 transition outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 dark:border-white/10 dark:bg-slate-900 dark:text-white"
                                    ></textarea>
                                    <p
                                        v-if="form.errors.description"
                                        class="text-xs text-rose-500"
                                    >
                                        {{ form.errors.description }}
                                    </p>
                                </div>
                            </section>
                        </div>

                        <SheetFooter
                            class="border-t border-slate-100 bg-white px-6 py-4 sm:flex-row sm:justify-end dark:border-white/10 dark:bg-slate-950"
                        >
                            <button
                                type="button"
                                class="h-10 rounded-lg px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5"
                                :disabled="form.processing"
                                @click="closeModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : editingId
                                          ? 'Save Changes'
                                          : 'Create Schedule'
                                }}
                            </button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>

            <ConfirmationModal
                :show="pendingDelete !== null"
                title="Delete Schedule"
                description="Are you sure you want to delete this schedule? All imported subjects will be lost."
                :processing="isDeleting"
                @close="pendingDelete = null"
                @confirm="confirmDelete"
            />
        </div>
    </SiteSettingsLayout>
</template>
