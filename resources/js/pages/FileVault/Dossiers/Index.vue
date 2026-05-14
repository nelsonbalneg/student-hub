<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import dossierRoutes from '@/routes/file-vault/dossiers';

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type Page<T> = {
    data: T[];
    links: PageLink[];
    meta: PageMeta;
};

type UserOption = {
    id: number;
    name: string;
};

type StudentOption = {
    id: number;
    name: string;
    student_no: string | null;
};

type DossierRow = {
    id: number;
    name: string;
    student_no: string | null;
    status: string;
    priority: string;
    transaction_type: string;
    current_owner_id: number | null;
    intake_date: string | null;
    completion_due_at: string | null;
    is_overdue: boolean;
    student: {
        id: number;
        name: string;
        student_no: string | null;
        email: string | null;
    } | null;
    owner: {
        id: number;
        name: string;
    } | null;
    updated_at: string | null;
};

const props = defineProps<{
    dossiers: Page<DossierRow>;
    filters: {
        search?: string;
        status?: string;
        priority?: string;
        owner_id?: string;
        view?: string;
    };
    statuses: string[];
    priorities: string[];
    owners: UserOption[];
    students: StudentOption[];
    transactionTypes: string[];
    queueViews: Array<{
        key: string;
        label: string;
    }>;
    queueStats: {
        all: number;
        my_queue: number;
        unassigned: number;
        overdue: number;
    };
    can: {
        create: boolean;
        update: boolean;
        transition: boolean;
        archive: boolean;
    };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
const ownerId = ref(props.filters.owner_id ?? '');
const view = ref(props.filters.view ?? 'all');

const showCreateForm = ref(false);

const createForm = useForm({
    student_id: '',
    transaction_type: '',
    priority: 'normal',
    current_owner_id: '',
    intake_date: '',
    completion_due_at: '',
});

const statusClass = (value: string) => {
    const map: Record<string, string> = {
        draft: 'border-slate-200 bg-slate-50 text-slate-700',
        for_intake_review: 'border-blue-200 bg-blue-50 text-blue-700',
        incomplete: 'border-amber-200 bg-amber-50 text-amber-700',
        active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        for_supervisor_approval: 'border-indigo-200 bg-indigo-50 text-indigo-700',
        released: 'border-teal-200 bg-teal-50 text-teal-700',
        archived: 'border-zinc-200 bg-zinc-50 text-zinc-700',
        on_hold: 'border-red-200 bg-red-50 text-red-700',
    };

    return map[value] ?? 'border-slate-200 bg-slate-50 text-slate-700';
};

const applyFilters = () => {
    router.get(
        dossierRoutes.index.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            owner_id: ownerId.value || undefined,
            view: view.value && view.value !== 'all' ? view.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

let filterTimeout: ReturnType<typeof setTimeout>;
watch([search, status, priority, ownerId, view], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 250);
});

const openCreate = () => {
    createForm.reset();
    createForm.priority = 'normal';
    showCreateForm.value = true;
};

const closeCreate = () => {
    showCreateForm.value = false;
    createForm.reset();
};

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            student_id: Number(data.student_id),
            current_owner_id: data.current_owner_id ? Number(data.current_owner_id) : null,
            intake_date: data.intake_date || null,
            completion_due_at: data.completion_due_at || null,
        }))
        .post(dossierRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => closeCreate(),
        });
};

const paginationLabel = computed(() => {
    const { from, to, total } = props.dossiers.meta;

    if (from === null || to === null) {
        return `Total ${total}`;
    }

    return `${from}-${to} of ${total}`;
});
</script>

