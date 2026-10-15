<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Head, useForm, router } from '@inertiajs/vue3';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import {
    destroy,
    store,
    toggle,
    update,
} from '@/routes/site-settings/grade-viewing';
import {
    Plus,
    Settings2,
    ShieldCheck,
    ShieldAlert,
    History,
    X,
    LoaderCircle,
    Trash2,
    Edit2,
    ToggleLeft,
    ToggleRight,
    Info,
} from 'lucide-vue-next';
import { format } from 'date-fns';

interface Campus {
    id: number;
    campus_name: string;
}

interface Rule {
    id: number;
    site_campus_id: number;
    rule_name: string;
    grade_evaluation_mode:
        | 'disabled_visible'
        | 'disabled_hidden'
        | 'enabled_required'
        | 'enabled_optional';
    access_scope: 'all_grades' | 'current_term';
    bypass_evaluation: boolean;
    show_gwa_gpa: boolean;
    is_active: boolean;
    description: string | null;
    campus: Campus;
    creator?: { name: string };
    updater?: { name: string };
    created_at: string;
    updated_at: string;
}

interface Log {
    id: number;
    action: string;
    changes: any;
    ip_address: string;
    created_at: string;
    user: { name: string };
    rule?: { campus: { campus_name: string } };
}

const props = defineProps<{
    rules: Rule[];
    campuses: Campus[];
    logs: Log[];
}>();

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isAuditDrawerOpen = ref(false);
const selectedRule = ref<Rule | null>(null);

const form = useForm({
    site_campus_id: '',
    rule_name: 'Bypass Faculty Evaluation',
    grade_evaluation_mode: 'enabled_required',
    access_scope: 'current_term',
    bypass_evaluation: true,
    show_gwa_gpa: true,
    is_active: true,
    description: '',
});

const policyOptions = [
    {
        value: 'enabled_optional',
        title: 'Evaluation On / View Grades',
        description:
            'Show the evaluation button on Grades. Students can view grades without completing evaluation.',
        evaluation: 'Optional',
        access: 'Visible',
    },
    {
        value: 'enabled_required',
        title: 'Evaluation On / Hide Grades Until Evaluated',
        description:
            'Show the evaluation button on Grades. Grades in the selected scope stay locked until the required faculty evaluation is completed.',
        evaluation: 'Required',
        access: 'Locked Until Evaluation',
    },
    {
        value: 'disabled_visible',
        title: 'Evaluation Off / View Grades',
        description:
            'Hide the evaluation button. Students can view their grades without evaluation.',
        evaluation: 'Disabled',
        access: 'Visible',
    },
    {
        value: 'disabled_hidden',
        title: 'Evaluation Off / Hide Grades',
        description:
            'Hide the evaluation button. Grades stay hidden regardless of evaluation status.',
        evaluation: 'Disabled',
        access: 'Hidden',
    },
] as const;
const baseline = ref('');
const modalOpen = computed(
    () => isCreateModalOpen.value || isEditModalOpen.value,
);
const hasChanges = computed(
    () => JSON.stringify(form.data()) !== baseline.value,
);
const preview = computed(() => {
    const policy =
        policyOptions.find(
            (option) => option.value === form.grade_evaluation_mode,
        ) ?? policyOptions[1];
    const access = form.bypass_evaluation ? 'Visible' : policy.access;
    return {
        evaluation: form.bypass_evaluation
            ? 'Disabled (bypass enabled)'
            : policy.evaluation,
        access,
        averages: !form.show_gwa_gpa
            ? 'Hidden'
            : access === 'Visible'
              ? 'Visible'
              : 'Conditional',
    };
});
let returnFocus: HTMLElement | null = null;
const prepareModal = () => {
    form.clearErrors();
    baseline.value = JSON.stringify(form.data());
    returnFocus =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
};
const requestClose = (open = false) => {
    if (open || form.processing) return;
    if (
        hasChanges.value &&
        !window.confirm('Discard unsaved changes to this grade access rule?')
    )
        return;
    isCreateModalOpen.value = false;
    isEditModalOpen.value = false;
};
const restoreFocus = (event: Event) => {
    event.preventDefault();
    returnFocus?.focus();
};
const focusFirstError = () =>
    nextTick(() => {
        document
            .querySelector<HTMLElement>(
                '[data-grade-rule-form] [aria-invalid="true"]',
            )
            ?.focus();
    });
