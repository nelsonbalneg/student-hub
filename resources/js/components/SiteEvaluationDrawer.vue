<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Clock3,
    MessageSquareHeart,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    dismiss as dismissSiteEvaluation,
    submit as submitSiteEvaluation,
} from '@/routes/site-evaluation';

type Answer = string | number | string[] | null;
type Statement = {
    id: number;
    statement: string;
    help_text: string | null;
    statement_type: string;
    is_required: boolean;
    settings_json: Record<string, unknown> | null;
    choices: Array<{ id: number; label: string; value: string }>;
    scale_options: Array<{
        id: number | string;
        label: string;
        value: number;
    }>;
};
type Prompt = {
    period: {
        id: number;
        title: string;
        description: string | null;
        end_date: string;
        max_skips: number;
        skips_used: number;
    };
    template: {
        id: number;
        name: string;
        description: string | null;
        categories: Array<{
            id: number | null;
            name: string;
            description: string | null;
            statements: Statement[];
        }>;
    };
};

const page = usePage();
const prompt = computed(
    () => (page.props.siteEvaluation as Prompt | null | undefined) ?? null,
);
const requiredTerms = computed(
    () =>
        (
            page.props.legal as {
                requiredTerms?: unknown;
            }
        )?.requiredTerms ?? null,
);
const inviteOpen = ref(Boolean(prompt.value) && !requiredTerms.value);
const surveyOpen = ref(false);
const dismissing = ref(false);
const form = useForm({
    period_id: prompt.value?.period.id ?? 0,
    answers: {} as Record<number, Answer>,
});
const dismissForm = useForm({ period_id: prompt.value?.period.id ?? 0 });
const validationMessage = ref('');

watch(
    [prompt, requiredTerms],
    ([currentPrompt, terms]) => {
        if (!currentPrompt) {
            inviteOpen.value = false;
            surveyOpen.value = false;
            return;
        }

        if (form.period_id !== currentPrompt.period.id) {
            form.period_id = currentPrompt.period.id;
            dismissForm.period_id = currentPrompt.period.id;
            form.answers = {};
            form.clearErrors();
        }

        inviteOpen.value = !terms;
        surveyOpen.value = false;
    },
    { deep: true },
);

const toggleCheckbox = (
    statementId: number,
    value: string,
    checked: boolean,
) => {
    const answer = form.answers[statementId];
    const current = Array.isArray(answer) ? [...answer] : [];
    form.answers[statementId] = checked
        ? Array.from(new Set([...current, value]))
        : current.filter((item) => item !== value);
};

const isChecked = (statementId: number, value: string) => {
    const answer = form.answers[statementId];
    return Array.isArray(answer) && answer.includes(value);
};

const likertTone = (label: string, value: number) => {
    const normalizedLabel = label.trim().toLowerCase();

    if (normalizedLabel === 'strongly disagree' || value === 1) {
        return {
            option: 'border-red-200 bg-red-50 text-red-800 hover:border-red-400 hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200 dark:hover:bg-red-500/20',
            selected:
                'border-red-500 bg-red-100 ring-2 ring-red-500/25 dark:border-red-400 dark:bg-red-500/25',
            radio: 'accent-red-600',
        };
    }

    if (normalizedLabel === 'disagree' || value === 2) {
        return {
            option: 'border-rose-200 bg-rose-50 text-rose-700 hover:border-rose-300 hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200 dark:hover:bg-rose-500/20',
            selected:
                'border-rose-400 bg-rose-100 ring-2 ring-rose-400/25 dark:border-rose-400 dark:bg-rose-500/25',
            radio: 'accent-rose-500',
        };
    }

    if (normalizedLabel === 'neutral' || value === 3) {
        return {
            option: 'border-slate-300 bg-slate-100 text-slate-700 hover:border-slate-400 hover:bg-slate-200 dark:border-slate-500/40 dark:bg-slate-500/15 dark:text-slate-200 dark:hover:bg-slate-500/25',
            selected:
                'border-slate-500 bg-slate-200 ring-2 ring-slate-500/25 dark:border-slate-400 dark:bg-slate-500/30',
            radio: 'accent-slate-600',
        };
    }

    if (normalizedLabel === 'agree' || value === 4) {
        return {
            option: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/20',
            selected:
                'border-emerald-400 bg-emerald-100 ring-2 ring-emerald-400/25 dark:border-emerald-400 dark:bg-emerald-500/25',
            radio: 'accent-emerald-500',
        };
    }

    return {
        option: 'border-green-300 bg-green-100 text-green-800 hover:border-green-500 hover:bg-green-200 dark:border-green-500/40 dark:bg-green-500/20 dark:text-green-200 dark:hover:bg-green-500/30',
        selected:
            'border-green-600 bg-green-200 ring-2 ring-green-600/25 dark:border-green-400 dark:bg-green-500/35',
        radio: 'accent-green-600',
    };
};

