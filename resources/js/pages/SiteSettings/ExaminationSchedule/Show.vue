<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    FileSpreadsheet,
    RefreshCw,
    Upload,
    Search,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import SiteSettingsLayout from '@/layouts/SiteSettingsLayout.vue';
import webRoutes from '@/routes/site-settings/examination-schedules';
import SearchableSelect from '@/components/SearchableSelect.vue';
import {
    preview as previewImportRoute,
    sync as syncImportRoute,
} from '@/routes/site-settings/examination-schedules/import';

const props = defineProps<{
    examinationSchedule: any;
    campuses: any[];
    terms: any[];
}>();

const activeTab = ref<'records' | 'logs'>('records');

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const previewLoading = ref(false);
const syncLoading = ref(false);
const importError = ref('');
const importPreview = ref<{
    token: string;
    filename: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    truncated: boolean;
    rows: {
        row: number;
        subject_code: string;
        section: string | null;
        room: string | null;
        day: string | null;
        start_time: string | null;
        end_time: string | null;
        instructor: string | null;
        proctor_name: string | null;
        valid: boolean;
        errors: string[];
    }[];
} | null>(null);

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const errorMessage = async (response: Response) => {
    const payload = await response.json().catch(() => ({}));

    return (
        payload.message ||
        Object.values(payload.errors ?? {})
            .flat()
            .join(' ') ||
        'The request could not be completed.'
    );
};

const resetImport = () => {
    selectedFile.value = null;
    importPreview.value = null;
    importError.value = '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const previewImport = async () => {
    if (!selectedFile.value) {
        return;
    }

    previewLoading.value = true;
    importError.value = '';
    importPreview.value = null;

    const data = new FormData();
    data.append('file', selectedFile.value);

    try {
        const response = await fetch(
            previewImportRoute.url(props.examinationSchedule.id),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: data,
            },
        );

        if (!response.ok) {
            throw new Error(await errorMessage(response));
        }

        importPreview.value = await response.json();
    } catch (error) {
        importError.value =
            error instanceof Error ? error.message : 'Preview failed.';
    } finally {
        previewLoading.value = false;
    }
};

const syncImport = async () => {
    if (!importPreview.value || importPreview.value.invalid_rows > 0) {
        return;
    }

    syncLoading.value = true;
    importError.value = '';

    try {
        const response = await fetch(
            syncImportRoute.url(props.examinationSchedule.id),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ token: importPreview.value.token }),
            },
        );

        if (!response.ok) {
            throw new Error(await errorMessage(response));
        }

        const payload = await response.json();
        toast.success(payload.message);
        resetImport();
        activeTab.value = 'logs';
        fetchLogs();
    } catch (error) {
        importError.value =
            error instanceof Error ? error.message : 'Sync failed.';
    } finally {
        syncLoading.value = false;
    }
};

const selectImportFile = (event: Event) => {
    selectedFile.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    importPreview.value = null;
    importError.value = '';
};

// DataTables (Records)
const records = ref<any>({ data: [], current_page: 1, last_page: 1, sections: [], rooms: [], days: [] });
const recordsLoading = ref(false);
const searchQuery = ref('');
const selectedSection = ref('');
const selectedRoom = ref('');
const selectedDay = ref('');

const groupedRecords = computed(() => {
    const groups: { [key: string]: any[] } = {};
    if (!records.value || !records.value.data) {
        return groups;
    }
    records.value.data.forEach((record: any) => {
        const sec = record.section || 'Unassigned';
        if (!groups[sec]) {
            groups[sec] = [];
        }
        groups[sec].push(record);
    });
    return groups;
});

const fetchRecords = async (page = 1) => {
    recordsLoading.value = true;

    try {
        const params = new URLSearchParams({ page: page.toString() });

        if (searchQuery.value) {
            params.append('search', searchQuery.value);
        }
        if (selectedSection.value) {
            params.append('section', selectedSection.value);
        }
        if (selectedRoom.value) {
            params.append('room', selectedRoom.value);
        }
        if (selectedDay.value) {
            params.append('day', selectedDay.value);
        }

        const response = await fetch(
            `${webRoutes.import.records.url(props.examinationSchedule.id)}?${params.toString()}`,
        );

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        records.value = await response.json();
    } catch {
        toast.error('Failed to load records.');
    } finally {
        recordsLoading.value = false;
    }
};

