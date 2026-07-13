<script setup lang="ts">
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { CheckCircle2, Dumbbell, Maximize2, Minimize2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import FitnessIntelligenceSidebar from '@/components/FitnessIntelligenceSidebar.vue';
import * as physicalFitnessPermissionRoutes from '@/routes/site-settings/student-profile/physical-fitness-permission';
import conditionRoutes from '@/routes/site-settings/medical-conditions';

import * as categoryRoutes from '@/routes/site-settings/physical-fitness/configuration/categories';
import * as componentRoutes from '@/routes/site-settings/physical-fitness/configuration/components';
import * as fieldRoutes from '@/routes/site-settings/physical-fitness/configuration/fields';
import * as interpretationRuleRoutes from '@/routes/site-settings/physical-fitness/configuration/interpretation-rules';
import * as procedureRoutes from '@/routes/site-settings/physical-fitness/configuration/procedures';
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
    sex: 'male' | 'female' | null;
    label: string;
    min_value: number | null;
    max_value: number | null;
    color: string | null;
    sort_order: number;
    is_active: boolean;
};

type PftProcedure = {
    id: number;
    pft_test_type_id: number;
    step_no: number;
    description: string;
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
    procedures: PftProcedure[];
};

type PftMedicalCondition = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
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
    medicalConditions: {
        data: PftMedicalCondition[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    physicalFitnessSetting: {
        enabled: boolean;
        permission: string;
        options: Array<{ label: string; value: string }>;
    };
    can: { create: boolean; update: boolean; delete: boolean; managePhysicalFitnessPermission?: boolean };
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

type RuleSexFilter = 'all' | 'general' | 'male' | 'female';

const selectedRuleSex = ref<RuleSexFilter>('all');
const ruleSexOptions = [
    { value: 'all', label: 'All' },
    { value: 'female', label: 'Female' },
    { value: 'male', label: 'Male' },
    { value: 'general', label: 'General' },
] as const;

const rulesForSex = (sex: RuleSexFilter) => {
    const rules = selectedTestType.value?.interpretation_rules ?? [];

    if (sex === 'all') {
        return rules;
    }

    if (sex === 'general') {
        return rules.filter((rule) => !rule.sex);
    }

    return rules.filter((rule) => rule.sex === sex);
};

const visibleInterpretationRules = computed(() => rulesForSex(selectedRuleSex.value));

watch(selectedTestTypeId, () => {
    selectedRuleSex.value = 'all';
});

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

const urlParams = new URLSearchParams(window.location.search);
const settingsVerticalTab = ref(urlParams.get('tab') || 'general');
const physicalFitnessPanel = ref<HTMLElement | null>(null);
const isFullscreen = ref(false);

const syncFullscreenState = () => {
    isFullscreen.value = document.fullscreenElement === physicalFitnessPanel.value;
};

const toggleFullscreen = async () => {
    if (!physicalFitnessPanel.value) {
        return;
    }

    if (document.fullscreenElement) {
        await document.exitFullscreen();

        return;
    }

    await physicalFitnessPanel.value.requestFullscreen();
};

onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreenState);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
});

watch(settingsVerticalTab, (newTab) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', newTab);
    window.history.replaceState(window.history.state, '', url);
});

const medicalConditionModalOpen = ref(false);
const deleteConditionModalOpen = ref(false);
const editingCondition = ref<PftMedicalCondition | null>(null);
const conditionToDelete = ref<PftMedicalCondition | null>(null);

const medicalConditionForm = useForm({
    name: '',
    is_active: true,
    sort_order: 0,
});

const physicalFitnessForm = useForm({
    enabled: Boolean(props.physicalFitnessSetting.enabled),
    permission: props.physicalFitnessSetting.permission,
});

const submitPhysicalFitnessPermission = () => {
    physicalFitnessForm.patch(physicalFitnessPermissionRoutes.update.url(), {
        preserveScroll: true,
        preserveState: true,
    });
};

const openCreateConditionModal = () => {
    medicalConditionForm.clearErrors();
    medicalConditionForm.reset();
    editingCondition.value = null;
    medicalConditionModalOpen.value = true;
};

