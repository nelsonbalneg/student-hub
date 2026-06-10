<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ requirements: any[] }>();

const form = useForm({
    name: '',
    description: '',
    category: 'general',
    is_required: true,
    is_active: true,
});

const submit = () =>
    form.post('/admin/societies/requirements', {
        onSuccess: () => form.reset('name', 'description'),
    });
</script>

<template>
    <Head title="Requirement Checklist Management" />

    <div
        class="min-h-screen bg-slate-50/50 px-6 py-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="border-b border-slate-200 pb-5 dark:border-slate-800">
                <p
                    class="text-[10px] font-black tracking-[0.24em] text-sky-600 uppercase dark:text-sky-400"
                >
                    OSA Configurable Checklist
                </p>
                <h1 class="text-xl font-black text-slate-950 dark:text-white">
                    Requirement Checklist Management
                </h1>
            </div>

            <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
                <form
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    @submit.prevent="submit"
                >
                    <h2
                        class="text-sm font-black tracking-wider text-slate-900 uppercase dark:text-white"
                    >
                        Add Requirement
                    </h2>
                    <div class="mt-4 space-y-3">
                        <input
                            v-model="form.name"
                            placeholder="Requirement name"
                            class="w-full rounded-md border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <input
                            v-model="form.category"
                            placeholder="Category"
                            class="w-full rounded-md border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Description"
                            class="w-full rounded-md border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        />
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300"
                            ><input
                                v-model="form.is_required"
                                type="checkbox"
                                class="rounded border-slate-300"
                            />
                            Required</label
                        >
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300"
                            />
                            Active</label
                        >
                        <button
                            class="w-full rounded-md bg-slate-950 px-4 py-3 text-sm font-black tracking-wider text-white uppercase dark:bg-sky-600"
                        >
                            Save Requirement
                        </button>
                    </div>
                </form>

                <section
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <table
                        class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800"
                    >
                        <thead
                            class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                        >
                            <tr>
                                <th class="px-4 py-3 text-left">Requirement</th>
                                <th class="px-4 py-3 text-left">Category</th>
                                <th class="px-4 py-3 text-left">Required</th>
                                <th class="px-4 py-3 text-left">Active</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="requirement in requirements"
                                :key="requirement.id"
                            >
                                <td
                                    class="px-4 py-3 font-bold text-slate-950 dark:text-white"
                                >
                                    {{ requirement.name }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                >
                                    {{ requirement.category }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                >
                                    {{ requirement.is_required ? 'Yes' : 'No' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        requirement.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</template>