watch([selectedSection, selectedRoom, selectedDay], () => {
    fetchRecords(1);
});

// DataTables (Logs)
const logs = ref<any>({ data: [], current_page: 1, last_page: 1 });
const logsLoading = ref(false);
let logsInterval: any = null;

const fetchLogs = async (page = 1) => {
    logsLoading.value = true;

    try {
        const params = new URLSearchParams({ page: page.toString() });
        const response = await fetch(
            `${webRoutes.import.logs.url(props.examinationSchedule.id)}?${params.toString()}`,
        );

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        logs.value = await response.json();
    } catch {
        // ignore background poll errors
    } finally {
        logsLoading.value = false;
    }
};

onMounted(() => {
    fetchRecords();
    fetchLogs();
    // Poll logs every 5 seconds if there are pending/processing items
    logsInterval = setInterval(() => {
        if (activeTab.value === 'logs') {
            const hasProcessing = logs.value.data.some(
                (l: any) => l.status === 'processing' || l.status === 'pending',
            );

            if (hasProcessing) {
                fetchLogs(logs.value.current_page);
            }
        }
    }, 5000);
});

onUnmounted(() => {
    if (logsInterval) {
        clearInterval(logsInterval);
    }
});

const dateValue = (value: string | null | undefined) =>
    value ? value.substring(0, 10) : '';
const formatTime = (time: string) => (time ? time.substring(0, 5) : '');
</script>

