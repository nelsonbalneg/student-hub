<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import dossierRoutes from '@/routes/file-vault/dossiers';
import * as documentRoutes from '@/routes/file-vault/documents';

type OwnerOption = {
    id: number;
    name: string;
};

type DossierDocument = {
    id: number;
    document_type: string;
    document_code: string | null;
    version: number;
    is_required: boolean;
    is_verified: boolean;
    original_filename: string;
    mime_type: string;
    file_size: number;
    scan_status: string;
    scanned_at: string | null;
    scan_message: string | null;
    uploaded_by: number;
    verified_by: number | null;
    verified_at: string | null;
    created_at: string | null;
};

type DossierStatusHistory = {
    id: number;
    from_status: string | null;
    to_status: string;
    remarks: string | null;
    changed_at: string | null;
    changer: {
        id: number;
        name: string;
    } | null;
};

type DossierAssignment = {
    id: number;
    reason: string | null;
    assigned_at: string | null;
    assignee: {
        id: number;
        name: string;
    } | null;
    assigner: {
        id: number;
        name: string;
    } | null;
};

type Dossier = {
    id: number;
    dossier_number: string;
    transaction_type: string;
    status: string;
    priority: string;
    current_owner_id: number | null;
    intake_date: string | null;
    completion_due_at: string | null;
    released_at: string | null;
    archived_at: string | null;
    approved_at: string | null;
    approval_remarks: string | null;
    approver: {
        id: number;
        name: string;
    } | null;
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
    documents: DossierDocument[];
    status_histories: DossierStatusHistory[];
    assignments: DossierAssignment[];
    checklist: {
        total_required: number;
        verified_required: number;
        is_complete: boolean;
        items: Array<{
            document_type: string;
            is_uploaded: boolean;
            is_verified: boolean;
        }>;
    };
};

const props = defineProps<{
    dossier: Dossier;
    statuses: string[];
    priorities: string[];
    transitionOptions: string[];
    owners: OwnerOption[];
    requiredDocumentTypes: string[];
    can: {
        update: boolean;
        assign: boolean;
        transition: boolean;
        archive: boolean;
        release: boolean;
        verifyDocument: boolean;
        uploadDocument: boolean;
        downloadDocument: boolean;
        approve: boolean;
    };
}>();

const updateForm = useForm({
    transaction_type: props.dossier.transaction_type,
    priority: props.dossier.priority,
    current_owner_id: props.dossier.current_owner_id ? String(props.dossier.current_owner_id) : '',
    intake_date: props.dossier.intake_date ?? '',
    completion_due_at: props.dossier.completion_due_at ? props.dossier.completion_due_at.slice(0, 16) : '',
});

const assignForm = useForm({
    assigned_to: props.dossier.current_owner_id ? String(props.dossier.current_owner_id) : '',
    reason: '',
});

const transitionForm = useForm({
    status: props.transitionOptions[0] ?? '',
    remarks: '',
    recipient_or_requestor: '',
    legal_basis: '',
    legitimate_interest: '',
});

const uploadForm = useForm({
    document_type: '',
    document_code: '',
    is_required: false,
    file: null as File | null,
});

const archiveForm = useForm({
    remarks: '',
});

const approveForm = useForm({
    remarks: '',
});

const isReleaseTransition = computed(() => transitionForm.status === 'released');

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

const saveMetadata = () => {
    updateForm
        .transform((data) => ({
            ...data,
            current_owner_id: data.current_owner_id ? Number(data.current_owner_id) : null,
            completion_due_at: data.completion_due_at || null,
            intake_date: data.intake_date || null,
        }))
        .patch(dossierRoutes.update.url(props.dossier.id), {
            preserveScroll: true,
        });
};

const assignOwner = () => {
    assignForm
        .transform((data) => ({
            ...data,
            assigned_to: Number(data.assigned_to),
        }))
        .post(dossierRoutes.assign.url(props.dossier.id), {
            preserveScroll: true,
            onSuccess: () => assignForm.reset('reason'),
        });
};

const transitionStatus = () => {
    transitionForm.patch(dossierRoutes.status.url(props.dossier.id), {
        preserveScroll: true,
        onSuccess: () => {
            transitionForm.reset('remarks', 'recipient_or_requestor', 'legal_basis', 'legitimate_interest');
        },
    });
};

const archiveDossier = () => {
    archiveForm.post(dossierRoutes.archive.url(props.dossier.id), {
        preserveScroll: true,
    });
};

