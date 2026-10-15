<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    Info,
    LoaderCircle,
    Settings2,
    Plus,
    Pencil,
    Trash2,
    UserRoundCog,
    Check,
    X,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import {
    update as updateSarSetting,
    storeRule,
    updateRule,
    setRuleStatus,
    destroyRule,
    searchOverrideStudents,
    saveOverrides,
} from '@/actions/App/Http/Controllers/SiteSettings/SarSettingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';

interface Campus {
    id: number;
    campus_name: string;
}
interface AcademicTerm {
    id: number;
    site_campus_id: number;
    school_year: string;
    semester: string;
    term_id: string | number;
    status: string;
}
interface AccessRule {
    id: number;
    is_enabled: boolean;
    override_users_count?: number;
    academic_term: AcademicTerm & { campus: Campus };
}
const props = defineProps<{
    settings: { sar_enabled: boolean };
    campuses: Campus[];
    terms: AcademicTerm[];
    rules: AccessRule[];
}>();
const moduleForm = useForm({ sar_enabled: props.settings.sar_enabled });
const ruleForm = useForm({
    site_campus_id: '',
    site_academic_term_id: '',
    is_enabled: true,
});
const availableTerms = computed(() =>
    props.terms.filter(
        (term) =>
            String(term.site_campus_id) === ruleForm.site_campus_id &&
            term.term_id !== null &&
            String(term.term_id).trim() !== '',
    ),
);
const termLabel = (term: AcademicTerm) =>
    `${term.school_year}, ${term.semester} (Term ID: ${term.term_id})`;
