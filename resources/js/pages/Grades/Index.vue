<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { edit as editProfileRoute } from '@/routes/profile';
import {
    AlertCircle,
    BookOpenCheck,
    CalendarDays,
    ChevronDown,
    CheckCircle2,
    FileText,
    GraduationCap,
    Dumbbell,
    MessageSquareQuote,
    Printer,
    ServerCrash,
    Star,
    TrendingUp,
    User,
    UserX,
    WifiOff,
} from 'lucide-vue-next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';

type Student = {
    name: string;
    student_no: string | null;
    campus_name: string | null;
    tenant_id: string | null;
    bypass_evaluation?: boolean;
    show_gwa_gpa?: boolean;
    evaluation_id?: string | null;
};

type PendingEvaluation = {
    faculty: string;
    type: 'lecture' | 'lab' | 'unknown';
    id: string;
    status?: string;
    isEvaluated?: boolean;
    surveyTemplateId: string;
    surveyTemplateDescription?: string;
    encodedSurveyTemplate?: string;
    encodedJsonString?: string;
    encodedSurveyAnswers?: string;
};

type EvaluatedEvaluation = PendingEvaluation & {
    status: string;
    isEvaluated: true;
    evaluationDate?: string | null;
    surveyTemplateName?: string;
};

type GradeRecord = {
    requires_evaluation?: boolean;
    evaluation_status?: string;
    evaluation_period_id?: string;
    subject_for_evaluation_id?: string;
    subject_id?: string;
    subject_title?: string;
    pending_evaluations?: PendingEvaluation[];
    evaluated_evaluations?: EvaluatedEvaluation[];
    faculty_names?: string[];
    evaluation_payload?: Record<string, any>;
} & Record<string, any>;

type TermGroup = {
    term: string;
    termId: string;
    section: string;
    creditUnits: string;
    gpa: string;
    computedGpa: string;
    countedUnits: string;
    includedSubjectCount: number;
    excludedSubjectCount: number;
    rows: GradeRecord[];
};

const props = defineProps<{
    student: Student;
    gradeReport: {
        data: GradeRecord[] | Record<string, any>;
        error: string | null;
        computed_summary?: {
            average_gwa?: number;
            average_gwa_display?: string;
            counted_units?: number;
            counted_units_display?: string;
            included_subject_count?: number;
            excluded_keywords?: string[];
            note?: string;
        };
    };
    evaluation_error: string | null;
    evaluation_error_type:
        | 'connectivity'
        | 'no_data'
        | 'missing_context'
        | null;
    active_term_id: string | number | null;
    physical_fitness_shortcut_enabled: boolean;
}>();

const expandedTerms = ref<Record<string, boolean>>({});
const evaluationModalOpen = ref(false);
const selectedEvaluation = ref<{
    row: GradeRecord;
    evalItem: PendingEvaluation;
    payload: Record<string, any>;
} | null>(null);
const selectedEvaluationGroup = ref<{
    row: GradeRecord;
    evalItems: PendingEvaluation[];
} | null>(null);
const evaluationAnswers = ref<Record<number, number | string>>({});
const isSubmittingEvaluation = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref<string | null>(null);
const completedEvaluationIds = ref<Set<string>>(new Set());
const calculationDetailsOpen = ref(false);

const asArray = (value: any): GradeRecord[] => {
    if (Array.isArray(value)) {
        return value.filter(
            (item): item is GradeRecord =>
                item !== null && typeof item === 'object',
        );
    }

    if (value && typeof value === 'object') {
        const record = value as Record<string, any>;
        const nested = [
            'grades',
            'data',
            'subjects',
            'records',
            'studentGrades',
        ]
            .map((key) => record[key])
            .find(Array.isArray);

        return nested ? asArray(nested) : [record];
    }

    return [];
};

const pick = (row: GradeRecord, keys: string[]): string => {
    const value = keys
        .map((key) => row[key])
        .find((item) => item !== null && item !== undefined && item !== '');

    return value === null || value === undefined ? '-' : String(value);
};

const rowSubjectCode = (row: GradeRecord) =>
    pick(row, [
        'courseCode',
        'course_code',
        'subjectCode',
        'subject_code',
        'code',
    ]);

const rowSubjectDescription = (row: GradeRecord) =>
    pick(row, [
        'courseTitle',
        'course_title',
        'courseDescription',
        'course_description',
        'subjectDescription',
        'subject_description',
        'description',
        'title',
    ]);

const isPhysicalFitnessSubject = (row: GradeRecord) => {
    const code = rowSubjectCode(row).toUpperCase();
    const description = rowSubjectDescription(row).toLowerCase();

    return (
        code.includes('PATHFIT') ||
        code.includes('PATH FIT') ||
        /(^|[^A-Z0-9])P\.?\s*E\.?([^A-Z0-9]|$)/.test(code) ||
        description.includes('pathfit') ||
        description.includes('physical fitness') ||
        description.includes('exercise-based fitness') ||
        description.includes('exercise based fitness')
    );
};

const isActiveTermGroup = (group: TermGroup) =>
    props.active_term_id !== null &&
    props.active_term_id !== undefined &&
    String(group.termId) === String(props.active_term_id);

const showPhysicalFitnessShortcut = (group: TermGroup, row: GradeRecord) =>
    props.physical_fitness_shortcut_enabled &&
    can('pft.view') &&
    isActiveTermGroup(group) &&
    isPhysicalFitnessSubject(row);

const openPhysicalFitnessTest = () => {
    window.location.href = '/student-profile#physical-fitness-test';
};