<template>
    <Head title="File Vault Dossiers" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-950 dark:text-white">File Vault Dossiers</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage intake, checklist progress, and lifecycle transitions.
                    </p>
                </div>
                <Button v-if="can.create" class="h-10 px-5 font-semibold" @click="openCreate">New Dossier</Button>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
                <button
                    v-for="queueView in queueViews"
                    :key="queueView.key"
                    type="button"
                    :class="[
                        'rounded-md border px-3 py-2 text-left text-xs font-semibold',
                        view === queueView.key
                            ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900'
                            : 'border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200',
                    ]"
                    @click="view = queueView.key"
                >
                    <p>{{ queueView.label }}</p>
                    <p class="mt-1 text-[11px] opacity-80">
                        {{
                            queueView.key === 'all'
                                ? queueStats.all
                                : queueView.key === 'my_queue'
                                  ? queueStats.my_queue
                                  : queueView.key === 'unassigned'
                                    ? queueStats.unassigned
                                    : queueStats.overdue
                        }}
                    </p>
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-4">
                <div>
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="search" placeholder="Dossier no, student no, name" class="mt-1.5" />
                </div>
                <div>
                    <Label for="status">Status</Label>
                    <select
                        id="status"
                        v-model="status"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">All statuses</option>
                        <option v-for="item in statuses" :key="item" :value="item">{{ item }}</option>
                    </select>
                </div>
                <div>
                    <Label for="priority">Priority</Label>
                    <select
                        id="priority"
                        v-model="priority"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">All priorities</option>
                        <option v-for="item in priorities" :key="item" :value="item">{{ item }}</option>
                    </select>
                </div>
                <div>
                    <Label for="owner">Owner</Label>
                    <select
                        id="owner"
                        v-model="ownerId"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">All owners</option>
                        <option v-for="item in owners" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="min-h-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Dossier</th>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="dossier in dossiers.data"
                            :key="dossier.id"
                            :class="[
                                'border-t border-slate-100 text-slate-700 dark:border-white/10 dark:text-slate-300',
                                dossier.is_overdue ? 'bg-red-50/40 dark:bg-red-500/10' : '',
                            ]"
                        >
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ dossier.name }}</p>
                                <p class="text-xs text-slate-500">{{ dossier.transaction_type }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ dossier.student?.name ?? '-' }}</p>
                                <p class="text-xs text-slate-500">{{ dossier.student?.student_no ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-md border px-2 py-1 text-xs font-semibold', statusClass(dossier.status)]">
                                    {{ dossier.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ dossier.priority }}</td>
                            <td class="px-4 py-3">{{ dossier.owner?.name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span :class="dossier.is_overdue ? 'font-semibold text-red-600 dark:text-red-300' : ''">
                                    {{ dossier.completion_due_at ? new Date(dossier.completion_due_at).toLocaleDateString('en-PH') : '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="dossierRoutes.show.url(dossier.id)"
                                    class="inline-flex rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                                >
                                    Open
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="dossiers.data.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                No dossiers found for current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-white/10">
                <p>{{ paginationLabel }}</p>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="link in dossiers.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url ?? '#'"
                        :class="[
                            'rounded-md px-2.5 py-1.5',
                            link.active
                                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                                : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                        preserve-state
                        preserve-scroll
                        v-html="link.label"
                    />
                </div>
            </div>
        </section>

        <section
            v-if="showCreateForm"
            class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Create Dossier</h2>
                <Button variant="outline" @click="closeCreate">Cancel</Button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <Label for="student_id">Student</Label>
                    <select
                        id="student_id"
                        v-model="createForm.student_id"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">Select student</option>
                        <option v-for="student in students" :key="student.id" :value="String(student.id)">
                            {{ student.student_no ?? 'N/A' }} - {{ student.name }}
                        </option>
                    </select>
                    <InputError :message="createForm.errors.student_id" />
                </div>

                <div>
                    <Label for="transaction_type">Transaction Type</Label>
                    <select
                        id="transaction_type"
                        v-model="createForm.transaction_type"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">Select type</option>
                        <option v-for="item in transactionTypes" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <InputError :message="createForm.errors.transaction_type" />
                </div>

                <div>
                    <Label for="create_priority">Priority</Label>
                    <select
                        id="create_priority"
                        v-model="createForm.priority"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option v-for="item in priorities" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <InputError :message="createForm.errors.priority" />
                </div>

                <div>
                    <Label for="owner_id">Owner</Label>
                    <select
                        id="owner_id"
                        v-model="createForm.current_owner_id"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">Assign later</option>
                        <option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">
                            {{ owner.name }}
                        </option>
                    </select>
                    <InputError :message="createForm.errors.current_owner_id" />
                </div>

                <div>
                    <Label for="intake_date">Intake Date</Label>
                    <Input id="intake_date" v-model="createForm.intake_date" type="date" class="mt-1.5" />
                    <InputError :message="createForm.errors.intake_date" />
                </div>

                <div>
                    <Label for="completion_due_at">Completion Due</Label>
                    <Input id="completion_due_at" v-model="createForm.completion_due_at" type="datetime-local" class="mt-1.5" />
                    <InputError :message="createForm.errors.completion_due_at" />
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <Button :disabled="createForm.processing" @click="submitCreate">Create Dossier</Button>
            </div>
        </section>
    </div>
</template>
