<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Dumbbell, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import * as categoryRoutes from '@/routes/site-settings/physical-fitness/configuration/categories';
import * as componentRoutes from '@/routes/site-settings/physical-fitness/configuration/components';
import * as fieldRoutes from '@/routes/site-settings/physical-fitness/configuration/fields';
import * as interpretationRuleRoutes from '@/routes/site-settings/physical-fitness/configuration/interpretation-rules';
import * as testTypeRoutes from '@/routes/site-settings/physical-fitness/configuration/test-types';

type PftField = {
    id: number;
    pft_test_type_id: number;
    field_name: string;
    field_label: string;
    field_type: string;
    options: string[] | null;
    placeholder: string | null;
    help_text: string | null;
    is_required: boolean;
    sort_order: number;
    is_active: boolean;
};

type PftInterpretationRule = {
    id: number;
    pft_test_type_id: number;
    field_name: string;
    label: string;
    min_value: number | null;
    max_value: number | null;
    color: string | null;
    sort_order: number;
    is_active: boolean;
};

type PftTestType = {
    id: number;
    pft_category_id: number;
    name: string;
    slug: string;
    description: string | null;
    unit: string | null;
    sort_order: number;
    is_active: boolean;
    results_count?: number;
    configurations: PftField[];
    interpretation_rules: PftInterpretationRule[];
};

type PftCategory = {
    id: number;
    pft_component_id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    test_types: PftTestType[];
};

type PftComponent = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    categories: PftCategory[];
};

