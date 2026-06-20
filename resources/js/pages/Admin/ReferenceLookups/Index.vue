<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Calendar, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import ConfirmationModal from '@/components/ConfirmationModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import * as semesterRoutes from '@/routes/admin/reference-lookups/semesters';

interface Semester {
    id: number;
    academic_year: string;
    term: string;
    campus_id: number;
    campus_name: string | null;
    is_active: boolean;
    start_date: string | null;
    end_date: string | null;
}

const props = defineProps<{
    semesters: Semester[];
}>();

const showModal = ref(false);
const showDeleteModal = ref(false);
const selectedSemester = ref<Semester | null>(null);

const form = useForm({
    academic_year: '',
    term: '',
    campus_id: 1 as number,
    is_active: false,
    start_date: '',
    end_date: '',
});

const openCreate = () => {
    selectedSemester.value = null;
    form.reset();
    form.campus_id = 1;
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (semester: Semester) => {
    selectedSemester.value = semester;
    form.academic_year = semester.academic_year;
    form.term = semester.term;
    form.campus_id = semester.campus_id;
    form.is_active = semester.is_active;
    form.start_date = semester.start_date?.split('T')[0] ?? '';
    form.end_date = semester.end_date?.split('T')[0] ?? '';
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showModal.value = false),
    };

    if (selectedSemester.value) {
        form.patch(
            semesterRoutes.update.url(selectedSemester.value.id),
            options,
        );

        return;
    }

    form.post(semesterRoutes.store.url(), options);
};

const confirmDelete = (semester: Semester) => {
    selectedSemester.value = semester;
    showDeleteModal.value = true;
};

const deleteSemester = () => {
    if (!selectedSemester.value) {
return;
}

    form.delete(semesterRoutes.destroy.url(selectedSemester.value.id), {
        preserveScroll: true,
        onSuccess: () => (showDeleteModal.value = false),
    });
};
</script>

<template>
    <Head title="Semester Reference Lookups" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-slate-900 dark:text-white">
                    Semester Reference Lookups
                </h1>
                <p class="text-xs text-slate-500">
                    Offices and clearance types are now managed under Site
                    Settings.
                </p>
            </div>
            <Button
                class="h-8 gap-1.5 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                @click="openCreate"
            >
                <Plus class="size-3.5" />
                Add Semester
            </Button>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
        >
            <table class="w-full text-left text-xs">
                <thead
                    class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-500 uppercase dark:border-white/10 dark:bg-white/5"
                >
                    <tr>
                        <th class="px-4 py-3">Academic year</th>
                        <th class="px-4 py-3">Term</th>
                        <th class="px-4 py-3">Campus</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    <tr
                        v-for="semester in props.semesters"
                        :key="semester.id"
                        class="hover:bg-slate-50 dark:hover:bg-white/5"
                    >
                        <td
                            class="px-4 py-3 font-semibold text-slate-900 dark:text-white"
                        >
                            {{ semester.academic_year }}
                        </td>
                        <td
                            class="px-4 py-3 text-slate-600 dark:text-slate-300"
                        >
                            {{ semester.term }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ semester.campus_name || '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'rounded-full px-2 py-1 text-[10px] font-bold',
                                    semester.is_active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400',
                                ]"
                            >
                                {{ semester.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    @click="openEdit(semester)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10"
                                    @click="confirmDelete(semester)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div
        v-if="showModal"
        class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
        @click.self="showModal = false"
    >
        <div
            class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-slate-950"
        >
            <div class="flex items-center gap-2">
                <Calendar class="size-4 text-emerald-500" />
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ selectedSemester ? 'Edit' : 'Add' }} Semester
                </h2>
            </div>
            <form class="mt-4 grid gap-3" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Academic Year
                        <input
                            v-model="form.academic_year"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 dark:border-white/10 dark:bg-slate-900"
                        />
                        <InputError :message="form.errors.academic_year" />
                    </label>
                    <label
                        class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Term
                        <input
                            v-model="form.term"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 dark:border-white/10 dark:bg-slate-900"
                        />
                        <InputError :message="form.errors.term" />
                    </label>
                </div>
                <label
                    class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                >
                    Campus
                    <select
                        v-model="form.campus_id"
                        class="h-9 rounded-md border border-slate-200 bg-white px-3 dark:border-white/10 dark:bg-slate-900"
                    >
                        <option :value="1">Main Campus</option>
                        <option :value="3">USM KCC</option>
                        <option :value="4">Advance Education</option>
                    </select>
                    <InputError :message="form.errors.campus_id" />
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label
                        class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Start Date
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 dark:border-white/10 dark:bg-slate-900"
                        />
                    </label>
                    <label
                        class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >
                        End Date
                        <input
                            v-model="form.end_date"
                            type="date"
                            class="h-9 rounded-md border border-slate-200 bg-white px-3 dark:border-white/10 dark:bg-slate-900"
                        />
                    </label>
                </div>
                <label
                    class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-emerald-600"
                    />
                    Mark as active semester
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="showModal = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        :disabled="form.processing"
                    >
                        Save Semester
                    </Button>
                </div>
            </form>
        </div>
    </div>

    <ConfirmationModal
        :show="showDeleteModal"
        title="Delete Semester"
        :description="`Delete ${selectedSemester?.academic_year ?? 'this semester'} ${selectedSemester?.term ?? ''}?`"
        confirm-text="Delete"
        variant="destructive"
        :loading="form.processing"
        @close="showDeleteModal = false"
        @confirm="deleteSemester"
    />
</template>