const openEditConditionModal = (condition: PftMedicalCondition) => {
    medicalConditionForm.clearErrors();
    medicalConditionForm.name = condition.name;
    medicalConditionForm.is_active = condition.is_active;
    medicalConditionForm.sort_order = condition.sort_order;
    editingCondition.value = condition;
    medicalConditionModalOpen.value = true;
};

const submitMedicalCondition = () => {
    // We assume conditionRoutes is defined or we can use direct urls
    // Wait, conditionRoutes is not defined in this file because this was moved!
    // I need to define conditionRoutes or use router directly!
    // Let's use direct router calls to be safe for now, or define the route.
    if (editingCondition.value) {
        medicalConditionForm.patch(conditionRoutes.update.url({ medicalCondition: editingCondition.value.id }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                medicalConditionModalOpen.value = false;
            },
        });
    } else {
        medicalConditionForm.post(conditionRoutes.store.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                medicalConditionModalOpen.value = false;
            },
        });
    }
};

const confirmDeleteCondition = (condition: PftMedicalCondition) => {
    conditionToDelete.value = condition;
    deleteConditionModalOpen.value = true;
};

const executeDeleteCondition = () => {
    if (conditionToDelete.value) {
        router.delete(conditionRoutes.destroy.url({ medicalCondition: conditionToDelete.value.id }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                deleteConditionModalOpen.value = false;
                conditionToDelete.value = null;
            },
        });
    }
};