const groupedGrades = computed<TermGroup[]>(() => {
    const rawData = asArray(props.gradeReport.data);

    const groups = rawData
        .map((term) => {
            const termName = pick(term, [
                'termName',
                'semesterName',
                'semester_name',
                'semester',
                'term',
            ]);
            const academicYear = pick(term, [
                'academicYear',
                'academic_year',
                'schoolYear',
                'school_year',
            ]);

            return {
                termId: pick(term, [
                    'termId',
                    'term_id',
                    'semesterId',
                    'semester_id',
                    'id',
                ]),
                term:
                    [academicYear, termName]
                        .filter((value) => value !== '-')
                        .join(' - ') || 'Grade Report',
                section: pick(term, ['sectionName', 'section_name', 'section']),
                creditUnits: pick(term, [
                    'totalCreditUnits',
                    'total_credit_units',
                    'totalUnits',
                    'total_units',
                    'computed_counted_units_display',
                ]),
                gpa: pick(term, ['gpa', 'GPA']),
                computedGpa: pick(term, ['computed_gpa_display']),
                countedUnits: pick(term, ['computed_counted_units_display']),
                includedSubjectCount: Number(
                    term.computed_included_subject_count ?? 0,
                ),
                excludedSubjectCount: Number(
                    term.computed_excluded_subject_count ?? 0,
                ),
                rows: asArray(term.grades),
            };
        })
        .filter((group) => group.rows.length);

    const finalGroups = [...groups].reverse();

    if (
        finalGroups.length > 0 &&
        Object.keys(expandedTerms.value).length === 0
    ) {
        expandedTerms.value[finalGroups[0].term] = true;
    }

    return finalGroups;
});

const records = computed(() =>
    groupedGrades.value.flatMap((group) => group.rows),
);

const totalCourses = computed(() => records.value.length);

const latestTerm = computed(() => groupedGrades.value[0]?.term ?? '-');

const columns = [
    {
        label: 'Course',
        keys: [
            'courseCode',
            'course_code',
            'subjectCode',
            'subject_code',
            'code',
        ],
    },
    {
        label: 'Description',
        keys: [
            'courseTitle',
            'course_title',
            'courseDescription',
            'course_description',
            'subjectDescription',
            'subject_description',
            'description',
            'title',
        ],
    },
    {
        label: 'Units',
        keys: ['unit', 'units', 'creditUnits', 'credit_units', 'credits'],
    },
    {
        label: 'Midterm',
        keys: ['midTerm', 'midterm', 'mid_term'],
    },
    {
        label: 'Final',
        keys: [
            'final',
            'final_exam',
            'finalGrade',
            'final_grade',
            'grade',
            'rating',
        ],
    },
    {
        label: 'Reexam',
        keys: [
            'reeExam',
            'reExam',
            'reexam',
            're_exam',
            'reExamination',
            're_examination',
        ],
    },
    {
        label: 'Remarks',
        keys: ['remarks', 'remark', 'status'],
    },
];

const printCOR = () => {
    window.print();
};

const termUnits = (group: TermGroup) => {
    const value = group.rows.reduce((sum, row) => sum + subjectUnits(row), 0);

    return Number.isFinite(value) ? String(value).replace(/\.0+$/, '') : '0';
};

const averageGrade = computed(
    () => props.gradeReport.computed_summary?.average_gwa_display ?? '0.0000',
);

const canViewGwaGpa = computed(() => props.student.show_gwa_gpa !== false);

const excludedKeywords = computed(
    () =>
        props.gradeReport.computed_summary?.excluded_keywords ?? [
            'NSTP',
            'THESIS',
            'CAPSTONE',
            'OJT',
            'PRACTICUM',
        ],
);

const countedUnitsDisplay = computed(() => {
    const value = Number(
        props.gradeReport.computed_summary?.counted_units ?? 0,
    );

    return Number.isFinite(value) ? String(value).replace(/\.0$/, '') : '0';
});

const numericPick = (row: GradeRecord, keys: string[]): number | null => {
    for (const key of keys) {
        const value = row[key];

        if (value !== null && value !== undefined && value !== '') {
            const numeric = Number(value);

            if (Number.isFinite(numeric)) {
                return numeric;
            }
        }
    }

    return null;
};

const subjectUnits = (row: GradeRecord) =>
    numericPick(row, columns[2].keys) ?? 0;

const totalUnitsDisplay = computed(() => {
    const value = records.value.reduce(
        (sum, row) => sum + subjectUnits(row),
        0,
    );

    return Number.isFinite(value) ? String(value).replace(/\.0$/, '') : '0';
});

