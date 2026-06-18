<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Check,
    ChevronRight,
    CirclePlus,
    ClipboardCheck,
    Edit3,
    GripVertical,
    Layers3,
    Plus,
    Scale,
    Settings2,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import { index as evaluationIndex } from '@/routes/site-settings/evaluation';
import * as categoryRoutes from '@/routes/site-settings/evaluation/categories';
import * as choiceRoutes from '@/routes/site-settings/evaluation/choices';
import * as scaleRoutes from '@/routes/site-settings/evaluation/scales';
import * as statementRoutes from '@/routes/site-settings/evaluation/statements';
import * as templateRoutes from '@/routes/site-settings/evaluation/templates';

type Status = 'active' | 'inactive';
type StatementType =
    | 'rating_scale'
    | 'yes_no'
    | 'text_answer'
    | 'multiple_choice';

type Choice = {
    id: number;
    statement_id: number;
    choice_text: string;
    choice_value: string;
    sort_order: number;
};

type RatingScale = {
    id: number;
    template_id: number;
    statement_id: number | null;
    value: string;
    label: string;
    interpretation: string | null;
    sort_order: number;
};

type Statement = {
    id: number;
    template_id: number;
    category_id: number | null;
    statement: string;
    statement_type: StatementType;
    is_required: boolean;
    sort_order: number;
    status: Status;
    choices: Choice[];
    rating_scales: RatingScale[];
};

type Category = {
    id: number;
    template_id: number;
    name: string;
    description: string | null;
    sort_order: number;
    status: Status;
};

type TemplateSummary = {
    id: number;
    name: string;
    description: string | null;
    status: Status;
    categories_count: number;
    statements_count: number;
    can_delete: boolean;
};

type TemplateDetail = TemplateSummary & {
    categories: Category[];
    statements: Statement[];
    rating_scales: RatingScale[];
};

