<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Download,
    FileText,
    Building2,
    History,
    Calendar,
    ArrowRight,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    clearance: any;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Clearance',
                href: '/student-services/clearance/my-clearance',
            },
            { title: 'Application Details', href: '#' },
        ],
    },
});

const statusColor = (s: string) => {
    switch (s) {
        case 'cleared':
            return 'text-emerald-600 bg-emerald-50';
        case 'not_cleared':
            return 'text-red-600 bg-red-50';
        case 'with_accountability':
            return 'text-amber-600 bg-amber-50';
        default:
            return 'text-slate-600 bg-slate-50';
    }
};
</script>

<template>
    <Head title="Clearance Details" />

    <div class="flex h-full flex-1 flex-col gap-6 bg-slate-50/50 p-6">
        <!-- Compact Enterprise Header -->
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div class="flex items-center gap-4">
                <Link
                    href="/student-services/clearance/my-clearance"
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm transition-all hover:bg-slate-50 hover:text-indigo-600"
                >
                    <ChevronLeft class="h-5 w-5" />
                </Link>
                <div class="grid gap-0.5">
                    <div class="flex items-center gap-3">
                        <h1
                            class="text-lg font-black tracking-tight text-slate-900"
                        >
                            {{
                                clearance.clearance_update?.title ||
                                'Clearance Details'
                            }}
                        </h1>
                        <span
                            class="rounded-md border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[9px] font-black tracking-widest text-slate-500 uppercase"
                            >{{ clearance.semester.academic_year }}</span
                        >
                    </div>
                    <p
                        class="flex items-center gap-2 font-mono text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                    >
                        Reference Number:
                        <span
                            class="font-black tracking-normal text-indigo-500"
                            >{{ clearance.reference_no }}</span
                        >
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Button
                    v-if="clearance.status === 'cleared'"
                    class="h-10 gap-2 rounded-xl bg-indigo-600 px-5 text-xs font-bold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700"
                >
                    <Download class="h-3.5 w-3.5" />
                    Download Official Certificate
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-8">
                <!-- Compact Status Monitor -->
                <div
                    :class="[
                        'flex flex-col items-center justify-between gap-6 rounded-2xl border p-5 transition-all md:flex-row',
                        clearance.status === 'cleared'
                            ? 'border-emerald-100 bg-white shadow-xl shadow-emerald-500/5'
                            : 'border-amber-100 bg-white shadow-xl shadow-amber-500/5',
                    ]"
                >
                    <div class="flex items-center gap-5">
                        <div
                            :class="[
                                'flex h-16 w-16 items-center justify-center rounded-2xl shadow-lg',
                                clearance.status === 'cleared'
                                    ? 'bg-emerald-500 text-white shadow-emerald-500/20'
                                    : 'bg-amber-500 text-white shadow-amber-500/20',
                            ]"
                        >
                            <CheckCircle2
                                v-if="clearance.status === 'cleared'"
                                class="h-8 w-8"
                            />
                            <AlertCircle v-else class="h-8 w-8" />
                        </div>
                        <div class="grid gap-1">
                            <p
                                class="text-[9px] font-black tracking-[0.2em] text-slate-400 uppercase"
                            >
                                Application Progress
                            </p>
                            <h2
                                :class="[
                                    'text-2xl font-black tracking-tight',
                                    clearance.status === 'cleared'
                                        ? 'text-emerald-600'
                                        : 'text-amber-600',
                                ]"
                            >
                                {{
                                    clearance.status
                                        ?.replace('_', ' ')
                                        .toUpperCase() || 'PENDING'
                                }}
                            </h2>
                        </div>
                    </div>
                    <div
                        class="flex w-full gap-6 border-t border-slate-100 pt-6 md:w-auto md:border-t-0 md:border-l md:pt-0 md:pl-6"
                    >
                        <div v-if="clearance.cleared_at" class="grid gap-0">
                            <p
                                class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Date Cleared
                            </p>
                            <p class="text-[11px] font-black text-slate-700">
                                {{ clearance.cleared_at }}
                            </p>
                        </div>
                        <div class="grid gap-0">
                            <p
                                class="text-[8px] font-black tracking-widest text-slate-400 uppercase"
                            >
                                Outstanding
                            </p>
                            <p class="text-[11px] font-black text-slate-900">
                                ₱0.00
                            </p>
                        </div>
                    </div>
                </div>

                <!-- High-Density Accountabilities -->
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/50"
                >
                    <div
                        class="border-b border-slate-100 bg-slate-50/50 px-6 py-4"
                    >
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-[10px] font-black tracking-widest text-slate-900 uppercase"
                            >
                                Active Requirements
                            </h3>
                            <span
                                class="text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                                >Live Sync</span
                            >
                        </div>
                    </div>

                    <div
                        v-if="
                            !clearance.clearance_update?.accountabilities ||
                            clearance.clearance_update.accountabilities
                                .length === 0
                        "
                        class="py-12 text-center"
                    >
                        <p class="text-[11px] font-bold text-slate-400">
                            No active requirements
                        </p>
                    </div>
                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="acc in clearance.clearance_update
                                .accountabilities?.data ||
                            clearance.clearance_update.accountabilities"
                            :key="acc.id"
                            class="group p-6 transition-colors hover:bg-slate-50/50"
                        >
                            <div
                                class="flex flex-col justify-between gap-4 md:flex-row md:items-start"
                            >
                                <div class="flex gap-4">
                                    <div
                                        class="mt-1 flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-red-100 bg-red-50 text-red-500 shadow-sm transition-transform group-hover:scale-105"
                                    >
                                        <AlertCircle class="h-6 w-6" />
                                    </div>
                                    <div class="grid gap-1">
                                        <h4
                                            class="text-sm font-black tracking-tight text-slate-900"
                                        >
                                            {{ acc.title }}
                                        </h4>
                                        <div
                                            class="flex flex-wrap items-center gap-3"
                                        >
                                            <div
                                                class="flex items-center gap-2 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                            >
                                                <div
                                                    class="h-1.5 w-1.5 rounded-full bg-indigo-500"
                                                ></div>
                                                {{
                                                    acc.office?.name ||
                                                    'Academic Office'
                                                }}
                                            </div>
                                            <div
                                                v-if="acc.amount"
                                                class="rounded-md border border-indigo-100 bg-indigo-50/50 px-2 py-0.5 font-mono text-[9px] font-bold text-indigo-600"
                                            >
                                                ₱{{
                                                    Number(
                                                        acc.amount,
                                                    ).toLocaleString(
                                                        undefined,
                                                        {
                                                            minimumFractionDigits: 2,
                                                        },
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <span
                                    :class="[
                                        'self-start rounded-full border px-3 py-1 text-[9px] font-black tracking-widest whitespace-nowrap uppercase shadow-sm md:self-center',
                                        acc.status === 'pending'
                                            ? 'border-amber-100 bg-amber-50 text-amber-600'
                                            : 'border-emerald-100 bg-emerald-50 text-emerald-600',
                                    ]"
                                >
                                    {{ acc.status }}
                                </span>
                            </div>

                            <!-- Enterprise Sub-items Breakdown -->
                            <div
                                v-if="
                                    acc.children?.length > 0 ||
                                    acc.children?.data?.length > 0
                                "
                                class="mt-5 ml-16"
                            >
                                <div class="mb-4 flex items-center gap-4">
                                    <span
                                        class="rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black tracking-[0.2em] text-slate-400 uppercase"
                                        >Required Sub-items</span
                                    >
                                    <div class="h-px flex-1 bg-slate-100"></div>
                                </div>
                                <div class="grid gap-2">
                                    <div
                                        v-for="child in acc.children?.data ||
                                        acc.children"
                                        :key="child.id"
                                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-lg border border-slate-200 bg-white text-[8px] font-black text-slate-400"
                                            >
                                                {{ child.id }}
                                            </div>
                                            <span
                                                class="text-[11px] font-bold tracking-tight text-slate-700"
                                                >{{ child.title }}</span
                                            >
                                        </div>
                                        <div
                                            :class="[
                                                'h-1.5 w-1.5 rounded-full',
                                                child.status === 'pending'
                                                    ? 'bg-amber-400'
                                                    : 'bg-emerald-400',
                                            ]"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Sidebar -->
            <div class="space-y-6 lg:col-span-4">
                <!-- Live Timeline -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/50"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h3
                            class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                        >
                            Activity Log
                        </h3>
                        <History class="h-3.5 w-3.5 text-slate-300" />
                    </div>
                    <div
                        class="relative space-y-6 before:absolute before:top-2 before:bottom-2 before:left-2 before:w-px before:bg-slate-100"
                    >
                        <div
                            v-for="log in clearance.logs"
                            :key="log.id"
                            class="relative pl-6"
                        >
                            <div
                                class="absolute top-1 left-0 h-3 w-3 rounded-full border-2 border-white bg-indigo-500 shadow-sm"
                            ></div>
                            <div class="grid gap-0.5">
                                <p
                                    class="text-[9px] font-black tracking-widest text-slate-900 uppercase"
                                >
                                    {{ log.action?.replace('_', ' ') }}
                                </p>
                                <p
                                    class="text-[10px] leading-tight text-slate-500"
                                >
                                    {{ log.remarks }}
                                </p>
                                <p
                                    class="mt-0.5 text-[8px] font-bold tracking-tighter text-slate-400 uppercase"
                                >
                                    {{ log.created_at }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Office Verification Network -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/50"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h3
                            class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase"
                        >
                            Office Verification
                        </h3>
                        <Building2 class="h-3.5 w-3.5 text-slate-300" />
                    </div>
                    <div class="grid gap-3">
                        <div
                            v-for="off in clearance.clearance_update?.offices"
                            :key="off.id"
                            class="group flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 transition-all hover:border-emerald-200 hover:bg-white hover:shadow-lg hover:shadow-emerald-500/5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white shadow-sm"
                                >
                                    <Building2
                                        class="h-3.5 w-3.5 text-slate-400 transition-colors group-hover:text-emerald-500"
                                    />
                                </div>
                                <span
                                    class="text-[10px] font-black tracking-tight text-slate-700"
                                    >{{
                                        off.office?.name || 'Academic Unit'
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 shadow-md shadow-emerald-500/20"
                            >
                                <CheckCircle2 class="h-3 w-3 text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