watch(
    () => ruleForm.site_campus_id,
    () => {
        ruleForm.site_academic_term_id = '';
        ruleForm.clearErrors();
    },
);
watch(
    () => ruleForm.site_academic_term_id,
    (id) => {
        if (editingRule.value) return;
        const existing = props.rules.find(
            (rule) => String(rule.academic_term.id) === id,
        );
        ruleForm.is_enabled = existing?.is_enabled ?? true;
    },
);
watch(
    () => props.settings.sar_enabled,
    (value) => {
        moduleForm.sar_enabled = value;
    },
);
const updateModule = (value: boolean) => {
    if (moduleForm.processing) return;
    moduleForm.sar_enabled = value;
    moduleForm.post(updateSarSetting.url(), {
        preserveScroll: true,
        onError: () => {
            moduleForm.sar_enabled = props.settings.sar_enabled;
        },
    });
};
const saveRule = () => {
    if (ruleForm.processing) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
        },
    };
    if (editingRule.value) {
        ruleForm.put(updateRule.url(editingRule.value.id), options);
    } else {
        ruleForm.post(storeRule.url(), options);
    }
};
const editorOpen = ref(false);
const editingRule = ref<AccessRule | null>(null);
const editorBaseline = ref('');
const openEditor = async (rule: AccessRule | null = null) => {
    editingRule.value = rule;
    ruleForm.reset();
    ruleForm.clearErrors();
    moduleForm.clearErrors();
    ruleForm.site_campus_id = rule
        ? String(rule.academic_term.site_campus_id)
        : '';
    await nextTick();
    ruleForm.site_academic_term_id = rule ? String(rule.academic_term.id) : '';
    await nextTick();
    ruleForm.is_enabled = rule?.is_enabled ?? true;
    editorBaseline.value = JSON.stringify(ruleForm.data());
    editorOpen.value = true;
};
const closeEditor = (open = false) => {
    if (open || ruleForm.processing || moduleForm.processing) return;
    if (
        JSON.stringify(ruleForm.data()) !== editorBaseline.value &&
        !window.confirm('Discard unsaved SAR rule changes?')
    )
        return;
    editorOpen.value = false;
};
const pendingDelete = ref<AccessRule | null>(null);
const deleting = ref(false);
const deleteError = ref('');
const requestDelete = (rule: AccessRule) => {
    if (rule.is_enabled || deleting.value) return;
    pendingDelete.value = rule;
    deleteError.value = '';
};
const closeDelete = (open = false) => {
    if (!open && !deleting.value) pendingDelete.value = null;
};
const confirmDelete = () => {
    if (
        !pendingDelete.value ||
        pendingDelete.value.is_enabled ||
        deleting.value
    )
        return;
    deleting.value = true;
    router.delete(destroyRule.url(pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onError: () => {
            deleteError.value =
                'Unable to delete this rule. Ensure it is inactive and try again.';
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};
const pendingRule = ref<AccessRule | null>(null);
const statusSaving = ref(false);
const statusError = ref('');
const closeConfirmation = (open = false) => {
    if (!open && !statusSaving.value) pendingRule.value = null;
};
const requestStatusChange = (rule: AccessRule) => {
    pendingRule.value = rule;
    statusError.value = '';
};
const confirmStatusChange = () => {
    if (!pendingRule.value || statusSaving.value) return;
    statusSaving.value = true;
    router.patch(
        setRuleStatus.url(pendingRule.value.id),
        { is_enabled: !pendingRule.value.is_enabled },
        {
            preserveScroll: true,
            onSuccess: () => {
                pendingRule.value = null;
            },
            onError: () => {
                statusError.value =
                    'Unable to change access. Please try again.';
            },
            onFinish: () => {
                statusSaving.value = false;
            },
        },
    );
};

interface OverrideStudent {
    id: number;
    name: string;
    student_no: string | null;
    email: string;
    overridden: boolean;
}
const overrideRule = ref<AccessRule | null>(null);
const studentSearch = ref('');
const selectedOnly = ref(false);
const searchResults = ref<OverrideStudent[]>([]);
const searchLoading = ref(false);
const searchError = ref('');
const nextSearchPage = ref<number | null>(null);
const studentChanges = ref<
    Record<number, { student: OverrideStudent; selected: boolean }>
>({});
const overrideForm = useForm({
    add_user_ids: [] as number[],
    remove_user_ids: [] as number[],
});
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let searchController: AbortController | null = null;
let searchVersion = 0;
const studentSelected = (student: OverrideStudent) =>
    studentChanges.value[student.id]?.selected ?? student.overridden;
const toggleStudent = (student: OverrideStudent) => {
    if (overrideForm.processing) return;
    const selected = !studentSelected(student);
    if (selected === student.overridden)
        delete studentChanges.value[student.id];
    else studentChanges.value[student.id] = { student, selected };
};
const findStudents = async (page = 1) => {
    if (
        !overrideRule.value ||
        (!selectedOnly.value && studentSearch.value.trim().length < 2)
    )
        return;
    const version = ++searchVersion;
    searchController?.abort();
    searchController = new AbortController();
    searchLoading.value = true;
    searchError.value = '';
    try {
        const response = await fetch(
            searchOverrideStudents.url(overrideRule.value.id, {
                query: {
                    q: studentSearch.value.trim(),
                    selected_only: selectedOnly.value ? 1 : 0,
                    page,
                },
            }),
            {
                signal: searchController.signal,
                headers: { Accept: 'application/json' },
            },
        );
        if (!response.ok)
            throw new Error('Unable to search students. Please try again.');
        const data = await response.json();
        if (version !== searchVersion) return;
        searchResults.value =
            page === 1 ? data.data : [...searchResults.value, ...data.data];
        nextSearchPage.value = data.next_page;
    } catch (error) {
        if (
            version === searchVersion &&
            !(error instanceof DOMException && error.name === 'AbortError')
        )
            searchError.value = 'Unable to search students. Please try again.';
    } finally {
        if (version === searchVersion) searchLoading.value = false;
    }
};
watch([studentSearch, selectedOnly, () => overrideRule.value?.id], () => {
    clearTimeout(searchTimer);
    searchController?.abort();
    searchVersion++;
    searchResults.value = [];
    nextSearchPage.value = null;
    searchLoading.value = false;
    searchError.value = '';
    if (
        overrideRule.value &&
        (selectedOnly.value || studentSearch.value.trim().length >= 2)
    )
        searchTimer = setTimeout(() => findStudents(), 300);
});
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    searchController?.abort();
});
const openOverrides = (rule: AccessRule) => {
    studentSearch.value = '';
    selectedOnly.value = false;
    studentChanges.value = {};
    overrideForm.clearErrors();
    overrideRule.value = rule;
};
const closeOverrides = (open = false) => {
    if (open || overrideForm.processing) return;
    if (
        Object.keys(studentChanges.value).length &&
        !window.confirm('Discard unsaved student override changes?')
    )
        return;
    overrideRule.value = null;
};
const saveStudentOverrides = () => {
    if (!overrideRule.value || overrideForm.processing) return;
    const changes = Object.values(studentChanges.value);
    overrideForm.add_user_ids = changes
        .filter((change) => change.selected)
        .map((change) => change.student.id);
    overrideForm.remove_user_ids = changes
        .filter((change) => !change.selected)
        .map((change) => change.student.id);
    overrideForm.post(saveOverrides.url(overrideRule.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            overrideRule.value = null;
        },
    });
};
const focusSearchResult = (index: number) => {
    const student =
        searchResults.value[
            Math.max(0, Math.min(searchResults.value.length - 1, index))
        ];
    if (student)
        document.getElementById(`override-student-${student.id}`)?.focus();
};
</script>