const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!modalOpen.value || !hasChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
};
onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload));
onBeforeUnmount(() =>
    window.removeEventListener('beforeunload', warnBeforeUnload),
);

const openCreateModal = () => {
    selectedRule.value = null;
    form.defaults({
        site_campus_id: '',
        rule_name: 'Bypass Faculty Evaluation',
        grade_evaluation_mode: 'enabled_required',
        access_scope: 'current_term',
        bypass_evaluation: true,
        show_gwa_gpa: true,
        is_active: true,
        description: '',
    });
    form.reset();
    prepareModal();
    isCreateModalOpen.value = true;
};

const openEditModal = (rule: Rule) => {
    selectedRule.value = rule;
    form.site_campus_id = String(rule.site_campus_id);
    form.rule_name = rule.rule_name;
    form.grade_evaluation_mode =
        rule.grade_evaluation_mode ?? 'enabled_required';
    form.access_scope = rule.access_scope ?? 'current_term';
    form.bypass_evaluation = rule.bypass_evaluation;
    form.show_gwa_gpa = rule.show_gwa_gpa;
    form.is_active = rule.is_active;
    form.description = rule.description || '';
    prepareModal();
    isEditModalOpen.value = true;
};

const submitCreate = () => {
    if (form.processing) return;
    form.post(store.url(), {
        onError: focusFirstError,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
        },
    });
};

const submitUpdate = () => {
    if (!selectedRule.value || form.processing || !hasChanges.value) return;
    form.patch(update.url(selectedRule.value.id), {
        onError: focusFirstError,
        onSuccess: () => {
            isEditModalOpen.value = false;
            selectedRule.value = null;
        },
    });
};

const deleteRule = (rule: Rule) => {
    if (rule.is_active) return;
    if (
        confirm(
            'Are you sure you want to delete this rule? This will be logged.',
        )
    ) {
        router.delete(destroy.url(rule.id));
    }
};

const pendingStatusRule = ref<Rule | null>(null);
const statusSaving = ref(false);
const statusError = ref('');
let statusReturnFocus: HTMLElement | null = null;
const toggleRule = (rule: Rule) => {
    if (statusSaving.value) return;
    statusReturnFocus =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    pendingStatusRule.value = rule;
    statusError.value = '';
};
const closeStatusDialog = (open = false) => {
    if (!open && !statusSaving.value) pendingStatusRule.value = null;
};
const restoreStatusFocus = (event: Event) => {
    event.preventDefault();
    statusReturnFocus?.focus();
};
const confirmStatusChange = () => {
    if (!pendingStatusRule.value || statusSaving.value) return;
    statusSaving.value = true;
    statusError.value = '';
    router.patch(
        toggle.url(pendingStatusRule.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                pendingStatusRule.value = null;
            },
            onError: () => {
                statusError.value =
                    'The rule status could not be updated. Please try again.';
            },
            onFinish: () => {
                statusSaving.value = false;
            },
        },
    );
};

const getActionColor = (action: string) => {
    switch (action) {
        case 'created':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400';
        case 'updated':
            return 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400';
        case 'deleted':
            return 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400';
        case 'toggled':
            return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400';
        default:
            return 'text-slate-600 bg-slate-50 dark:bg-white/5 dark:text-slate-400';
    }
};
</script>