const props = defineProps<{
    templates: TemplateSummary[];
    selectedTemplate: TemplateDetail | null;
    statementTypes: Array<{ value: StatementType; label: string }>;
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const modal = ref<
    'template' | 'category' | 'statement' | 'scale' | 'choice' | null
>(null);
const editingId = ref<number | null>(null);
const draggedCategoryId = ref<number | null>(null);
const draggedStatementId = ref<number | null>(null);
const draggedScaleId = ref<number | null>(null);
const draggedChoiceId = ref<number | null>(null);

const templateForm = useForm({
    name: '',
    description: '',
    status: 'active' as Status,
});

const categoryForm = useForm({
    template_id: props.selectedTemplate?.id ?? 0,
    name: '',
    description: '',
    sort_order: 0,
    status: 'active' as Status,
});

const statementForm = useForm({
    template_id: props.selectedTemplate?.id ?? 0,
    category_id: null as number | null,
    statement: '',
    statement_type: 'rating_scale' as StatementType,
    is_required: true,
    sort_order: 0,
    status: 'active' as Status,
});

const scaleForm = useForm({
    template_id: props.selectedTemplate?.id ?? 0,
    statement_id: null as number | null,
    value: 5,
    label: '',
    interpretation: '',
    sort_order: 0,
});

const choiceForm = useForm({
    statement_id: 0,
    choice_text: '',
    choice_value: '',
    sort_order: 0,
});

const categoryGroups = computed(() => {
    if (!props.selectedTemplate) return [];

    const categories = props.selectedTemplate.categories.map((category) => ({
        category,
        statements: props.selectedTemplate!.statements.filter(
            (statement) => statement.category_id === category.id,
        ),
    }));
    const uncategorized = props.selectedTemplate.statements.filter(
        (statement) => statement.category_id === null,
    );

    return [
        ...categories,
        ...(uncategorized.length
            ? [{ category: null, statements: uncategorized }]
            : []),
    ];
});

const closeModal = () => {
    modal.value = null;
    editingId.value = null;
    templateForm.clearErrors();
    categoryForm.clearErrors();
    statementForm.clearErrors();
    scaleForm.clearErrors();
    choiceForm.clearErrors();
};

const selectTemplate = (template: TemplateSummary) => {
    router.get(
        evaluationIndex.url({ query: { template: template.id } }),
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const openTemplate = (template?: TemplateSummary) => {
    editingId.value = template?.id ?? null;
    templateForm.name = template?.name ?? '';
    templateForm.description = template?.description ?? '';
    templateForm.status = template?.status ?? 'active';
    modal.value = 'template';
};

const openCategory = (category?: Category) => {
    if (!props.selectedTemplate) return;
    editingId.value = category?.id ?? null;
    categoryForm.template_id = props.selectedTemplate.id;
    categoryForm.name = category?.name ?? '';
    categoryForm.description = category?.description ?? '';
    categoryForm.sort_order =
        category?.sort_order ?? props.selectedTemplate.categories.length + 1;
    categoryForm.status = category?.status ?? 'active';
    modal.value = 'category';
};

const openStatement = (statement?: Statement, categoryId?: number | null) => {
    if (!props.selectedTemplate) return;
    editingId.value = statement?.id ?? null;
    statementForm.template_id = props.selectedTemplate.id;
    statementForm.category_id =
        statement?.category_id ??
        categoryId ??
        props.selectedTemplate.categories[0]?.id ??
        null;
    statementForm.statement = statement?.statement ?? '';
    statementForm.statement_type = statement?.statement_type ?? 'rating_scale';
    statementForm.is_required = statement?.is_required ?? true;
    statementForm.sort_order =
        statement?.sort_order ?? props.selectedTemplate.statements.length + 1;
    statementForm.status = statement?.status ?? 'active';
    modal.value = 'statement';
};

const openScale = (statement: Statement | null, scale?: RatingScale) => {
    if (!props.selectedTemplate) return;
    editingId.value = scale?.id ?? null;
    scaleForm.template_id = props.selectedTemplate.id;
    scaleForm.statement_id = statement?.id ?? null;
    scaleForm.value = Number(scale?.value ?? 5);
    scaleForm.label = scale?.label ?? '';
    scaleForm.interpretation = scale?.interpretation ?? '';
    scaleForm.sort_order =
        scale?.sort_order ??
        (statement?.rating_scales.length ??
            props.selectedTemplate.rating_scales.length) + 1;
    modal.value = 'scale';
};

const openChoice = (statement: Statement, choice?: Choice) => {
    editingId.value = choice?.id ?? null;
    choiceForm.statement_id = statement.id;
    choiceForm.choice_text = choice?.choice_text ?? '';
    choiceForm.choice_value = choice?.choice_value ?? '';
    choiceForm.sort_order = choice?.sort_order ?? statement.choices.length + 1;
    modal.value = 'choice';
};

const submitWithToast = (
    method: 'post' | 'patch',
    form: typeof templateForm,
    url: string,
    message: string,
) => {
    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success(message);
        },
        onError: () => toast.error('Please check the highlighted fields.'),
    });
};

const submitTemplate = () =>
    submitWithToast(
        editingId.value ? 'patch' : 'post',
        templateForm,
        editingId.value
            ? templateRoutes.update.url(editingId.value)
            : templateRoutes.store.url(),
        editingId.value ? 'Template updated.' : 'Template created.',
    );

const submitCategory = () =>
    submitWithToast(
        editingId.value ? 'patch' : 'post',
        categoryForm as typeof templateForm,
        editingId.value
            ? categoryRoutes.update.url(editingId.value)
            : categoryRoutes.store.url(),
        editingId.value ? 'Category updated.' : 'Category created.',
    );

const submitStatement = () =>
    submitWithToast(
        editingId.value ? 'patch' : 'post',
        statementForm as typeof templateForm,
        editingId.value
            ? statementRoutes.update.url(editingId.value)
            : statementRoutes.store.url(),
        editingId.value ? 'Statement updated.' : 'Statement created.',
    );

const submitScale = () =>
    submitWithToast(
        editingId.value ? 'patch' : 'post',
        scaleForm as typeof templateForm,
        editingId.value
            ? scaleRoutes.update.url(editingId.value)
            : scaleRoutes.store.url(),
        editingId.value ? 'Scale updated.' : 'Scale created.',
    );

const submitChoice = () =>
    submitWithToast(
        editingId.value ? 'patch' : 'post',
        choiceForm as typeof templateForm,
        editingId.value
            ? choiceRoutes.update.url(editingId.value)
            : choiceRoutes.store.url(),
        editingId.value ? 'Choice updated.' : 'Choice created.',
    );

const destroy = (url: string, message: string) => {
    if (!window.confirm('Delete this item? This action cannot be undone.')) {
        return;
    }

    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => toast.success(message),
        onError: () => toast.error('This item could not be deleted.'),
    });
};

