<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Loader2, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit } from '@/routes/profile';
import { assign as assignCampus } from '@/routes/profile/campus';
import { send } from '@/routes/verification';

type Campus = {
    record_id: number;
    name: string;
    tenant_id: number;
    campus_id: number;
};

type Office = {
    id: number;
    name: string;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    requiresCampusSelection: boolean;
    campuses: Campus[];
    officesByCampus: Record<number, Office[]>;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const campusForm = useForm({
    campus_record_id: null as number | null,
    office_id: null as number | null,
});

const selectedCampus = computed(
    () =>
        props.campuses.find(
            (campus) => campus.record_id === campusForm.campus_record_id,
        ) ?? null,
);

const availableOffices = computed<Office[]>(() => {
    if (!campusForm.campus_record_id || !props.officesByCampus) {
        return [];
    }
    return props.officesByCampus[campusForm.campus_record_id] ?? [];
});

const selectedOffice = computed(
    () =>
        availableOffices.value.find(
            (office) => office.id === campusForm.office_id,
        ) ?? null,
);

const selectCampusValue = computed({
    get: () => campusForm.campus_record_id !== null ? String(campusForm.campus_record_id) : undefined,
    set: (val) => {
        campusForm.campus_record_id = val ? Number(val) : null;
        campusForm.office_id = null;
        campusForm.clearErrors('campus_record_id');
        campusForm.clearErrors('office_id');
    }
});

const selectOfficeValue = computed({
    get: () => campusForm.office_id !== null ? String(campusForm.office_id) : undefined,
    set: (val) => {
        campusForm.office_id = val ? Number(val) : null;
        campusForm.clearErrors('office_id');
    }
});

const saveCampus = () => {
    let hasError = false;

    if (!campusForm.campus_record_id) {
        campusForm.setError('campus_record_id', 'Please select your campus.');
        hasError = true;
    }
    if (!campusForm.office_id) {
        campusForm.setError('office_id', 'Please select your office.');
        hasError = true;
    }

    if (hasError) {
        return;
    }

    campusForm.patch(assignCampus.url(), {
        preserveScroll: true,
    });
};

const valueOrDash = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
};

const accountType = computed(
    () => user.value.sso_account_type ?? user.value.user_type,
);

const isEmployeeAccount = computed(() =>
    ['employee', 'faculty', 'staff', 'admin'].some((type) =>
        String(accountType.value ?? '')
            .toLowerCase()
            .includes(type),
    ),
);

const accountDetails = computed(() =>
    [
        {
            label: 'Student Number',
            value: user.value.student_no,
            show: true,
        },
        {
            label: 'Employee Number',
            value: user.value.employee_no,
            show: isEmployeeAccount.value && Boolean(user.value.employee_no),
        },
        {
            label: 'Account Type',
            value: accountType.value,
            show: true,
        },
        {
            label: 'Campus',
            value: user.value.campus_name,
            show: true,
        },
        {
            label: 'Department',
            value: user.value.department,
            show: true,
        },
        {
            label: 'Office',
            value: user.value.office,
            show: true,
        },
        {
            label: 'SSO Username',
            value: user.value.sso_username,
            show: true,
        },
    ].filter((detail) => detail.show),
);

const initials = computed(
    () =>
        String(user.value.name ?? '')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') || 'U',
);
</script>