<template>
    <Head title="Grade Viewing Rules" />

    <SiteSettingsLayout>
        <div class="p-6">
            <div>
                <!-- Rules Table -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <Settings2 class="size-4 text-slate-400" />
                            <h2
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                Active Rules
                            </h2>
                        </div>
                        <button
                            @click="openCreateModal"
                            class="inline-flex h-8 items-center justify-center gap-2 rounded-md bg-indigo-600 px-3 text-[11px] font-bold text-white transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                        >
                            <Plus class="size-3.5" />
                            New Rule
                        </button>
                    </div>
                    <div
                        class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead
                                class="bg-slate-50 text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Campus & Rule</th>
                                    <th class="px-4 py-3">
                                        Grade Access Policy
                                    </th>
                                    <th class="px-4 py-3">GWA/GPA</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-white/10"
                            >
                                <tr v-if="rules.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-12 text-center"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-2 text-slate-400"
                                        >
                                            <Settings2
                                                class="size-10 opacity-20"
                                            />
                                            <p class="text-xs font-medium">
                                                No grade viewing rules defined
                                                yet.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="rule in rules"
                                    :key="rule.id"
                                    class="hover:bg-slate-50 dark:hover:bg-white/5"
                                >
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span
                                                class="font-bold text-slate-900 dark:text-white"
                                                >{{ rule.rule_name }}</span
                                            >
                                            <span
                                                class="text-[11px] font-medium text-indigo-600 dark:text-indigo-400"
                                                >{{
                                                    rule.campus.campus_name
                                                }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex size-5 items-center justify-center rounded-full"
                                                :class="
                                                    rule.bypass_evaluation
                                                        ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400'
                                                        : 'bg-slate-100 text-slate-400 dark:bg-white/10'
                                                "
                                            >
                                                <ShieldCheck
                                                    v-if="
                                                        rule.bypass_evaluation
                                                    "
                                                    class="size-3"
                                                />
                                                <ShieldAlert
                                                    v-else
                                                    class="size-3"
                                                />
                                            </div>
                                            <span
                                                class="text-xs font-medium"
                                                :class="
                                                    rule.bypass_evaluation
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-slate-500'
                                                "
                                            >
                                                {{
                                                    rule.bypass_evaluation
                                                        ? 'Bypass Enabled'
                                                        : (policyOptions.find(
                                                              (option) =>
                                                                  option.value ===
                                                                  rule.grade_evaluation_mode,
                                                          )?.title ??
                                                          'Evaluation On / Hide Grades Until Evaluated')
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex size-5 items-center justify-center rounded-full"
                                                :class="
                                                    rule.show_gwa_gpa
                                                        ? 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400'
                                                        : 'bg-slate-100 text-slate-400 dark:bg-white/10'
                                                "
                                            >
                                                <ToggleRight
                                                    v-if="rule.show_gwa_gpa"
                                                    class="size-3"
                                                />
                                                <ToggleLeft
                                                    v-else
                                                    class="size-3"
                                                />
                                            </div>
                                            <span
                                                class="text-xs font-medium"
                                                :class="
                                                    rule.show_gwa_gpa
                                                        ? 'text-sky-600 dark:text-sky-400'
                                                        : 'text-slate-500'
                                                "
                                            >
                                                {{
                                                    rule.show_gwa_gpa
                                                        ? 'Visible'
                                                        : 'Hidden'
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            class="inline-flex items-center gap-2.5"
                                        >
                                            <Switch
                                                :id="`rule-status-${rule.id}`"
                                                :model-value="rule.is_active"
                                                :disabled="statusSaving"
                                                :aria-label="`${rule.is_active ? 'Deactivate' : 'Activate'} ${rule.rule_name} for ${rule.campus.campus_name}`"
                                                @update:model-value="
                                                    toggleRule(rule)
                                                "
                                            />
                                            <span
                                                class="text-xs font-medium"
                                                :class="
                                                    rule.is_active
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-slate-500 dark:text-slate-400'
                                                "
                                                >{{
                                                    rule.is_active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1"
                                        >
                                            <button
                                                @click="openEditModal(rule)"
                                                class="flex size-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                                            >
                                                <Edit2 class="size-4" />
                                            </button>
                                            <button
                                                v-if="!rule.is_active"
                                                aria-label="Delete inactive rule"
                                                @click="deleteRule(rule)"
                                                class="flex size-8 items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="rounded-lg border border-indigo-100 bg-indigo-50/30 p-4 dark:border-indigo-500/20 dark:bg-indigo-500/5"
                    >
                        <div class="flex gap-3">
                            <Info
                                class="mt-0.5 size-4 shrink-0 text-indigo-500"
                            />
                            <div
                                class="text-xs text-indigo-900 dark:text-indigo-300"
                            >
                                <p class="font-bold">About Evaluation Bypass</p>
                                <p class="mt-1 leading-relaxed opacity-80">
                                    When "Bypass Evaluation" is enabled,
                                    students on the selected campus will be able
                                    to view their final grades even if they
                                    haven't completed the faculty evaluation
                                    process. This rule overrides the system
                                    default behavior of hiding grades when no
                                    campus rule is active.
                                </p>
                                <p class="mt-2 leading-relaxed opacity-80">
                                    Use "Show GWA/GPA" to control whether
                                    students can see computed Average GWA and
                                    Semester GPA values for the selected campus.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="fixed top-1/2 right-0 z-40 flex -translate-y-1/2 items-center gap-2 rounded-l-lg border border-r-0 border-slate-200 bg-white px-2 py-3 text-[11px] font-bold tracking-wider text-slate-600 uppercase shadow-lg transition hover:bg-slate-50 hover:text-indigo-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
            style="writing-mode: vertical-rl"
            @click="isAuditDrawerOpen = true"
        >
            <History class="size-4" />
            Audit Logs
        </button>

        <div
            v-if="isAuditDrawerOpen"
            class="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm"
            @click.self="isAuditDrawerOpen = false"
        >
            <aside
                class="ml-auto flex h-full w-full max-w-sm flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-white/10"
                >
                    <div class="flex items-center gap-2">
                        <History class="size-4 text-slate-400" />
                        <h2
                            class="text-sm font-bold text-slate-900 dark:text-white"
                        >
                            Audit Logs
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/10 dark:hover:text-white"
                        @click="isAuditDrawerOpen = false"
                        aria-label="Close audit logs"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <div
                        v-if="logs.length === 0"
                        class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-xs font-medium text-slate-400 dark:border-white/10"
                    >
                        No audit logs yet.
                    </div>
                    <div
                        v-for="log in logs"
                        :key="log.id"
                        class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                                :class="getActionColor(log.action)"
                            >
                                {{ log.action }}
                            </span>
                            <span
                                class="text-[10px] font-medium text-slate-400"
                            >
                                {{
                                    format(
                                        new Date(log.created_at),
                                        'MMM d, h:mm a',
                                    )
                                }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-[11px] leading-relaxed text-slate-700 dark:text-slate-300"
                        >
                            <span
                                class="font-bold text-slate-900 dark:text-white"
                                >{{ log.user.name }}</span
                            >
                            {{ log.action }} rule for
                            <span
                                class="font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{
                                    log.rule?.campus?.campus_name ||
                                    'Deleted Rule'
                                }}
                            </span>
                        </p>
                        <div
                            class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-[9px] font-medium text-slate-400 dark:border-white/5"
                        >
                            <span>IP: {{ log.ip_address }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <Dialog :open="!!pendingStatusRule" @update:open="closeStatusDialog">
            <DialogContent
                :show-close-button="false"
                class="sm:max-w-md"
                @close-auto-focus="restoreStatusFocus"
            >
                <DialogTitle
                    >{{
                        pendingStatusRule?.is_active ? 'Deactivate' : 'Activate'
                    }}
                    Grade Access Rule?</DialogTitle
                >
                <DialogDescription class="text-sm leading-relaxed">
                    Confirm
                    {{
                        pendingStatusRule?.is_active
                            ? 'deactivation'
                            : 'activation'
                    }}
                    of
                    <span class="font-medium text-slate-900 dark:text-white">{{
                        pendingStatusRule?.rule_name
                    }}</span>
                    for {{ pendingStatusRule?.campus.campus_name }}.
                </DialogDescription>
                <p
                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    {{
                        pendingStatusRule?.is_active
                            ? 'This rule will stop applying and its Delete action will become available. Students will use the latest remaining active campus rule, or grades will be hidden if no active rule remains.'
                            : 'This rule will become active and its Delete action will be hidden. Students use the latest active rule for their campus.'
                    }}
                </p>
                <p
                    v-if="statusError"
                    role="alert"
                    class="text-sm text-red-600 dark:text-red-400"
                >
                    {{ statusError }}
                </p>
                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="statusSaving"
                        @click="closeStatusDialog()"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        :disabled="statusSaving"
                        @click="confirmStatusChange"
                    >
                        <LoaderCircle
                            v-if="statusSaving"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        {{
                            statusSaving
                                ? 'Updating...'
                                : pendingStatusRule?.is_active
                                  ? 'Deactivate Rule'
                                  : 'Activate Rule'
                        }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog :open="modalOpen" @update:open="requestClose">
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[90dvh] w-[calc(100%-1.5rem)] flex-col gap-0 overflow-hidden rounded-xl p-0 sm:max-w-[620px]"
                @close-auto-focus="restoreFocus"
            >
                <header
                    class="relative shrink-0 border-b border-slate-200 px-5 py-5 sm:px-6 dark:border-white/10"
                >
                    <DialogTitle class="pr-10 text-lg font-semibold">{{
                        isCreateModalOpen
                            ? 'Create Grade Access Rule'
                            : 'Edit Grade Access Rule'
                    }}</DialogTitle>
                    <DialogDescription
                        class="mt-1.5 pr-6 text-sm leading-relaxed"
                        >Configure faculty evaluation requirements and student
                        grade visibility.</DialogDescription
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Close grade access rule"
                        :disabled="form.processing"
                        class="absolute top-4 right-4"
                        @click="requestClose()"
                        ><X class="size-4"
                    /></Button>
                </header>
                <form
                    data-grade-rule-form
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="
                        isCreateModalOpen ? submitCreate() : submitUpdate()
                    "
                >
                    <div
                        class="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6"
                    >
                        <p
                            v-if="Object.keys(form.errors).length"
                            role="alert"
                            class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
                        >
                            Please correct the highlighted fields and save
                            again.
                        </p>
                        <fieldset :disabled="form.processing" class="space-y-4">
                            <legend class="mb-3 text-sm font-semibold">
                                Rule Information
                            </legend>
                            <div class="space-y-2">
                                <label
                                    for="rule-campus"
                                    class="text-sm font-medium"
                                    >Campus
                                    <span class="text-slate-500"
                                        >(required)</span
                                    ></label
                                >
                                <select
                                    id="rule-campus"
                                    v-model="form.site_campus_id"
                                    required
                                    :aria-invalid="!!form.errors.site_campus_id"
                                    aria-describedby="rule-campus-error"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-white/15 dark:bg-slate-800"
                                >
                                    <option value="">Select Campus</option>
                                    <option
                                        v-if="
                                            selectedRule &&
                                            !campuses.some(
                                                (campus) =>
                                                    campus.id ===
                                                    selectedRule?.site_campus_id,
                                            )
                                        "
                                        :value="
                                            String(selectedRule.site_campus_id)
                                        "
                                    >
                                        {{ selectedRule.campus.campus_name }}
                                    </option>
                                    <option
                                        v-for="campus in campuses"
                                        :key="campus.id"
                                        :value="String(campus.id)"
                                    >
                                        {{ campus.campus_name }}
                                    </option>
                                </select>
                                <p
                                    id="rule-campus-error"
                                    v-if="form.errors.site_campus_id"
                                    class="text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.site_campus_id }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <label
                                    for="rule-name"
                                    class="text-sm font-medium"
                                    >Rule Name
                                    <span class="text-slate-500"
                                        >(required)</span
                                    ></label
                                >
                                <input
                                    id="rule-name"
                                    v-model="form.rule_name"
                                    required
                                    maxlength="255"
                                    :aria-invalid="!!form.errors.rule_name"
                                    aria-describedby="rule-name-error"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-white/15 dark:bg-slate-800"
                                    placeholder="e.g. End of Semester Policy"
                                />
                                <p
                                    id="rule-name-error"
                                    v-if="form.errors.rule_name"
                                    class="text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.rule_name }}
                                </p>
                            </div>
                        </fieldset>
                        <fieldset
                            :disabled="form.processing"
                            aria-describedby="policy-note policy-error"
                            class="space-y-3 border-t border-slate-200 pt-5 dark:border-white/10"
                        >
                            <legend
                                class="float-left mb-3 w-full text-sm font-semibold"
                            >
                                Grade Access Policy
                            </legend>
                            <label
                                v-for="option in policyOptions"
                                :key="option.value"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-indigo-400 dark:focus-within:ring-offset-slate-900"
                                :class="
                                    form.grade_evaluation_mode === option.value
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10'
                                        : 'border-slate-200 dark:border-white/15'
                                "
                            >
                                <input
                                    v-model="form.grade_evaluation_mode"
                                    type="radio"
                                    name="grade_evaluation_mode"
                                    :value="option.value"
                                    :aria-invalid="
                                        !!form.errors.grade_evaluation_mode
                                    "
                                    class="mt-0.5 size-4 shrink-0 accent-indigo-600"
                                />
                                <span class="space-y-1"
                                    ><span
                                        class="block text-sm font-semibold"
                                        >{{ option.title }}</span
                                    ><span
                                        class="block text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                                        >{{ option.description }}</span
                                    ></span
                                >
                            </label>
                            <div class="space-y-2 pt-2">
                                <label
                                    for="access-scope"
                                    class="text-sm font-semibold"
                                    >Apply Access Policy To</label
                                >
                                <select
                                    id="access-scope"
                                    v-model="form.access_scope"
                                    :aria-invalid="!!form.errors.access_scope"
                                    aria-describedby="access-scope-help access-scope-error"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-white/15 dark:bg-slate-800"
                                >
                                    <option value="all_grades">
                                        All grades (all terms)
                                    </option>
                                    <option value="current_term">
                                        Current active term only
                                    </option>
                                </select>
                                <p
                                    id="access-scope-help"
                                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                                >
                                    Current term uses the active term_id
                                    configured in the selected campus's Terms
                                    settings. Other terms remain accessible. If
                                    no active term is configured, no term is
                                    affected. Show GWA/GPA remains a campus-wide
                                    display setting.
                                </p>
                                <p
                                    id="access-scope-error"
                                    v-if="form.errors.access_scope"
                                    class="text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.access_scope }}
                                </p>
                            </div>
                            <p
                                id="policy-note"
                                class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                Evaluation and grade access follow the selected
                                scope.
                            </p>
                            <p
                                id="policy-error"
                                v-if="form.errors.grade_evaluation_mode"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.grade_evaluation_mode }}
                            </p>
                            <div
                                class="rounded-lg border border-slate-200 p-4 dark:border-white/15"
                            >
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <div>
                                        <label
                                            for="rule-bypass"
                                            class="text-sm font-semibold"
                                            >Bypass Evaluation</label
                                        >
                                        <p
                                            id="bypass-description"
                                            class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                                        >
                                            Overrides every policy and allows
                                            grade viewing.
                                        </p>
                                    </div>
                                    <Switch
                                        id="rule-bypass"
                                        v-model="form.bypass_evaluation"
                                        aria-describedby="bypass-description"
                                        :aria-invalid="
                                            !!form.errors.bypass_evaluation
                                        "
                                        :disabled="form.processing"
                                    />
                                </div>
                                <p
                                    class="mt-2 text-sm font-medium"
                                    :class="
                                        form.bypass_evaluation
                                            ? 'text-indigo-600 dark:text-indigo-300'
                                            : 'text-slate-500'
                                    "
                                >
                                    {{
                                        form.bypass_evaluation
                                            ? 'On: selected policy is overridden'
                                            : 'Off: selected policy applies'
                                    }}
                                </p>
                                <p
                                    v-if="form.errors.bypass_evaluation"
                                    class="mt-2 text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.bypass_evaluation }}
                                </p>
                            </div>
                        </fieldset>
                        <section
                            class="space-y-3 border-t border-slate-200 pt-5 dark:border-white/10"
                            aria-labelledby="display-options-title"
                        >
                            <h3
                                id="display-options-title"
                                class="text-sm font-semibold"
                            >
                                Additional Display Options
                            </h3>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <label
                                        for="rule-gwa"
                                        class="text-sm font-medium"
                                        >Show GWA/GPA
                                        <span class="ml-2 text-slate-500">{{
                                            form.show_gwa_gpa ? 'On' : 'Off'
                                        }}</span></label
                                    >
                                    <p
                                        id="gwa-description"
                                        class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                                    >
                                        Allow students to view their computed
                                        General Weighted Average and Semester
                                        GPA when grade access is permitted.
                                    </p>
                                </div>
                                <Switch
                                    id="rule-gwa"
                                    v-model="form.show_gwa_gpa"
                                    aria-describedby="gwa-description"
                                    :aria-invalid="!!form.errors.show_gwa_gpa"
                                    :disabled="form.processing"
                                    class="mt-1"
                                />
                            </div>
                            <p
                                v-if="form.errors.show_gwa_gpa"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.show_gwa_gpa }}
                            </p>
                        </section>
                        <div
                            class="space-y-2 border-t border-slate-200 pt-5 dark:border-white/10"
                        >
                            <label
                                for="rule-description"
                                class="text-sm font-semibold"
                                >Description
                                <span class="font-normal text-slate-500"
                                    >(optional)</span
                                ></label
                            >
                            <textarea
                                id="rule-description"
                                v-model="form.description"
                                rows="3"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.description"
                                aria-describedby="description-error"
                                class="min-h-24 w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-white/15 dark:bg-slate-800"
                                placeholder="Add an administrative note about this rule..."
                            />
                            <p
                                id="description-error"
                                v-if="form.errors.description"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                        <section
                            aria-labelledby="access-preview-title"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"
                        >
                            <h3
                                id="access-preview-title"
                                class="text-sm font-semibold"
                            >
                                Student Access Preview
                            </h3>
                            <dl
                                aria-live="polite"
                                aria-atomic="true"
                                class="mt-3 space-y-2 text-sm"
                            >
                                <div
                                    class="flex flex-wrap justify-between gap-x-4 gap-y-1"
                                >
                                    <dt
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        Faculty Evaluation
                                    </dt>
                                    <dd class="font-medium">
                                        {{ preview.evaluation }}
                                    </dd>
                                </div>
                                <div
                                    class="flex flex-wrap justify-between gap-x-4 gap-y-1"
                                >
                                    <dt
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        Grade Access
                                    </dt>
                                    <dd class="font-medium">
                                        {{ preview.access }}
                                    </dd>
                                </div>
                                <div
                                    class="flex flex-wrap justify-between gap-x-4 gap-y-1"
                                >
                                    <dt
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        GWA/GPA
                                    </dt>
                                    <dd class="font-medium">
                                        {{ preview.averages }}
                                    </dd>
                                </div>
                            </dl>
                            <p class="mt-3 text-sm font-medium">
                                Scope:
                                {{
                                    form.access_scope === 'all_grades'
                                        ? 'All grades, across all terms'
                                        : 'Current active campus term only'
                                }}
                            </p>
                            <p
                                v-if="preview.averages === 'Conditional'"
                                class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                            >
                                Semester GPA is hidden while grades are locked.
                                The overall GWA panel remains visible and uses
                                available, unlocked grades only.
                            </p>
                            <p
                                v-if="!form.is_active"
                                class="mt-3 text-sm leading-relaxed text-amber-700 dark:text-amber-300"
                            >
                                This rule is inactive. The preview shows its
                                settings; students use the latest active rule
                                for their campus, or grades will be hidden if no
                                active rule remains.
                            </p>
                            <p
                                v-else
                                class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                Applies when this is the latest active rule for
                                the selected campus.
                            </p>
                        </section>
                        <p
                            v-if="form.errors.is_active"
                            role="alert"
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                    <footer
                        class="flex shrink-0 justify-end gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:px-6 dark:border-white/10 dark:bg-slate-900"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            class="min-h-11 flex-1 sm:flex-none"
                            @click="requestClose()"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                (isEditModalOpen && !hasChanges)
                            "
                            class="min-h-11 flex-1 bg-indigo-600 text-white hover:bg-indigo-700 sm:flex-none"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : isCreateModalOpen
                                      ? 'Create Rule'
                                      : 'Save Changes'
                            }}
                        </Button>
                    </footer>
                </form>
            </DialogContent>
        </Dialog>
    </SiteSettingsLayout>
</template>
