<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

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

const valueOrDash = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
};

const accountType = computed(() => user.value.sso_account_type ?? user.value.user_type);

const isEmployeeAccount = computed(() =>
    ['employee', 'faculty', 'staff', 'admin'].some((type) =>
        String(accountType.value ?? '').toLowerCase().includes(type),
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
            label: 'Tenant ID',
            value: user.value.tenant_id,
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

const initials = computed(() =>
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
                        class="inline-flex w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        Active Account
                    </span>
                </div>
            </div>

            <div class="grid gap-0 md:grid-cols-2">
                <div class="border-b border-slate-100 px-5 py-4 md:border-r dark:border-white/10">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        Full Name
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.name) }}
                    </p>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        Email Address
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.email) }}
                    </p>
                </div>
                <div class="border-b border-slate-100 px-5 py-4 md:border-r md:border-b-0 dark:border-white/10">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        Account Type
                    </p>
                    <p
                        class="mt-1 truncate text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ valueOrDash(user.sso_account_type ?? user.user_type) }}
                    </p>
                </div>
                <div class="px-5 py-4">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
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
            <div class="border-b border-slate-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-sm font-bold text-slate-950 dark:text-white">
                    Account details
                </h2>
                <p class="mt-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                    Important profile information synced from StudentHub and SSO.
                </p>
            </div>

            <dl class="grid gap-0 sm:grid-cols-2">
                <div
                    v-for="detail in accountDetails"
                    :key="detail.label"
                    class="border-b border-slate-100 px-4 py-3 last:border-b-0 odd:sm:border-r sm:[&:nth-last-child(-n+2)]:border-b-0 dark:border-white/10"
                >
                    <dt
                        class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"
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