const remarkClass = (remark: string) => {
    const normalized = remark.toLowerCase();

    if (normalized.includes('pass') || normalized === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (normalized === '-' || normalized.includes('progress')) {
        return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300';
    }

    return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
};

const page = usePage();

const permissions = computed<string[]>(
    () => (page.props.auth as { permissions?: string[] }).permissions ?? [],
);

const roles = computed<string[]>(
    () => (page.props.auth as { roles?: string[] }).roles ?? [],
);

const can = (permission?: string | string[]): boolean => {
    if (!permission) {
        return true;
    }

    const requiredPermissions = Array.isArray(permission)
        ? permission
        : [permission];

    return (
        roles.value.includes('Super Admin') ||
        requiredPermissions.some((requiredPermission) =>
            permissions.value.includes(requiredPermission),
        )
    );
};

const evaluate = (row: GradeRecord, evalItem: PendingEvaluation) => {
    if (
        evalItem.isEvaluated ||
        completedEvaluationIds.value.has(String(evalItem.id))
    ) {
        return;
    }

    const payload = {
        ...(row.evaluation_payload || {}),
        evaluationId: evalItem.id,
        surveyTemplateId: evalItem.surveyTemplateId,
        type: evalItem.type,
    };

    selectedEvaluation.value = { row, evalItem, payload };
    evaluationAnswers.value = {};
    submitError.value = null;
    submitSuccess.value = null;
    evaluationModalOpen.value = true;
};

const openEvaluationChooser = (row: GradeRecord) => {
    const evalItems = [
        ...(row.pending_evaluations ?? []),
        ...(row.evaluated_evaluations ?? []),
    ];
    if (!evalItems.length) return;
    selectedEvaluationGroup.value = { row, evalItems };
    selectedEvaluation.value = null;
    evaluationAnswers.value = {};
    submitError.value = null;
    submitSuccess.value = null;
    evaluationModalOpen.value = true;
};

const isEvaluationClosed = (row: GradeRecord) => {
    return (
        row.evaluation_status === 'Verification Unavailable' ||
        ![
            ...(row.pending_evaluations ?? []),
            ...(row.evaluated_evaluations ?? []),
        ].length
    );
};

const hasEvaluationItems = (row: GradeRecord) => {
    return (
        [
            ...(row.pending_evaluations ?? []),
            ...(row.evaluated_evaluations ?? []),
        ].length > 0
    );
};

const evaluatedSummary = (row: GradeRecord) => {
    return (row.evaluated_evaluations ?? []).filter(
        (item) => item.faculty && item.faculty !== 'Unknown Faculty',
    );
};

const formatEvaluationType = (type: EvaluatedEvaluation['type']) => {
    if (type === 'lecture') {
        return 'Lecture';
    }

    if (type === 'lab') {
        return 'Laboratory';
    }

    return 'Evaluation';
};

const decodeBase64Json = (encoded?: string): any | null => {
    if (!encoded) {
        return null;
    }

    try {
        return JSON.parse(atob(encoded));
    } catch {
        return null;
    }
};

const decodedSurveyTemplate = computed(() =>
    decodeBase64Json(selectedEvaluation.value?.evalItem.encodedSurveyTemplate),
);

const decodedJsonString = computed(() =>
    decodeBase64Json(selectedEvaluation.value?.evalItem.encodedJsonString),
);

const decodedSurveyAnswers = computed<any[]>(() => {
    const answers = decodeBase64Json(
        selectedEvaluation.value?.evalItem.encodedSurveyAnswers,
    );

    return Array.isArray(answers) ? answers : [];
});

const selectedEvaluationIsReadOnly = computed(
    () => selectedEvaluation.value?.evalItem.isEvaluated === true,
);

const questionnaireItems = computed<any[]>(() => {
    const template = decodedSurveyTemplate.value;
    if (!template || typeof template !== 'object') {
        return [];
    }

    const candidates = [
        (template as Record<string, any>).questionnaires,
        (template as Record<string, any>).questions,
        (template as Record<string, any>).items,
        (template as Record<string, any>).fields,
        (template as Record<string, any>).sections,
    ];

    const found = candidates.find((value) => Array.isArray(value));
    return Array.isArray(found) ? found : [];
});

const questionTypeLabel = (
    type: number | string | null | undefined,
): string => {
    const value = Number(type);
    if (value === 3) {
        return 'Star Rating';
    }
    if (value === 0 || value === 99) {
        return 'Open-ended';
    }
    return 'Unknown';
};

const questionIdFor = (item: Record<string, any>) =>
    item.id ?? item.templateQuestionId ?? item.templateSurveyQuestionId;

const normalizedQuestionType = (item: Record<string, any>): number | null => {
    const rawType =
        item.questionType ??
        item.question_type ??
        item.type ??
        item.questionTypeId ??
        item.question_type_id;

    if (rawType !== null && rawType !== undefined && rawType !== '') {
        const numericType = Number(rawType);

        if (Number.isFinite(numericType)) {
            return numericType;
        }

        const textType = String(rawType).trim().toLowerCase();

        if (
            textType.includes('star') ||
            textType.includes('rating') ||
            textType.includes('scale')
        ) {
            return 3;
        }

        if (
            textType.includes('text') ||
            textType.includes('answer') ||
            textType.includes('comment') ||
            textType.includes('essay')
        ) {
            return 0;
        }
    }

    const statement = String(
        item.questionStatement ??
            item.question ??
            item.text ??
            item.title ??
            item.label ??
            '',
    ).toLowerCase();

    if (
        statement.includes('suggestion') ||
        statement.includes('improvement') ||
        statement.includes('comment')
    ) {
        return 0;
    }

    if (Number(item.starCount ?? 0) > 0) {
        return 3;
    }

    return null;
};

const isRatingQuestion = (item: Record<string, any>) =>
    normalizedQuestionType(item) === 3;

const isTextQuestion = (item: Record<string, any>) => {
    const type = normalizedQuestionType(item);

    return type === 0 || type === 99;
};

const submittedAnswerForQuestion = (question: Record<string, any>) => {
    const questionId = question.id ?? question.templateQuestionId;
    const answers = decodedSurveyAnswers.value.flatMap((answer) => {
        if (Array.isArray(answer?.surveyAnswers)) {
            return answer.surveyAnswers;
        }

        if (Array.isArray(answer?.answers)) {
            return answer.answers;
        }

        return [answer];
    });

    return answers.find((answer) => {
        const answerQuestionId =
            answer?.templateQuestionId ??
            answer?.questionId ??
            answer?.templateSurveyQuestionId ??
            answer?.id;

        return String(answerQuestionId) === String(questionId);
    });
};

const submittedRatingForQuestion = (question: Record<string, any>) => {
    const answer = submittedAnswerForQuestion(question);

    return Number(answer?.starRating ?? answer?.rating ?? answer?.value ?? 0);
};

const submittedTextForQuestion = (question: Record<string, any>) => {
    const answer = submittedAnswerForQuestion(question);

    return String(
        answer?.shortAnswer ??
            answer?.longAnswer ??
            answer?.answer ??
            answer?.value ??
            '',
    ).trim();
};

const setRatingAnswer = (questionId: number, value: number) => {
    evaluationAnswers.value[questionId] = value;
};

const setTextAnswer = (questionId: number, value: string) => {
    evaluationAnswers.value[questionId] = value;
};

const canSubmitEvaluation = computed(() => {
    if (!questionnaireItems.value.length) {
        return false;
    }

    return questionnaireItems.value.every((item) => {
        const value = evaluationAnswers.value[questionIdFor(item)];

        if (isRatingQuestion(item)) {
            return Number(value) > 0;
        }

        return true;
    });
});

const submitEvaluation = () => {
    if (
        !selectedEvaluation.value ||
        selectedEvaluationIsReadOnly.value ||
        !decodedSurveyTemplate.value ||
        !decodedJsonString.value ||
        !canSubmitEvaluation.value
    ) {
        return;
    }

    const template = decodedSurveyTemplate.value as Record<string, any>;
    const context = decodedJsonString.value as Record<string, any>;

    const orderedItems = [...questionnaireItems.value].sort(
        (a, b) => Number(a.sortOrder ?? 0) - Number(b.sortOrder ?? 0),
    );

    const commentValues = orderedItems
        .filter((item) => isTextQuestion(item))
        .map((item) =>
            String(evaluationAnswers.value[questionIdFor(item)] ?? '').trim(),
        )
        .filter((text) => text.length > 0);

    const mergedComment = commentValues.join(', ');
    const lastIndex = orderedItems.length - 1;

    const surveyAnswers = orderedItems.map((item, index) => {
        const type = normalizedQuestionType(item) ?? 0;
        const questionId = questionIdFor(item);
        const answer = evaluationAnswers.value[questionId];

        return {
            sortOrder: item.sortOrder ?? 0,
            templateQuestionId: questionId,
            questionType: type,
            questionStatement: item.questionStatement ?? '',
            description: item.description ?? '',
            starCount: item.starCount ?? 0,
            starRating: type === 3 ? Number(answer ?? 0) : 0,
            shortAnswer: index === lastIndex ? mergedComment : '',
        };
    });

    isSubmittingEvaluation.value = true;

    router.post(
        '/grades/evaluation/submit',
        {
            studentId: context.studentId ?? '',
            templateSurveyId:
                context.templateSurveyId ??
                template.id ??
                selectedEvaluation.value.evalItem.surveyTemplateId,
            evaluationId:
                context.evaluationId ?? selectedEvaluation.value.evalItem.id,
            code: context.code ?? template.code ?? '',
            name: context.name ?? template.name ?? '',
            studentNo: context.studentNo ?? props.student.student_no ?? '',
            studentName: context.studentName ?? props.student.name ?? '',
            subjectId:
                context.subjectId ??
                selectedEvaluation.value.payload.subjectId ??
                '',
            schedId: context.schedId ?? context.scheduleId ?? '',
            campusId: context.campusId ?? '',
            termId:
                context.termId ?? selectedEvaluation.value.payload.termId ?? '',
            isLaboratory:
                context.isLaboratory ??
                selectedEvaluation.value.evalItem.type === 'lab',
            surveyAnswers,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                submitError.value = null;
                submitSuccess.value =
                    'You casted your evaluation successfully.';
                completedEvaluationIds.value.add(
                    String(selectedEvaluation.value?.evalItem.id ?? ''),
                );
            },
            onError: (errors) => {
                submitError.value = String(
                    errors.evaluation_submit ||
                        errors.studentNo ||
                        errors.termId ||
                        errors.templateSurveyId ||
                        'Unable to submit your evaluation. Please check your inputs and try again.',
                );
            },
            onFinish: () => {
                isSubmittingEvaluation.value = false;
            },
        },
    );
};

