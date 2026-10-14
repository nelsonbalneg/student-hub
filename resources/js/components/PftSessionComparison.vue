<script setup lang="ts">
import { Plus, X, ChartColumnIncreasing } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';

type Result = {
    pft_test_type_id: number;
    term_id: string;
    status: string;
    results_json: Record<string, unknown>;
    remarks: string | null;
    interpretation?: string | null;
    color_class?: string | null;
};
type Test = {
    id: number;
    name: string;
    slug: string;
    unit: string | null;
    configurations: Array<{
        field_key?: string;
        field_name?: string;
        field_label?: string;
        label?: string;
        name?: string;
    }>;
};
const props = defineProps<{
    terms: Array<{ term_id: string; school_year: string; semester: string }>;
    components: Array<{
        name: string;
        categories: Array<{ name: string; test_types: Test[] }>;
    }>;
    results: Record<string, Result>;
}>();
const sessions = computed(() =>
    props.terms
        .flatMap((term) =>
            ['pre', 'post'].map((phase) => ({
                key: `${term.term_id}:${phase}`,
                label: `${term.school_year}, ${term.semester} · ${phase === 'pre' ? 'Pre-test' : 'Post-test'}`,
            })),
        )
        .filter((session) =>
            Object.entries(props.results).some(
                ([key, result]) =>
                    key.startsWith(`${session.key}:`) &&
                    result.status === 'completed',
            ),
        ),
);
const selectedKeys = ref<string[]>([]);
const sessionToAdd = ref('');
const selectedSessions = computed(() =>
    selectedKeys.value.flatMap((key) =>
        sessions.value.filter((session) => session.key === key),
    ),
);
const availableSessions = computed(() =>
    sessions.value.filter(
        (session) => !selectedKeys.value.includes(session.key),
    ),
);
const sessionLabelToAdd = computed({
    get: () =>
        availableSessions.value.find(
            (session) => session.key === sessionToAdd.value,
        )?.label ?? '',
    set: (label: string) => {
        sessionToAdd.value =
            availableSessions.value.find((session) => session.label === label)
                ?.key ?? '';
    },
});
const addSession = () => {
    if (
        sessionToAdd.value &&
        !selectedKeys.value.includes(sessionToAdd.value)
    ) {
        selectedKeys.value.push(sessionToAdd.value);
    }

    sessionToAdd.value = '';
};
const tests = computed(() =>
    props.components.flatMap((component) =>
        component.categories.flatMap((category) =>
            category.test_types.map((test) => ({
                ...test,
                group: `${component.name} · ${category.name}`,
            })),
        ),
    ),
);
const resultFor = (key: string, id: number) => {
    const result = props.results[`${key}:${id}`];

    return result?.status === 'completed' ? result : null;
};
const fieldLabel = (test: Test, key: string) => {
    const config = test.configurations.find(
        (field) => field.field_key === key || field.field_name === key,
    );

    return (
        config?.field_label ??
        config?.label ??
        config?.name ??
        key.replace(/_/g, ' ').replace(/^./, (char) => char.toUpperCase())
    );
};
const values = (test: Test, result: Result) =>
    Object.entries(result.results_json)
        .filter(
            ([key, value]) =>
                ![
                    'remarks',
                    'date_tested',
                    'tested_at',
                    'interpretation',
                    'interpretation_color',
                    'color_class',
                ].includes(key) &&
                value !== null &&
                value !== '',
        )
        .map(([key, value]) => ({
            key,
            label: fieldLabel(test, key),
            value: Array.isArray(value) ? value.join(', ') : String(value),
        }));
const interpretationLabel = (result: Result) =>
    String(
        result.results_json.interpretation ??
            result.interpretation ??
            result.remarks ??
            '',
    );
const interpretationClasses = (result: Result) => {
    const color = String(
        result.results_json.interpretation_color ?? result.color_class ?? '',
    ).toLowerCase();
    const colors: Record<string, string> = {
        emerald:
            'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
        lime: 'bg-lime-50 text-lime-700 ring-lime-200 dark:bg-lime-500/10 dark:text-lime-300 dark:ring-lime-500/30',
        blue: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/30',
        amber: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
        orange: 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/30',
        red: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
        green: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30',
        yellow: 'bg-yellow-50 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-300 dark:ring-yellow-500/30',
    };
    const name = Object.keys(colors).find(
        (name) => color === name || color.includes(`-${name}-`),
    );

    return name
        ? colors[name]
        : 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10';
};