const isSelectedScaleOption = (statementId: number, value: number) =>
    String(form.answers[statementId] ?? '') === String(value);

const submit = () => {
    if (!prompt.value) return;

    const requiredStatements = prompt.value.template.categories.flatMap(
        (category) =>
            category.statements.filter((statement) => statement.is_required),
    );
    const unanswered = requiredStatements.filter((statement) => {
        const answer = form.answers[statement.id];

        return (
            answer === null ||
            answer === undefined ||
            answer === '' ||
            (Array.isArray(answer) && answer.length === 0)
        );
    });

    if (unanswered.length > 0) {
        validationMessage.value = `${unanswered.length} required ${
            unanswered.length === 1 ? 'question is' : 'questions are'
        } still unanswered.`;
        toast.error(validationMessage.value);
        document
            .querySelector(`[data-statement-id="${unanswered[0].id}"]`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return;
    }

    validationMessage.value = '';
    form.clearErrors();
    form.post(submitSiteEvaluation.url(), {
        preserveScroll: true,
        onSuccess: () => {
            inviteOpen.value = false;
            surveyOpen.value = false;
        },
        onError: (errors) => {
            validationMessage.value =
                Object.keys(errors).length > 0
                    ? 'Some answers need your attention. Please review the highlighted questions.'
                    : 'The evaluation could not be saved. Please try again.';
            toast.error(validationMessage.value);

            const firstAnswerError = Object.keys(errors).find((key) =>
                key.startsWith('answers.'),
            );
            const statementId = firstAnswerError?.split('.')[1];

            if (statementId) {
                document
                    .querySelector(`[data-statement-id="${statementId}"]`)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
    });
};

const dismiss = () => {
    if (!prompt.value || dismissing.value) return;

    dismissing.value = true;
    dismissForm.period_id = prompt.value.period.id;
    dismissForm.post(dismissSiteEvaluation.url(), {
        preserveScroll: true,
        onSuccess: () => {
            inviteOpen.value = false;
            surveyOpen.value = false;
        },
        onFinish: () => {
            dismissing.value = false;
        },
    });
};

const continueToSurvey = () => {
    inviteOpen.value = false;
    surveyOpen.value = true;
};

const handleSurveyOpenChange = (value: boolean) => {
    surveyOpen.value = value;
};
</script>

<template>
    <Dialog :open="inviteOpen">
        <DialogContent
            v-if="prompt"
            :show-close-button="false"
            class="overflow-hidden border-0 p-0 sm:max-w-md"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
        >
            <div
                class="bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-7 text-white"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20"
                >
                    <MessageSquareHeart class="size-6" />
                </div>
                <DialogHeader class="mt-5 space-y-2 text-left">
                    <DialogTitle class="text-2xl font-bold text-white">
                        Help us improve the site
                    </DialogTitle>
                    <DialogDescription
                        class="text-sm leading-relaxed text-emerald-50"
                    >
                        Take a quick survey and tell us about your experience.
                        It won’t take long.
                    </DialogDescription>
                </DialogHeader>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div
                    class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-white/5"
                >
                    <Clock3 class="size-5 shrink-0 text-emerald-600" />
                    <div>
                        <p class="text-sm font-bold">
                            {{ prompt.period.title }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Optional · Available until
                            {{ prompt.period.end_date }}
                        </p>
                        <p
                            v-if="prompt.period.max_skips > 1"
                            class="mt-0.5 text-[11px] text-slate-400"
                        >
                            Skip {{ prompt.period.skips_used + 1 }} of
                            {{ prompt.period.max_skips }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                        :disabled="dismissing"
                        @click="dismiss"
                    >
                        {{ dismissing ? 'Skipping...' : 'Skip' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700"
                        @click="continueToSurvey"
                    >
                        Continue
                        <ArrowRight class="size-4" />
                    </button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <Sheet :open="surveyOpen" @update:open="handleSurveyOpenChange">
        <SheetContent
            v-if="prompt"
            side="right"
            class="w-full overflow-y-auto border-slate-200 bg-white p-0 sm:max-w-3xl dark:border-white/10 dark:bg-slate-950"
        >
            <div
                class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur dark:border-white/10 dark:bg-slate-950/95"
            >
                <SheetHeader class="space-y-1 pr-8 text-left">
                    <div
                        class="flex items-center gap-2 text-xs font-bold tracking-wide text-emerald-600 uppercase"
                    >
                        <MessageSquareHeart class="size-4" />
                        Site Evaluation
                    </div>
                    <SheetTitle class="text-xl font-bold">
                        {{ prompt.period.title }}
                    </SheetTitle>
                    <SheetDescription class="text-xs leading-relaxed">
                        {{
                            prompt.period.description ||
                            prompt.template.description
                        }}
                        Available until {{ prompt.period.end_date }}.
                    </SheetDescription>
                </SheetHeader>
            </div>

            <form class="space-y-5 p-5" @submit.prevent="submit">
                <div
                    v-if="validationMessage"
                    class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300"
                >
                    {{ validationMessage }}
                </div>

                <section
                    v-for="category in prompt.template.categories"
                    :key="category.id ?? 'general'"
                    class="overflow-hidden rounded-xl border border-slate-200 dark:border-white/10"
                >
                    <header
                        class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <h3 class="text-sm font-bold">{{ category.name }}</h3>
                        <p
                            v-if="category.description"
                            class="mt-1 text-xs text-slate-500"
                        >
                            {{ category.description }}
                        </p>
                    </header>

                    <div class="divide-y divide-slate-100 dark:divide-white/10">
                        <div
                            v-for="(statement, index) in category.statements"
                            :key="statement.id"
                            :data-statement-id="statement.id"
                            class="space-y-3 p-4"
                            :class="
                                form.errors[`answers.${statement.id}`]
                                    ? 'bg-red-50/60 dark:bg-red-500/5'
                                    : ''
                            "
                        >
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ index + 1 }}. {{ statement.statement }}
                                    <span
                                        v-if="statement.is_required"
                                        class="text-red-500"
                                        >*</span
                                    >
                                </p>
                                <p
                                    v-if="statement.help_text"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    {{ statement.help_text }}
                                </p>
                            </div>

                            <div
                                v-if="statement.scale_options.length > 0"
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <label
                                    v-for="option in statement.scale_options"
                                    :key="option.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors"
                                    :class="[
                                        likertTone(option.label, option.value)
                                            .option,
                                        isSelectedScaleOption(
                                            statement.id,
                                            option.value,
                                        )
                                            ? likertTone(
                                                  option.label,
                                                  option.value,
                                              ).selected
                                            : '',
                                    ]"
                                >
                                    <input
                                        v-model="form.answers[statement.id]"
                                        :name="`site_evaluation_${statement.id}`"
                                        :value="option.value"
                                        type="radio"
                                        :class="
                                            likertTone(
                                                option.label,
                                                option.value,
                                            ).radio
                                        "
                                    />
                                    {{ option.label }}
                                </label>
                            </div>

                            <div
                                v-else-if="
                                    ['multiple_choice', 'yes_no'].includes(
                                        statement.statement_type,
                                    )
                                "
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <label
                                    v-for="choice in statement.choices"
                                    :key="choice.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-emerald-50 dark:border-white/10 dark:hover:bg-emerald-500/10"
                                >
                                    <input
                                        v-model="form.answers[statement.id]"
                                        :name="`site_evaluation_${statement.id}`"
                                        :value="choice.value"
                                        type="radio"
                                    />
                                    {{ choice.label }}
                                </label>
                            </div>

                            <div
                                v-else-if="
                                    statement.statement_type === 'checkbox'
                                "
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <label
                                    v-for="choice in statement.choices"
                                    :key="choice.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            isChecked(
                                                statement.id,
                                                choice.value,
                                            )
                                        "
                                        @change="
                                            toggleCheckbox(
                                                statement.id,
                                                choice.value,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    {{ choice.label }}
                                </label>
                            </div>

                            <textarea
                                v-else-if="
                                    ['long_answer', 'text_answer'].includes(
                                        statement.statement_type,
                                    )
                                "
                                v-model="form.answers[statement.id]"
                                class="min-h-28 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                                :placeholder="
                                    String(
                                        statement.settings_json?.placeholder ??
                                            '',
                                    )
                                "
                            />

                            <input
                                v-else
                                v-model="form.answers[statement.id]"
                                :type="
                                    statement.statement_type === 'numeric_score'
                                        ? 'number'
                                        : 'text'
                                "
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900"
                            />

                            <p
                                v-if="form.errors[`answers.${statement.id}`]"
                                class="text-xs font-semibold text-red-600"
                            >
                                This question is required.
                            </p>
                        </div>
                    </div>
                </section>

                <div
                    class="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-slate-200 bg-white/95 py-4 backdrop-blur sm:flex-row sm:justify-end dark:border-white/10 dark:bg-slate-950/95"
                >
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                        :disabled="dismissing || form.processing"
                        @click="dismiss"
                    >
                        {{ dismissing ? 'Skipping...' : 'Skip Evaluation' }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="form.processing || dismissing"
                    >
                        <CheckCircle2 class="size-4" />
                        {{
                            form.processing
                                ? 'Submitting...'
                                : 'Submit Evaluation'
                        }}
                    </button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
