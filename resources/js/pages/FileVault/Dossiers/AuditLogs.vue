<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type LogEntry = {
    id: number;
    action: string;
    occurred_at: string | null;
    recipient_or_requestor: string | null;
    legal_basis: string | null;
    legitimate_interest: string | null;
    metadata_json: Record<string, unknown> | null;
    actor: {
        id: number;
        name: string;
        email: string | null;
    } | null;
};

const props = defineProps<{
    dossier: {
        id: number;
        dossier_number: string;
        student_name: string | null;
        student_no: string | null;
    };
    logs: Page<LogEntry>;
    filters: {
        action?: string;
        actor_id?: string;
        date_from?: string;
        date_to?: string;
    };
    actions: string[];
    actors: Array<{
        id: number;
        name: string;
    }>;
}>();

const action = ref(props.filters.action ?? '');
const actorId = ref(props.filters.actor_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const applyFilters = () => {
    router.get(
        dossierRoutes.auditLogs.url(props.dossier.id),
        {
            action: action.value || undefined,
            actor_id: actorId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

watch([action, actorId, dateFrom, dateTo], applyFilters);

const formatDateTime = (value: string | null) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const paginationLabel = computed(() => {
    const { from, to, total } = props.logs.meta;

    if (from === null || to === null) {
        return `Total ${total}`;
    }

    return `${from}-${to} of ${total}`;
});
</script>

<template>
    <Head :title="`Audit Logs - ${dossier.dossier_number}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Audit Explorer</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ dossier.dossier_number }} · {{ dossier.student_name ?? '-' }} ({{ dossier.student_no ?? '-' }})
                    </p>
                </div>
                <Link
                    :href="dossierRoutes.show.url(dossier.id)"
                    class="inline-flex h-10 items-center rounded-md border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                >
                    Back to Dossier
                </Link>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-4">
                <div>
                    <Label for="action">Action</Label>
                    <select
                        id="action"
                        v-model="action"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">All actions</option>
                        <option v-for="item in actions" :key="item" :value="item">{{ item }}</option>
                    </select>
                </div>
                <div>
                    <Label for="actor">Actor</Label>
                    <select
                        id="actor"
                        v-model="actorId"
                        class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                    >
                        <option value="">All actors</option>
                        <option v-for="item in actors" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </div>
                <div>
                    <Label for="date_from">Date From</Label>
                    <Input id="date_from" v-model="dateFrom" type="date" class="mt-1.5" />
                </div>
                <div>
                    <Label for="date_to">Date To</Label>
                    <Input id="date_to" v-model="dateTo" type="date" class="mt-1.5" />
                </div>
            </div>
        </section>

        <section class="min-h-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="overflow-y-auto p-4">
                <div v-if="logs.data.length === 0" class="py-8 text-center text-sm text-slate-500">No audit events found.</div>

                <div v-for="log in logs.data" :key="log.id" class="mb-3 rounded-md border border-slate-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ log.action }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatDateTime(log.occurred_at) }} · {{ log.actor?.name ?? 'System' }}
                            </p>
                        </div>
                        <p class="text-xs text-slate-500">#{{ log.id }}</p>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 dark:text-slate-300 lg:grid-cols-3">
                        <p><span class="font-semibold">Recipient/Requestor:</span> {{ log.recipient_or_requestor ?? '-' }}</p>
                        <p><span class="font-semibold">Legal Basis:</span> {{ log.legal_basis ?? '-' }}</p>
                        <p><span class="font-semibold">Legitimate Interest:</span> {{ log.legitimate_interest ?? '-' }}</p>
                    </div>

                    <details v-if="log.metadata_json" class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-300">Metadata</summary>
                        <pre class="mt-2 overflow-auto rounded-md bg-slate-100 p-2 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300">{{ JSON.stringify(log.metadata_json, null, 2) }}</pre>
                    </details>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500 dark:border-white/10">
                <p>{{ paginationLabel }}</p>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="link in logs.links"
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
    </div>
</template>