const groupHasPendingEvaluations = (group: TermGroup) => {
    return group.rows.some((row) => row.requires_evaluation);
};
</script>

<template>
    <Head title="My Grades" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 lg:p-5">
        <Sheet v-model:open="evaluationModalOpen">
            <SheetContent side="right" class="w-full p-0 sm:max-w-3xl">
                <div class="flex h-full min-h-0 flex-col">
                    <SheetHeader
                        class="border-b border-slate-200 px-5 py-4 text-left dark:border-white/10"
                    >
                        <SheetTitle> Evaluate Faculty </SheetTitle>
                        <SheetDescription>
                            Complete the faculty subject evaluation form below.
                        </SheetDescription>
                    </SheetHeader>

                    <div
                        v-if="selectedEvaluationGroup"
                        class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5 text-xs"
                    >
                        <div
                            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Course
                                </p>
                                <p
                                    class="mt-1 font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        pick(
                                            selectedEvaluationGroup.row,
                                            columns[0].keys,
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-0.5 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        pick(
                                            selectedEvaluationGroup.row,
                                            columns[1].keys,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Evaluation Details
                                </p>
                                <p
                                    class="mt-1 font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        selectedEvaluation?.evalItem.faculty ??
                                        'Select evaluation type'
                                    }}
                                </p>
                                <p
                                    class="mt-0.5 text-slate-600 dark:text-slate-300"
                                >
                                    Type:
                                    {{
                                        selectedEvaluation?.evalItem.type?.toUpperCase() ??
                                        '-'
                                    }}
                                </p>
                                <p
                                    v-if="selectedEvaluation?.evalItem.status"
                                    class="mt-0.5 text-slate-600 dark:text-slate-300"
                                >
                                    Status:
                                    {{ selectedEvaluation.evalItem.status }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="!selectedEvaluation"
                            class="rounded-md border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-900"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-slate-700 dark:text-slate-200"
                            >
                                Choose evaluation type
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="item in selectedEvaluationGroup.evalItems"
                                    :key="item.id"
                                    type="button"
                                    class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-bold transition"
                                    :class="
                                        item.isEvaluated
                                            ? 'cursor-not-allowed border-emerald-200 bg-emerald-50 text-emerald-700 opacity-75 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200'
                                    "
                                    :disabled="item.isEvaluated"
                                    @click="
                                        evaluate(
                                            selectedEvaluationGroup.row,
                                            item,
                                        )
                                    "
                                >
                                    Evaluate {{ item.type }}
                                    <span
                                        v-if="item.isEvaluated"
                                        class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                                    >
                                        Evaluated
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div
                            class="space-y-2 border-t border-slate-200 pt-2 dark:border-white/10"
                        >
                            <div
                                v-if="submitError"
                                class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] font-medium text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300"
                            >
                                {{ submitError }}
                            </div>
                            <div
                                v-if="submitSuccess"
                                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                {{ submitSuccess }}
                            </div>

                            <p
                                class="font-semibold text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    selectedEvaluationIsReadOnly
                                        ? 'Evaluation Summary'
                                        : 'Evaluation Questions'
                                }}
                            </p>

                            <div
                                v-if="decodedSurveyTemplate"
                                class="rounded-md border border-slate-200 bg-white p-2 text-[11px] dark:border-white/10 dark:bg-slate-900"
                            >
                                <p
                                    class="font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{
                                        decodedSurveyTemplate.name ??
                                        'Untitled Template'
                                    }}
                                </p>
                                <p class="text-slate-500 dark:text-slate-400">
                                    Code:
                                    {{ decodedSurveyTemplate.code ?? '-' }}
                                </p>
                                <p class="text-slate-500 dark:text-slate-400">
                                    Description:
                                    {{
                                        decodedSurveyTemplate.description ?? '-'
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    selectedEvaluationIsReadOnly &&
                                    !decodedSurveyAnswers.length
                                "
                                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                            >
                                This evaluation is marked as evaluated, but the
                                API did not return submitted answer values.
                            </div>

                            <div
                                v-if="
                                    selectedEvaluation &&
                                    questionnaireItems.length
                                "
                                class="space-y-3"
                            >
                                <div
                                    v-for="(item, idx) in questionnaireItems"
                                    :key="idx"
                                    class="rounded-md border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-900"
                                >
                                    <p
                                        class="font-semibold text-slate-800 dark:text-slate-100"
                                    >
                                        Q{{ idx + 1 }}:
                                        {{
                                            item.questionStatement ||
                                            item.question ||
                                            item.text ||
                                            item.title ||
                                            item.label ||
                                            'Untitled question'
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            Array.isArray(item.options) &&
                                            item.options.length
                                        "
                                        class="text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        Options:
                                        {{
                                            item.options
                                                .map(
                                                    (opt: any) =>
                                                        opt.label ??
                                                        opt.text ??
                                                        String(opt),
                                                )
                                                .join(', ')
                                        }}
                                    </p>

                                    <div
                                        v-if="isRatingQuestion(item)"
                                        class="mt-2 flex flex-wrap gap-1.5"
                                    >
                                        <button
                                            v-for="n in Number(
                                                item.starCount || 5,
                                            )"
                                            :key="n"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md border transition"
                                            :class="
                                                (selectedEvaluationIsReadOnly
                                                    ? submittedRatingForQuestion(
                                                          item,
                                                      )
                                                    : Number(
                                                          evaluationAnswers[
                                                              questionIdFor(
                                                                  item,
                                                              )
                                                          ] || 0,
                                                      )) >= n
                                                    ? 'border-amber-300 bg-amber-50 text-amber-600 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300'
                                                    : 'border-slate-200 bg-white text-slate-400 hover:border-amber-300 hover:text-amber-600 dark:border-white/10 dark:bg-slate-900 dark:text-slate-500'
                                            "
                                            :disabled="
                                                selectedEvaluationIsReadOnly
                                            "
                                            @click="
                                                !selectedEvaluationIsReadOnly &&
                                                setRatingAnswer(
                                                    questionIdFor(item),
                                                    n,
                                                )
                                            "
                                        >
                                            <Star class="size-4" />
                                        </button>
                                        <span
                                            v-if="
                                                selectedEvaluationIsReadOnly &&
                                                !submittedRatingForQuestion(
                                                    item,
                                                )
                                            "
                                            class="ml-1 self-center text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            No rating returned.
                                        </span>
                                    </div>

                                    <div
                                        v-else-if="isTextQuestion(item)"
                                        class="mt-2"
                                    >
                                        <div
                                            v-if="selectedEvaluationIsReadOnly"
                                            class="min-h-[70px] rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                        >
                                            {{
                                                submittedTextForQuestion(
                                                    item,
                                                ) || 'No answer returned.'
                                            }}
                                        </div>
                                        <textarea
                                            v-else
                                            class="min-h-[90px] w-full rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-800 outline-none placeholder:text-slate-400 focus:border-sky-400 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                            placeholder="Write your suggestion here..."
                                            :value="
                                                String(
                                                    evaluationAnswers[
                                                        questionIdFor(item)
                                                    ] ?? '',
                                                )
                                            "
                                            @input="
                                                setTextAnswer(
                                                    questionIdFor(item),
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="selectedEvaluation"
                                class="space-y-2"
                            >
                                <p class="text-slate-500 dark:text-slate-400">
                                    No direct question array found.
                                </p>
                                <pre
                                    class="max-h-52 overflow-auto rounded-md border border-slate-200 bg-white p-2 text-[11px] text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                                    >{{
                                        JSON.stringify(
                                            decodedSurveyTemplate,
                                            null,
                                            2,
                                        )
                                    }}</pre
                                >
                                <pre
                                    class="max-h-40 overflow-auto rounded-md border border-slate-200 bg-white p-2 text-[11px] text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200"
                                    >{{
                                        JSON.stringify(
                                            decodedJsonString,
                                            null,
                                            2,
                                        )
                                    }}</pre
                                >
                            </div>
                        </div>
                    </div>

                    <SheetFooter
                        class="border-t border-slate-200 px-5 py-3 dark:border-white/10"
                    >
                        <div
                            class="mr-auto inline-flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            <CheckCircle2 class="size-4 text-emerald-500" />
                            Lecture and lab forms use their own question set
                            automatically.
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            @click="
                                evaluationModalOpen = false;
                                if (submitSuccess) {
                                    router.reload({
                                        only: [
                                            'gradeReport',
                                            'evaluation_error',
                                        ],
                                    });
                                }
                            "
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            :disabled="
                                !selectedEvaluation ||
                                selectedEvaluationIsReadOnly ||
                                !canSubmitEvaluation ||
                                isSubmittingEvaluation ||
                                !!submitSuccess
                            "
                            @click="submitEvaluation"
                        >
                            {{
                                isSubmittingEvaluation
                                    ? 'Submitting...'
                                    : 'Submit Evaluation'
                            }}
                        </Button>
                    </SheetFooter>
                </div>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="calculationDetailsOpen">
            <SheetContent side="right" class="w-full p-0 sm:max-w-xl">
                <div class="flex h-full min-h-0 flex-col">
                    <SheetHeader
                        class="border-b border-slate-200 px-5 py-4 text-left dark:border-white/10"
                    >
                        <SheetTitle class="text-sm">
                            GPA Calculation Information
                        </SheetTitle>
                        <SheetDescription class="text-xs leading-relaxed">
                            Semester GPA and Average GWA are computed using
                            weighted averages based on credited units and valid
                            graded subjects only.
                        </SheetDescription>
                    </SheetHeader>

                    <div
                        class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5 text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                    >
                        <div
                            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-400/30 dark:bg-emerald-500/10"
                        >
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <p
                                        class="text-[10px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                    >
                                        Average GWA
                                    </p>
                                    <p
                                        class="mt-1 text-base font-bold text-emerald-950 dark:text-emerald-100"
                                    >
                                        {{ averageGrade }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                    >
                                        Counted Units
                                    </p>
                                    <p
                                        class="mt-1 text-base font-bold text-emerald-950 dark:text-emerald-100"
                                    >
                                        {{ countedUnitsDisplay }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                    >
                                        Included Subjects
                                    </p>
                                    <p
                                        class="mt-1 text-base font-bold text-emerald-950 dark:text-emerald-100"
                                    >
                                        {{
                                            gradeReport.computed_summary
                                                ?.included_subject_count ?? 0
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <p>
                                The following subjects are not included in the
                                computation:
                                <span
                                    class="font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    NSTP, Thesis, Capstone, OJT, and Practicum
                                    subjects.
                                </span>
                            </p>
                            <p>
                                Subjects with IP, INC, DRP, incomplete, blank,
                                or non-numeric grades are automatically excluded
                                from the calculation.
                            </p>
                            <p>
                                The system computes grades directly from subject
                                records and not from averaging semester GPAs.
                            </p>
                        </div>

                        <div
                            class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <p class="font-bold">Official Disclaimer</p>
                            <p class="mt-1">
                                Please note that GPA and GWA computations in the
                                portal are system-generated and may occasionally
                                encounter computation or data synchronization
                                issues. The displayed values are for reference
                                only and are not considered official or final.
                            </p>
                            <p class="mt-2 font-semibold">
                                The Admission and Records Office remains the
                                official authority for the verification,
                                validation, and final computation of academic
                                records, GPA, and GWA.
                            </p>
                        </div>
                    </div>

                    <SheetFooter
                        class="border-t border-slate-200 px-5 py-3 dark:border-white/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="calculationDetailsOpen = false"
                        >
                            Close
                        </Button>
                    </SheetFooter>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Evaluation Warning Banner -->
        <div
            v-if="evaluation_error"
            class="rounded-xl border p-4 shadow-sm"
            :class="{
                'border-red-200 bg-red-50 dark:border-red-400/30 dark:bg-red-500/10':
                    evaluation_error_type === 'connectivity',
                'border-amber-200 bg-amber-50 dark:border-amber-400/30 dark:bg-amber-500/10':
                    evaluation_error_type !== 'connectivity',
            }"
        >
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                    :class="{
                        'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400':
                            evaluation_error_type === 'connectivity',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400':
                            evaluation_error_type === 'no_data',
                        'bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400':
                            evaluation_error_type === 'missing_context',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400':
                            !evaluation_error_type,
                    }"
                >
                    <WifiOff
                        v-if="evaluation_error_type === 'connectivity'"
                        class="size-5"
                    />
                    <ServerCrash
                        v-else-if="evaluation_error_type === 'no_data'"
                        class="size-5"
                    />
                    <UserX
                        v-else-if="evaluation_error_type === 'missing_context'"
                        class="size-5"
                    />
                    <AlertCircle v-else class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        class="text-sm font-semibold"
                        :class="{
                            'text-red-800 dark:text-red-300':
                                evaluation_error_type === 'connectivity',
                            'text-amber-800 dark:text-amber-200':
                                evaluation_error_type !== 'connectivity',
                        }"
                    >
                        <span v-if="evaluation_error_type === 'connectivity'"
                            >Evaluation Service Unavailable</span
                        >
                        <span v-else-if="evaluation_error_type === 'no_data'"
                            >Evaluation Data Not Found</span
                        >
                        <span
                            v-else-if="
                                evaluation_error_type === 'missing_context'
                            "
                            >Account Setup Incomplete</span
                        >
                        <span v-else>Grades Temporarily Locked</span>
                    </p>
                    <div v-if="evaluation_error_type === 'missing_context'">
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                            Your account is missing required information (Campus or Tenant assignment).
                        </p>
                        <p class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">
                            Please update your Campus in your profile. If you cannot update it, contact your Registrar or System Administrator to complete your account setup.
                        </p>
                        <div class="mt-3 text-xs font-semibold">
                            <Link
                                :href="editProfileRoute.url()"
                                class="inline-flex items-center text-amber-950 underline hover:text-amber-900 dark:text-amber-200 dark:hover:text-white"
                            >
                                → Go to <strong class="font-bold">Profile</strong> to update your Campus.
                            </Link>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-1 text-xs"
                        :class="{
                            'text-red-700 dark:text-red-400':
                                evaluation_error_type === 'connectivity',
                            'text-amber-700 dark:text-amber-300':
                                evaluation_error_type !== 'connectivity',
                        }"
                    >
                        {{ evaluation_error }}
                    </p>
                </div>
            </div>
        </div>

        <section
            class="rounded-lg border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-sky-50/60 shadow-sm dark:border-white/10 dark:from-slate-950 dark:via-slate-950 dark:to-sky-950/20"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                    >
                        <GraduationCap class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-lg font-bold text-slate-950 dark:text-white"
                        >
                            Grade Report
                        </h1>
                        <p
                            class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Academic performance, terms, grades, and official
                            course outcomes.
                        </p>
                    </div>
                </div>

                <!-- <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                    @click="printCOR"
                >
                    <Printer class="size-4" />
                    Print
                </button> -->
            </div>

            <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-sky-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-sky-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <BookOpenCheck class="size-4 text-sky-600" />
                        Courses
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ totalCourses }}
                    </div>
                </div>
                <div
                    v-if="canViewGwaGpa"
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-emerald-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-emerald-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <TrendingUp class="size-4 text-emerald-600" />
                        Average GWA
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ averageGrade }}
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p
                            class="text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Tentative computation, not final.
                        </p>
                        <button
                            type="button"
                            class="rounded-full border border-emerald-200 bg-white/70 px-2 py-0.5 text-[10px] font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                            title="NSTP, Thesis, Capstone, OJT, and Practicum subjects are excluded from GPA computation."
                            @click="calculationDetailsOpen = true"
                        >
                            Exclusions applied
                        </button>
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-amber-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-amber-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <CalendarDays class="size-4 text-amber-600" />
                        Terms
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ groupedGrades.length }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-violet-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-violet-500/10"
                >
                    <div
                        class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                    >
                        <FileText class="size-4 text-violet-600" />
                        Units
                    </div>
                    <div
                        class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                    >
                        {{ totalUnitsDisplay }}
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <section
                class="min-w-0 rounded-lg border border-emerald-100 bg-gradient-to-br from-white via-white to-emerald-50/60 shadow-sm dark:border-emerald-400/20 dark:from-slate-950 dark:via-slate-950 dark:to-emerald-950/20"
            >
                <div
                    class="flex items-center justify-between gap-3 rounded-t-lg border-b border-emerald-100 bg-gradient-to-br from-white to-emerald-50/80 px-4 py-3 dark:border-emerald-400/20 dark:from-white/5 dark:to-emerald-500/10"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-950 dark:text-white"
                        >
                            Term Records
                        </h2>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ latestTerm }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="gradeReport.error"
                    class="m-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ gradeReport.error }}</span>
                </div>

                <div
                    v-else-if="!records.length"
                    class="flex min-h-[320px] flex-col items-center justify-center gap-2 p-6 text-center"
                >
                    <GraduationCap
                        class="size-10 text-slate-300 dark:text-slate-700"
                    />
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        No grades available
                    </h3>
                    <p
                        class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        No grade records are currently linked to this SSO
                        account.
                    </p>
                </div>

                <div
                    v-else
                    class="divide-y divide-slate-200 dark:divide-white/10"
                >
                    <Collapsible
                        v-for="group in groupedGrades"
                        :key="group.term"
                        v-model:open="expandedTerms[group.term]"
                    >
                        <CollapsibleTrigger as-child>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 bg-gradient-to-r from-white via-white to-emerald-50/60 px-4 py-3 text-left transition hover:from-emerald-50 hover:to-cyan-50/70 dark:from-slate-950 dark:via-slate-950 dark:to-emerald-950/20 dark:hover:from-emerald-950/30 dark:hover:to-cyan-950/20"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="truncate text-sm font-bold text-slate-950 dark:text-white"
                                        >
                                            {{ group.term }}
                                        </h3>
                                        <span
                                            v-if="group.section !== '-'"
                                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                        >
                                            Section {{ group.section }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{ group.rows.length }} courses /
                                        {{ termUnits(group) }} units
                                        <template
                                            v-if="
                                                canViewGwaGpa &&
                                                groupHasPendingEvaluations(
                                                    group,
                                                )
                                            "
                                        >
                                            /
                                            <span
                                                class="font-bold text-amber-600 dark:text-amber-400"
                                            >
                                                GPA Hidden (Evaluation Required)
                                            </span>
                                        </template>
                                        <template v-else-if="canViewGwaGpa">
                                            / Semester GPA
                                            {{
                                                group.computedGpa !== '-'
                                                    ? group.computedGpa
                                                    : '0.0000'
                                            }}
                                        </template>
                                    </p>
                                </div>
                                <ChevronDown
                                    class="size-4 shrink-0 text-slate-400 transition"
                                    :class="
                                        expandedTerms[group.term]
                                            ? 'rotate-180'
                                            : ''
                                    "
                                />
                            </button>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <div
                                class="overflow-x-auto border-t border-slate-200 dark:border-white/10"
                            >
                                <table class="w-full min-w-[900px] text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                    >
                                        <tr>
                                            <th class="px-4 py-3">Course</th>
                                            <th class="px-4 py-3 text-center">
                                                Units
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Midterm
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Final
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Reexam
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Action
                                            </th>
                                            <th class="px-4 py-3 text-center">
                                                Remarks
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-white/10"
                                    >
                                        <tr
                                            v-for="(
                                                row, rowIndex
                                            ) in group.rows"
                                            :key="rowIndex"
                                            class="hover:bg-slate-50/80 dark:hover:bg-white/5"
                                        >
                                            <td class="px-4 py-3">
                                                <div
                                                    class="flex flex-col gap-0.5"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-center gap-2"
                                                    >
                                                        <span
                                                            class="font-bold text-slate-900 dark:text-white"
                                                        >
                                                            {{
                                                                pick(
                                                                    row,
                                                                    columns[0]
                                                                        .keys,
                                                                )
                                                            }}
                                                        </span>
                                                        <button
                                                            v-if="
                                                                showPhysicalFitnessShortcut(
                                                                    group,
                                                                    row,
                                                                )
                                                            "
                                                            type="button"
                                                            class="inline-flex h-7 items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 text-[10px] font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                                            @click.stop="
                                                                openPhysicalFitnessTest
                                                            "
                                                        >
                                                            <Dumbbell
                                                                class="size-3"
                                                            />
                                                            Physical Fitness
                                                            Test
                                                        </button>
                                                    </div>
                                                    <span
                                                        class="max-w-[400px] truncate text-xs text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            pick(
                                                                row,
                                                                columns[1].keys,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-bold text-slate-700 dark:text-slate-200"
                                            >
                                                {{ pick(row, columns[2].keys) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                <template
                                                    v-if="
                                                        row.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[3].keys,
                                                        )
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                <template
                                                    v-if="
                                                        row.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[4].keys,
                                                        )
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                <template
                                                    v-if="
                                                        row.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[5].keys,
                                                        )
                                                    }}
                                                </template>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top"
                                            >
                                                <template
                                                    v-if="
                                                        row.requires_evaluation
                                                    "
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-7 items-center justify-center gap-1.5 rounded-md px-3 text-[10px] font-bold text-white shadow-sm transition dark:shadow-none"
                                                        :class="
                                                            isEvaluationClosed(
                                                                row,
                                                            )
                                                                ? 'cursor-not-allowed bg-slate-400 shadow-none dark:bg-slate-700 dark:text-slate-300'
                                                                : 'bg-indigo-600 shadow-indigo-200 hover:bg-indigo-700'
                                                        "
                                                        :disabled="
                                                            isEvaluationClosed(
                                                                row,
                                                            )
                                                        "
                                                        @click="
                                                            openEvaluationChooser(
                                                                row,
                                                            )
                                                        "
                                                    >
                                                        <MessageSquareQuote
                                                            class="size-3"
                                                        />
                                                        {{
                                                            isEvaluationClosed(
                                                                row,
                                                            )
                                                                ? 'Evaluation is closed'
                                                                : 'Evaluate'
                                                        }}
                                                    </button>
                                                    <p
                                                        class="mt-1 text-[10px] font-semibold text-slate-400"
                                                    >
                                                        {{
                                                            row.pending_evaluations
                                                                ?.map(
                                                                    (p) =>
                                                                        p.type,
                                                                )
                                                                .join(' / ')
                                                        }}
                                                    </p>
                                                </template>
                                                <button
                                                    v-else-if="
                                                        evaluatedSummary(row)
                                                            .length
                                                    "
                                                    type="button"
                                                    class="inline-flex items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-bold text-emerald-700 uppercase dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                                                    @click="
                                                        openEvaluationChooser(
                                                            row,
                                                        )
                                                    "
                                                >
                                                    Evaluated
                                                </button>
                                                <span
                                                    v-else
                                                    class="text-[10px] font-semibold text-slate-400"
                                                    >-</span
                                                >
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center align-top"
                                            >
                                                <template
                                                    v-if="
                                                        row.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 uppercase dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                                                    >
                                                        Evaluate faculty first
                                                    </span>
                                                </template>
                                                <span
                                                    v-else
                                                    class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="
                                                        remarkClass(
                                                            pick(
                                                                row,
                                                                columns[6].keys,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        pick(
                                                            row,
                                                            columns[6].keys,
                                                        )
                                                    }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </section>

            <aside class="space-y-4">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-slate-50 text-slate-700 dark:bg-white/5 dark:text-slate-200"
                        >
                            <User class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                Student Information
                            </p>
                            <h3
                                class="truncate text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.name }}
                            </h3>
                        </div>
                    </div>

                    <div
                        class="mt-4 space-y-3 border-t border-slate-100 pt-4 dark:border-white/5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                Student ID
                            </span>
                            <span
                                class="font-mono text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.student_no }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                            >
                                Campus
                            </span>
                            <span
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ student.campus_name }}
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
                >
                    <h3
                        class="text-xs font-bold text-slate-900 dark:text-white"
                    >
                        About Grade Report
                    </h3>
                    <p
                        class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        Your grade report displays official academic records per
                        term. Grades for subjects requiring faculty evaluation
                        are hidden until the evaluation process is completed.
                    </p>
                    <div class="mt-4 flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <div
                                class="size-2 rounded-full bg-indigo-600"
                            ></div>
                            <span
                                class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                            >
                                Requires Evaluation
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="size-2 rounded-full bg-slate-900 dark:bg-white"
                            ></div>
                            <span
                                class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                            >
                                Official Grade Posted
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