<template>
    <Head title="Site Settings - Student Academic Registration" />
    <SiteSettingsLayout>
        <div class="space-y-6 p-4 sm:p-6 lg:p-8">
            <header>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white"
                >
                    Student Academic Registration
                </h1>
                <p
                    class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                >
                    Control SAR access by campus and registration term. SAR
                    stays closed unless a matching rule is On.
                </p>
            </header>
            <section aria-labelledby="rules-title" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="rules-title" class="text-base font-semibold">
                        Saved Access Rules
                    </h2>
                    <Button type="button" @click="openEditor()"
                        ><Plus class="size-4" />New Access Rule</Button
                    >
                </div>
                <div
                    v-if="!settings.sar_enabled"
                    class="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />The SAR module is
                    Off. All registration access is closed, including rules
                    marked On.
                </div>
                <div
                    class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Campus</th>
                                <th class="px-4 py-3 font-semibold">
                                    Academic Term
                                </th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-white/10"
                        >
                            <tr v-if="!rules.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-slate-500"
                                >
                                    No access rules yet. SAR is closed for all
                                    campuses and terms.
                                </td>
                            </tr>
                            <tr v-for="rule in rules" :key="rule.id">
                                <td class="px-4 py-4">
                                    {{ rule.academic_term.campus.campus_name }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ termLabel(rule.academic_term) }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <Switch
                                            :model-value="rule.is_enabled"
                                            :disabled="statusSaving"
                                            :aria-label="`${rule.is_enabled ? 'Turn off' : 'Turn on'} SAR for ${rule.academic_term.campus.campus_name}, ${termLabel(rule.academic_term)}`"
                                            @update:model-value="
                                                requestStatusChange(rule)
                                            "
                                        /><span>{{
                                            rule.is_enabled
                                                ? 'Active'
                                                : 'Inactive'
                                        }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Student overrides for ${rule.academic_term.campus.campus_name}, ${termLabel(rule.academic_term)}`"
                                            :title="`Student overrides (${rule.override_users_count ?? 0})`"
                                            @click="openOverrides(rule)"
                                            ><UserRoundCog class="size-4"
                                        /></Button>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Edit SAR access for ${rule.academic_term.campus.campus_name}`"
                                            @click="openEditor(rule)"
                                            ><Pencil class="size-4"
                                        /></Button>
                                        <Button
                                            v-if="!rule.is_enabled"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="deleting"
                                            :aria-label="`Delete inactive SAR rule for ${rule.academic_term.campus.campus_name}`"
                                            class="text-red-600 hover:text-red-700"
                                            @click="requestDelete(rule)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                >
                    Each configured campus term appears as its own student
                    registration row. Turning a rule Off closes only that term's
                    Apply action; submitted records remain viewable.
                </p>
            </section>
        </div>
        <Dialog :open="!!overrideRule" @update:open="closeOverrides">
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-[620px]"
            >
                <header class="shrink-0 border-b p-5">
                    <DialogTitle>Student Access Overrides</DialogTitle>
                    <DialogDescription class="mt-2"
                        >{{ overrideRule?.academic_term.campus.campus_name }} /
                        {{
                            overrideRule
                                ? termLabel(overrideRule.academic_term)
                                : ''
                        }}</DialogDescription
                    >
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div class="space-y-4">
                        <div class="min-w-0 space-y-4">
                            <p
                                class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                            >
                                Selected students can Apply for this term even
                                when its rule is inactive. The master SAR module
                                switch must still be On. Saving does not change
                                access for other students.
                            </p>
                            <div class="space-y-2">
                                <Label for="sar-student-search"
                                    >Search students</Label
                                >
                                <div class="relative">
                                    <Search
                                        class="absolute top-3 left-3 size-4 text-slate-400"
                                        aria-hidden="true"
                                    /><input
                                        id="sar-student-search"
                                        v-model="studentSearch"
                                        type="search"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="sar-student-results"
                                        :aria-expanded="!!searchResults.length"
                                        :disabled="overrideForm.processing"
                                        placeholder="Type a name, email or student number (at least 2 characters)"
                                        class="h-11 w-full rounded-lg border border-slate-300 bg-white pr-3 pl-10 text-sm focus:ring-2 focus:ring-indigo-500 dark:border-white/15 dark:bg-slate-800"
                                        @keydown.down.prevent="
                                            focusSearchResult(0)
                                        "
                                    />
                                </div>
                                <label class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="selectedOnly"
                                        type="checkbox"
                                        :disabled="overrideForm.processing"
                                        class="size-4 accent-indigo-600"
                                    />Show students with existing
                                    overrides</label
                                >
                            </div>
                            <p
                                v-if="searchError"
                                role="alert"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ searchError
                                }}<Button
                                    type="button"
                                    variant="ghost"
                                    @click="findStudents()"
                                    >Retry</Button
                                >
                            </p>
                            <div
                                id="sar-student-results"
                                role="listbox"
                                aria-label="Matching students"
                                aria-multiselectable="true"
                                class="max-h-64 overflow-y-auto rounded-lg border border-slate-200 dark:border-white/10"
                                :aria-busy="searchLoading"
                            >
                                <div
                                    v-for="(student, index) in searchResults"
                                    :id="`override-student-${student.id}`"
                                    :key="student.id"
                                    role="option"
                                    :aria-selected="studentSelected(student)"
                                    :aria-disabled="overrideForm.processing"
                                    :tabindex="overrideForm.processing ? -1 : 0"
                                    class="flex min-h-12 w-full items-center justify-between gap-3 border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-slate-50 focus-visible:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:border-white/10 dark:hover:bg-white/5 dark:focus-visible:bg-indigo-500/10"
                                    @click="toggleStudent(student)"
                                    @keydown.enter.self.prevent="
                                        toggleStudent(student)
                                    "
                                    @keydown.space.self.prevent="
                                        toggleStudent(student)
                                    "
                                    @keydown.down.prevent="
                                        focusSearchResult(index + 1)
                                    "
                                    @keydown.up.prevent="
                                        focusSearchResult(index - 1)
                                    "
                                >
                                    <span>
                                        <span class="block font-medium">{{
                                            student.name
                                        }}</span>
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                            >{{ student.email
                                            }}<span v-if="student.student_no">
                                                / {{ student.student_no }}</span
                                            ></span
                                        >
                                        <span
                                            v-if="
                                                studentChanges[student.id] &&
                                                !studentSelected(student)
                                            "
                                            class="mt-1 block text-xs font-medium text-red-600 dark:text-red-400"
                                            >Removal will be confirmed once
                                            saved.</span
                                        >
                                    </span>
                                    <button
                                        v-if="
                                            selectedOnly && student.overridden
                                        "
                                        type="button"
                                        :disabled="overrideForm.processing"
                                        :aria-label="`${studentSelected(student) ? 'Remove override for' : 'Undo removal for'} ${student.name}`"
                                        :title="
                                            studentSelected(student)
                                                ? 'Remove override'
                                                : 'Undo removal'
                                        "
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md text-red-600 hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-red-500/10"
                                        @click.stop="toggleStudent(student)"
                                    >
                                        <X
                                            v-if="studentSelected(student)"
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <RotateCcw
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <Check
                                        v-else-if="studentSelected(student)"
                                        class="size-4 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="searchLoading"
                                role="status"
                                class="flex items-center gap-2 text-sm text-slate-500"
                            >
                                <LoaderCircle
                                    class="size-4 animate-spin"
                                />Searching students...
                            </p>
                            <p
                                v-else-if="!searchResults.length"
                                class="text-sm text-slate-500"
                            >
                                {{
                                    selectedOnly ||
                                    studentSearch.trim().length >= 2
                                        ? 'No matching students.'
                                        : 'Search all users by any part of a name, email or student number. Select multiple results.'
                                }}
                            </p>
                            <Button
                                v-if="nextSearchPage"
                                type="button"
                                variant="outline"
                                :disabled="
                                    searchLoading || overrideForm.processing
                                "
                                @click="findStudents(nextSearchPage)"
                                >Load more students</Button
                            >
                            <p class="text-xs text-slate-500">
                                {{
                                    Object.values(studentChanges).filter(
                                        (change) => change.selected,
                                    ).length
                                }}
                                to allow;
                                {{
                                    Object.values(studentChanges).filter(
                                        (change) => !change.selected,
                                    ).length
                                }}
                                to revoke. Maximum 500 changes of each type per
                                save.
                            </p>

                            <p
                                v-for="(error, key) in overrideForm.errors"
                                :key="key"
                                role="alert"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ error }}
                            </p>
                        </div>
                    </div>
                </div>
                <footer class="flex shrink-0 justify-end gap-3 border-t p-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="overrideForm.processing"
                        @click="closeOverrides()"
                        >Cancel</Button
                    ><Button
                        type="button"
                        :disabled="
                            overrideForm.processing ||
                            !Object.keys(studentChanges).length
                        "
                        @click="saveStudentOverrides"
                        ><LoaderCircle
                            v-if="overrideForm.processing"
                            class="size-4 animate-spin"
                        />{{
                            overrideForm.processing
                                ? 'Saving...'
                                : 'Save Overrides'
                        }}</Button
                    >
                </footer>
            </DialogContent>
        </Dialog>
        <Dialog :open="editorOpen" @update:open="closeEditor">
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-[640px]"
            >
                <header class="shrink-0 border-b px-5 py-4">
                    <DialogTitle>{{
                        editingRule
                            ? 'Edit SAR Access Rule'
                            : 'Manage SAR Access'
                    }}</DialogTitle>
                    <DialogDescription class="mt-2"
                        >Configure module availability and campus registration
                        access.</DialogDescription
                    >
                </header>
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                    <section
                        aria-labelledby="module-title"
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2
                                    id="module-title"
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Settings2 class="size-4" />Module Settings
                                </h2>
                                <Label
                                    for="sar-enabled"
                                    class="mt-4 inline-block"
                                    >Enable SAR Module</Label
                                >
                                <p
                                    id="module-help"
                                    class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                                >
                                    Master control: Off closes SAR for everyone.
                                    On allows the campus and term rules below to
                                    apply.
                                </p>
                            </div>
                            <Switch
                                id="sar-enabled"
                                :model-value="props.settings.sar_enabled"
                                :disabled="moduleForm.processing"
                                aria-describedby="module-help"
                                @update:model-value="updateModule"
                            />
                        </div>
                        <InputError
                            :message="moduleForm.errors.sar_enabled"
                            class="mt-2"
                        />
                        <p
                            v-if="moduleForm.processing"
                            role="status"
                            class="mt-2 text-sm text-slate-500"
                        >
                            Updating module settings...
                        </p>
                    </section>
                    <section
                        aria-labelledby="rule-title"
                        class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-slate-950"
                    >
                        <h2 id="rule-title" class="text-base font-semibold">
                            Campus and Term Access Rule
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                        >
                            Select a campus and its configured academic term,
                            then turn registration On or Off. Saving an existing
                            campus and term updates its rule.
                        </p>
                        <form
                            id="sar-rule-form"
                            class="mt-5 space-y-4"
                            @submit.prevent="saveRule"
                        >
                            <div class="grid gap-4">
                                <div class="space-y-2">
                                    <Label for="sar-campus">Campus</Label>
                                    <select
                                        id="sar-campus"
                                        v-model="ruleForm.site_campus_id"
                                        required
                                        :disabled="ruleForm.processing"
                                        :aria-invalid="
                                            !!ruleForm.errors.site_campus_id
                                        "
                                        aria-describedby="campus-error"
                                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-white/15 dark:bg-slate-800"
                                    >
                                        <option value="">Select campus</option>
                                        <option
                                            v-for="campus in campuses"
                                            :key="campus.id"
                                            :value="String(campus.id)"
                                        >
                                            {{ campus.campus_name }}
                                        </option>
                                    </select>
                                    <InputError
                                        id="campus-error"
                                        :message="
                                            ruleForm.errors.site_campus_id
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="sar-term">Academic Term</Label>
                                    <select
                                        id="sar-term"
                                        v-model="ruleForm.site_academic_term_id"
                                        required
                                        :disabled="
                                            !ruleForm.site_campus_id ||
                                            ruleForm.processing
                                        "
                                        :aria-invalid="
                                            !!ruleForm.errors
                                                .site_academic_term_id
                                        "
                                        aria-describedby="term-error term-help"
                                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:border-white/15 dark:bg-slate-800"
                                    >
                                        <option value="">
                                            Select academic term
                                        </option>
                                        <option
                                            v-for="term in availableTerms"
                                            :key="term.id"
                                            :value="String(term.id)"
                                        >
                                            {{ termLabel(term) }}
                                        </option>
                                    </select>
                                    <p
                                        id="term-help"
                                        v-if="
                                            ruleForm.site_campus_id &&
                                            !availableTerms.length
                                        "
                                        class="text-sm text-amber-700 dark:text-amber-300"
                                    >
                                        Add a term with a term ID in this
                                        campus's Terms settings first.
                                    </p>
                                    <InputError
                                        id="term-error"
                                        :message="
                                            ruleForm.errors
                                                .site_academic_term_id
                                        "
                                    />
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-4 dark:border-white/10"
                            >
                                <div>
                                    <Label for="sar-rule-enabled"
                                        >SAR Access:
                                        {{
                                            ruleForm.is_enabled ? 'On' : 'Off'
                                        }}</Label
                                    >
                                    <p
                                        id="rule-help"
                                        class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            ruleForm.is_enabled
                                                ? 'Allow registration for this campus and term while the module is enabled.'
                                                : 'Close registration for this campus and term.'
                                        }}
                                    </p>
                                </div>
                                <Switch
                                    id="sar-rule-enabled"
                                    v-model="ruleForm.is_enabled"
                                    :disabled="ruleForm.processing"
                                    aria-describedby="rule-help"
                                />
                            </div>
                            <InputError :message="ruleForm.errors.is_enabled" />
                        </form>
                    </section>

                    <p v-if="editingRule" class="text-sm text-slate-500">
                        Changing campus or term moves this rule and clears its
                        student overrides. A campus and term can have only one
                        rule.
                    </p>
                </div>
                <footer
                    class="flex shrink-0 justify-end gap-3 border-t px-5 py-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="ruleForm.processing || moduleForm.processing"
                        @click="closeEditor()"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        form="sar-rule-form"
                        :disabled="
                            ruleForm.processing ||
                            moduleForm.processing ||
                            !ruleForm.site_academic_term_id
                        "
                        ><LoaderCircle
                            v-if="ruleForm.processing"
                            class="size-4 animate-spin"
                        />{{
                            ruleForm.processing
                                ? 'Saving...'
                                : 'Save Access Rule'
                        }}</Button
                    >
                </footer>
            </DialogContent>
        </Dialog>
        <Dialog :open="!!pendingDelete" @update:open="closeDelete">
            <DialogContent :show-close-button="false" class="sm:max-w-md">
                <DialogTitle>Delete Inactive SAR Rule?</DialogTitle>
                <DialogDescription
                    >Delete the rule for
                    {{ pendingDelete?.academic_term.campus.campus_name }},
                    {{
                        pendingDelete
                            ? termLabel(pendingDelete.academic_term)
                            : ''
                    }}. SAR remains closed unless a matching active rule is
                    saved.</DialogDescription
                >
                <p
                    v-if="deleteError"
                    role="alert"
                    class="text-sm text-red-600 dark:text-red-400"
                >
                    {{ deleteError }}
                </p>
                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deleting"
                        @click="closeDelete()"
                        >Cancel</Button
                    ><Button
                        type="button"
                        variant="destructive"
                        :disabled="deleting"
                        @click="confirmDelete"
                        ><LoaderCircle
                            v-if="deleting"
                            class="size-4 animate-spin"
                        />{{ deleting ? 'Deleting...' : 'Delete Rule' }}</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
        <Dialog :open="!!pendingRule" @update:open="closeConfirmation">
            <DialogContent :show-close-button="false" class="sm:max-w-md">
                <DialogTitle
                    >Turn SAR
                    {{ pendingRule?.is_enabled ? 'Off' : 'On' }}?</DialogTitle
                >
                <DialogDescription
                    >Change access for
                    {{ pendingRule?.academic_term.campus.campus_name }},
                    {{
                        pendingRule ? termLabel(pendingRule.academic_term) : ''
                    }}.
                    {{
                        pendingRule?.is_enabled
                            ? 'Students in this campus and term will no longer be able to register.'
                            : 'Students in this campus and term can register while the master module switch is On.'
                    }}</DialogDescription
                >
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
                        @click="closeConfirmation()"
                        >Cancel</Button
                    ><Button
                        type="button"
                        :disabled="statusSaving"
                        @click="confirmStatusChange"
                        ><LoaderCircle
                            v-if="statusSaving"
                            class="size-4 animate-spin"
                        />{{ statusSaving ? 'Updating...' : 'Confirm' }}</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
    </SiteSettingsLayout>
</template>