const performance = (test: Test) => {
    if (selectedSessions.value.length < 2) {
        return [];
    }

    const first = resultFor(selectedSessions.value[0].key, test.id);
    const last = resultFor(
        selectedSessions.value[selectedSessions.value.length - 1].key,
        test.id,
    );

    if (!first || !last) {
        return [{ label: 'Incomplete comparison', tone: 'neutral' }];
    }

    if (test.slug === 'zipper-test') {
        const ranks = [
            'Fingertips do not touch',
            'Fingertips just touch',
            'Fingertips overlap',
        ];
        const before = ranks.indexOf(String(first.results_json.observation));
        const after = ranks.indexOf(String(last.results_json.observation));

        if (before >= 0 && after >= 0) {
            return [
                {
                    label:
                        after === before
                            ? 'Unchanged'
                            : after > before
                              ? 'Improved flexibility'
                              : 'Declined flexibility',
                    tone:
                        after === before
                            ? 'neutral'
                            : after > before
                              ? 'positive'
                              : 'negative',
                },
            ];
        }
    }

    const changes = Object.entries(first.results_json).flatMap(
        ([key, value]) => {
            const current = last.results_json[key];

            if (
                value === null ||
                current === null ||
                value === '' ||
                current === '' ||
                typeof value === 'boolean' ||
                typeof current === 'boolean'
            ) {
                return [];
            }

            if (
                !['number', 'string'].includes(typeof value) ||
                !['number', 'string'].includes(typeof current)
            ) {
                return [];
            }

            const before = Number(value),
                after = Number(current);

            if (
                !Number.isFinite(before) ||
                !Number.isFinite(after) ||
                /date/.test(key) ||
                ['height', 'weight', 'age'].includes(key)
            ) {
                return [];
            }

            const delta = Math.round((after - before) * 100) / 100;
            const lowerBetter =
                (/sprint|agility|shuttle/.test(test.slug) &&
                    /time|second|duration/.test(key)) ||
                (test.slug === 'ruler-drop-test' &&
                    ['distance_cm', 'reaction_time'].includes(key)) ||
                (test.slug === '3-minute-step-test' && key === 'heart_rate');
            const higherBetter =
                /push-up|sit-up|curl-up|jump|sit-and-reach|plank|medicine-ball|stork-balance|wall-toss/.test(
                    test.slug,
                );
            const improved = lowerBetter
                ? delta < 0
                : higherBetter
                  ? delta > 0
                  : null;
            const status =
                delta === 0
                    ? 'Unchanged'
                    : improved === null
                      ? delta > 0
                          ? 'Increased'
                          : 'Decreased'
                      : improved
                        ? 'Improved'
                        : 'Declined';
            const percent =
                before !== 0
                    ? ` (${delta > 0 ? '+' : ''}${Math.round((delta / Math.abs(before)) * 1000) / 10}%)`
                    : '';

            return [
                {
                    label: `${fieldLabel(test, key)}: ${status} · ${delta > 0 ? '+' : ''}${delta}${percent}`,
                    tone:
                        delta === 0 || improved === null
                            ? 'neutral'
                            : improved
                              ? 'positive'
                              : 'negative',
                },
            ];
        },
    );

    return changes.length
        ? changes
        : [
              {
                  label:
                      JSON.stringify(first.results_json) ===
                      JSON.stringify(last.results_json)
                          ? 'Unchanged'
                          : 'Review the recorded results',
                  tone: 'neutral',
              },
          ];
};
</script>

