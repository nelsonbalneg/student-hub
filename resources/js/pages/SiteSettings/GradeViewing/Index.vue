<script setup lang="ts">
import { ref } from 'vue';
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
    Search,
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
    bypass_evaluation: true,
    show_gwa_gpa: true,
    is_active: true,
    description: '',
});

const openCreateModal = () => {
    form.reset();
    isCreateModalOpen.value = true;
};

const openEditModal = (rule: Rule) => {
    selectedRule.value = rule;
    form.site_campus_id = String(rule.site_campus_id);
    form.rule_name = rule.rule_name;
    form.bypass_evaluation = rule.bypass_evaluation;
    form.show_gwa_gpa = rule.show_gwa_gpa;
    form.is_active = rule.is_active;
    form.description = rule.description || '';
    isEditModalOpen.value = true;
};

const submitCreate = () => {
    form.post(store.url(), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
        },
    });
};

const submitUpdate = () => {
    if (!selectedRule.value) return;
    form.patch(update.url(selectedRule.value.id), {
        onSuccess: () => {
            isEditModalOpen.value = false;
            selectedRule.value = null;
        },
    });
};

const deleteRule = (rule: Rule) => {
    if (
        confirm(
            'Are you sure you want to delete this rule? This will be logged.',
        )
    ) {
        router.delete(destroy.url(rule.id));
    }
};

const toggleRule = (rule: Rule) => {
    router.patch(toggle.url(rule.id));
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
                                    <th class="px-4 py-3">Evaluation Bypass</th>
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
                                                        : 'Strict Evaluation'
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
                                        <button
                                            @click="toggleRule(rule)"
                                            class="group inline-flex items-center gap-2 transition"
                                        >
                                            <div
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    rule.is_active
                                                        ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]'
                                                        : 'bg-slate-300'
                                                "
                                            ></div>
                                            <span
                                                class="text-[11px] font-bold tracking-tight uppercase"
                                                :class="
                                                    rule.is_active
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-slate-400'
                                                "
                                            >
                                                {{
                                                    rule.is_active
                                                        ? 'Active'
                                                        : 'Disabled'
                                                }}
                                            </span>
                                        </button>
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
                                    default strict checking.
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
                    >
                        ×
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

        <!-- Create/Edit Modal Placeholder (Manual implementation or use a UI library component) -->
        <div
            v-if="isCreateModalOpen || isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-slate-900"
            >
                <div
                    class="shrink-0 border-b border-slate-200 px-6 py-4 dark:border-white/10"
                >
                    <h3
                        class="text-lg font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            isCreateModalOpen ? 'Create New Rule' : 'Edit Rule'
                        }}
                    </h3>
                </div>

                <form
                    @submit.prevent="
                        isCreateModalOpen ? submitCreate() : submitUpdate()
                    "
                    class="flex min-h-0 flex-1 flex-col"
                >
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
                        <div class="space-y-1.5">
                            <label
                                class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                                >Campus</label
                            >
                            <select
                                v-model="form.site_campus_id"
                                class="w-full rounded-lg border-slate-200 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Select Campus</option>
                                <option
                                    v-for="campus in campuses"
                                    :key="campus.id"
                                    :value="String(campus.id)"
                                >
                                    {{ campus.campus_name }}
                                </option>
                            </select>
                            <div
                                v-if="form.errors.site_campus_id"
                                class="text-[10px] text-red-500"
                            >
                                {{ form.errors.site_campus_id }}
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                                >Rule Name</label
                            >
                            <input
                                v-model="form.rule_name"
                                type="text"
                                class="w-full rounded-lg border-slate-200 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-white"
                                placeholder="e.g. End of Semester Policy"
                            />
                            <div
                                v-if="form.errors.rule_name"
                                class="text-[10px] text-red-500"
                            >
                                {{ form.errors.rule_name }}
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-3 dark:border-white/10"
                        >
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span
                                    class="text-xs font-bold text-slate-900 dark:text-white"
                                    >Bypass Evaluation</span
                                >
                                <span class="text-[10px] text-slate-500"
                                    >Allow grade viewing without
                                    evaluation</span
                                >
                            </div>
                            <button
                                type="button"
                                @click="
                                    form.bypass_evaluation =
                                        !form.bypass_evaluation
                                "
                                class="shrink-0 transition"
                            >
                                <ToggleRight
                                    v-if="form.bypass_evaluation"
                                    class="size-6 text-indigo-500"
                                />
                                <ToggleLeft
                                    v-else
                                    class="size-6 text-slate-300"
                                />
                            </button>
                        </div>

                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-3 dark:border-white/10"
                        >
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <span
                                    class="text-xs font-bold text-slate-900 dark:text-white"
                                    >Show GWA/GPA</span
                                >
                                <span class="text-[10px] text-slate-500"
                                    >Allow students to view computed Average GWA
                                    and Semester GPA</span
                                >
                            </div>
                            <button
                                type="button"
                                @click="form.show_gwa_gpa = !form.show_gwa_gpa"
                                class="shrink-0 transition"
                            >
                                <ToggleRight
                                    v-if="form.show_gwa_gpa"
                                    class="size-6 text-sky-500"
                                />
                                <ToggleLeft
                                    v-else
                                    class="size-6 text-slate-300"
                                />
                            </button>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="text-xs font-bold text-slate-500 uppercase dark:text-slate-400"
                                >Description</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-lg border-slate-200 text-sm dark:border-white/10 dark:bg-slate-800 dark:text-white"
                            ></textarea>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-200 px-6 py-4 dark:border-white/10"
                    >
                        <button
                            type="button"
                            @click="
                                isCreateModalOpen = false;
                                isEditModalOpen = false;
                            "
                            class="h-9 px-4 text-xs font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-9 items-center justify-center rounded-md bg-indigo-600 px-6 text-xs font-bold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{
                                isCreateModalOpen
                                    ? 'Create Rule'
                                    : 'Save Changes'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SiteSettingsLayout>
</template>