const toggleTemplate = (template: TemplateSummary) => {
    router.patch(
        templateRoutes.update.url(template.id),
        {
            name: template.name,
            description: template.description,
            status: template.status === 'active' ? 'inactive' : 'active',
        },
        { preserveScroll: true },
    );
};

const toggleCategory = (category: Category) => {
    router.patch(
        categoryRoutes.update.url(category.id),
        {
            ...category,
            status: category.status === 'active' ? 'inactive' : 'active',
        },
        { preserveScroll: true },
    );
};

const toggleStatement = (statement: Statement) => {
    router.patch(
        statementRoutes.update.url(statement.id),
        {
            template_id: statement.template_id,
            category_id: statement.category_id,
            statement: statement.statement,
            statement_type: statement.statement_type,
            is_required: statement.is_required,
            sort_order: statement.sort_order,
            status: statement.status === 'active' ? 'inactive' : 'active',
        },
        { preserveScroll: true },
    );
};

const reorder = (
    ids: number[],
    draggedId: number,
    targetId: number,
): number[] => {
    const next = [...ids];
    const from = next.indexOf(draggedId);
    const to = next.indexOf(targetId);
    if (from < 0 || to < 0 || from === to) return next;
    next.splice(to, 0, next.splice(from, 1)[0]);
    return next;
};

const dropCategory = (targetId: number) => {
    if (!props.selectedTemplate || !draggedCategoryId.value) return;
    const ids = reorder(
        props.selectedTemplate.categories.map((item) => item.id),
        draggedCategoryId.value,
        targetId,
    );
    router.patch(
        categoryRoutes.reorder.url(),
        { template_id: props.selectedTemplate.id, ids },
        { preserveScroll: true },
    );
    draggedCategoryId.value = null;
};

const dropStatement = (targetId: number) => {
    if (!props.selectedTemplate || !draggedStatementId.value) return;
    const ids = reorder(
        props.selectedTemplate.statements.map((item) => item.id),
        draggedStatementId.value,
        targetId,
    );
    router.patch(
        statementRoutes.reorder.url(),
        { template_id: props.selectedTemplate.id, ids },
        { preserveScroll: true },
    );
    draggedStatementId.value = null;
};

const dropScale = (targetId: number, scales: RatingScale[]) => {
    if (!props.selectedTemplate || !draggedScaleId.value) return;
    const ids = reorder(
        scales.map((item) => item.id),
        draggedScaleId.value,
        targetId,
    );
    router.patch(
        scaleRoutes.reorder.url(),
        { template_id: props.selectedTemplate.id, ids },
        { preserveScroll: true },
    );
    draggedScaleId.value = null;
};

const dropChoice = (targetId: number, statement: Statement) => {
    if (!draggedChoiceId.value) return;
    const ids = reorder(
        statement.choices.map((item) => item.id),
        draggedChoiceId.value,
        targetId,
    );
    router.patch(
        choiceRoutes.reorder.url(),
        { statement_id: statement.id, ids },
        { preserveScroll: true },
    );
    draggedChoiceId.value = null;
};

const typeLabel = (type: StatementType) =>
    props.statementTypes.find((item) => item.value === type)?.label ?? type;
</script>