const toggleConditionActive = (condition: PftMedicalCondition, isActive: boolean) => {
    router.patch(
        conditionRoutes.update.url({ medicalCondition: condition.id }),
        {
            name: condition.name,
            sort_order: condition.sort_order,
            is_active: isActive
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

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
    sex: null as 'male' | 'female' | null,
    label: '',
    min_value: null as number | null,
    max_value: null as number | null,
    color: '',
    sort_order: 0,
    is_active: true,
});

const procedureForm = useForm({
    pft_test_type_id: selectedTestTypeId.value,
    step_no: 1,
    description: '',
    is_active: true,
});

const modal = ref<
    | null
    | { type: 'component'; record?: PftComponent }
    | { type: 'category'; record?: PftCategory }
    | { type: 'testType'; record?: PftTestType }
    | { type: 'field'; record?: PftField }
    | { type: 'interpretationRule'; record?: PftInterpretationRule }
    | { type: 'procedure'; record?: PftProcedure }
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

const openProcedure = (record?: PftProcedure) => {
    procedureForm.clearErrors();
    procedureForm.pft_test_type_id =
        record?.pft_test_type_id ?? selectedTestTypeId.value;
    procedureForm.step_no = record?.step_no ?? (selectedTestType.value?.procedures?.length ? Math.max(...selectedTestType.value.procedures.map(p => p.step_no)) + 1 : 1);
    procedureForm.description = record?.description ?? '';
    procedureForm.is_active = record?.is_active ?? true;
    modal.value = { type: 'procedure', record };
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
    interpretationRuleForm.sex = record?.sex ?? null;
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

    if (modal.value.type === 'procedure') {
        return modal.value.record
            ? procedureForm.patch(
                procedureRoutes.update.url(modal.value.record.id),
                close,
            )
            : procedureForm.post(
                procedureRoutes.store.url(),
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

    if (type === 'procedure') {
        router.delete(procedureRoutes.destroy.url(id), options);
    }
};

const statusClass = (active: boolean) =>
    active
        ? 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20'
        : 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-white/5 dark:text-slate-400 dark:ring-white/10';

defineOptions({
    layout: null,
});
</script>

<template>

    <Head title="Physical Fitness Configuration" />

    <div class="min-h-screen font-sans bg-slate-50 text-slate-800 lg:flex dark:bg-slate-950">
        <FitnessIntelligenceSidebar active="settings" />

        <main id="settings" class="flex min-w-0 flex-1 flex-col bg-slate-50/60 p-4 dark:bg-slate-950">
        <div ref="physicalFitnessPanel" class="flex min-h-0 w-full min-w-0 flex-1 flex-col bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <header class="border-b border-slate-100 px-5 py-4 dark:border-white/10">
                <p class="text-[11px] font-bold tracking-[0.2em] text-blue-600 uppercase dark:text-blue-300">
                    Site Settings
                </p>
                <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-slate-950 dark:text-white">
                            Physical Fitness
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Manage test structure, fields, and student
                            Physical Fitness Test access.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="settingsVerticalTab === 'general' && can.create" type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white hover:bg-blue-700"
                            @click="openComponent()">
                            <Plus class="size-4" /> Component
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                            :title="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                            :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                            @click="toggleFullscreen"
                        >
                            <Minimize2 v-if="isFullscreen" class="size-4" />
                            <Maximize2 v-else class="size-4" />
                        </button>
                    </div>
                </div>
            </header>

            <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col lg:flex-row">
                <aside class="w-full shrink-0 border-b border-slate-200 bg-slate-50/50 p-3 lg:w-56 lg:border-b-0 lg:border-r lg:p-4 dark:border-white/10 dark:bg-slate-900/20">
                    <nav class="flex flex-row gap-1 overflow-x-auto lg:flex-col pb-2 lg:pb-0 hide-scrollbar">
                        <button
                            type="button"
                            @click="settingsVerticalTab = 'general'"
                            :class="[
                                settingsVerticalTab === 'general' ? 'text-blue-700 font-bold bg-white shadow-sm border border-slate-200/60 dark:bg-white/5 dark:text-blue-400 dark:border-white/10' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-transparent',
                                'flex w-full text-left text-sm transition-all rounded-md px-3 py-2 shrink-0'
                            ]"
                        >
                            General Settings
                        </button>
                        <button
                            type="button"
                            @click="settingsVerticalTab = 'medical-conditions'"
                            :class="[
                                settingsVerticalTab === 'medical-conditions' ? 'text-blue-700 font-bold bg-white shadow-sm border border-slate-200/60 dark:bg-white/5 dark:text-blue-400 dark:border-white/10' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-transparent',
                                'flex w-full text-left text-sm transition-all rounded-md px-3 py-2 shrink-0'
                            ]"
                        >
                            Medical Conditions
                        </button>
                        <button
                            type="button"
                            @click="settingsVerticalTab = 'permissions'"
                            :class="[
                                settingsVerticalTab === 'permissions' ? 'text-blue-700 font-bold bg-white shadow-sm border border-slate-200/60 dark:bg-white/5 dark:text-blue-400 dark:border-white/10' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white border border-transparent',
                                'flex w-full text-left text-sm transition-all rounded-md px-3 py-2 shrink-0'
                            ]"
                        >
                            Student Access
                        </button>
                    </nav>
                </aside>

                <main class="min-h-0 flex-1 overflow-y-auto p-4 lg:p-8 bg-white dark:bg-slate-950">
                    <section v-if="settingsVerticalTab === 'permissions'" class="flex flex-col gap-6">
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950 max-w-3xl overflow-hidden">
                            <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-white/5">
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white">Show in Grades</p>
                                    <p class="mt-1 text-sm text-slate-500">Display the Physical Fitness Test button beside eligible subjects for students with permission.</p>
                                </div>

                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="physicalFitnessForm.enabled"
                                    @click="physicalFitnessForm.enabled = !physicalFitnessForm.enabled; submitPhysicalFitnessPermission()"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="physicalFitnessForm.enabled ? 'bg-blue-600' : 'bg-slate-200 dark:bg-slate-700'"
                                    :disabled="!can.managePhysicalFitnessPermission || physicalFitnessForm.processing"
                                >
                                    <span class="sr-only">Toggle Show in Grades</span>
                                    <span
                                        aria-hidden="true"
                                        class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="physicalFitnessForm.enabled ? 'translate-x-5' : 'translate-x-0'"
                                    />
                                </button>
                            </div>

                            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/30">
                                <p class="font-bold text-slate-900 dark:text-white">
                                    PFT Fill-up Permission
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    Choose whether only students enrolled in PE/PATHFIT subjects can encode PFT results, or all students can fill up the form.
                                </p>

                                <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitPhysicalFitnessPermission">
                                    <label class="flex-1">
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Permission</span>
                                        <select v-model="physicalFitnessForm.permission" class="mt-1 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:focus:ring-blue-500/20">
                                            <option v-for="option in physicalFitnessSetting.options" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-bold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!can.managePhysicalFitnessPermission || physicalFitnessForm.processing">
                                        Save Setting
                                    </button>
                                </form>
                            </div>
                        </div>
                    </section>

                    <section v-if="settingsVerticalTab === 'general'" class="flex flex-col gap-6">
                        <div class="grid min-h-0 w-full min-w-0 flex-1 gap-4 xl:grid-cols-2 2xl:grid-cols-[260px_260px_300px_minmax(0,1fr)]">
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

                    <div v-if="selectedTestType && selectedTestType.description" class="border-b border-slate-100 p-4 bg-slate-50/50 dark:border-white/10 dark:bg-slate-900/30 text-xs">
                        <div>
                            <span class="font-bold text-slate-800 dark:text-slate-200 block mb-0.5">Description:</span>
                            <span class="text-slate-600 dark:text-slate-400 font-light">{{ selectedTestType.description }}</span>
                        </div>
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
                                        <CheckCircle2 v-if="field.is_required" class="size-4 text-blue-600" />
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
                        <div class="mb-3 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-950 dark:text-white">
                                    Procedures
                                </h3>
                                <p class="text-xs text-slate-500">
                                   Steps on how to execute this test type.
                                </p>
                            </div>
                            <button v-if="can.create && selectedTestType" class="pft-icon-btn"
                                @click="openProcedure()">
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead
                                    class="border-b border-slate-100 text-[10px] font-bold tracking-wide text-slate-400 uppercase dark:border-white/10">
                                    <tr>
                                        <th class="px-3 py-2 w-16">Step</th>
                                        <th class="px-3 py-2">Description</th>
                                        <th class="px-3 py-2 w-20">Active</th>
                                        <th class="px-3 py-2 text-right w-24">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                    <tr v-for="step in selectedTestType?.procedures ?? []" :key="step.id">
                                        <td class="px-3 py-3 font-semibold text-slate-800 dark:text-slate-100">
                                            {{ step.step_no }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300 whitespace-pre-wrap">
                                            {{ step.description }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">
                                            <span class="pft-badge" :class="statusClass(step.is_active)">
                                                {{ step.is_active ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex justify-end gap-1">
                                                <button v-if="can.update" class="pft-icon-btn" @click="openProcedure(step)">
                                                    <Pencil class="size-3.5" />
                                                </button>
                                                <button v-if="can.delete" class="pft-icon-btn text-red-600" @click="
                                                    destroyRecord(
                                                        'procedure',
                                                        step.id,
                                                    )
                                                    ">
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="
                                        (selectedTestType?.procedures ?? []).length === 0
                                    ">
                                        <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-500">
                                            No procedure steps yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4 dark:border-white/10">
                        <div class="mb-3 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-950 dark:text-white">
                                    Interpretation Rules
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Labels based on result ranges.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-slate-950">
                                    <button
                                        v-for="option in ruleSexOptions"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                                        :class="selectedRuleSex === option.value
                                            ? 'bg-blue-600 text-white shadow-sm'
                                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white'"
                                        @click="selectedRuleSex = option.value"
                                    >
                                        {{ option.label }}
                                        <span class="ml-1 text-[10px] opacity-75">({{ rulesForSex(option.value).length }})</span>
                                    </button>
                                </div>
                                <button v-if="can.create && selectedTestType" class="pft-icon-btn"
                                    @click="openInterpretationRule()">
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead
                                    class="border-b border-slate-100 text-[10px] font-bold tracking-wide text-slate-400 uppercase dark:border-white/10">
                                    <tr>
                                        <th class="px-3 py-2">Field</th>
                                        <th class="px-3 py-2">Sex</th>
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
                                    <tr v-for="rule in visibleInterpretationRules" :key="rule.id">
                                        <td class="px-3 py-3 font-mono text-xs">
                                            {{ rule.field_name }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-500">
                                            {{ rule.sex ? rule.sex.charAt(0).toUpperCase() + rule.sex.slice(1) : 'General' }}
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
                                            visibleInterpretationRules
                                        ).length === 0
                                    ">
                                        <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">
                                            No interpretation rules found for this view.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                        </div>
                    </section>
                    <section v-if="settingsVerticalTab === 'medical-conditions'" class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Medical Conditions</h2>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700" @click="openCreateConditionModal">
                                <Plus class="size-4" />
                                Add Condition
                            </button>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
                            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead class="border-b border-slate-200 bg-slate-50/50 text-xs uppercase text-slate-500 dark:border-white/10 dark:bg-slate-900/50">
                                    <tr>
                                        <th class="px-6 py-4 font-bold text-slate-900 dark:text-white">Condition Name</th>
                                        <th class="px-6 py-4 text-center font-bold text-slate-900 dark:text-white">Sort Order</th>
                                        <th class="px-6 py-4 text-center font-bold text-slate-900 dark:text-white">Active</th>
                                        <th class="px-6 py-4 text-right font-bold text-slate-900 dark:text-white">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    <tr v-if="medicalConditions.data.length === 0">
                                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                            No medical conditions found.
                                        </td>
                                    </tr>
                                    <tr v-for="condition in medicalConditions.data" :key="condition.id" class="hover:bg-slate-50 dark:hover:bg-white/5">
                                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                            {{ condition.name }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            {{ condition.sort_order }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                :checked="condition.is_active"
                                                @change="(e) => toggleConditionActive(condition, (e.target as HTMLInputElement).checked)"
                                            />
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="pft-icon-btn" @click="openEditConditionModal(condition)">
                                                    <Pencil class="size-3.5" />
                                                </button>
                                                <button type="button" class="pft-icon-btn text-rose-600" @click="confirmDeleteCondition(condition)">
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="medicalConditions.links && medicalConditions.links.length > 0" class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 dark:border-white/10 dark:bg-slate-900">
                                <div class="flex flex-1 justify-between sm:hidden">
                                    <Link
                                        v-if="medicalConditions.links[0].url"
                                        :href="medicalConditions.links[0].url"
                                        preserve-state
                                        preserve-scroll
                                        class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        v-html="medicalConditions.links[0].label"
                                    />
                                    <span
                                        v-else
                                        class="relative inline-flex items-center rounded-md border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400 opacity-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-500"
                                        v-html="medicalConditions.links[0].label"
                                    />
                                    <Link
                                        v-if="medicalConditions.links[medicalConditions.links.length - 1].url"
                                        :href="medicalConditions.links[medicalConditions.links.length - 1].url"
                                        preserve-state
                                        preserve-scroll
                                        class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        v-html="medicalConditions.links[medicalConditions.links.length - 1].label"
                                    />
                                    <span
                                        v-else
                                        class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400 opacity-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-500"
                                        v-html="medicalConditions.links[medicalConditions.links.length - 1].label"
                                    />
                                </div>
                                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-end">
                                    <div>
                                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                            <template v-for="(link, index) in medicalConditions.links" :key="index">
                                                <Link
                                                    v-if="link.url"
                                                    :href="link.url"
                                                    preserve-state
                                                    preserve-scroll
                                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 border"
                                                    :class="[
                                                        link.active ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 border-blue-600' : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 border-slate-300 dark:text-slate-200 dark:border-white/10 dark:hover:bg-white/5 dark:ring-0',
                                                        index === 0 ? 'rounded-l-md' : '',
                                                        index === medicalConditions.links.length - 1 ? 'rounded-r-md' : ''
                                                    ]"
                                                    v-html="link.label"
                                                />
                                                <span
                                                    v-else
                                                    class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400 opacity-50 border border-slate-300 bg-slate-50 dark:border-white/10 dark:bg-slate-800 dark:text-slate-500"
                                                    :class="[
                                                        index === 0 ? 'rounded-l-md' : '',
                                                        index === medicalConditions.links.length - 1 ? 'rounded-r-md' : ''
                                                    ]"
                                                    v-html="link.label"
                                                />
                                            </template>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
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
                                        : modal.type === 'procedure'
                                            ? 'Procedure Step'
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

                <div v-else-if="modal.type === 'interpretationRule'" class="grid gap-3 md:grid-cols-2">
                    <select v-model="interpretationRuleForm.field_name" class="pft-input">
                        <option v-for="field in selectedTestType?.configurations ??
                            []" :key="field.id" :value="field.field_name">
                            {{ field.field_label }} ({{ field.field_name }})
                        </option>
                    </select>
                    <select v-model="interpretationRuleForm.sex" class="pft-input">
                        <option :value="null">General</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                    <input v-model="interpretationRuleForm.label" class="pft-input"
                        placeholder="Interpretation label" />
                    <input v-model.number="interpretationRuleForm.min_value" class="pft-input" type="number"
                        step="0.0001" placeholder="Min value" />
                    <input v-model.number="interpretationRuleForm.max_value" class="pft-input" type="number"
                        step="0.0001" placeholder="Max value" />
                    <input v-model="interpretationRuleForm.color" class="pft-input"
                        placeholder="Color token, e.g. blue" />
                    <input v-model.number="interpretationRuleForm.sort_order" class="pft-input" type="number"
                        placeholder="Sort order" />
                    <label class="pft-check"><input v-model="interpretationRuleForm.is_active" type="checkbox" />
                        Active</label>
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2">
                    <input v-model.number="procedureForm.step_no" class="pft-input" type="number" min="1" placeholder="Step No." />
                    <label class="pft-check"><input v-model="procedureForm.is_active" type="checkbox" />
                        Active</label>
                    <textarea v-model="procedureForm.description" class="pft-input md:col-span-2 min-h-24"
                        placeholder="Description / Instruction Step"></textarea>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                        @click="modal = null">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        Save
                    </button>
                </div>
            </form>
        </div>

    <Dialog :open="medicalConditionModalOpen" @update:open="(val) => { if(!val) medicalConditionModalOpen = false; }">
        <DialogContent class="sm:max-w-[425px]">
            <form @submit.prevent="submitMedicalCondition">
                <DialogHeader>
                    <DialogTitle>{{ editingCondition ? 'Edit Condition' : 'Add Condition' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingCondition ? 'Update the details below.' : 'Add a new medical condition.' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="medicalConditionForm.name" />
                        <p v-if="medicalConditionForm.errors.name" class="text-xs text-rose-500">{{ medicalConditionForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sort_order">Sort Order</Label>
                        <Input id="sort_order" type="number" v-model="medicalConditionForm.sort_order" />
                        <p v-if="medicalConditionForm.errors.sort_order" class="text-xs text-rose-500">{{ medicalConditionForm.errors.sort_order }}</p>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="is_active"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            v-model="medicalConditionForm.is_active"
                        />
                        <Label for="is_active">Active (Show in questionnaire)</Label>
                        <p v-if="medicalConditionForm.errors.is_active" class="text-xs text-rose-500">{{ medicalConditionForm.errors.is_active }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:hover:bg-slate-800 dark:hover:text-slate-50" @click="medicalConditionModalOpen = false">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50" :disabled="medicalConditionForm.processing">Save</button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleteConditionModalOpen" @update:open="(val) => { if(!val) deleteConditionModalOpen = false; }">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Delete Medical Condition</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete the condition "<span class="font-bold text-slate-900 dark:text-white">{{ conditionToDelete?.name }}</span>"? This action cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4">
                <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:hover:bg-slate-800 dark:hover:text-slate-50" @click="deleteConditionModalOpen = false">Cancel</button>
                <button type="button" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50" @click="executeDeleteCondition">Delete</button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
    </div>
        </main>
    </div>
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
    @apply flex w-full items-center gap-2 border-b border-slate-50 px-4 py-3 text-left text-sm text-slate-600 transition hover:bg-blue-50 hover:text-blue-800;
}

.pft-row-active {
    @apply bg-blue-50 text-blue-800;
}

.pft-actions {
    @apply flex gap-2 border-t border-slate-100 bg-slate-50/60 p-3 text-xs font-semibold text-slate-600;
}

.pft-actions button,
.pft-icon-btn {
    @apply inline-flex h-8 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700;
    color-scheme: light;
}

.pft-badge {
    @apply rounded-full px-2 py-0.5 text-[10px] font-bold ring-1;
}

.pft-input {
    @apply min-h-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10;
    color-scheme: light;
}

.pft-check {
    @apply flex items-center gap-2 text-sm font-semibold text-slate-700;
}

.pft-check input {
    @apply size-4 rounded border-slate-300 text-blue-600 accent-blue-600;
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
    @apply border-white/5 text-slate-300 hover:bg-blue-500/10 hover:text-blue-200;
}

.dark .pft-row-active {
    @apply bg-blue-500/10 text-blue-200;
}

.dark .pft-actions {
    @apply border-white/10 bg-white/[0.03] text-slate-300;
}

.dark .pft-actions button,
.dark .pft-icon-btn {
    @apply border-white/10 bg-white/5 text-slate-300 shadow-none hover:border-blue-500/30 hover:bg-blue-500/10 hover:text-blue-200;
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