<template>
    <Head title="Profile settings" />

    <Dialog :open="requiresCampusSelection">
        <DialogContent
            :show-close-button="false"
            class="max-h-[92vh] overflow-hidden border-slate-200 p-0 shadow-2xl sm:max-w-xl dark:border-white/10"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
        >
            <DialogHeader class="border-b border-slate-200 px-6 py-5 text-left dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400"
                    >
                        <Building2 class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold text-slate-900 dark:text-white">
                            Select your campus
                        </DialogTitle>
                    </div>
                </div>
                <DialogDescription class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Your account is missing campus information. Choose your official campus to continue.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[60vh] overflow-y-auto px-6 py-6 space-y-4">
                <div v-if="campuses.length" class="space-y-4">
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Campus
                        </Label>
                        <Select v-model="selectCampusValue">
                            <SelectTrigger class="w-full border-slate-200 bg-white hover:bg-slate-50/50 dark:border-white/10 dark:bg-slate-950">
                                <SelectValue placeholder="Choose your campus..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-[300px] overflow-y-auto">
                                <SelectItem
                                    v-for="campus in campuses"
                                    :key="campus.record_id"
                                    :value="String(campus.record_id)"
                                    class="cursor-pointer py-2.5"
                                >
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="campusForm.errors.campus_record_id"
                            class="text-xs font-semibold text-red-600 dark:text-red-400"
                        >
                            {{ campusForm.errors.campus_record_id }}
                        </p>
                    </div>

                    <div v-if="campusForm.campus_record_id" class="space-y-2 animate-in fade-in slide-in-from-top-1 duration-200">
                        <Label class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                            Office
                        </Label>
                        <Select v-if="availableOffices.length" v-model="selectOfficeValue">
                            <SelectTrigger class="w-full border-slate-200 bg-white hover:bg-slate-50/50 dark:border-white/10 dark:bg-slate-950">
                                <SelectValue placeholder="Choose your office..." />
                            </SelectTrigger>
                            <SelectContent class="max-h-[300px] overflow-y-auto">
                                <SelectItem
                                    v-for="office in availableOffices"
                                    :key="office.id"
                                    :value="String(office.id)"
                                    class="cursor-pointer py-2.5"
                                >
                                    {{ office.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div
                            v-else
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            No offices available for the selected campus. Please contact the administrator.
                        </div>
                        <p
                            v-if="campusForm.errors.office_id"
                            class="text-xs font-semibold text-red-600 dark:text-red-400"
                        >
                            {{ campusForm.errors.office_id }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    Campus options are temporarily unavailable. Please refresh
                    the page or contact the system administrator.
                </div>
            </div>

            <DialogFooter
                class="border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-white/10 dark:bg-slate-900"
            >
                <div class="flex w-full flex-col gap-2">
                    <p
                        v-if="selectedCampus"
                        class="text-center text-xs text-slate-500 dark:text-slate-400"
                    >
                        Selected: {{ selectedCampus.name }}<span v-if="selectedOffice"> — {{ selectedOffice.name }}</span>
                    </p>
                    <Button
                        type="button"
                        class="w-full bg-emerald-600 text-white hover:bg-emerald-700"
                        :disabled="
                            !campusForm.campus_record_id ||
                            !campusForm.office_id ||
                            campusForm.processing ||
                            campuses.length === 0
                        "
                        @click="saveCampus"
                    >
                        <Loader2
                            v-if="campusForm.processing"
                            class="mr-2 size-4 animate-spin"
                        />
                        <Building2 v-else class="mr-2 size-4" />
                        {{
                            campusForm.processing
                                ? 'Saving campus...'
                                : 'Save campus and continue'
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Profile"
            description="Review your official StudentHub identity and account details"
        />

        <section
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="border-b border-slate-200 bg-slate-50 px-5 py-5 dark:border-white/10 dark:bg-white/[0.03]"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div
                        class="flex size-16 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-xl font-bold text-white dark:bg-white dark:text-slate-950"
                    >
                        {{ initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2
                            class="truncate text-xl font-bold text-slate-950 dark:text-white"
                        >
                            {{ user.name }}
                        </h2>
                        <p
                            class="mt-1 truncate text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ user.email }}
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 uppercase dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        Active Account
                    </span>
                </div>
            </div>

            <div class="grid gap-0 md:grid-cols-2">
                <div
                    class="border-b border-slate-100 px-5 py-4 md:border-r dark:border-white/10"
                >
                    <p
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Full Name
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.name) }}
                    </p>
                </div>
                <div
                    class="border-b border-slate-100 px-5 py-4 dark:border-white/10"
                >
                    <p
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Email Address
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.email) }}
                    </p>
                </div>
                <div
                    class="border-b border-slate-100 px-5 py-4 md:border-r md:border-b-0 dark:border-white/10"
                >
                    <p
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Account Type
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{
                            valueOrDash(user.sso_account_type ?? user.user_type)
                        }}
                    </p>
                </div>
                <div class="px-5 py-4">
                    <p
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        Campus
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.campus_name) }}
                    </p>
                </div>
            </div>
        </section>

        <div v-if="mustVerifyEmail && !user.email_verified_at">
            <p class="text-sm text-muted-foreground">
                Your email address is unverified.
                <Link
                    :href="send()"
                    as="button"
                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >
                    Click here to resend the verification email.
                </Link>
            </p>

            <div
                v-if="status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                A new verification link has been sent to your email address.
            </div>
        </div>

        <section
            class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="border-b border-slate-200 px-4 py-3 dark:border-white/10"
            >
                <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                    Account details
                </h2>
                <p
                    class="mt-0.5 text-xs font-medium text-slate-500 dark:text-slate-400"
                >
                    Important profile information synced from StudentHub and
                    SSO.
                </p>
            </div>

            <dl class="grid gap-0 sm:grid-cols-2">
                <div
                    v-for="detail in accountDetails"
                    :key="detail.label"
                    class="border-b border-slate-100 px-4 py-3 last:border-b-0 odd:sm:border-r dark:border-white/10 sm:[&:nth-last-child(-n+2)]:border-b-0"
                >
                    <dt
                        class="text-[11px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ detail.label }}
                    </dt>
                    <dd
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(detail.value) }}
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
