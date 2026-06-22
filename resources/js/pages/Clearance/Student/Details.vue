<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    CheckCircle2,
    AlertCircle,
    Download,
    Building2,
    Clock3,
    ChevronDown,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

const accountabilities = computed(
    () =>
        props.clearance.clearance_update?.accountabilities?.data ||
        props.clearance.clearance_update?.accountabilities ||
        [],
);

const officeRows = computed(() =>
    (props.clearance.clearance_update?.offices || []).map((office: any) => {
        const deficiencies = accountabilities.value.filter(
            (item: any) =>
                item.office?.id === office.office?.id &&
                item.status === 'pending',
        );

        return {
            ...office,
            deficiencies,
            status:
                deficiencies.length > 0
                    ? 'deficiencies'
                    : office.finalized_at
                      ? 'verified'
                      : 'processing',
        };
    }),
);

const expandedOffices = ref<number[]>([]);

const toggleOffice = (officeId: number) => {
    expandedOffices.value = expandedOffices.value.includes(officeId)
        ? expandedOffices.value.filter((id) => id !== officeId)
        : [...expandedOffices.value, officeId];
};
</script>

<template>
    <Head title="Clearance Details" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-emerald-50/30 p-4 sm:p-6">
        <!-- Compact Enterprise Header -->
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-center"
        >
            <div
                class="flex min-w-0 items-start gap-3 sm:items-center sm:gap-4"
            >
                <Link
                    href="/student-services/clearance/my-clearance"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-white text-slate-400 shadow-sm transition-all hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                >
                    <ChevronLeft class="h-5 w-5" />
                </Link>
                <div class="grid min-w-0 flex-1 gap-1">
                    <div class="flex min-w-0 items-start justify-between gap-2">
                        <h1
                            class="min-w-0 text-base leading-snug font-semibold tracking-tight text-slate-900 sm:text-lg"
                        >
                            {{
                                clearance.clearance_update?.title ||
                                'Clearance Details'
                            }}
                        </h1>
                        <span
                            class="shrink-0 rounded-md border border-emerald-100 bg-emerald-50 px-2 py-1 text-[8px] font-medium tracking-wider text-emerald-700 uppercase sm:text-[9px]"
                            >{{ clearance.semester.academic_year }}</span
                        >
                    </div>
                    <p
                        class="flex min-w-0 flex-wrap items-center gap-x-2 font-mono text-[8px] font-medium tracking-wider text-slate-400 uppercase sm:text-[9px]"
                    >
                        <span>Reference Number:</span>
                        <span
                            class="font-semibold tracking-normal text-emerald-600"
                            >{{ clearance.reference_no }}</span
                        >
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 pl-13 sm:pl-14 md:pl-0">
                <Button
                    v-if="clearance.status === 'cleared'"
                    class="h-10 w-full gap-2 rounded-xl bg-emerald-600 px-5 text-xs font-medium text-white shadow-sm hover:bg-emerald-700 sm:w-auto"
                >
                    <Download class="h-3.5 w-3.5" />
                    Download Official Certificate
                </Button>
            </div>
        </div>

        <div class="space-y-5">
            <div class="space-y-5">
                <!-- Compact Status Monitor -->
                <div
                    :class="[
                        'flex flex-col justify-between gap-5 rounded-2xl border p-4 transition-all sm:p-5 md:flex-row md:items-center',
                        'border-emerald-100 bg-white shadow-sm',
                    ]"
                >
                    <div class="flex items-center gap-4 sm:gap-5">
                        <div
                            :class="[
                                'flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl shadow-sm sm:h-16 sm:w-16',
                                clearance.status === 'cleared'
                                    ? 'bg-emerald-600 text-white shadow-emerald-500/15'
                                    : 'bg-emerald-100 text-emerald-700',
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
                                class="text-[9px] font-medium tracking-[0.16em] text-slate-400 uppercase"
                            >
                                Application Progress
                            </p>
                            <h2
                                :class="[
                                    'text-xl font-semibold tracking-tight',
                                    clearance.status === 'cleared'
                                        ? 'text-emerald-600'
                                        : 'text-emerald-700',
                                ]"
                            >
                                {{
                                    clearance.status === 'pending_review'
                                        ? 'PROCESSING'
                                        : clearance.status
                                              ?.replace('_', ' ')
                                              .toUpperCase() || 'PENDING'
                                }}
                            </h2>
                        </div>
                    </div>
                    <div
                        class="grid w-full grid-cols-2 gap-4 border-t border-emerald-50 pt-4 md:w-auto md:grid-cols-1 md:border-t-0 md:border-l md:pt-0 md:pl-6"
                    >
                        <div v-if="clearance.cleared_at" class="grid gap-0">
                            <p
                                class="text-[8px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                Date Cleared
                            </p>
                            <p class="text-[11px] font-medium text-slate-700">
                                {{ clearance.cleared_at }}
                            </p>
                        </div>
                        <div class="grid gap-0">
                            <p
                                class="text-[8px] font-medium tracking-wider text-slate-400 uppercase"
                            >
                                Outstanding
                            </p>
                            <p class="text-[11px] font-medium text-slate-900">
                                ₱0.00
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Office Verification Table -->
                <div
                    class="overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm"
                >
                    <div
                        class="border-b border-emerald-100 bg-emerald-50/50 px-5 py-4"
                    >
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-xs font-semibold tracking-wide text-slate-800"
                            >
                                Clearing offices and accountabilities
                            </h3>
                        </div>
                    </div>
                    <!-- Mobile office cards -->
                    <div class="divide-y divide-emerald-50 sm:hidden">
                        <div
                            v-for="office in officeRows"
                            :key="office.id"
                            class="p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600"
                                >
                                    <Building2 class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-xs leading-snug font-medium text-slate-800"
                                    >
                                        {{
                                            office.office?.name ||
                                            'Academic Unit'
                                        }}
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-medium',
                                                office.status === 'verified'
                                                    ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                                                    : office.status ===
                                                        'deficiencies'
                                                      ? 'border-red-100 bg-red-50 text-red-700'
                                                      : 'border-yellow-200 bg-yellow-50 text-yellow-700',
                                            ]"
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    office.status === 'verified'
                                                "
                                                class="h-3 w-3"
                                            />
                                            <AlertCircle
                                                v-else-if="
                                                    office.status ===
                                                    'deficiencies'
                                                "
                                                class="h-3 w-3"
                                            />
                                            <Clock3 v-else class="h-3 w-3" />
                                            {{
                                                office.status === 'verified'
                                                    ? 'Cleared'
                                                    : office.status ===
                                                        'deficiencies'
                                                      ? 'With deficiencies'
                                                      : 'Processing'
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                office.deficiencies.length > 0
                                            "
                                            class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[9px] font-medium text-red-700"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-red-500"
                                            ></span>
                                            {{ office.deficiencies.length }}
                                            tagged
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button
                                v-if="office.deficiencies.length > 0"
                                type="button"
                                class="mt-3 flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-white text-[10px] font-medium text-emerald-700 transition hover:bg-emerald-50"
                                :aria-expanded="
                                    expandedOffices.includes(office.id)
                                "
                                @click="toggleOffice(office.id)"
                            >
                                {{
                                    expandedOffices.includes(office.id)
                                        ? 'Hide deficiencies'
                                        : 'View deficiencies'
                                }}
                                <ChevronDown
                                    :class="[
                                        'h-3.5 w-3.5 transition-transform',
                                        expandedOffices.includes(office.id)
                                            ? 'rotate-180'
                                            : '',
                                    ]"
                                />
                            </button>

                            <div
                                v-if="
                                    expandedOffices.includes(office.id) &&
                                    office.deficiencies.length > 0
                                "
                                class="mt-3 grid gap-2 rounded-xl bg-red-50/50 p-3"
                            >
                                <div
                                    v-for="deficiency in office.deficiencies"
                                    :key="deficiency.id"
                                    class="rounded-lg border border-red-100 bg-white p-3"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-medium text-slate-900"
                                            >
                                                {{ deficiency.title }}
                                            </p>
                                            <p
                                                v-if="deficiency.description"
                                                class="mt-1 text-[10px] leading-relaxed text-slate-500"
                                            >
                                                {{ deficiency.description }}
                                            </p>
                                        </div>
                                        <span
                                            v-if="deficiency.amount"
                                            class="shrink-0 rounded-md bg-red-50 px-2 py-1 font-mono text-[9px] font-medium text-red-700"
                                        >
                                            ₱{{
                                                Number(
                                                    deficiency.amount,
                                                ).toLocaleString(undefined, {
                                                    minimumFractionDigits: 2,
                                                })
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop office table -->
                    <div class="hidden overflow-x-auto sm:block">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-emerald-100 bg-emerald-50/20"
                                >
                                    <th
                                        class="px-5 py-3 text-left text-[9px] font-medium tracking-wider text-slate-400 uppercase"
                                    >
                                        Office
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left text-[9px] font-medium tracking-wider text-slate-400 uppercase"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-5 py-3 text-left text-[9px] font-medium tracking-wider text-slate-400 uppercase"
                                    >
                                        Deficiencies
                                    </th>
                                    <th
                                        class="px-5 py-3 text-right text-[9px] font-medium tracking-wider text-slate-400 uppercase"
                                    >
                                        Details
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-emerald-50">
                                <template
                                    v-for="office in officeRows"
                                    :key="office.id"
                                >
                                    <tr
                                        class="transition-colors hover:bg-emerald-50/30"
                                    >
                                        <td class="px-5 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600"
                                                >
                                                    <Building2
                                                        class="h-4 w-4"
                                                    />
                                                </div>
                                                <span
                                                    class="text-xs font-medium text-slate-800"
                                                >
                                                    {{
                                                        office.office?.name ||
                                                        'Academic Unit'
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-medium tracking-wide',
                                                    office.status === 'verified'
                                                        ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                                                        : office.status ===
                                                            'deficiencies'
                                                          ? 'border-red-100 bg-red-50 text-red-700'
                                                          : 'border-yellow-200 bg-yellow-50 text-yellow-700',
                                                ]"
                                            >
                                                <CheckCircle2
                                                    v-if="
                                                        office.status ===
                                                        'verified'
                                                    "
                                                    class="h-3 w-3"
                                                />
                                                <AlertCircle
                                                    v-else-if="
                                                        office.status ===
                                                        'deficiencies'
                                                    "
                                                    class="h-3 w-3"
                                                />
                                                <Clock3
                                                    v-else
                                                    class="h-3 w-3"
                                                />
                                                {{
                                                    office.status === 'verified'
                                                        ? 'Cleared'
                                                        : office.status ===
                                                            'deficiencies'
                                                          ? 'With deficiencies'
                                                          : 'Processing'
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span
                                                v-if="
                                                    office.deficiencies.length >
                                                    0
                                                "
                                                class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[9px] font-medium text-red-700"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full bg-red-500"
                                                ></span>
                                                {{ office.deficiencies.length }}
                                                tagged
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] font-normal text-slate-400"
                                            >
                                                None
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <button
                                                v-if="
                                                    office.deficiencies.length >
                                                    0
                                                "
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-3 text-[9px] font-medium tracking-wide text-emerald-700 transition hover:bg-emerald-50"
                                                :aria-expanded="
                                                    expandedOffices.includes(
                                                        office.id,
                                                    )
                                                "
                                                @click="toggleOffice(office.id)"
                                            >
                                                {{
                                                    expandedOffices.includes(
                                                        office.id,
                                                    )
                                                        ? 'Hide'
                                                        : 'View'
                                                }}
                                                <ChevronDown
                                                    :class="[
                                                        'h-3.5 w-3.5 transition-transform',
                                                        expandedOffices.includes(
                                                            office.id,
                                                        )
                                                            ? 'rotate-180'
                                                            : '',
                                                    ]"
                                                />
                                            </button>
                                            <span
                                                v-else
                                                class="text-[9px] font-normal text-slate-300"
                                            >
                                                No details
                                            </span>
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            expandedOffices.includes(
                                                office.id,
                                            ) && office.deficiencies.length > 0
                                        "
                                    >
                                        <td
                                            colspan="4"
                                            class="bg-emerald-50/30 px-5 py-5"
                                        >
                                            <div class="grid gap-3">
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <p
                                                        class="text-[10px] font-medium tracking-wide text-emerald-800"
                                                    >
                                                        Tagged deficiencies
                                                    </p>
                                                    <span
                                                        class="text-[9px] font-medium text-emerald-700"
                                                    >
                                                        {{
                                                            office.deficiencies
                                                                .length
                                                        }}
                                                        item(s)
                                                    </span>
                                                </div>
                                                <div
                                                    v-for="deficiency in office.deficiencies"
                                                    :key="deficiency.id"
                                                    class="rounded-xl border border-emerald-100 bg-white p-4"
                                                >
                                                    <div
                                                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                                                    >
                                                        <div class="grid gap-1">
                                                            <p
                                                                class="text-xs font-semibold text-slate-900"
                                                            >
                                                                {{
                                                                    deficiency.title
                                                                }}
                                                            </p>
                                                            <p
                                                                v-if="
                                                                    deficiency.description
                                                                "
                                                                class="text-[10px] leading-relaxed text-slate-500"
                                                            >
                                                                {{
                                                                    deficiency.description
                                                                }}
                                                            </p>
                                                        </div>
                                                        <span
                                                            v-if="
                                                                deficiency.amount
                                                            "
                                                            class="shrink-0 rounded-lg bg-emerald-50 px-2.5 py-1 font-mono text-[10px] font-medium text-emerald-700"
                                                        >
                                                            ₱{{
                                                                Number(
                                                                    deficiency.amount,
                                                                ).toLocaleString(
                                                                    undefined,
                                                                    {
                                                                        minimumFractionDigits: 2,
                                                                    },
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            deficiency.children
                                                                ?.length > 0 ||
                                                            deficiency.children
                                                                ?.data?.length >
                                                                0
                                                        "
                                                        class="mt-3 grid gap-2 border-t border-slate-100 pt-3"
                                                    >
                                                        <div
                                                            v-for="child in deficiency
                                                                .children
                                                                ?.data ||
                                                            deficiency.children"
                                                            :key="child.id"
                                                            class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                                                        >
                                                            <span
                                                                class="text-[10px] font-medium text-slate-700"
                                                            >
                                                                {{
                                                                    child.title
                                                                }}
                                                            </span>
                                                            <span
                                                                :class="[
                                                                    'rounded-full px-2 py-0.5 text-[8px] font-medium',
                                                                    child.status ===
                                                                    'pending'
                                                                        ? 'bg-amber-100 text-amber-700'
                                                                        : 'bg-emerald-100 text-emerald-700',
                                                                ]"
                                                            >
                                                                {{
                                                                    child.status
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