<template>
    <Head title="Evaluation Template Builder" />

    <SiteSettingsLayout>
        <div class="space-y-5 p-4 lg:p-6">
            <header
                class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <div>
                    <p class="text-xs font-bold text-emerald-600 uppercase">
                        Evaluation Template
                    </p>
                    <h1
                        class="text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        Dynamic Evaluation Builder
                    </h1>
                    <p class="text-sm text-slate-500">
                        Create reusable templates, statements, rating scales,
                        and response choices.
                    </p>
                </div>
                <button
                    v-if="can.create"
                    class="builder-primary"
                    type="button"
                    @click="openTemplate()"
                >
                    <Plus class="size-4" />
                    New Template
                </button>
            </header>

            <div class="grid gap-5 xl:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="builder-panel p-3">
                    <div class="mb-3 flex items-center justify-between px-2">
                        <div>
                            <h2
                                class="font-bold text-slate-900 dark:text-white"
                            >
                                Templates
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{ templates.length }} configured
                            </p>
                        </div>
                        <ClipboardCheck class="size-5 text-emerald-600" />
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="template in templates"
                            :key="template.id"
                            type="button"
                            class="w-full rounded-xl border p-3 text-left transition"
                            :class="
                                selectedTemplate?.id === template.id
                                    ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10'
                                    : 'border-slate-200 hover:border-emerald-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'
                            "
                            @click="selectTemplate(template)"
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="truncate text-sm font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ template.name }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                            :class="
                                                template.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                    : 'bg-slate-100 text-slate-500 dark:bg-white/10'
                                            "
                                        >
                                            {{ template.status }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 line-clamp-2 text-xs text-slate-500"
                                    >
                                        {{
                                            template.description ||
                                            'No description'
                                        }}
                                    </p>
                                    <p class="mt-2 text-[11px] text-slate-400">
                                        {{ template.categories_count }}
                                        categories ·
                                        {{ template.statements_count }}
                                        statements
                                    </p>
                                </div>
                                <ChevronRight class="size-4 text-slate-400" />
                            </div>
                        </button>

                        <div
                            v-if="templates.length === 0"
                            class="rounded-xl border border-dashed border-slate-300 p-6 text-center dark:border-white/15"
                        >
                            <p class="text-sm font-bold">No templates yet</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Create the first reusable evaluation template.
                            </p>
                        </div>
                    </div>
                </aside>

                <main v-if="selectedTemplate" class="min-w-0 space-y-5">
                    <section class="builder-panel p-5">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="text-xl font-bold text-slate-950 dark:text-white"
                                    >
                                        {{ selectedTemplate.name }}
                                    </h2>
                                    <span
                                        class="builder-badge"
                                        :class="
                                            selectedTemplate.status === 'active'
                                                ? 'builder-badge-active'
                                                : 'builder-badge-inactive'
                                        "
                                    >
                                        {{ selectedTemplate.status }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 max-w-3xl text-sm text-slate-500"
                                >
                                    {{
                                        selectedTemplate.description ||
                                        'No description provided.'
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="can.update"
                                    class="builder-secondary"
                                    type="button"
                                    @click="toggleTemplate(selectedTemplate)"
                                >
                                    <Activity class="size-4" />
                                    {{
                                        selectedTemplate.status === 'active'
                                            ? 'Deactivate'
                                            : 'Activate'
                                    }}
                                </button>
                                <button
                                    v-if="can.update"
                                    class="builder-secondary"
                                    type="button"
                                    @click="openTemplate(selectedTemplate)"
                                >
                                    <Edit3 class="size-4" />
                                    Edit
                                </button>
                                <button
                                    v-if="
                                        can.delete &&
                                        selectedTemplate.can_delete
                                    "
                                    class="builder-danger"
                                    type="button"
                                    @click="
                                        destroy(
                                            templateRoutes.destroy.url(
                                                selectedTemplate.id,
                                            ),
                                            'Template deleted.',
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="builder-panel overflow-hidden">
                        <div class="builder-section-header">
                            <div>
                                <h3 class="builder-section-title">
                                    <Layers3 class="size-4 text-emerald-600" />
                                    Statement Configuration
                                </h3>
                                <p class="builder-section-copy">
                                    Group and reorder statements by category.
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    v-if="can.create"
                                    class="builder-secondary"
                                    type="button"
                                    @click="openCategory()"
                                >
                                    <CirclePlus class="size-4" />
                                    Category
                                </button>
                                <button
                                    v-if="can.create"
                                    class="builder-primary"
                                    type="button"
                                    @click="openStatement()"
                                >
                                    <Plus class="size-4" />
                                    Statement
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4 p-4">
                            <article
                                v-for="group in categoryGroups"
                                :key="group.category?.id ?? 'uncategorized'"
                                class="rounded-xl border border-slate-200 bg-slate-50/60 dark:border-white/10 dark:bg-white/[0.02]"
                                :draggable="
                                    Boolean(group.category && can.update)
                                "
                                @dragstart="
                                    draggedCategoryId =
                                        group.category?.id ?? null
                                "
                                @dragover.prevent
                                @drop="
                                    group.category &&
                                    dropCategory(group.category.id)
                                "
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                                >
                                    <div class="flex items-center gap-2">
                                        <GripVertical
                                            v-if="group.category"
                                            class="size-4 cursor-grab text-slate-400"
                                        />
                                        <div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <h4 class="text-sm font-bold">
                                                    {{
                                                        group.category?.name ||
                                                        'Uncategorized'
                                                    }}
                                                </h4>
                                                <span
                                                    v-if="group.category"
                                                    class="builder-badge"
                                                    :class="
                                                        group.category
                                                            .status === 'active'
                                                            ? 'builder-badge-active'
                                                            : 'builder-badge-inactive'
                                                    "
                                                >
                                                    {{ group.category.status }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    group.category
                                                        ?.description ||
                                                    `${group.statements.length} statements`
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="group.category"
                                        class="flex gap-1"
                                    >
                                        <button
                                            v-if="can.create"
                                            class="builder-icon"
                                            type="button"
                                            title="Add statement"
                                            @click="
                                                openStatement(
                                                    undefined,
                                                    group.category.id,
                                                )
                                            "
                                        >
                                            <Plus class="size-4" />
                                        </button>
                                        <button
                                            v-if="can.update"
                                            class="builder-icon"
                                            type="button"
                                            @click="
                                                toggleCategory(group.category)
                                            "
                                        >
                                            <Activity class="size-4" />
                                        </button>
                                        <button
                                            v-if="can.update"
                                            class="builder-icon"
                                            type="button"
                                            @click="
                                                openCategory(group.category)
                                            "
                                        >
                                            <Edit3 class="size-4" />
                                        </button>
                                        <button
                                            v-if="can.delete"
                                            class="builder-icon text-red-600"
                                            type="button"
                                            @click="
                                                destroy(
                                                    categoryRoutes.destroy.url(
                                                        group.category.id,
                                                    ),
                                                    'Category deleted.',
                                                )
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-2 p-3">
                                    <div
                                        v-for="statement in group.statements"
                                        :key="statement.id"
                                        class="rounded-lg border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950"
                                        :draggable="can.update"
                                        @dragstart="
                                            draggedStatementId = statement.id
                                        "
                                        @dragover.prevent
                                        @drop="dropStatement(statement.id)"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div class="flex min-w-0 gap-2">
                                                <GripVertical
                                                    class="mt-1 size-4 shrink-0 cursor-grab text-slate-400"
                                                />
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-sm font-semibold text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            statement.statement
                                                        }}
                                                    </p>
                                                    <div
                                                        class="mt-2 flex flex-wrap gap-2"
                                                    >
                                                        <span
                                                            class="builder-badge bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"
                                                        >
                                                            {{
                                                                typeLabel(
                                                                    statement.statement_type,
                                                                )
                                                            }}
                                                        </span>
                                                        <span
                                                            v-if="
                                                                statement.is_required
                                                            "
                                                            class="builder-badge bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                                                        >
                                                            Required
                                                        </span>
                                                        <span
                                                            class="builder-badge"
                                                            :class="
                                                                statement.status ===
                                                                'active'
                                                                    ? 'builder-badge-active'
                                                                    : 'builder-badge-inactive'
                                                            "
                                                        >
                                                            {{
                                                                statement.status
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 gap-1">
                                                <button
                                                    v-if="can.update"
                                                    class="builder-icon"
                                                    type="button"
                                                    @click="
                                                        toggleStatement(
                                                            statement,
                                                        )
                                                    "
                                                >
                                                    <Activity class="size-4" />
                                                </button>
                                                <button
                                                    v-if="can.update"
                                                    class="builder-icon"
                                                    type="button"
                                                    @click="
                                                        openStatement(statement)
                                                    "
                                                >
                                                    <Edit3 class="size-4" />
                                                </button>
                                                <button
                                                    v-if="can.delete"
                                                    class="builder-icon text-red-600"
                                                    type="button"
                                                    @click="
                                                        destroy(
                                                            statementRoutes.destroy.url(
                                                                statement.id,
                                                            ),
                                                            'Statement deleted.',
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                statement.statement_type ===
                                                'rating_scale'
                                            "
                                            class="mt-3 rounded-lg bg-slate-50 p-3 dark:bg-white/[0.03]"
                                        >
                                            <div
                                                class="mb-2 flex items-center justify-between"
                                            >
                                                <p
                                                    class="text-xs font-bold text-slate-500 uppercase"
                                                >
                                                    Statement rating scale
                                                </p>
                                                <button
                                                    v-if="can.create"
                                                    class="builder-text-button"
                                                    type="button"
                                                    @click="
                                                        openScale(statement)
                                                    "
                                                >
                                                    <Plus class="size-3.5" />
                                                    Add
                                                </button>
                                            </div>
                                            <div class="space-y-1.5">
                                                <div
                                                    v-for="scale in statement.rating_scales"
                                                    :key="scale.id"
                                                    class="builder-config-row"
                                                    :draggable="can.update"
                                                    @dragstart="
                                                        draggedScaleId =
                                                            scale.id
                                                    "
                                                    @dragover.prevent
                                                    @drop="
                                                        dropScale(
                                                            scale.id,
                                                            statement.rating_scales,
                                                        )
                                                    "
                                                >
                                                    <GripVertical
                                                        class="size-3.5 text-slate-400"
                                                    />
                                                    <strong>{{
                                                        Number(scale.value)
                                                    }}</strong>
                                                    <span
                                                        class="min-w-0 flex-1"
                                                        >{{ scale.label }}</span
                                                    >
                                                    <button
                                                        v-if="can.update"
                                                        class="builder-icon"
                                                        @click="
                                                            openScale(
                                                                statement,
                                                                scale,
                                                            )
                                                        "
                                                    >
                                                        <Edit3
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                    <button
                                                        v-if="can.delete"
                                                        class="builder-icon text-red-600"
                                                        @click="
                                                            destroy(
                                                                scaleRoutes.destroy.url(
                                                                    scale.id,
                                                                ),
                                                                'Scale deleted.',
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                </div>
                                                <p
                                                    v-if="
                                                        statement.rating_scales
                                                            .length === 0
                                                    "
                                                    class="text-xs text-slate-400"
                                                >
                                                    Uses the template-level
                                                    scale below.
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                statement.statement_type ===
                                                'multiple_choice'
                                            "
                                            class="mt-3 rounded-lg bg-slate-50 p-3 dark:bg-white/[0.03]"
                                        >
                                            <div
                                                class="mb-2 flex items-center justify-between"
                                            >
                                                <p
                                                    class="text-xs font-bold text-slate-500 uppercase"
                                                >
                                                    Choices
                                                </p>
                                                <button
                                                    v-if="can.create"
                                                    class="builder-text-button"
                                                    type="button"
                                                    @click="
                                                        openChoice(statement)
                                                    "
                                                >
                                                    <Plus class="size-3.5" />
                                                    Add
                                                </button>
                                            </div>
                                            <div class="space-y-1.5">
                                                <div
                                                    v-for="choice in statement.choices"
                                                    :key="choice.id"
                                                    class="builder-config-row"
                                                    :draggable="can.update"
                                                    @dragstart="
                                                        draggedChoiceId =
                                                            choice.id
                                                    "
                                                    @dragover.prevent
                                                    @drop="
                                                        dropChoice(
                                                            choice.id,
                                                            statement,
                                                        )
                                                    "
                                                >
                                                    <GripVertical
                                                        class="size-3.5 text-slate-400"
                                                    />
                                                    <span
                                                        class="min-w-0 flex-1"
                                                        >{{
                                                            choice.choice_text
                                                        }}</span
                                                    >
                                                    <code
                                                        class="text-[10px] text-slate-400"
                                                        >{{
                                                            choice.choice_value
                                                        }}</code
                                                    >
                                                    <button
                                                        v-if="can.update"
                                                        class="builder-icon"
                                                        @click="
                                                            openChoice(
                                                                statement,
                                                                choice,
                                                            )
                                                        "
                                                    >
                                                        <Edit3
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                    <button
                                                        v-if="can.delete"
                                                        class="builder-icon text-red-600"
                                                        @click="
                                                            destroy(
                                                                choiceRoutes.destroy.url(
                                                                    choice.id,
                                                                ),
                                                                'Choice deleted.',
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <p
                                        v-if="group.statements.length === 0"
                                        class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-xs text-slate-500 dark:border-white/15"
                                    >
                                        No statements in this category.
                                    </p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="builder-panel overflow-hidden">
                        <div class="builder-section-header">
                            <div>
                                <h3 class="builder-section-title">
                                    <Scale class="size-4 text-emerald-600" />
                                    Template Rating Scale
                                </h3>
                                <p class="builder-section-copy">
                                    Reusable default scale for rating
                                    statements.
                                </p>
                            </div>
                            <button
                                v-if="can.create"
                                class="builder-primary"
                                type="button"
                                @click="openScale(null)"
                            >
                                <Plus class="size-4" />
                                Scale Item
                            </button>
                        </div>
                        <div class="grid gap-2 p-4 md:grid-cols-2">
                            <div
                                v-for="scale in selectedTemplate.rating_scales"
                                :key="scale.id"
                                class="builder-config-row rounded-lg border border-slate-200 dark:border-white/10"
                                :draggable="can.update"
                                @dragstart="draggedScaleId = scale.id"
                                @dragover.prevent
                                @drop="
                                    dropScale(
                                        scale.id,
                                        selectedTemplate.rating_scales,
                                    )
                                "
                            >
                                <GripVertical class="size-4 text-slate-400" />
                                <span
                                    class="flex size-8 items-center justify-center rounded-lg bg-emerald-50 font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    {{ Number(scale.value) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold">
                                        {{ scale.label }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{
                                            scale.interpretation ||
                                            'No interpretation'
                                        }}
                                    </p>
                                </div>
                                <button
                                    v-if="can.update"
                                    class="builder-icon"
                                    @click="openScale(null, scale)"
                                >
                                    <Edit3 class="size-4" />
                                </button>
                                <button
                                    v-if="can.delete"
                                    class="builder-icon text-red-600"
                                    @click="
                                        destroy(
                                            scaleRoutes.destroy.url(scale.id),
                                            'Scale deleted.',
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <p
                                v-if="
                                    selectedTemplate.rating_scales.length === 0
                                "
                                class="col-span-full rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-white/15"
                            >
                                No reusable rating scale configured.
                            </p>
                        </div>
                    </section>
                </main>

                <div
                    v-else
                    class="builder-panel flex min-h-96 items-center justify-center p-8 text-center"
                >
                    <div>
                        <Settings2 class="mx-auto size-10 text-slate-300" />
                        <h2 class="mt-3 font-bold">
                            Select or create a template
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            The statement builder will appear here.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="modal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            @click.self="closeModal"
        >
            <div
                class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-white/10"
                >
                    <div>
                        <h3 class="font-bold capitalize">
                            {{ editingId ? 'Edit' : 'Add' }} {{ modal }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            Configure this evaluation builder item.
                        </p>
                    </div>
                    <button
                        class="builder-icon"
                        type="button"
                        @click="closeModal"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <form
                    v-if="modal === 'template'"
                    class="builder-form"
                    @submit.prevent="submitTemplate"
                >
                    <label class="builder-field">
                        <span>Name</span>
                        <input
                            v-model="templateForm.name"
                            class="builder-input"
                        />
                        <small>{{ templateForm.errors.name }}</small>
                    </label>
                    <label class="builder-field">
                        <span>Description</span>
                        <textarea
                            v-model="templateForm.description"
                            class="builder-input min-h-24"
                        />
                    </label>
                    <label class="builder-field">
                        <span>Status</span>
                        <select
                            v-model="templateForm.status"
                            class="builder-input"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                    <button
                        class="builder-primary justify-center"
                        type="submit"
                    >
                        <Check class="size-4" /> Save Template
                    </button>
                </form>

                <form
                    v-else-if="modal === 'category'"
                    class="builder-form"
                    @submit.prevent="submitCategory"
                >
                    <label class="builder-field">
                        <span>Category name</span>
                        <input
                            v-model="categoryForm.name"
                            class="builder-input"
                        />
                        <small>{{ categoryForm.errors.name }}</small>
                    </label>
                    <label class="builder-field">
                        <span>Description</span>
                        <textarea
                            v-model="categoryForm.description"
                            class="builder-input min-h-20"
                        />
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="builder-field">
                            <span>Sort order</span>
                            <input
                                v-model.number="categoryForm.sort_order"
                                class="builder-input"
                                type="number"
                            />
                        </label>
                        <label class="builder-field">
                            <span>Status</span>
                            <select
                                v-model="categoryForm.status"
                                class="builder-input"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                    </div>
                    <button
                        class="builder-primary justify-center"
                        type="submit"
                    >
                        <Check class="size-4" /> Save Category
                    </button>
                </form>

                <form
                    v-else-if="modal === 'statement'"
                    class="builder-form"
                    @submit.prevent="submitStatement"
                >
                    <label class="builder-field">
                        <span>Statement</span>
                        <textarea
                            v-model="statementForm.statement"
                            class="builder-input min-h-24"
                            placeholder="The service was provided promptly."
                        />
                        <small>{{ statementForm.errors.statement }}</small>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="builder-field">
                            <span>Category</span>
                            <select
                                v-model="statementForm.category_id"
                                class="builder-input"
                            >
                                <option :value="null">Uncategorized</option>
                                <option
                                    v-for="category in selectedTemplate?.categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </label>
                        <label class="builder-field">
                            <span>Statement type</span>
                            <select
                                v-model="statementForm.statement_type"
                                class="builder-input"
                            >
                                <option
                                    v-for="type in statementTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="builder-field">
                            <span>Sort order</span>
                            <input
                                v-model.number="statementForm.sort_order"
                                class="builder-input"
                                type="number"
                            />
                        </label>
                        <label class="builder-field">
                            <span>Status</span>
                            <select
                                v-model="statementForm.status"
                                class="builder-input"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                        <label class="builder-check">
                            <input
                                v-model="statementForm.is_required"
                                type="checkbox"
                            />
                            Required
                        </label>
                    </div>
                    <button
                        class="builder-primary justify-center"
                        type="submit"
                    >
                        <Check class="size-4" /> Save Statement
                    </button>
                </form>

                <form
                    v-else-if="modal === 'scale'"
                    class="builder-form"
                    @submit.prevent="submitScale"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <label class="builder-field">
                            <span>Scale value</span>
                            <input
                                v-model.number="scaleForm.value"
                                class="builder-input"
                                type="number"
                                step="0.01"
                            />
                        </label>
                        <label class="builder-field">
                            <span>Sort order</span>
                            <input
                                v-model.number="scaleForm.sort_order"
                                class="builder-input"
                                type="number"
                            />
                        </label>
                    </div>
                    <label class="builder-field">
                        <span>Label</span>
                        <input
                            v-model="scaleForm.label"
                            class="builder-input"
                            placeholder="Excellent"
                        />
                    </label>
                    <label class="builder-field">
                        <span>Interpretation</span>
                        <textarea
                            v-model="scaleForm.interpretation"
                            class="builder-input min-h-20"
                        />
                    </label>
                    <button
                        class="builder-primary justify-center"
                        type="submit"
                    >
                        <Check class="size-4" /> Save Scale
                    </button>
                </form>

                <form
                    v-else-if="modal === 'choice'"
                    class="builder-form"
                    @submit.prevent="submitChoice"
                >
                    <label class="builder-field">
                        <span>Choice text</span>
                        <input
                            v-model="choiceForm.choice_text"
                            class="builder-input"
                            placeholder="Strongly Agree"
                        />
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="builder-field">
                            <span>Choice value</span>
                            <input
                                v-model="choiceForm.choice_value"
                                class="builder-input"
                                placeholder="strongly_agree"
                            />
                        </label>
                        <label class="builder-field">
                            <span>Sort order</span>
                            <input
                                v-model.number="choiceForm.sort_order"
                                class="builder-input"
                                type="number"
                            />
                        </label>
                    </div>
                    <button
                        class="builder-primary justify-center"
                        type="submit"
                    >
                        <Check class="size-4" /> Save Choice
                    </button>
                </form>
            </div>
        </div>
    </SiteSettingsLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.builder-panel {
    @apply rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950;
}
.builder-primary {
    @apply inline-flex h-9 items-center gap-2 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50;
}
.builder-secondary {
    @apply inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5;
}
.builder-danger {
    @apply inline-flex h-9 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-bold text-red-700 transition hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300;
}
.builder-icon {
    @apply inline-flex size-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white;
}
.builder-section-header {
    @apply flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10;
}
.builder-section-title {
    @apply flex items-center gap-2 text-sm font-bold text-slate-950 dark:text-white;
}
.builder-section-copy {
    @apply mt-0.5 text-xs text-slate-500;
}
.builder-badge {
    @apply rounded-full px-2 py-0.5 text-[10px] font-bold uppercase;
}
.builder-badge-active {
    @apply bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300;
}
.builder-badge-inactive {
    @apply bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400;
}
.builder-config-row {
    @apply flex items-center gap-2 bg-white px-2.5 py-2 text-xs dark:bg-slate-950;
}
.builder-text-button {
    @apply inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:text-emerald-700;
}
.builder-form {
    @apply space-y-4 p-5;
}
.builder-field {
    @apply flex flex-col gap-1.5 text-xs font-bold text-slate-600 dark:text-slate-300;
}
.builder-field small {
    @apply font-medium text-red-600;
}
.builder-input {
    @apply w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-900 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:focus:ring-emerald-500/20;
}
.builder-check {
    @apply flex items-center gap-2 self-end rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-bold dark:border-white/10;
}
</style>