const approveDossier = () => {
    approveForm.post(`/student-services/file-vault/dossiers/${props.dossier.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => approveForm.reset(),
    });
};

const uploadDocument = () => {
    uploadForm.post(dossierRoutes.documents.store.url(props.dossier.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => uploadForm.reset(),
    });
};

const verifyDocument = (document: DossierDocument, isVerified: boolean) => {
    router.patch(
        documentRoutes.verify.url(document.id),
        { is_verified: isVerified },
        { preserveScroll: true },
    );
};

const retryScan = (document: DossierDocument) => {
    router.post(documentRoutes.retryScan.url(document.id), {}, { preserveScroll: true });
};

const canVerifyDocument = (document: DossierDocument) => document.scan_status === 'clean';

const deleteDocument = (document: DossierDocument) => {
    router.delete(documentRoutes.destroy.url(document.id), {
        preserveScroll: true,
    });
};

const downloadDocument = async (document: DossierDocument) => {
    const response = await fetch(documentRoutes.download.url(document.id), {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        return;
    }

    const payload = (await response.json()) as { url?: string };
    if (!payload.url) {
        return;
    }

    window.open(payload.url, '_blank', 'noopener,noreferrer');
};

const scanSummary = computed(() => ({
    pending: props.dossier.documents.filter((document) => document.scan_status === 'pending').length,
    clean: props.dossier.documents.filter((document) => document.scan_status === 'clean').length,
    flagged: props.dossier.documents.filter((document) => ['failed', 'infected'].includes(document.scan_status)).length,
}));
</script>

<template>
    <Head :title="`Dossier ${dossier.dossier_number}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-slate-50/60 p-4 dark:bg-slate-950 lg:p-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">File Vault</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ dossier.dossier_number }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ dossier.transaction_type }}</p>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300">
                        {{ dossier.student?.name ?? '-' }} ({{ dossier.student?.student_no ?? '-' }})
                    </p>
                </div>
                <Link
                    :href="dossierRoutes.index.url()"
                    class="inline-flex h-10 items-center rounded-md border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                >
                    Back to Queue
                </Link>
                <Link
                    :href="dossierRoutes.auditLogs.url(dossier.id)"
                    class="inline-flex h-10 items-center rounded-md border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/10"
                >
                    View Audit Logs
                </Link>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <div class="space-y-5 xl:col-span-7">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Checklist Workflow</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Required documents must be verified before supervisor approval and release.
                    </p>
                    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-white/10 dark:bg-slate-900">
                        <p>
                            Required verified:
                            <strong>{{ dossier.checklist.verified_required }}</strong>
                            /
                            <strong>{{ dossier.checklist.total_required }}</strong>
                        </p>
                        <p class="mt-1">
                            Checklist complete:
                            <strong>{{ dossier.checklist.is_complete ? 'Yes' : 'No' }}</strong>
                        </p>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="item in dossier.checklist.items"
                            :key="item.document_type"
                            class="rounded-md border border-slate-200 px-4 py-3 dark:border-white/10"
                        >
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ item.document_type }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ item.is_uploaded ? 'Uploaded' : 'Missing upload' }}
                                    </p>
                                </div>
                            </div>
                            <p class="mt-1 text-xs" :class="item.is_verified ? 'text-emerald-600' : 'text-amber-600'">
                                {{ item.is_verified ? 'Verified' : 'Pending verification' }}
                            </p>
                        </div>
                        <p v-if="dossier.checklist.items.length === 0" class="text-sm text-slate-500">
                            No required checklist items yet. Upload required documents below.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Documents</h2>
                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs lg:grid-cols-3">
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            Pending scans: <strong>{{ scanSummary.pending }}</strong>
                        </div>
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                            Clean scans: <strong>{{ scanSummary.clean }}</strong>
                        </div>
                        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                            Flagged/failed: <strong>{{ scanSummary.flagged }}</strong>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-2 py-2">Type</th>
                                    <th class="px-2 py-2">Filename</th>
                                    <th class="px-2 py-2">Required</th>
                                    <th class="px-2 py-2">Verified</th>
                                    <th class="px-2 py-2">Scan</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="document in dossier.documents" :key="document.id" class="border-t border-slate-100 dark:border-white/10">
                                    <td class="px-2 py-2">{{ document.document_type }}</td>
                                    <td class="px-2 py-2">{{ document.original_filename }}</td>
                                    <td class="px-2 py-2">{{ document.is_required ? 'Yes' : 'No' }}</td>
                                    <td class="px-2 py-2">{{ document.is_verified ? 'Yes' : 'No' }}</td>
                                    <td class="px-2 py-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                document.scan_status === 'clean'
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200'
                                                    : document.scan_status === 'pending'
                                                      ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200'
                                                      : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200'
                                            "
                                        >
                                            {{ document.scan_status }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                v-if="can.downloadDocument"
                                                size="sm"
                                                variant="outline"
                                                @click="downloadDocument(document)"
                                            >
                                                Download
                                            </Button>
                                            <Button
                                                v-if="can.verifyDocument"
                                                size="sm"
                                                variant="outline"
                                                :disabled="!canVerifyDocument(document)"
                                                @click="verifyDocument(document, !document.is_verified)"
                                            >
                                                {{ document.is_verified ? 'Unverify' : 'Verify' }}
                                            </Button>
                                            <Button
                                                v-if="can.verifyDocument && document.scan_status !== 'clean'"
                                                size="sm"
                                                variant="outline"
                                                @click="retryScan(document)"
                                            >
                                                Retry Scan
                                            </Button>
                                            <Button
                                                v-if="can.uploadDocument"
                                                size="sm"
                                                variant="destructive"
                                                @click="deleteDocument(document)"
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="can.uploadDocument" class="mt-5 grid grid-cols-1 gap-3 rounded-md border border-slate-200 p-4 dark:border-white/10 lg:grid-cols-2">
                        <div>
                            <Label for="document_type">Document Type</Label>
                            <Input id="document_type" v-model="uploadForm.document_type" class="mt-1.5" />
                            <InputError :message="uploadForm.errors.document_type" />
                        </div>
                        <div>
                            <Label for="document_code">Document Code</Label>
                            <Input id="document_code" v-model="uploadForm.document_code" class="mt-1.5" />
                            <InputError :message="uploadForm.errors.document_code" />
                        </div>
                        <div>
                            <Label for="document_file">File</Label>
                            <Input
                                id="document_file"
                                type="file"
                                class="mt-1.5"
                                @input="uploadForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <InputError :message="uploadForm.errors.file" />
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="uploadForm.is_required" type="checkbox" />
                                Required checklist item
                            </label>
                        </div>
                        <div class="lg:col-span-2">
                            <Button :disabled="uploadForm.processing" @click="uploadDocument">Upload Document</Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-5 xl:col-span-5">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Metadata</h2>
                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <div>
                            <Label for="transaction_type">Transaction Type</Label>
                            <Input id="transaction_type" v-model="updateForm.transaction_type" class="mt-1.5" />
                            <InputError :message="updateForm.errors.transaction_type" />
                        </div>
                        <div>
                            <Label for="priority">Priority</Label>
                            <select
                                id="priority"
                                v-model="updateForm.priority"
                                class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <option v-for="priority in priorities" :key="priority" :value="priority">{{ priority }}</option>
                            </select>
                            <InputError :message="updateForm.errors.priority" />
                        </div>
                        <div>
                            <Label for="owner">Owner</Label>
                            <select
                                id="owner"
                                v-model="updateForm.current_owner_id"
                                class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <option value="">Unassigned</option>
                                <option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">{{ owner.name }}</option>
                            </select>
                            <InputError :message="updateForm.errors.current_owner_id" />
                        </div>
                        <div>
                            <Label for="completion_due_at">Completion Due</Label>
                            <Input id="completion_due_at" v-model="updateForm.completion_due_at" type="datetime-local" class="mt-1.5" />
                            <InputError :message="updateForm.errors.completion_due_at" />
                        </div>
                        <div>
                            <Button v-if="can.update" :disabled="updateForm.processing" @click="saveMetadata">Save Metadata</Button>
                        </div>
                    </div>
                </div>

                <div v-if="can.assign" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Assignment</h2>
                    <div class="mt-4 space-y-3">
                        <div>
                            <Label for="assigned_to">Assign To</Label>
                            <select
                                id="assigned_to"
                                v-model="assignForm.assigned_to"
                                class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <option value="">Select owner</option>
                                <option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">{{ owner.name }}</option>
                            </select>
                            <InputError :message="assignForm.errors.assigned_to" />
                        </div>
                        <div>
                            <Label for="assign_reason">Reason</Label>
                            <Input id="assign_reason" v-model="assignForm.reason" class="mt-1.5" />
                            <InputError :message="assignForm.errors.reason" />
                        </div>
                        <Button :disabled="assignForm.processing" @click="assignOwner">Assign</Button>
                    </div>
                </div>

                <div v-if="can.transition" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Transition</h2>
                    <div class="mt-4 space-y-3">
                        <div>
                            <Label for="transition_status">Next Status</Label>
                            <select
                                id="transition_status"
                                v-model="transitionForm.status"
                                class="mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-slate-900"
                            >
                                <option value="" disabled>Select transition</option>
                                <option v-for="statusItem in transitionOptions" :key="statusItem" :value="statusItem">
                                    {{ statusItem }}
                                </option>
                            </select>
                            <InputError :message="transitionForm.errors.status" />
                        </div>
                        <div>
                            <Label for="transition_remarks">Remarks</Label>
                            <textarea
                                id="transition_remarks"
                                v-model="transitionForm.remarks"
                                class="mt-1.5 min-h-20 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-slate-900"
                                placeholder="Provide transition notes"
                            />
                            <InputError :message="transitionForm.errors.remarks" />
                        </div>

                        <template v-if="isReleaseTransition">
                            <div>
                                <Label for="recipient_or_requestor">Recipient or Requestor</Label>
                                <Input id="recipient_or_requestor" v-model="transitionForm.recipient_or_requestor" class="mt-1.5" />
                                <InputError :message="transitionForm.errors.recipient_or_requestor" />
                            </div>
                            <div>
                                <Label for="legal_basis">Legal Basis</Label>
                                <Input id="legal_basis" v-model="transitionForm.legal_basis" class="mt-1.5" />
                                <InputError :message="transitionForm.errors.legal_basis" />
                            </div>
                            <div>
                                <Label for="legitimate_interest">Legitimate Interest</Label>
                                <Input id="legitimate_interest" v-model="transitionForm.legitimate_interest" class="mt-1.5" />
                                <InputError :message="transitionForm.errors.legitimate_interest" />
                            </div>
                        </template>

                        <Button :disabled="transitionForm.processing || !transitionForm.status" @click="transitionStatus">
                            Apply Transition
                        </Button>
                    </div>
                </div>

                <div
                    v-if="can.approve && dossier.status === 'for_supervisor_approval' && !dossier.approved_at"
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-500/30 dark:bg-indigo-500/10"
                >
                    <h2 class="text-lg font-semibold text-indigo-700 dark:text-indigo-200">Supervisor Approval</h2>
                    <p class="mt-1 text-sm text-indigo-700/80 dark:text-indigo-200/80">
                        Approval is required before release. Add approval notes below.
                    </p>
                    <div class="mt-3">
                        <Label for="approve_remarks">Approval Remarks</Label>
                        <textarea
                            id="approve_remarks"
                            v-model="approveForm.remarks"
                            class="mt-1.5 min-h-20 w-full rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm dark:border-indigo-400/40 dark:bg-slate-900"
                        />
                        <InputError :message="approveForm.errors.remarks" />
                    </div>
                    <Button class="mt-3" :disabled="approveForm.processing" @click="approveDossier">Approve Dossier</Button>
                </div>

                <div v-if="dossier.approved_at" class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <h2 class="text-lg font-semibold text-emerald-700 dark:text-emerald-200">Approved</h2>
                    <p class="mt-1 text-sm text-emerald-700/80 dark:text-emerald-200/80">
                        {{ formatDateTime(dossier.approved_at) }} by {{ dossier.approver?.name ?? 'Unknown' }}
                    </p>
                    <p v-if="dossier.approval_remarks" class="mt-2 text-sm text-emerald-700/90 dark:text-emerald-200/90">
                        {{ dossier.approval_remarks }}
                    </p>
                </div>

                <div v-if="can.archive && dossier.status === 'released'" class="rounded-lg border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-500/30 dark:bg-red-500/10">
                    <h2 class="text-lg font-semibold text-red-700 dark:text-red-300">Archive Dossier</h2>
                    <p class="mt-1 text-sm text-red-700/80 dark:text-red-300/80">
                        Archiving is irreversible in normal workflow. Remarks are required.
                    </p>
                    <div class="mt-3">
                        <Label for="archive_remarks">Archive Remarks</Label>
                        <textarea
                            id="archive_remarks"
                            v-model="archiveForm.remarks"
                            class="mt-1.5 min-h-20 w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm dark:border-red-400/40 dark:bg-slate-900"
                        />
                        <InputError :message="archiveForm.errors.remarks" />
                    </div>
                    <Button class="mt-3" variant="destructive" :disabled="archiveForm.processing" @click="archiveDossier">
                        Archive
                    </Button>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Status History</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="entry in dossier.status_histories" :key="entry.id" class="rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-white/10">
                            <p class="font-semibold text-slate-900 dark:text-white">
                                {{ entry.from_status ?? 'none' }} -> {{ entry.to_status }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ formatDateTime(entry.changed_at) }} by {{ entry.changer?.name ?? 'System' }}
                            </p>
                            <p v-if="entry.remarks" class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ entry.remarks }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