const props = defineProps<{
    components: PftComponent[];
    fieldTypes: string[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const selectedComponentId = ref(props.components[0]?.id ?? null);
const selectedCategoryId = ref<number | null>(
    props.components[0]?.categories[0]?.id ?? null,
);
const selectedTestTypeId = ref<number | null>(
    props.components[0]?.categories[0]?.test_types[0]?.id ?? null,
);

const selectedComponent = computed(() =>
    props.components.find((item) => item.id === selectedComponentId.value),
);
const selectedCategory = computed(() =>
    selectedComponent.value?.categories.find(
        (item) => item.id === selectedCategoryId.value,
    ),
);
const selectedTestType = computed(() =>
    selectedCategory.value?.test_types.find(
        (item) => item.id === selectedTestTypeId.value,
    ),
);

watch(selectedComponentId, () => {
    selectedCategoryId.value =
        selectedComponent.value?.categories[0]?.id ?? null;
    selectedTestTypeId.value =
        selectedCategory.value?.test_types[0]?.id ?? null;
});

watch(selectedCategoryId, () => {
    selectedTestTypeId.value =
        selectedCategory.value?.test_types[0]?.id ?? null;
});

const componentForm = useForm({
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const categoryForm = useForm({
    pft_component_id: selectedComponentId.value,
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const testTypeForm = useForm({
    pft_category_id: selectedCategoryId.value,
    name: '',
    slug: '',
    description: '',
    unit: '',
    sort_order: 0,
    is_active: true,
});

const fieldForm = useForm({
    pft_test_type_id: selectedTestTypeId.value,
    field_name: '',
    field_label: '',
    field_type: 'text',
    options: '',
    placeholder: '',
    help_text: '',
    is_required: false,
    sort_order: 0,
    is_active: true,
});

const interpretationRuleForm = useForm({
    pft_test_type_id: selectedTestTypeId.value,
    field_name: '',
    label: '',
    min_value: null as number | null,
    max_value: null as number | null,
    color: '',
    sort_order: 0,
    is_active: true,
});

const modal = ref<
    | null
    | { type: 'component'; record?: PftComponent }
    | { type: 'category'; record?: PftCategory }
    | { type: 'testType'; record?: PftTestType }
    | { type: 'field'; record?: PftField }
    | { type: 'interpretationRule'; record?: PftInterpretationRule }
>(null);

const fillCommon = (form: any, record: any = {}) => {
    form.name = record.name ?? '';
    form.slug = record.slug ?? '';
    form.description = record.description ?? '';
    form.sort_order = record.sort_order ?? 0;
    form.is_active = record.is_active ?? true;
};

const openComponent = (record?: PftComponent) => {
    componentForm.clearErrors();
    fillCommon(componentForm, record);
    modal.value = { type: 'component', record };
};

const openCategory = (record?: PftCategory) => {
    categoryForm.clearErrors();
    fillCommon(categoryForm, record);
    categoryForm.pft_component_id =
        record?.pft_component_id ?? selectedComponentId.value;
    modal.value = { type: 'category', record };
};

const openTestType = (record?: PftTestType) => {
    testTypeForm.clearErrors();
    fillCommon(testTypeForm, record);
    testTypeForm.pft_category_id =
        record?.pft_category_id ?? selectedCategoryId.value;
    testTypeForm.unit = record?.unit ?? '';
    modal.value = { type: 'testType', record };
};

const openField = (record?: PftField) => {
    fieldForm.clearErrors();
    fieldForm.pft_test_type_id =
        record?.pft_test_type_id ?? selectedTestTypeId.value;
    fieldForm.field_name = record?.field_name ?? '';
    fieldForm.field_label = record?.field_label ?? '';
    fieldForm.field_type = record?.field_type ?? 'text';
    fieldForm.options = record?.options?.join('\n') ?? '';
    fieldForm.placeholder = record?.placeholder ?? '';
    fieldForm.help_text = record?.help_text ?? '';
    fieldForm.is_required = record?.is_required ?? false;
    fieldForm.sort_order = record?.sort_order ?? 0;
    fieldForm.is_active = record?.is_active ?? true;
    modal.value = { type: 'field', record };
};

const openInterpretationRule = (record?: PftInterpretationRule) => {
    interpretationRuleForm.clearErrors();
    interpretationRuleForm.pft_test_type_id =
        record?.pft_test_type_id ?? selectedTestTypeId.value;
    interpretationRuleForm.field_name =
        record?.field_name ??
        selectedTestType.value?.configurations[0]?.field_name ??
        '';
    interpretationRuleForm.label = record?.label ?? '';
    interpretationRuleForm.min_value = record?.min_value ?? null;
    interpretationRuleForm.max_value = record?.max_value ?? null;
    interpretationRuleForm.color = record?.color ?? '';
    interpretationRuleForm.sort_order = record?.sort_order ?? 0;
    interpretationRuleForm.is_active = record?.is_active ?? true;
    modal.value = { type: 'interpretationRule', record };
};

const submitModal = () => {
    if (!modal.value) {
        return;
    }

    const close = {
        preserveScroll: true,
        onSuccess: () => (modal.value = null),
    };

    if (modal.value.type === 'component') {
        return modal.value.record
            ? componentForm.patch(
                componentRoutes.update.url(modal.value.record.id),
                close,
            )
            : componentForm.post(componentRoutes.store.url(), close);
    }

    if (modal.value.type === 'category') {
        return modal.value.record
            ? categoryForm.patch(
                categoryRoutes.update.url(modal.value.record.id),
                close,
            )
            : categoryForm.post(categoryRoutes.store.url(), close);
    }

    if (modal.value.type === 'testType') {
        return modal.value.record
            ? testTypeForm.patch(
                testTypeRoutes.update.url(modal.value.record.id),
                close,
            )
            : testTypeForm.post(testTypeRoutes.store.url(), close);
    }

    if (modal.value.type === 'interpretationRule') {
        return modal.value.record
            ? interpretationRuleForm.patch(
                interpretationRuleRoutes.update.url(modal.value.record.id),
                close,
            )
            : interpretationRuleForm.post(
                interpretationRuleRoutes.store.url(),
                close,
            );
    }

    return modal.value.record
        ? fieldForm.patch(fieldRoutes.update.url(modal.value.record.id), close)
        : fieldForm.post(fieldRoutes.store.url(), close);
};

const destroyRecord = (type: string, id: number) => {
    if (
        !confirm(
            'Delete this PFT record? If results exist, it will be deactivated instead.',
        )
    ) {
        return;
    }

    const options = { preserveScroll: true };

    if (type === 'component') {
        router.delete(componentRoutes.destroy.url(id), options);
    }

    if (type === 'category') {
        router.delete(categoryRoutes.destroy.url(id), options);
    }

    if (type === 'testType') {
        router.delete(testTypeRoutes.destroy.url(id), options);
    }

    if (type === 'field') {
        router.delete(fieldRoutes.destroy.url(id), options);
    }

    if (type === 'interpretationRule') {
        router.delete(interpretationRuleRoutes.destroy.url(id), options);
    }
};

const statusClass = (active: boolean) =>
    active
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
        : 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-white/5 dark:text-slate-400 dark:ring-white/10';
</script>

<template>

    <Head title="Physical Fitness Configuration" />

    <SiteSettingsLayout>
        <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col text-slate-900 dark:text-slate-100">
            <header class="border-b border-slate-100 px-5 py-4 dark:border-white/10">
                <p class="text-[11px] font-bold tracking-[0.2em] text-emerald-600 uppercase dark:text-emerald-300">
                    Site Settings
                </p>
                <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-slate-950 dark:text-white">
                            Physical Fitness
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Manage Components, Categories, Test Types, and
                            dynamic form fields.
                        </p>
                    </div>
                    <button v-if="can.create" type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700"
                        @click="openComponent()">
                        <Plus class="size-4" /> Component
                    </button>
                </div>
            </header>

            <div
                class="grid min-h-0 w-full min-w-0 flex-1 gap-4 overflow-y-auto p-4 xl:grid-cols-2 2xl:grid-cols-[260px_260px_300px_minmax(0,1fr)]">
                <section class="pft-panel">
                    <div class="pft-panel-head">
                        <h2>Components</h2>
                        <button v-if="can.create" class="pft-icon-btn" @click="openComponent()">
                            <Plus class="size-4" />
                        </button>
                    </div>
                    <button v-for="component in components" :key="component.id" type="button" class="pft-row" :class="component.id === selectedComponentId
                            ? 'pft-row-active'
                            : ''
                        " @click="selectedComponentId = component.id">
                        <Dumbbell class="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{
                            component.name
                            }}</span>
                        <span class="pft-badge" :class="statusClass(component.is_active)">{{ component.is_active ? 'On'
                            : 'Off' }}</span>
                    </button>
                    <div v-if="selectedComponent" class="pft-actions">
                        <button v-if="can.update" @click="openComponent(selectedComponent)">
                            <Pencil class="size-3.5" /> Edit
                        </button>
                        <button v-if="can.delete" @click="
                            destroyRecord('component', selectedComponent.id)
                            ">
                            <Trash2 class="size-3.5" /> Delete
                        </button>
                    </div>
                </section>

                <section class="pft-panel">
                    <div class="pft-panel-head">
                        <h2>Categories</h2>
                        <button v-if="can.create && selectedComponent" class="pft-icon-btn" @click="openCategory()">
                            <Plus class="size-4" />
                        </button>
                    </div>
                    <button v-for="category in selectedComponent?.categories ?? []" :key="category.id" type="button"
                        class="pft-row" :class="category.id === selectedCategoryId
                                ? 'pft-row-active'
                                : ''
                            " @click="selectedCategoryId = category.id">
                        <span class="min-w-0 flex-1 truncate">{{
                            category.name
                            }}</span>
                        <span class="pft-badge" :class="statusClass(category.is_active)">{{ category.is_active ? 'On' :
                            'Off' }}</span>
                    </button>
                    <div v-if="selectedCategory" class="pft-actions">
                        <button v-if="can.update" @click="openCategory(selectedCategory)">
                            <Pencil class="size-3.5" /> Edit
                        </button>
                        <button v-if="can.delete" @click="
                            destroyRecord('category', selectedCategory.id)
                            ">
                            <Trash2 class="size-3.5" /> Delete
                        </button>
                    </div>
                </section>

                <section class="pft-panel">
                    <div class="pft-panel-head">
                        <h2>Test Types</h2>
                        <button v-if="can.create && selectedCategory" class="pft-icon-btn" @click="openTestType()">
                            <Plus class="size-4" />
                        </button>
                    </div>
                    <button v-for="testType in selectedCategory?.test_types ?? []" :key="testType.id" type="button"
                        class="pft-row" :class="testType.id === selectedTestTypeId
                                ? 'pft-row-active'
                                : ''
                            " @click="selectedTestTypeId = testType.id">
                        <span class="min-w-0 flex-1 truncate">{{
                            testType.name
                            }}</span>
                        <span class="pft-badge" :class="statusClass(testType.is_active)">{{ testType.is_active ? 'On' :
                            'Off' }}</span>
                    </button>
                    <div v-if="selectedTestType" class="pft-actions">
                        <button v-if="can.update" @click="openTestType(selectedTestType)">
                            <Pencil class="size-3.5" /> Edit
                        </button>
                        <button v-if="can.delete" @click="
                            destroyRecord('testType', selectedTestType.id)
                            ">
                            <Trash2 class="size-3.5" /> Delete
                        </button>
                    </div>
                </section>

                <section class="pft-panel">
                    <div class="pft-panel-head">
                        <div>
                            <h2>Configurations</h2>
                            <p class="text-xs font-normal text-slate-500">
                                {{
                                    selectedTestType?.name ??
                                    'Select a test type'
                                }}
                            </p>
                        </div>
                        <button v-if="can.create && selectedTestType" class="pft-icon-btn" @click="openField()">
                            <Plus class="size-4" />
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead
                                class="border-b border-slate-100 text-[10px] font-bold tracking-wide text-slate-400 uppercase dark:border-white/10">
                                <tr>
                                    <th class="px-3 py-2">Label</th>
                                    <th class="px-3 py-2">Name</th>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2">Required</th>
                                    <th class="px-3 py-2 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="field in selectedTestType?.configurations ??
                                    []" :key="field.id">
                                    <td class="px-3 py-3 font-semibold text-slate-800 dark:text-slate-100">
                                        {{ field.field_label }}
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs text-slate-500">
                                        {{ field.field_name }}
                                    </td>
                                    <td class="px-3 py-3 text-slate-500">
                                        {{ field.field_type }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <CheckCircle2 v-if="field.is_required" class="size-4 text-emerald-600" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-1">
                                            <button v-if="can.update" class="pft-icon-btn" @click="openField(field)">
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button v-if="can.delete" class="pft-icon-btn text-red-600" @click="
                                                destroyRecord(
                                                    'field',
                                                    field.id,
                                                )
                                                ">
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="
                                    (selectedTestType?.configurations ?? [])
                                        .length === 0
                                ">
                                    <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">
                                        No configuration fields yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4 dark:border-white/10">
                        <div class="mb-3 flex items-center justify-between p-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-950 dark:text-white">
                                    Interpretation Rules
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Dynamic labels based on result ranges.
                                </p>
                            </div>
                            <button v-if="can.create && selectedTestType" class="pft-icon-btn"
                                @click="openInterpretationRule()">
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead
                                    class="border-b border-slate-100 text-[10px] font-bold tracking-wide text-slate-400 uppercase dark:border-white/10">
                                    <tr>
                                        <th class="px-3 py-2">Field</th>
                                        <th class="px-3 py-2">Label</th>
                                        <th class="px-3 py-2">Min</th>
                                        <th class="px-3 py-2">Max</th>
                                        <th class="px-3 py-2">Color</th>
                                        <th class="px-3 py-2 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                    <tr v-for="rule in selectedTestType?.interpretation_rules ??
                                        []" :key="rule.id">
                                        <td class="px-3 py-3 font-mono text-xs">
                                            {{ rule.field_name }}
                                        </td>
                                        <td class="px-3 py-3 font-semibold text-slate-800 dark:text-slate-100">
                                            {{ rule.label }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">
                                            {{ rule.min_value ?? '-' }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">
                                            {{ rule.max_value ?? '-' }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">
                                            {{ rule.color ?? '-' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex justify-end gap-1">
                                                <button v-if="can.update" class="pft-icon-btn" @click="
                                                    openInterpretationRule(
                                                        rule,
                                                    )
                                                    ">
                                                    <Pencil class="size-3.5" />
                                                </button>
                                                <button v-if="can.delete" class="pft-icon-btn text-red-600" @click="
                                                    destroyRecord(
                                                        'interpretationRule',
                                                        rule.id,
                                                    )
                                                    ">
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="
                                        (
                                            selectedTestType?.interpretation_rules ??
                                            []
                                        ).length === 0
                                    ">
                                        <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">
                                            No interpretation rules yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="modal" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4"
            @click.self="modal = null">
            <form
                class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl dark:border-white/10 dark:bg-slate-950 dark:text-slate-100"
                @submit.prevent="submitModal">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-bold text-slate-950 dark:text-white">
                        {{ modal.record ? 'Edit' : 'Create' }}
                        {{
                            modal.type === 'testType'
                                ? 'Test Type'
                                : modal.type === 'field'
                                    ? 'Configuration Field'
                                    : modal.type === 'interpretationRule'
                                        ? 'Interpretation Rule'
                                        : modal.type
                        }}
                    </h3>
                    <button type="button"
                        class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        @click="modal = null">
                        Close
                    </button>
                </div>

                <div v-if="modal.type === 'component'" class="grid gap-3 md:grid-cols-2">
                    <input v-model="componentForm.name" class="pft-input" placeholder="Name" />
                    <input v-model="componentForm.slug" class="pft-input" placeholder="Slug (optional)" />
                    <textarea v-model="componentForm.description" class="pft-input md:col-span-2"
                        placeholder="Description"></textarea>
                    <input v-model.number="componentForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <label class="pft-check"><input v-model="componentForm.is_active" type="checkbox" />
                        Active</label>
                </div>

                <div v-else-if="modal.type === 'category'" class="grid gap-3 md:grid-cols-2">
                    <select v-model="categoryForm.pft_component_id" class="pft-input">
                        <option v-for="component in components" :key="component.id" :value="component.id">
                            {{ component.name }}
                        </option>
                    </select>
                    <input v-model="categoryForm.name" class="pft-input" placeholder="Name" />
                    <input v-model="categoryForm.slug" class="pft-input" placeholder="Slug (optional)" />
                    <input v-model.number="categoryForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <textarea v-model="categoryForm.description" class="pft-input md:col-span-2"
                        placeholder="Description"></textarea>
                    <label class="pft-check"><input v-model="categoryForm.is_active" type="checkbox" />
                        Active</label>
                </div>

                <div v-else-if="modal.type === 'testType'" class="grid gap-3 md:grid-cols-2">
                    <select v-model="testTypeForm.pft_category_id" class="pft-input">
                        <option v-for="category in selectedComponent?.categories ??
                            []" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <input v-model="testTypeForm.name" class="pft-input" placeholder="Name" />
                    <input v-model="testTypeForm.slug" class="pft-input" placeholder="Slug (optional)" />
                    <input v-model="testTypeForm.unit" class="pft-input" placeholder="Unit" />
                    <input v-model.number="testTypeForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <label class="pft-check"><input v-model="testTypeForm.is_active" type="checkbox" />
                        Active</label>
                    <textarea v-model="testTypeForm.description" class="pft-input md:col-span-2"
                        placeholder="Description"></textarea>
                </div>

                <div v-else-if="modal.type === 'field'" class="grid gap-3 md:grid-cols-2">
                    <input v-model="fieldForm.field_label" class="pft-input" placeholder="Field label" />
                    <input v-model="fieldForm.field_name" class="pft-input" placeholder="field_name" />
                    <select v-model="fieldForm.field_type" class="pft-input">
                        <option v-for="type in fieldTypes" :key="type" :value="type">
                            {{ type }}
                        </option>
                    </select>
                    <input v-model.number="fieldForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <input v-model="fieldForm.placeholder" class="pft-input" placeholder="Placeholder" />
                    <input v-model="fieldForm.help_text" class="pft-input" placeholder="Help text" />
                    <textarea v-model="fieldForm.options" class="pft-input md:col-span-2"
                        placeholder="Options, one per line"></textarea>
                    <label class="pft-check"><input v-model="fieldForm.is_required" type="checkbox" />
                        Required</label>
                    <label class="pft-check"><input v-model="fieldForm.is_active" type="checkbox" />
                        Active</label>
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <select v-model="interpretationRuleForm.field_name" class="pft-input">
                        <option v-for="field in selectedTestType?.configurations ??
                            []" :key="field.id" :value="field.field_name">
                            {{ field.field_label }} ({{ field.field_name }})
                        </option>
                    </select>
                    <input v-model="interpretationRuleForm.label" class="pft-input"
                        placeholder="Interpretation label" />
                    <input v-model.number="interpretationRuleForm.min_value" class="pft-input" type="number"
                        step="0.0001" placeholder="Min value" />
                    <input v-model.number="interpretationRuleForm.max_value" class="pft-input" type="number"
                        step="0.0001" placeholder="Max value" />
                    <input v-model="interpretationRuleForm.color" class="pft-input"
                        placeholder="Color token, e.g. emerald" />
                    <input v-model.number="interpretationRuleForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <label class="pft-check"><input v-model="interpretationRuleForm.is_active" type="checkbox" />
                        Active</label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                        @click="modal = null">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </SiteSettingsLayout>
</template>

<style scoped>
@reference "tailwindcss";

.pft-panel {
    @apply min-h-[220px] overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-sm;
}

.pft-panel-head {
    @apply flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-950;
}

.pft-row {
    @apply flex w-full items-center gap-2 border-b border-slate-50 px-4 py-3 text-left text-sm text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-800;
}

.pft-row-active {
    @apply bg-emerald-50 text-emerald-800;
}

.pft-actions {
    @apply flex gap-2 border-t border-slate-100 bg-slate-50/60 p-3 text-xs font-semibold text-slate-600;
}

.pft-actions button,
.pft-icon-btn {
    @apply inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700;
    color-scheme: light;
}

.pft-badge {
    @apply rounded-full px-2 py-0.5 text-[10px] font-bold ring-1;
}

.pft-input {
    @apply min-h-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10;
    color-scheme: light;
}

.pft-check {
    @apply flex items-center gap-2 text-sm font-semibold text-slate-700;
}

.pft-check input {
    @apply size-4 rounded border-slate-300 text-emerald-600 accent-emerald-600;
}

.pft-input option {
    background-color: #ffffff;
    color: #0f172a;
}

.dark .pft-panel {
    @apply border-white/10 bg-slate-950 text-slate-100;
}

.dark .pft-panel-head {
    @apply border-white/10 text-white;
}

.dark .pft-row {
    @apply border-white/5 text-slate-300 hover:bg-emerald-500/10 hover:text-emerald-200;
}

.dark .pft-row-active {
    @apply bg-emerald-500/10 text-emerald-200;
}

.dark .pft-actions {
    @apply border-white/10 bg-white/[0.03] text-slate-300;
}

.dark .pft-actions button,
.dark .pft-icon-btn {
    @apply border-white/10 bg-white/5 text-slate-300 shadow-none hover:border-emerald-500/30 hover:bg-emerald-500/10 hover:text-emerald-200;
    color-scheme: dark;
}

.dark .pft-input {
    @apply border-white/10 bg-slate-900 text-slate-100 placeholder:text-slate-500;
    color-scheme: dark;
}

.dark .pft-input option {
    background-color: #020617;
    color: #f1f5f9;
}
</style>