<template>
    <SiteSettingsLayout>
        <Head :title="`Schedule: ${examinationSchedule.title}`" />

        <div class="flex h-full flex-col p-4 sm:p-6 lg:p-8">
            <div class="mb-6 flex items-center gap-4">
                <button
                    @click="router.visit(webRoutes.index.url())"
                    class="flex size-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-colors hover:bg-slate-50 hover:text-slate-900 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                >
                    <ChevronLeft class="size-5" />
                </button>
                <div>
                    <h1
                        class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white"
                    >
                        {{ examinationSchedule.title }}
                    </h1>
                    <div
                        class="mt-1 flex items-center gap-3 text-[13px] text-slate-500 dark:text-slate-400"
                    >
                        <span
                            >{{ examinationSchedule.academic_term.semester }} -
                            A.Y.
                            {{
                                examinationSchedule.academic_term.school_year
                            }}</span
                        >
                        <span>&bull;</span>
                        <span>{{
                            examinationSchedule.campus.campus_name
                        }}</span>
                        <span>&bull;</span>
                        <span>{{ dateValue(examinationSchedule.start_date) }} — {{ dateValue(examinationSchedule.end_date) }}</span>
                        <span>&bull;</span>
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium',
                                examinationSchedule.status === 'Published'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                        >
                            {{ examinationSchedule.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Import Section -->
            <div
                class="mb-8 rounded-2xl border border-emerald-500/20 bg-emerald-50/50 p-6 dark:border-emerald-500/10 dark:bg-emerald-500/5"
            >
                <h3
                    class="flex items-center gap-2 text-[13px] font-semibold text-emerald-900 dark:text-emerald-300"
                >
                    <FileSpreadsheet class="size-5" />
                    Import Subjects Data
                </h3>
                <p
                    class="mt-1 text-[13px] text-emerald-700/80 dark:text-emerald-400/80"
                >
                    Upload an Excel or CSV file containing the examination
                    subjects. The file must include: <code>subject_code</code>,
                    <code>section</code>, <code>room</code>,
                    <code>schedule_date</code>, <code>start_time</code>, and
                    optionally <code>end_time</code>.
                </p>

                <div
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="block w-full max-w-md text-[13px] text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-100 file:px-4 file:py-2 file:text-[13px] file:font-medium file:text-emerald-700 hover:file:bg-emerald-200 dark:text-slate-400 dark:file:bg-emerald-500/20 dark:file:text-emerald-300"
                        @change="selectImportFile"
                    />
                    <button
                        type="button"
                        :disabled="!selectedFile || previewLoading"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="previewImport"
                    >
                        <Upload class="size-4" />
                        {{ previewLoading ? 'Reading file...' : 'Preview' }}
                    </button>
                </div>

                <p v-if="importError" class="mt-3 text-sm text-rose-600">
                    {{ importError }}
                </p>

                <div
                    v-if="importPreview"
                    class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                    >
                        <div>
                            <p
                                class="text-[13px] font-semibold text-slate-900 dark:text-white"
                            >
                                Import Preview
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ importPreview.filename }} ·
                                {{ importPreview.total_rows }} rows
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-[11px]">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="size-3.5" />
                                {{ importPreview.valid_rows }} valid
                            </span>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1',
                                    importPreview.invalid_rows > 0
                                        ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                                        : 'bg-slate-50 text-slate-500 dark:bg-white/5 dark:text-slate-400',
                                ]"
                            >
                                <AlertTriangle class="size-3.5" />
                                {{ importPreview.invalid_rows }} invalid
                            </span>
                        </div>
                    </div>

                    <div class="max-h-80 overflow-auto">
                        <table class="min-w-full text-left">
                            <thead
                                class="sticky top-0 bg-slate-50 text-[11px] font-medium text-slate-500 uppercase dark:bg-slate-900"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">Row</th>
                                    <th class="px-3 py-2 font-medium">
                                        Subject
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Section
                                    </th>
                                    <th class="px-3 py-2 font-medium">Room</th>
                                    <th class="px-3 py-2 font-medium">Day</th>
                                    <th class="px-3 py-2 font-medium">Time</th>
                                    <th class="px-3 py-2 font-medium">Instructor</th>
                                    <th class="px-3 py-2 font-medium">Proctor</th>
                                    <th class="px-3 py-2 font-medium">
                                        Validation
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-[11px] dark:divide-white/5"
                            >
                                <tr
                                    v-for="row in importPreview.rows"
                                    :key="row.row"
                                    :class="
                                        row.valid
                                            ? ''
                                            : 'bg-rose-50/50 dark:bg-rose-500/5'
                                    "
                                >
                                    <td class="px-3 py-2 text-slate-400">
                                        {{ row.row }}
                                    </td>
                                    <td
                                        class="px-3 py-2 font-medium text-slate-800 dark:text-slate-200"
                                    >
                                        {{ row.subject_code || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.section || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.room || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.day || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.start_time || '—' }}
                                        <span v-if="row.end_time">
                                            – {{ row.end_time }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.instructor || '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-500">
                                        {{ row.proctor_name || '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            v-if="row.valid"
                                            class="text-emerald-600 dark:text-emerald-400"
                                        >
                                            Ready
                                        </span>
                                        <span
                                            v-else
                                            class="text-rose-600 dark:text-rose-400"
                                            :title="row.errors.join(' ')"
                                        >
                                            {{ row.errors.join(' ') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                    >
                        <p class="text-[11px] text-slate-500">
                            <span v-if="importPreview.truncated">
                                Showing the first 100 rows.
                            </span>
                            <span v-if="importPreview.invalid_rows > 0">
                                Fix invalid rows and preview the file again
                                before syncing.
                            </span>
                        </p>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-9 rounded-lg px-3 text-[11px] font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5"
                                @click="resetImport"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                :disabled="
                                    importPreview.invalid_rows > 0 ||
                                    syncLoading
                                "
                                class="h-9 rounded-lg bg-emerald-600 px-4 text-[11px] font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="syncImport"
                            >
                                {{
                                    syncLoading
                                        ? 'Syncing...'
                                        : 'Confirm and Sync'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div
                class="flex gap-4 border-b border-slate-200 dark:border-white/5"
            >
                <button
                    @click="activeTab = 'records'"
                    :class="[
                        'border-b-2 px-1 pb-4 text-[13px] font-medium transition-colors',
                        activeTab === 'records'
                            ? 'border-emerald-500 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-white/20 dark:hover:text-slate-300',
                    ]"
                >
                    Imported Records
                </button>
                <button
                    @click="activeTab = 'logs'"
                    :class="[
                        'border-b-2 px-1 pb-4 text-[13px] font-medium transition-colors',
                        activeTab === 'logs'
                            ? 'border-emerald-500 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400'
                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:border-white/20 dark:hover:text-slate-300',
                    ]"
                >
                    Import Logs
                </button>
            </div>

            <!-- Tab Content: Records -->
            <div v-show="activeTab === 'records'" class="mt-6 flex-1">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative w-full max-w-sm">
                            <input
                                v-model="searchQuery"
                                @input="fetchRecords(1)"
                                type="text"
                                placeholder="Search subjects, sections, or rooms..."
                                class="block w-full rounded-xl border-slate-300 pl-10 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-[13px] dark:border-white/10 dark:bg-slate-800 dark:text-white"
                            />
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                        </div>
                        
                        <!-- Section Filter -->
                        <div class="w-full sm:w-48">
                            <SearchableSelect
                                v-model="selectedSection"
                                :options="records.sections || []"
                                empty-text="All Sections"
                                placeholder="Search section..."
                            />
                        </div>
                        
                        <!-- Room Filter -->
                        <div class="w-full sm:w-44">
                            <SearchableSelect
                                v-model="selectedRoom"
                                :options="records.rooms || []"
                                empty-text="All Rooms"
                                placeholder="Search room..."
                            />
                        </div>
                        
                        <!-- Day Filter -->
                        <div class="w-full sm:w-40">
                            <SearchableSelect
                                v-model="selectedDay"
                                :options="records.days || []"
                                empty-text="All Days"
                                placeholder="Search day..."
                            />
                        </div>
                    </div>
                    
                    <button
                        @click="fetchRecords(records.current_page)"
                        class="self-end text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 sm:self-auto"
                    >
                        <RefreshCw
                            :class="[
                                'size-5',
                                { 'animate-spin': recordsLoading },
                            ]"
                        />
                    </button>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/5 dark:bg-slate-900/50"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-slate-200 dark:divide-white/5"
                        >
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Subject
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Section
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Room
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Day
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Time
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Instructor
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-[11px] font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        Proctor
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-200 bg-white dark:divide-white/5 dark:bg-transparent"
                            >
                                <tr
                                    v-if="
                                        recordsLoading &&
                                        records.data.length === 0
                                    "
                                >
                                    <td
                                        colspan="7"
                                        class="py-12 text-center text-[13px] text-slate-500"
                                    >
                                        Loading records...
                                    </td>
                                </tr>
                                <tr v-else-if="records.data.length === 0">
                                    <td
                                        colspan="7"
                                        class="py-12 text-center text-[13px] text-slate-500"
                                    >
                                        No records found.
                                    </td>
                                </tr>
                                <template
                                    v-for="(groupRecords, sectionName) in groupedRecords"
                                    :key="sectionName"
                                >
                                    <!-- Section Header Row -->
                                    <tr class="bg-slate-50/70 dark:bg-slate-800/40">
                                        <td
                                            colspan="7"
                                            class="px-6 py-2 text-[11px] font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase"
                                        >
                                            Section: {{ sectionName }} ({{ dateValue(examinationSchedule.start_date) }} — {{ dateValue(examinationSchedule.end_date) }})
                                        </td>
                                    </tr>
                                    <!-- Subject Rows -->
                                    <tr
                                        v-for="record in groupRecords"
                                        :key="record.id"
                                        class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                    >
                                        <td
                                            class="px-6 py-3 text-[13px] font-medium whitespace-nowrap text-slate-900 dark:text-white"
                                        >
                                            {{ record.subject_code }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ record.section || '-' }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ record.room || '-' }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ record.day || '-' }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ formatTime(record.start_time) }} -
                                            {{ formatTime(record.end_time) }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ record.instructor || '-' }}
                                        </td>
                                        <td
                                            class="px-6 py-3 text-[13px] whitespace-nowrap text-slate-500 dark:text-slate-400"
                                        >
                                            {{ record.proctor_name || '-' }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div
                        v-if="records.last_page > 1"
                        class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 dark:border-white/5 dark:bg-slate-800/50"
                    >
                        <button
                            :disabled="records.current_page === 1"
                            @click="fetchRecords(records.current_page - 1)"
                            class="text-[13px] font-medium text-emerald-600 disabled:opacity-50 dark:text-emerald-400"
                        >
                            Previous
                        </button>
                        <span class="text-[11px] font-medium text-slate-500"
                            >Page {{ records.current_page }} of
                            {{ records.last_page }}</span
                        >
                        <button
                            :disabled="
                                records.current_page === records.last_page
                            "
                            @click="fetchRecords(records.current_page + 1)"
                            class="text-[13px] font-medium text-emerald-600 disabled:opacity-50 dark:text-emerald-400"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Logs -->
            <div v-show="activeTab === 'logs'" class="mt-6 flex-1">
                <div class="mb-4 flex justify-end">
                    <button
                        @click="fetchLogs(logs.current_page)"
                        class="text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400"
                    >
                        <RefreshCw
                            :class="['size-5', { 'animate-spin': logsLoading }]"
                        />
                    </button>
                </div>

                <div class="space-y-4">
                    <div
                        v-if="logsLoading && logs.data.length === 0"
                        class="py-12 text-center text-[13px] text-slate-500"
                    >
                        Loading logs...
                    </div>
                    <div
                        v-else-if="logs.data.length === 0"
                        class="py-12 text-center text-[13px] text-slate-500"
                    >
                        No import history found.
                    </div>

                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/5 dark:bg-slate-900/50"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-white/5"
                        >
                            <div>
                                <h4
                                    class="text-[13px] font-medium text-slate-900 dark:text-white"
                                >
                                    {{ log.uploaded_filename }}
                                </h4>
                                <p class="text-[11px] text-slate-500">
                                    Uploaded by {{ log.uploader?.name }} on
                                    {{
                                        new Date(
                                            log.created_at,
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium capitalize',
                                    log.status === 'completed'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400'
                                        : log.status === 'failed'
                                          ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-400'
                                          : 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
                                ]"
                            >
                                {{ log.status }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-4 gap-4">
                            <div
                                class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800/50"
                            >
                                <p class="text-[11px] font-medium text-slate-500">
                                    Total Rows
                                </p>
                                <p
                                    class="mt-1 text-[13px] font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ log.total_rows }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800/50"
                            >
                                <p class="text-[11px] font-medium text-slate-500">
                                    Processed
                                </p>
                                <p
                                    class="mt-1 text-[13px] font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ log.processed_rows }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-emerald-50/50 p-3 text-center dark:bg-emerald-500/5"
                            >
                                <p
                                    class="text-[11px] font-medium text-emerald-700 dark:text-emerald-400"
                                >
                                    Successful
                                </p>
                                <p
                                    class="mt-1 text-[13px] font-semibold text-emerald-700 dark:text-emerald-400"
                                >
                                    {{ log.successful_rows }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-rose-50/50 p-3 text-center dark:bg-rose-500/5"
                            >
                                <p
                                    class="text-[11px] font-medium text-rose-700 dark:text-rose-400"
                                >
                                    Failed
                                </p>
                                <p
                                    class="mt-1 text-[13px] font-semibold text-rose-700 dark:text-rose-400"
                                >
                                    {{ log.failed_rows }}
                                </p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div
                            v-if="log.status === 'processing'"
                            class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="h-full bg-emerald-500 transition-all duration-500"
                                :style="{
                                    width:
                                        log.total_rows > 0
                                            ? `${(log.processed_rows / log.total_rows) * 100}%`
                                            : '0%',
                                }"
                            ></div>
                        </div>

                        <!-- Error Messages -->
                        <div
                            v-if="
                                log.error_messages &&
                                log.error_messages.length > 0
                            "
                            class="mt-4"
                        >
                            <p
                                class="mb-2 text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                            >
                                Errors (Showing first few):
                            </p>
                            <ul
                                class="max-h-32 overflow-y-auto rounded-lg bg-rose-50 p-3 text-[11px] text-rose-800 dark:bg-rose-500/10 dark:text-rose-300"
                            >
                                <li
                                    v-for="(
                                        error, i
                                    ) in log.error_messages.slice(0, 10)"
                                    :key="i"
                                    class="mb-1 last:mb-0"
                                >
                                    {{ error }}
                                </li>
                                <li
                                    v-if="log.error_messages.length > 10"
                                    class="mt-2 font-medium"
                                >
                                    ...and
                                    {{ log.error_messages.length - 10 }} more
                                    errors.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div
                        v-if="logs.last_page > 1"
                        class="mt-4 flex items-center justify-between px-2"
                    >
                        <button
                            :disabled="logs.current_page === 1"
                            @click="fetchLogs(logs.current_page - 1)"
                            class="text-[13px] font-medium text-emerald-600 disabled:opacity-50 dark:text-emerald-400"
                        >
                            Previous
                        </button>
                        <span class="text-[11px] font-medium text-slate-500"
                            >Page {{ logs.current_page }} of
                            {{ logs.last_page }}</span
                        >
                        <button
                            :disabled="logs.current_page === logs.last_page"
                            @click="fetchLogs(logs.current_page + 1)"
                            class="text-[13px] font-medium text-emerald-600 disabled:opacity-50 dark:text-emerald-400"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SiteSettingsLayout>
</template>