<template>
    <div class="space-y-5">
        <div>
            <h4 class="text-lg font-semibold text-slate-950 dark:text-white">
                Compare test sessions
            </h4>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Add two or more sessions in the order you want to compare. Only
                completed results are included.
            </p>
        </div>
        <section
            class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5"
        >
            <label
                for="comparison-session"
                class="block text-xs font-semibold text-slate-600 dark:text-slate-300"
                >Academic year, semester &amp; test phase</label
            >
            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="min-w-0 flex-1">
                    <SearchableSelect
                        id="comparison-session"
                        v-model="sessionLabelToAdd"
                        :options="
                            availableSessions.map((session) => session.label)
                        "
                        :empty-text="
                            availableSessions.length
                                ? 'Select a test session'
                                : 'No more saved sessions available'
                        "
                        aria-label="Academic year, semester and test phase"
                    />
                </div>
                <Button :disabled="!sessionToAdd" @click="addSession"
                    ><Plus class="size-4" /> Add session</Button
                >
            </div>
            <div v-if="selectedSessions.length" class="flex flex-wrap gap-2">
                <div
                    v-for="(session, index) in selectedSessions"
                    :key="session.key"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs dark:border-emerald-500/30 dark:bg-slate-950"
                >
                    <span
                        class="font-semibold text-emerald-700 dark:text-emerald-400"
                        >{{ index + 1 }}.</span
                    >
                    <span class="text-slate-700 dark:text-slate-200">{{
                        session.label
                    }}</span>
                    <button
                        type="button"
                        class="rounded p-1 hover:bg-slate-100 dark:hover:bg-white/10"
                        :aria-label="`Remove ${session.label}`"
                        @click="
                            selectedKeys = selectedKeys.filter(
                                (key) => key !== session.key,
                            )
                        "
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>
        </section>
        <div
            v-if="selectedSessions.length < 2"
            class="rounded-xl border border-dashed border-slate-200 p-10 text-center dark:border-white/10"
        >
            <ChartColumnIncreasing class="mx-auto size-8 text-emerald-500" />
            <p
                class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
            >
                {{
                    sessions.length < 2
                        ? 'Complete at least two test sessions to compare'
                        : 'Select at least two sessions'
                }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Compare Pre-test and Post-test within a term, or sessions across
                different terms.
            </p>
        </div>
        <template v-else>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Performance compares session 1 with session
                {{ selectedSessions.length }}. All selected sessions appear
                below. Missing results are not treated as zero. BMI changes are
                shown as increases or decreases.
            </p>
            <div class="space-y-4 md:hidden">
                <article
                    v-for="test in tests"
                    :key="test.id"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                >
                    <header
                        class="border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <h5
                            class="text-sm font-semibold text-slate-950 dark:text-white"
                        >
                            {{ test.name }}
                        </h5>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ test.group
                            }}<span v-if="test.unit"> · {{ test.unit }}</span>
                        </p>
                    </header>
                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <section
                            v-for="(session, index) in selectedSessions"
                            :key="session.key"
                            class="space-y-3 p-4"
                        >
                            <h6
                                class="flex items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200"
                            >
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                    >{{ index + 1 }}</span
                                >
                                <span class="min-w-0 leading-5">{{
                                    session.label
                                }}</span>
                            </h6>
                            <template v-if="resultFor(session.key, test.id)">
                                <dl class="space-y-2">
                                    <div
                                        v-for="field in values(
                                            test,
                                            resultFor(session.key, test.id)!,
                                        )"
                                        :key="field.key"
                                        class="grid grid-cols-2 gap-3 text-xs"
                                    >
                                        <dt
                                            class="text-slate-500 dark:text-slate-400"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            class="text-right font-medium break-words text-slate-800 dark:text-slate-100"
                                        >
                                            {{ field.value }}
                                        </dd>
                                    </div>
                                </dl>
                                <span
                                    v-if="
                                        interpretationLabel(
                                            resultFor(session.key, test.id)!,
                                        )
                                    "
                                    class="inline-flex rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="
                                        interpretationClasses(
                                            resultFor(session.key, test.id)!,
                                        )
                                    "
                                    >{{
                                        interpretationLabel(
                                            resultFor(session.key, test.id)!,
                                        )
                                    }}</span
                                >
                                <p
                                    v-if="
                                        resultFor(session.key, test.id)
                                            ?.remarks &&
                                        resultFor(session.key, test.id)
                                            ?.remarks !==
                                            interpretationLabel(
                                                resultFor(
                                                    session.key,
                                                    test.id,
                                                )!,
                                            )
                                    "
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        resultFor(session.key, test.id)?.remarks
                                    }}
                                </p>
                            </template>
                            <p v-else class="text-xs text-slate-400">
                                No completed result
                            </p>
                        </section>
                    </div>
                    <footer
                        class="space-y-2 border-t border-slate-100 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wide text-slate-500 uppercase"
                        >
                            Performance · Session 1 →
                            {{ selectedSessions.length }}
                        </p>
                        <p
                            v-for="change in performance(test)"
                            :key="change.label"
                            class="text-xs leading-relaxed"
                            :class="
                                change.tone === 'positive'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : change.tone === 'negative'
                                      ? 'text-amber-700 dark:text-amber-400'
                                      : 'text-slate-500 dark:text-slate-400'
                            "
                        >
                            {{ change.label }}
                        </p>
                    </footer>
                </article>
            </div>
            <div
                class="hidden overflow-x-auto rounded-xl border border-slate-200 md:block dark:border-white/10"
            >
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        Physical fitness results by selected session
                    </caption>
                    <thead class="bg-slate-50 dark:bg-white/5">
                        <tr>
                            <th
                                scope="col"
                                class="min-w-48 px-4 py-3 font-semibold"
                            >
                                Test
                            </th>
                            <th
                                v-for="session in selectedSessions"
                                :key="session.key"
                                scope="col"
                                class="min-w-56 px-4 py-3 font-semibold"
                            >
                                {{ session.label }}
                            </th>
                            <th
                                scope="col"
                                class="min-w-64 px-4 py-3 font-semibold"
                            >
                                Performance
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-white/10"
                    >
                        <tr
                            v-for="test in tests"
                            :key="test.id"
                            class="align-top"
                        >
                            <th scope="row" class="px-4 py-4 font-medium">
                                <p>{{ test.name }}</p>
                                <p
                                    class="mt-1 text-xs font-normal text-slate-500"
                                >
                                    {{ test.group
                                    }}<span v-if="test.unit">
                                        · {{ test.unit }}</span
                                    >
                                </p>
                            </th>
                            <td
                                v-for="session in selectedSessions"
                                :key="session.key"
                                class="px-4 py-4"
                            >
                                <template
                                    v-if="resultFor(session.key, test.id)"
                                >
                                    <p
                                        v-for="field in values(
                                            test,
                                            resultFor(session.key, test.id)!,
                                        )"
                                        :key="field.key"
                                        class="mb-1 text-xs"
                                    >
                                        <span class="text-slate-500"
                                            >{{ field.label }}:</span
                                        >
                                        {{ field.value }}
                                    </p>
                                    <span
                                        v-if="
                                            interpretationLabel(
                                                resultFor(
                                                    session.key,
                                                    test.id,
                                                )!,
                                            )
                                        "
                                        class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="
                                            interpretationClasses(
                                                resultFor(
                                                    session.key,
                                                    test.id,
                                                )!,
                                            )
                                        "
                                        >{{
                                            interpretationLabel(
                                                resultFor(
                                                    session.key,
                                                    test.id,
                                                )!,
                                            )
                                        }}</span
                                    >
                                    <p
                                        v-if="
                                            resultFor(session.key, test.id)
                                                ?.remarks &&
                                            resultFor(session.key, test.id)
                                                ?.remarks !==
                                                interpretationLabel(
                                                    resultFor(
                                                        session.key,
                                                        test.id,
                                                    )!,
                                                )
                                        "
                                        class="mt-2 text-xs text-slate-500"
                                    >
                                        {{
                                            resultFor(session.key, test.id)
                                                ?.remarks
                                        }}
                                    </p>
                                </template>
                                <span v-else class="text-xs text-slate-400"
                                    >No completed result</span
                                >
                            </td>
                            <td class="space-y-2 px-4 py-4">
                                <p
                                    v-for="change in performance(test)"
                                    :key="change.label"
                                    class="text-xs"
                                    :class="
                                        change.tone === 'positive'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : change.tone === 'negative'
                                              ? 'text-amber-700 dark:text-amber-400'
                                              : 'text-slate-500 dark:text-slate-400'
                                    "
                                >
                                    {{ change.label }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
