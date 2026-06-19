<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    Clock,
    Download,
    ExternalLink,
    FileSearch,
    FileText,
    GraduationCap,
    Layers,
    Library,
    Printer,
    RefreshCw,
    ScrollText,
    Search,
    ServerCrash,
    User,
    UserRound,
    UserX,
    WifiOff,
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    index as reportOfGradesIndex,
    search as searchReportOfGrades,
} from '@/routes/admin/registrar/report-of-grades';
import { print as printCurriculumRoute } from '@/routes/admin/registrar/report-of-grades/curriculum';

type Campus = {
    id: number | string;
    name: string;
    tenant_id: number | null;
    address?: string | null;
    logo_url?: string | null;
};

type GradeRow = Record<string, any>;

type TermRecord = {
    termId?: number | string;
    termName?: string;
    academicYear?: string;
    sectionName?: string;
    enrolledSubjects?: number;
    totalCreditUnits?: number | string;
    totalLectureUnits?: number | string;
    totalLabUnits?: number | string;
    gpa?: number | string;
    computed_gpa_display?: string;
    computed_counted_units_display?: string;
    grades?: GradeRow[];
    [key: string]: any;
};

type CurriculumRecord = Record<string, any>;

type CeeDocument = {
    key: string;
    field: string;
    label: string;
    name: string;
    path: string;
    url: string;
    exists: boolean;
    extension: string;
};

type SemesterGroup = {
    semester: string;
    rows: CurriculumRecord[];
};

type YearGroup = {
    year: string;
    semesters: SemesterGroup[];
};

type SearchResult = {
    student_no: string;
    campus: Campus;
    terms: TermRecord[];
    summary?: Record<string, any>;
    profile?: Record<string, any> | null;
    curriculum?: {
        data: CurriculumRecord[] | Record<string, any>;
        error: string | null;
    };
    ceeDocuments?: {
        data: CeeDocument[];
        error: string | null;
    };
    bypass_evaluation?: boolean;
    show_gwa_gpa?: boolean;
    evaluation_error?: string | null;
    evaluation_error_type?: string | null;
} | null;

const props = defineProps<{
    campuses: Campus[];
    filters: {
        student_no: string;
        campus_id: string | number;
    };
    result: SearchResult;
    error: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Registrar', href: '/admin/registrar/report-of-grades' },
            {
                title: 'Report of Grades',
                href: '/admin/registrar/report-of-grades',
            },
        ],
    },
});

const loading = ref(false);
const activeStudentTab = ref('student-profile');
const expandedCurriculumSems = ref<Record<string, boolean>>({});
const printPreviewHtml = ref('');
const printPreviewTitle = ref('');
const printPreviewOpen = ref(false);
const printPreviewLoading = ref(false);
const curriculumPreviewOpen = ref(false);
const curriculumPreviewUrl = ref('');
const curriculumDownloadUrl = ref('');
const curriculumPreviewTitle = ref('');
const form = useForm({
    student_no: props.filters.student_no ?? '',
    campus_id: props.filters.campus_id ? String(props.filters.campus_id) : '',
});

const studentTabs = [
    { id: 'student-profile', label: 'Student Profile', icon: UserRound },
    { id: 'report-of-grades', label: 'Report of Grades', icon: GraduationCap },
    { id: 'clearance', label: 'Clearance', icon: ClipboardCheck },
    { id: 'evaluation', label: 'Evaluation', icon: FileSearch },
    { id: 'curriculum', label: 'Curriculum', icon: ScrollText },
    { id: 'class-schedule', label: 'Class Schedule', icon: CalendarDays },
    { id: 'documents', label: 'Documents', icon: FileText },
];

const selectedCampus = computed(
    () =>
        props.campuses.find((campus) => String(campus.id) === form.campus_id) ??
        null,
);

const submit = () => {
    form.post(searchReportOfGrades.url(), {
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const reset = () => {
    form.reset();
    router.get(reportOfGradesIndex.url(), { reset: 1 });
};

const curriculumPdfUrl = (download = false) => {
    if (!props.result) {
        return '';
    }

    return printCurriculumRoute.url({
        query: {
            student_no: props.result.student_no,
            campus_id: props.result.campus.id,
            ...(download ? { download: 1 } : {}),
        },
    });
};

const printCurriculum = () => {
    curriculumPreviewUrl.value = curriculumPdfUrl();
    curriculumDownloadUrl.value = curriculumPdfUrl(true);
    curriculumPreviewTitle.value = `Curriculum - ${props.result?.student_no ?? ''}`;
    curriculumPreviewOpen.value = true;
};

const closeCurriculumPreview = () => {
    curriculumPreviewOpen.value = false;
};

const printCurriculumPreviewFrame = () => {
    const frame = document.getElementById(
        'curriculum-preview-frame',
    ) as HTMLIFrameElement | null;

    frame?.contentWindow?.focus();
    frame?.contentWindow?.print();
};

const valueFrom = (
    row: Record<string, any>,
    keys: string[],
    fallback = '-',
) => {
    const value = keys
        .map((key) => row[key])
        .find((item) => item !== null && item !== undefined && item !== '');

    return value === null || value === undefined || value === ''
        ? fallback
        : String(value);
};

const curriculumData = computed(
    () => props.result?.curriculum ?? { data: [], error: null },
);

const ceeDocuments = computed(
    () => props.result?.ceeDocuments ?? { data: [], error: null },
);

const ceeDocumentCount = computed(() => ceeDocuments.value.data.length);

const groupedCeeDocuments = computed(() => {
    const groups = new Map<
        string,
        { key: string; label: string; documents: CeeDocument[] }
    >();

    for (const document of ceeDocuments.value.data) {
        const key = document.field;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                label: document.label,
                documents: [],
            });
        }

        groups.get(key)?.documents.push(document);
    }

    return Array.from(groups.values());
});

const isImageDocument = (document: CeeDocument) =>
    ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(document.extension);

const isPdfDocument = (document: CeeDocument) => document.extension === 'pdf';

const curriculumAsArray = (value: any): CurriculumRecord[] => {
    if (!value) {
        return [];
    }

    if (value.yearAndLevel && Array.isArray(value.yearAndLevel)) {
        return value.yearAndLevel.flatMap((group: any) =>
            (group.subjects || []).map((subject: any) => ({
                ...subject,
                yearTermDesc: group.yearTermDesc,
                yearLevelId: group.yearLevelId,
                yearTermId: group.yearTermId,
            })),
        );
    }

    if (Array.isArray(value)) {
        return value.filter(
            (item) => item !== null && typeof item === 'object',
        );
    }

    if (typeof value === 'object') {
        for (const key of [
            'curriculum',
            'data',
            'subjects',
            'records',
            'items',
            'details',
            'curriculumDetails',
            'list',
        ]) {
            if (Array.isArray(value[key])) {
                return curriculumAsArray(value[key]);
            }
        }
    }

    return [];
};

const curriculumPick = (row: CurriculumRecord, keys: string[]): string =>
    valueFrom(row, keys);

const curriculumColumns = [
    {
        label: 'Code',
        keys: [
            'subjectCode',
            'courseCode',
            'course_code',
            'subject_code',
            'code',
            'subjectId',
            'subject_id',
        ],
    },
    {
        label: 'Description',
        keys: [
            'subjectDesc',
            'courseTitle',
            'course_title',
            'courseDescription',
            'course_description',
            'subjectDescription',
            'subject_description',
            'description',
            'title',
            'subjectName',
            'subject_name',
        ],
    },
    {
        label: 'Prerequisites',
        keys: [
            'preReqs',
            'prerequisites',
            'pre_requisites',
            'prereq',
            'pre_req',
            'preRequisite',
            'pre_requisite',
        ],
    },
    {
        label: 'Taken',
        keys: ['subjectTaken', 'subject_taken', 'taken', 'isTaken'],
    },
    {
        label: 'Final Grade',
        keys: ['finalGrade', 'final_grade', 'grade', 'rating'],
    },
    {
        label: 'Final Remarks',
        keys: ['finalRemarks', 'final_remarks', 'remarks', 'remark'],
    },
];

const curriculumLectureUnitKeys = [
    'acadUnits',
    'academicUnits',
    'academic_units',
    'lecUnits',
    'lectureUnits',
    'lecture_units',
    'lec_units',
];

const curriculumLabUnitKeys = [
    'labUnits',
    'laboratoryUnits',
    'laboratory_units',
    'lab_units',
];

const curriculumTotalUnitKeys = [
    'totalUnits',
    'total_units',
    'totalCreditUnits',
    'total_credit_units',
    'unit',
    'units',
    'creditUnits',
    'credit_units',
    'credits',
    'units_load',
    'unitsLoad',
];

const curriculumNumericPick = (
    row: CurriculumRecord,
    keys: string[],
): number | null => {
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

const curriculumSubjectLectureUnits = (row: CurriculumRecord) =>
    curriculumNumericPick(row, curriculumLectureUnitKeys) ?? 0;

const curriculumSubjectLabUnits = (row: CurriculumRecord) =>
    curriculumNumericPick(row, curriculumLabUnitKeys) ?? 0;

const curriculumSubjectUnits = (row: CurriculumRecord) => {
    const lectureUnits = curriculumSubjectLectureUnits(row);
    const labUnits = curriculumSubjectLabUnits(row);

    if (lectureUnits > 0 || labUnits > 0) {
        return lectureUnits + labUnits;
    }

    return curriculumNumericPick(row, curriculumTotalUnitKeys) ?? 0;
};

const formatCurriculumUnit = (units: number) =>
    units > 0 ? String(units).replace(/\.0$/, '') : '-';

const curriculumTotalSubjects = computed(
    () => curriculumAsArray(curriculumData.value.data).length,
);

const curriculumTotalLectureUnits = computed(() =>
    curriculumAsArray(curriculumData.value.data).reduce(
        (sum, row) => sum + curriculumSubjectLectureUnits(row),
        0,
    ),
);

const curriculumTotalLabUnits = computed(() =>
    curriculumAsArray(curriculumData.value.data).reduce(
        (sum, row) => sum + curriculumSubjectLabUnits(row),
        0,
    ),
);

const curriculumTotalUnits = computed(() =>
    curriculumAsArray(curriculumData.value.data).reduce(
        (sum, row) => sum + curriculumSubjectUnits(row),
        0,
    ),
);

const curriculumSubjectLectureUnitsDisplay = (row: CurriculumRecord) =>
    formatCurriculumUnit(curriculumSubjectLectureUnits(row));

const curriculumSubjectLabUnitsDisplay = (row: CurriculumRecord) =>
    formatCurriculumUnit(curriculumSubjectLabUnits(row));

const curriculumSubjectLookup = computed(() => {
    const lookup = new Map<string, string>();

    curriculumAsArray(curriculumData.value.data).forEach((row) => {
        const subjectId = curriculumPick(row, [
            'subjectId',
            'subject_id',
            'id',
        ]);
        const subjectCode = curriculumPick(row, [
            'subjectCode',
            'courseCode',
            'course_code',
            'subject_code',
            'code',
        ]);

        if (subjectId !== '-' && subjectCode !== '-') {
            lookup.set(subjectId, subjectCode);
        }
    });

    return lookup;
});

const curriculumPrerequisiteItems = (row: CurriculumRecord): string[] => {
    const prerequisites = row.preRequisites ?? row.pre_requisites;

    if (Array.isArray(prerequisites) && prerequisites.length > 0) {
        return prerequisites
            .map((prerequisite) => {
                if (!prerequisite || typeof prerequisite !== 'object') {
                    return null;
                }

                const subjectId = curriculumPick(prerequisite, [
                    'subjectId',
                    'subject_id',
                    'prerequisiteSubjectId',
                    'prerequisite_subject_id',
                ]);

                if (subjectId === '-') {
                    return null;
                }

                return (
                    curriculumSubjectLookup.value.get(subjectId) ?? subjectId
                );
            })
            .filter((item): item is string => Boolean(item));
    }

    const textValue = curriculumPick(row, curriculumColumns[2].keys);

    if (textValue === '-') {
        return [];
    }

    return textValue
        .split(/[,\n;/]+/)
        .map((item) => item.trim())
        .filter(Boolean);
};

const curriculumSemesterUnits = (rows: CurriculumRecord[]) =>
    rows.reduce((sum, row) => sum + curriculumSubjectUnits(row), 0);

const curriculumIsTruthy = (value: unknown) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        return ['true', '1', 'yes', 'y'].includes(value.trim().toLowerCase());
    }

    return false;
};

const curriculumTakenStatus = (row: CurriculumRecord) => {
    const finalGrade = curriculumPick(row, curriculumColumns[4].keys);
    const finalRemarks = curriculumPick(row, curriculumColumns[5].keys);

    if (finalGrade !== '-' && finalRemarks.trim().toLowerCase() === 'failed') {
        return 'Failed';
    }

    const takenValue = curriculumColumns[3].keys
        .map((key) => row[key])
        .find((value) => value !== null && value !== undefined && value !== '');

    return curriculumIsTruthy(takenValue) ? 'Taken' : '';
};

const curriculumTakenStatusClass = (status: string) => {
    if (status === 'Failed') {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300';
    }

    if (status === 'Taken') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    return '';
};

const curriculumProcessedData = computed(() => {
    const rawData: any = curriculumData.value.data;
    let groups: YearGroup[] = [];

    if (
        rawData &&
        rawData.yearAndLevel &&
        Array.isArray(rawData.yearAndLevel)
    ) {
        const yearGroups: Record<
            string,
            Record<string, CurriculumRecord[]>
        > = {};

        rawData.yearAndLevel.forEach((group: any) => {
            const desc = group.yearTermDesc || 'Other';
            const [yearPart, semPart] = desc.split(' - ');
            const year = yearPart || 'Other';
            const semester = semPart || 'Other';

            if (!yearGroups[year]) {
                yearGroups[year] = {};
            }

            yearGroups[year][semester] = group.subjects || [];
        });

        groups = Object.entries(yearGroups).map(([year, semesters]) => ({
            year,
            semesters: Object.entries(semesters).map(([semester, rows]) => ({
                semester,
                rows,
            })),
        }));
    } else {
        const raw = curriculumAsArray(rawData);
        const yearGroups: Record<
            string,
            Record<string, CurriculumRecord[]>
        > = {};

        raw.forEach((row) => {
            let year = curriculumPick(row, [
                'yearLevel',
                'year_level',
                'year',
                'yearLevelName',
                'year_level_name',
                'yearLevelId',
                'year_level_id',
            ]);

            if (year === '-' || year === '0') {
                year = 'Other';
            }

            if (year === '1') {
                year = '1st Year';
            }

            if (year === '2') {
                year = '2nd Year';
            }

            if (year === '3') {
                year = '3rd Year';
            }

            if (year === '4') {
                year = '4th Year';
            }

            let semester = curriculumPick(row, [
                'semester',
                'semesterName',
                'semester_name',
                'term',
                'semester_id',
                'semesterId',
            ]);

            if (semester === '-' || semester === '0') {
                semester = 'Other';
            }

            if (semester === '1') {
                semester = '1st Semester';
            }

            if (semester === '2') {
                semester = '2nd Semester';
            }

            if (semester === '3') {
                semester = 'Summer';
            }

            if (!yearGroups[year]) {
                yearGroups[year] = {};
            }

            if (!yearGroups[year][semester]) {
                yearGroups[year][semester] = [];
            }

            yearGroups[year][semester].push(row);
        });

        groups = Object.entries(yearGroups)
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([year, semesters]) => ({
                year,
                semesters: Object.entries(semesters)
                    .sort(([a], [b]) => a.localeCompare(b))
                    .map(([semester, rows]) => ({
                        semester,
                        rows,
                    })),
            }));
    }

    return groups;
});

const curriculumTotalSemesters = computed(() =>
    curriculumProcessedData.value.reduce(
        (sum, year) => sum + year.semesters.length,
        0,
    ),
);

watch(
    curriculumProcessedData,
    (groups) => {
        if (
            groups.length > 0 &&
            groups[0].semesters.length > 0 &&
            Object.keys(expandedCurriculumSems.value).length === 0
        ) {
            expandedCurriculumSems.value[
                `${groups[0].year}-${groups[0].semesters[0].semester}`
            ] = true;
        }
    },
    { immediate: true },
);

const termTitle = (term: TermRecord) =>
    [
        valueFrom(
            term,
            ['academicYear', 'academic_year', 'schoolYear', 'school_year'],
            '',
        ),
        valueFrom(
            term,
            ['termName', 'term_name', 'semester', 'schoolTerm'],
            '',
        ),
    ]
        .filter(Boolean)
        .join(' - ') || 'Academic Term';

const termGrades = (term: TermRecord) =>
    Array.isArray(term.grades) ? term.grades : [];

const normalizedRemark = (grade: GradeRow) =>
    valueFrom(grade, ['remarks', 'remark', 'status'], '').trim().toUpperCase();

const rowStatusClass = (grade: GradeRow) => {
    const remark = normalizedRemark(grade);

    if (remark.includes('FAILED') || remark === 'FAIL') {
        return 'registrar-row-failed';
    }

    if (remark === 'IP' || remark.includes('IN PROGRESS')) {
        return 'registrar-row-ip';
    }

    if (remark === 'INC' || remark.includes('INCOMPLETE')) {
        return 'registrar-row-inc';
    }

    return '';
};

const remarkBadgeClass = (grade: GradeRow) => {
    const remark = normalizedRemark(grade);

    if (remark.includes('FAILED') || remark === 'FAIL') {
        return 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30';
    }

    if (remark === 'IP' || remark.includes('IN PROGRESS')) {
        return 'bg-yellow-50 text-yellow-700 ring-yellow-200 dark:bg-yellow-500/10 dark:text-yellow-300 dark:ring-yellow-500/30';
    }

    if (remark === 'INC' || remark.includes('INCOMPLETE')) {
        return 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/30';
    }

    return 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-white/5 dark:text-slate-300 dark:ring-white/10';
};

const formatDateTime = (grade: GradeRow) => {
    const rawValue = valueFrom(
        grade,
        [
            'datePosted',
            'date_posted',
            'postedAt',
            'posted_at',
            'datePostedAt',
            'date_posted_at',
            'postedDate',
            'posted_date',
            'lastModifiedDate',
            'last_modified_date',
        ],
        '',
    );

    if (!rawValue) {
        return '-';
    }

    const date = new Date(rawValue);

    if (Number.isNaN(date.getTime())) {
        return rawValue;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date);
};

const escapeHtml = (value: unknown) =>
    String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

const reportValue = (value: unknown) => escapeHtml(value || '-');

const reportGradeValue = (grade: GradeRow, keys: string[]) =>
    grade.requires_evaluation ? 'LOCKED' : valueFrom(grade, keys);

const termCreditUnits = (term: TermRecord) =>
    Number(
        valueFrom(
            term,
            [
                'computed_counted_units_display',
                'totalCreditUnits',
                'total_credit_units',
            ],
            '0',
        ),
    ) || 0;

const termTotalUnits = (term: TermRecord) =>
    termGrades(term).reduce((sum, grade) => {
        const units = Number(valueFrom(grade, ['unit', 'units'], '0'));

        return sum + (Number.isFinite(units) ? units : 0);
    }, 0);

const termProblemCount = (term: TermRecord) =>
    termGrades(term).filter((grade) => {
        if (grade.requires_evaluation) {
            return true;
        }

        const finalGrade = valueFrom(
            grade,
            ['finalGrade', 'final_grade', 'grade', 'final'],
            '',
        );

        return finalGrade.includes('*') || finalGrade.trim() === '';
    }).length;

const buildTermReportHtml = async (term: TermRecord) => {
    const result = props.result;
    const profile = result?.profile ?? {};
    const campus = result?.campus;
    const title = termTitle(term);
    const generatedAt =
        new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        })
            .format(new Date())
            .replace(' at ', ', ') + ' PHT';
    const qrPayload = JSON.stringify({
        document: 'Report of Grades',
        student_no: result?.student_no,
        term: title,
        campus: campus?.name,
        generated_at: new Date().toISOString(),
    });
    const qrCodeUrl = await QRCode.toDataURL(qrPayload, {
        width: 108,
        margin: 1,
        errorCorrectionLevel: 'M',
    });
    const rows = termGrades(term)
        .map((grade) => {
            const statusClass = rowStatusClass(grade);

            return `
                <tr class="${statusClass}">
                    <td>${reportValue(valueFrom(grade, ['courseCode', 'course_code']))}</td>
                    <td>${reportValue(valueFrom(grade, ['courseTitle', 'course_title']))}</td>
                    <td class="center">${reportValue(reportGradeValue(grade, ['finalGrade', 'final_grade', 'grade', 'final']))}</td>
                    <td class="center">${reportValue(reportGradeValue(grade, ['reeExam', 'reExam', 'reexam']))}</td>
                    <td class="center">${reportValue(valueFrom(grade, ['unit', 'units']))}</td>
                    <td class="center">${reportValue(grade.requires_evaluation ? 'Evaluate faculty first' : valueFrom(grade, ['remarks', 'remark', 'status']))}</td>
                </tr>
            `;
        })
        .join('');
    const problemCount = termProblemCount(term);
    const validationMessage = problemCount
        ? `* ${problemCount} subject(s) has/have a problem in final grade. Unable to compute GWA & Units Earned.`
        : 'Grades encoded and generated from StudentHub.';
    const logoUrl = campus?.logo_url
        ? `${window.location.origin}${campus.logo_url}`
        : '';

    return `
        <!doctype html>
        <html>
            <head>
                <base href="${window.location.origin}">
                <meta charset="utf-8">
                <title>${escapeHtml(`Report of Grades - ${title}`)}</title>
                <style>
                    @page { size: A4; margin: 0; }
                    * { box-sizing: border-box; }
                    html, body { min-height: 100%; }
                    body { margin: 0; background: #e2e8f0; color: #111827; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
                    .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; overflow: hidden; background: #fff; padding: 14mm 14mm 36mm; box-shadow: 0 12px 32px rgba(15, 23, 42, .18); }
                    .sheet::before { content: "STUDENTHUB"; position: absolute; left: 50%; top: 52%; z-index: 0; transform: translate(-50%, -50%) rotate(-32deg); color: rgba(15, 23, 42, .055); font-size: 58px; font-weight: 800; letter-spacing: .18em; white-space: nowrap; pointer-events: none; }
                    .report-content { position: relative; z-index: 1; }
                    .header { display: grid; grid-template-columns: 82px 1fr 82px; align-items: center; gap: 12px; text-align: center; }
                    .logo { width: 72px; height: 72px; object-fit: contain; }
                    .logo-placeholder { width: 72px; height: 72px; border: 1px solid #cbd5e1; border-radius: 50%; display: grid; place-items: center; color: #64748b; font-weight: 700; font-size: 10px; }
                    .republic { font-size: 11px; }
                    .university { margin-top: 2px; font-size: 14px; font-weight: 800; letter-spacing: .04em; }
                    .campus { margin-top: 2px; font-size: 11px; }
                    h1 { margin: 12px 0 10px; text-align: center; font-size: 15px; letter-spacing: .08em; }
                    .student-grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 16px; margin-top: 8px; }
                    .details { display: grid; grid-template-columns: 92px 10px 1fr; row-gap: 4px; }
                    .label { color: #334155; font-weight: 700; }
                    .value { color: #111827; font-weight: 700; }
                    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                    th, td { border: 1px solid #111827; padding: 5px 6px; vertical-align: top; }
                    th { text-align: center; font-size: 10px; text-transform: uppercase; }
                    td { font-size: 10.5px; }
                    .center { text-align: center; }
                    .registrar-row-failed { background: #fee2e2; }
                    .registrar-row-ip { background: #fef9c3; }
                    .registrar-row-inc { background: #ffedd5; }
                    .nothing { text-align: center; font-weight: 700; font-style: italic; }
                    .totals { width: 52%; margin-left: auto; margin-top: 10px; display: grid; grid-template-columns: 1fr 12px 80px; row-gap: 4px; }
                    .report-footer { position: absolute; right: 14mm; bottom: 12mm; left: 14mm; z-index: 1; display: grid; grid-template-columns: 1fr 118px; align-items: end; gap: 16px; border-top: 1px solid #111827; padding-top: 8px; }
                    .validation { display: block; }
                    .validation-title { font-weight: 800; letter-spacing: .04em; }
                    .validation-note { margin-top: 6px; font-size: 10px; }
                    .qr { width: 108px; height: 108px; }
                    .qr-caption { margin-top: 4px; text-align: center; font-size: 9px; font-weight: 700; }
                    .seal { margin-top: 7px; font-size: 10px; font-weight: 800; }
                    .generated { margin-top: 6px; font-size: 10px; }
                    @media print {
                        body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        .sheet { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; }
                    }
                </style>
            </head>
            <body>
                <main class="sheet">
                    <div class="report-content">
                        <header class="header">
                            ${logoUrl ? `<img class="logo" src="${escapeHtml(logoUrl)}" alt="Campus logo">` : '<div class="logo-placeholder">LOGO</div>'}
                            <div>
                                <div class="republic">Republic of the Philippines</div>
                                <div class="university">UNIVERSITY OF SOUTHERN MINDANAO</div>
                                <div class="campus">${reportValue(campus?.address || campus?.name || 'Kabacan, North Cotabato')}</div>
                            </div>
                            <div></div>
                        </header>

                        <h1>REPORT OF GRADES</h1>

                        <section class="student-grid">
                            <div class="details">
                                <div class="label">Fullname</div><div>:</div><div class="value">${reportValue(studentFullName.value)}</div>
                                <div class="label">Gender</div><div>:</div><div>${reportValue(genderLabel.value)}</div>
                                <div class="label">College</div><div>:</div><div>${reportValue(valueFrom(profile, ['college', 'collegeName', 'college_name'], ''))}</div>
                                <div class="label">Program</div><div>:</div><div>${reportValue(valueFrom(profile, ['program', 'programName', 'program_name', 'progName'], ''))}</div>
                                <div class="label">Major</div><div>:</div><div>${reportValue(valueFrom(profile, ['major', 'majorName', 'major_name'], ''))}</div>
                            </div>
                            <div class="details">
                                <div class="label">Student No.</div><div>:</div><div class="value">${reportValue(result?.student_no)}</div>
                                <div class="label">Year Level</div><div>:</div><div>${reportValue(valueFrom(profile, ['yearLevel', 'yearLevelName', 'year_level', 'yearLevelId'], ''))}</div>
                                <div class="label">Academic Year</div><div>:</div><div>${reportValue(valueFrom(term, ['academicYear', 'academic_year', 'schoolYear', 'school_year']))}</div>
                                <div class="label">Term</div><div>:</div><div>${reportValue(valueFrom(term, ['termName', 'term_name', 'semester', 'schoolTerm']))}</div>
                                <div class="label">Section</div><div>:</div><div>${reportValue(valueFrom(term, ['sectionName', 'section_name']))}</div>
                            </div>
                        </section>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Code</th>
                                    <th>Subject Title</th>
                                    <th style="width: 11%;">Final Grades</th>
                                    <th style="width: 10%;">Re-Ex</th>
                                    <th style="width: 10%;">Credit Units</th>
                                    <th style="width: 14%;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows || '<tr><td colspan="6" class="center">No course-level grades in this term.</td></tr>'}
                                <tr><td colspan="6" class="nothing">*** Nothing Follows ***</td></tr>
                            </tbody>
                        </table>

                        <section class="totals">
                            <div>Total Credit Units Enrolled</div><div>:</div><div class="center">${formatCurriculumUnit(termTotalUnits(term))}</div>
                            <div>Total Credit Units Earned</div><div>:</div><div class="center">${formatCurriculumUnit(termCreditUnits(term))}</div>
                            <div>General Weighted Average</div><div>:</div><div class="center">${reportValue(termHasPendingEvaluations(term) ? 'LOCKED' : valueFrom(term, ['computed_gpa_display', 'gpa', 'GPA'], '0.0000'))}</div>
                            <div>Total Subject Enrolled</div><div>:</div><div class="center">${termGrades(term).length}</div>
                        </section>
                    </div>

                    <footer class="report-footer">
                        <div class="validation">
                            <div class="validation-title">VALIDATION:</div>
                            <div class="validation-note">${escapeHtml(validationMessage)}</div>
                            <div class="generated">${escapeHtml(generatedAt)}</div>
                            <div class="seal">NOT VALID WITHOUT THE UNIVERSITY SEAL</div>
                        </div>
                        <div>
                            <img class="qr" src="${qrCodeUrl}" alt="Validation QR code">
                            <div class="qr-caption">SCAN TO VALIDATE</div>
                        </div>
                    </footer>
                </main>
            </body>
        </html>
    `;
};

const printTerm = async (term: TermRecord) => {
    printPreviewLoading.value = true;
    printPreviewTitle.value = termTitle(term);

    try {
        printPreviewHtml.value = await buildTermReportHtml(term);
        printPreviewOpen.value = true;
    } finally {
        printPreviewLoading.value = false;
    }
};

const closePrintPreview = () => {
    printPreviewOpen.value = false;
};

const printPreviewFrame = () => {
    const frame = document.getElementById(
        'grade-report-preview-frame',
    ) as HTMLIFrameElement | null;

    frame?.contentWindow?.focus();
    frame?.contentWindow?.print();
};

const termHasPendingEvaluations = (term: TermRecord) => {
    return termGrades(term).some((grade) => grade.requires_evaluation === true);
};

const studentPictureUrl = computed(() => {
    const raw = String(props.result?.profile?.studentPicture ?? '').trim();

    if (!raw) {
        return '';
    }

    if (raw.startsWith('data:image')) {
        return raw;
    }

    if (raw.startsWith('http://') || raw.startsWith('https://')) {
        return raw;
    }

    if (/^[A-Za-z0-9+/=\r\n]+$/.test(raw)) {
        return `data:image/jpeg;base64,${raw.replace(/\s+/g, '')}`;
    }

    return '';
});

const studentFullName = computed(() => {
    if (!props.result?.profile) {
        return '';
    }

    const { firstName, middleInitial, lastName, extName } =
        props.result.profile;

    return [
        firstName,
        middleInitial ? `${middleInitial}` : '',
        lastName,
        extName,
    ]
        .filter(Boolean)
        .join(' ');
});

const formatDate = (dateString?: string) => {
    if (!dateString) {
        return '-';
    }

    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
};

const genderLabel = computed(() => {
    const gender = props.result?.profile?.gender?.trim().toUpperCase();

    if (gender === 'M') {
        return 'Male';
    }

    if (gender === 'F') {
        return 'Female';
    }

    return gender || '-';
});

const formatProfileValue = (key: string, value: any) => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (!trimmed) {
            return '-';
        }

        if (/date|validity/i.test(key) && /^\d{4}-\d{2}-\d{2}/.test(trimmed)) {
            return formatDate(trimmed);
        }

        return trimmed;
    }

    return String(value);
};

const profileItem = (label: string, key: string) => ({
    label,
    value: computed(() =>
        formatProfileValue(key, props.result?.profile?.[key]),
    ),
});

const profileSections = [
    {
        title: 'Academic',
        items: [
            profileItem('Student No.', 'studentNo'),
            profileItem('Campus ID', 'campusId'),
            profileItem('Program ID', 'progId'),
            profileItem('Major/Disc ID', 'majorDiscId'),
            profileItem('Year Level ID', 'yearLevelId'),
            profileItem('Application No.', 'appNo'),
            profileItem('Term ID', 'termId'),
            profileItem('Curriculum ID', 'curriculumId'),
            profileItem('Max Units Load', 'maxUnitsLoad'),
            profileItem('Fees ID', 'tblFeesId'),
            profileItem('Date Admitted', 'dateAdmitted'),
            profileItem('Date Graduated', 'dateGraduated'),
            profileItem('Program Class ID', 'progClassId'),
            profileItem('Program Class', 'progClass'),
            profileItem('Student Type', 'studentType'),
            profileItem('Student Category', 'studentCategory'),
            profileItem('Currently Enrolled', 'currentlyEnrolled'),
            profileItem('Inactive', 'inactive'),
            profileItem('Status ID', 'statusId'),
            profileItem('Status Remarks', 'statusRemarks'),
        ],
    },
    {
        title: 'Personal',
        items: [
            profileItem('Last Name', 'lastName'),
            profileItem('First Name', 'firstName'),
            profileItem('Middle Name', 'middlename'),
            profileItem('Middle Initial', 'middleInitial'),
            profileItem('Extension Name', 'extName'),
            profileItem('Date of Birth', 'dateOfBirth'),
            profileItem('Place of Birth', 'placeOfBirth'),
            profileItem('Gender', 'gender'),
            profileItem('Civil Status ID', 'civilStatusId'),
            profileItem('Religion ID', 'religionId'),
            profileItem('Nationality ID', 'nationalityId'),
            profileItem('Tribe ID', 'tribeId'),
            profileItem('Tribe', 'tribe'),
            profileItem('IP Member', 'ipMember'),
            profileItem('IP Member Tribe', 'ipMemberTribe'),
            profileItem('PWD Member', 'pwdMember'),
            profileItem('PWD Member ID', 'pwdMemberId'),
            profileItem('PWD Category', 'pwdCategory'),
            profileItem('Solo Parent', 'soloParent'),
            profileItem('Solo Parent ID', 'soloParentId'),
            profileItem('Raised by Solo Parent', 'raisedBySoloParent'),
            profileItem('Illegitimate Child', 'isIllegitimateChild'),
            profileItem('Illegitimate', 'isIllegitimate'),
        ],
    },
    {
        title: 'Contact & Health',
        items: [
            profileItem('Telephone No.', 'telNo'),
            profileItem('Mobile No.', 'mobileNo'),
            profileItem('Fax No.', 'faxNo'),
            profileItem('Email', 'email'),
            profileItem('Health ID', 'healthId'),
            profileItem('Height', 'height'),
            profileItem('Weight', 'weight'),
            profileItem('Blood Type', 'bloodType'),
            profileItem('Visa ID', 'visaId'),
            profileItem('Foreign Student', 'foreignStudent'),
            profileItem('Passport Number', 'passportNumber'),
            profileItem('Visa Status', 'visaStatus'),
            profileItem('SSP Number', 'sspNumber'),
            profileItem('Validity of Stay', 'validityOfStay'),
        ],
    },
    {
        title: 'Addresses',
        items: [
            profileItem('Residential Address', 'resAddress'),
            profileItem('Residential Street', 'resStreet'),
            profileItem('Residential Barangay', 'resBarangay'),
            profileItem('Residential Town/City', 'resTownCity'),
            profileItem('Residential Province', 'resProvince'),
            profileItem('Residential Region', 'resRegion'),
            profileItem('Residential Zip Code', 'resZipCode'),
            profileItem('Permanent Address', 'permAddress'),
            profileItem('Permanent Street', 'permStreet'),
            profileItem('Permanent Barangay', 'permBarangay'),
            profileItem('Permanent Town/City', 'permTownCity'),
            profileItem('Permanent Province', 'permProvince'),
            profileItem('Permanent Region', 'permRegion'),
            profileItem('Permanent Zip Code', 'permZipCode'),
        ],
    },
    {
        title: 'Family',
        items: [
            profileItem('Father', 'father'),
            profileItem('Father Birth Date', 'fatherBirthDate'),
            profileItem('Father Occupation', 'fatherOccupation'),
            profileItem('Father Company', 'fatherCompany'),
            profileItem('Father Company Address', 'fatherCompanyAddress'),
            profileItem('Father Tel. No.', 'fatherTelNo'),
            profileItem('Father Email', 'fatherEmail'),
            profileItem('Father Citizenship', 'fatherCitizenship'),
            profileItem('Father Education', 'fatherEducAttain'),
            profileItem(
                'Father Educational Attainment',
                'fatherEducationalAttainment',
            ),
            profileItem('Father Nature of Work', 'fatherNatureOfWork'),
            profileItem('Father Employment Status', 'fatherEmploymentStatus'),
            profileItem('Father Income From', 'fatherIncomeFrom'),
            profileItem('Father Income To', 'fatherIncomeTo'),
            profileItem('Mother', 'mother'),
            profileItem('Mother Birth Date', 'motherBirthDate'),
            profileItem('Mother Occupation', 'motherOccupation'),
            profileItem('Mother Company', 'motherCompany'),
            profileItem('Mother Company Address', 'motherCompanyAddress'),
            profileItem('Mother Tel. No.', 'motherTelNo'),
            profileItem('Mother Email', 'motherEmail'),
            profileItem('Mother Citizenship', 'motherCitizenship'),
            profileItem('Mother Education', 'motherEducAttain'),
            profileItem(
                'Mother Educational Attainment',
                'motherEducationalAttainment',
            ),
            profileItem('Mother Nature of Work', 'motherNatureOfWork'),
            profileItem('Mother Employment Status', 'motherEmploymentStatus'),
            profileItem('Mother Income From', 'motherIncomeFrom'),
            profileItem('Mother Income To', 'motherIncomeTo'),
            profileItem('Brothers', 'noofBrothers'),
            profileItem('Sisters', 'noofSisters'),
            profileItem('Family Size', 'familySize'),
            profileItem('Household No.', 'houseHoldNo'),
        ],
    },
    {
        title: 'Guardian & Emergency',
        items: [
            profileItem('Guardian', 'guardian'),
            profileItem('Relationship', 'guardianRelationship'),
            profileItem('Guardian Address', 'guardianAddress'),
            profileItem('Guardian Street', 'guardianStreet'),
            profileItem('Guardian Barangay', 'guardianBarangay'),
            profileItem('Guardian Town/City', 'guardianTownCity'),
            profileItem('Guardian Province', 'guardianProvince'),
            profileItem('Guardian Zip Code', 'guardianZipCode'),
            profileItem('Guardian Occupation', 'guardianOccupation'),
            profileItem('Guardian Company', 'guardianCompany'),
            profileItem('Guardian Tel. No.', 'guardianTelNo'),
            profileItem('Guardian Email', 'guardianEmail'),
            profileItem('Emergency Contact', 'emergencyContact'),
            profileItem('Emergency Address', 'emergencyAddress'),
            profileItem('Emergency Mobile No.', 'emergencyMobileNo'),
            profileItem('Emergency Tel. No.', 'emergencyTelNo'),
        ],
    },
    {
        title: 'Education',
        items: [
            profileItem('Elementary School', 'elemSchool'),
            profileItem('Elementary Address', 'elemAddress'),
            profileItem('Elementary Inclusive Dates', 'elemInclDates'),
            profileItem('Elementary Award/Honor', 'elemAwardHonor'),
            profileItem('High School', 'hsSchool'),
            profileItem('High School Address', 'hsAddress'),
            profileItem('High School Inclusive Dates', 'hsInclDates'),
            profileItem('High School Award/Honor', 'hsAwardHonor'),
            profileItem('SHS Track', 'shsTrack'),
            profileItem('SHS Strand', 'shsStrand'),
            profileItem('SHS School', 'shsSchool'),
            profileItem('SHS Inclusive Dates', 'shsIncldates'),
            profileItem('SHS Awards/Honors', 'shsAwardsHonors'),
            profileItem('Vocational', 'vocational'),
            profileItem('Vocational Address', 'vocationalAddress'),
            profileItem('Vocational Degree', 'vocationalDegree'),
            profileItem('Vocational Inclusive Dates', 'vocationalInclDates'),
            profileItem('College School', 'collegeSchool'),
            profileItem('College Address', 'collegeAddress'),
            profileItem('College Degree', 'collegeDegree'),
            profileItem('College Inclusive Dates', 'collegeInclDates'),
            profileItem('College Award/Honor', 'collegeAwardHonor'),
            profileItem('Last School Attended Type', 'lastSchoolAttendedType'),
            profileItem('Type of School', 'typeOfSchool'),
            profileItem('ADM', 'isAdm'),
            profileItem('ADM School', 'admSchool'),
            profileItem('ADM School Year', 'admSchoolYear'),
            profileItem('ALS', 'isAls'),
            profileItem('ALS School', 'alsSchool'),
            profileItem('ALS School Year', 'alsSchoolYear'),
        ],
    },
    {
        title: 'Additional Details',
        items: [
            profileItem('Thesis Title', 'thesisTitle'),
            profileItem('Thesis Title 2', 'thesisTitle2'),
            profileItem('Thesis Title 3', 'thesisTitle3'),
            profileItem('Admitted From GS to HS', 'admittedFromGsToHs'),
            profileItem('SES', 'ses'),
            profileItem('First Generation Student', 'firstGenerationStudent'),
            profileItem('GIDA', 'isGida'),
            profileItem('GIDA Description', 'descGida'),
            profileItem('Belongs to Farmer Family', 'isBelongToFarmer'),
            profileItem('Rebel Returnee', 'isRebelReturnee'),
        ],
    },
];
</script>

<template>
    <Head title="Registrar - Student Profile" />

    <div
        class="flex h-full flex-1 flex-col gap-4 bg-slate-50/60 p-4 dark:bg-slate-950"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">
                    Student Profile
                </h1>
                <p class="text-xs text-slate-500">
                    Search a student profile from the Academic API.
                </p>
            </div>
            <span
                v-if="result"
                class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ result.terms.length }} term{{
                    result.terms.length === 1 ? '' : 's'
                }}
                found
            </span>
        </div>

        <form
            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950"
            @submit.prevent="submit"
        >
            <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
                <label class="grid gap-1.5">
                    <span
                        class="text-xs font-bold text-slate-600 dark:text-slate-300"
                    >
                        Student Number
                    </span>
                    <input
                        v-model="form.student_no"
                        type="text"
                        maxlength="50"
                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                        placeholder="e.g. 25-15340"
                    />
                    <span
                        v-if="form.errors.student_no"
                        class="text-xs text-red-600"
                    >
                        {{ form.errors.student_no }}
                    </span>
                </label>

                <label class="grid gap-1.5">
                    <span
                        class="text-xs font-bold text-slate-600 dark:text-slate-300"
                    >
                        Campus
                    </span>
                    <select
                        v-model="form.campus_id"
                        class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                    >
                        <option value="">Select campus</option>
                        <option
                            v-for="campus in campuses"
                            :key="campus.id"
                            :value="String(campus.id)"
                        >
                            {{ campus.name }}
                            <template v-if="campus.tenant_id">
                                (Tenant {{ campus.tenant_id }})
                            </template>
                        </option>
                    </select>
                    <span
                        v-if="form.errors.campus_id"
                        class="text-xs text-red-600"
                    >
                        {{ form.errors.campus_id }}
                    </span>
                </label>

                <button
                    type="submit"
                    class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="
                        form.processing || loading || campuses.length === 0
                    "
                >
                    <Search class="h-4 w-4" />
                    {{
                        loading || form.processing
                            ? 'Searching...'
                            : 'Search Student'
                    }}
                </button>

                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                    @click="reset"
                >
                    <RefreshCw class="h-4 w-4" />
                    Reset
                </button>
            </div>

            <div
                v-if="selectedCampus"
                class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-white/[0.03] dark:text-slate-300"
            >
                Selected tenant source:
                <span class="font-bold">{{ selectedCampus.name }}</span>
                <span class="pl-2"
                    >Tenant ID: {{ selectedCampus.tenant_id ?? '-' }}</span
                >
            </div>
        </form>

        <div
            v-if="campuses.length === 0"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            Campus list is unavailable. Please check the SSO database
            connection.
        </div>

        <div
            v-if="error"
            class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            {{ error }}
        </div>

        <div
            v-if="!result && !error"
            class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <Search class="mx-auto h-10 w-10 text-slate-300" />
            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                Search Student and Campus
            </p>
        </div>

        <div
            v-else-if="result && !result.profile && result.terms.length === 0"
            class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <UserX class="mx-auto h-10 w-10 text-slate-300" />
            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                Student not Found, try again.
            </p>
        </div>

        <!-- Student Profile Card -->
        <div
            v-if="result && result.profile"
            class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
        >
            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                <!-- Profile Image -->
                <div
                    class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-100 bg-slate-50 dark:border-white/10 dark:bg-white/5"
                >
                    <img
                        v-if="studentPictureUrl"
                        :src="studentPictureUrl"
                        alt="Student profile image"
                        class="h-full w-full object-cover"
                    />
                    <User v-else class="h-10 w-10 text-slate-400" />
                </div>

                <!-- Profile Details -->
                <div class="min-w-0 flex-1">
                    <span
                        class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-bold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                    >
                        Student Profile
                    </span>
                    <h2
                        class="mt-1 text-base font-bold text-slate-900 sm:text-lg dark:text-white"
                    >
                        {{ studentFullName }}
                    </h2>

                    <div
                        class="mt-3 grid gap-x-4 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <span class="text-slate-400">Gender / DOB</span>
                            <p
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ genderLabel }} /
                                {{ formatDate(result.profile.dateOfBirth) }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Place of Birth</span>
                            <p
                                class="truncate font-bold text-slate-700 dark:text-slate-200"
                                :title="result.profile.placeOfBirth"
                            >
                                {{ result.profile.placeOfBirth || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Email Address</span>
                            <p
                                class="truncate font-bold text-slate-700 dark:text-slate-200"
                                :title="result.profile.email"
                            >
                                {{ result.profile.email || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400">Student Number</span>
                            <p
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ result.student_no }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="border-y border-slate-100 px-3 py-2 dark:border-white/10"
            >
                <div class="flex gap-2 overflow-x-auto">
                    <button
                        v-for="tab in studentTabs"
                        :key="tab.id"
                        type="button"
                        class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-xs font-bold transition"
                        :class="
                            activeStudentTab === tab.id
                                ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5'
                        "
                        @click="activeStudentTab = tab.id"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="p-4">
                <div
                    v-if="activeStudentTab === 'student-profile'"
                    class="space-y-4"
                >
                    <section
                        v-for="section in profileSections"
                        :key="section.title"
                        class="rounded-lg border border-slate-200 dark:border-white/10"
                    >
                        <div
                            class="border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <h3
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                {{ section.title }}
                            </h3>
                        </div>
                        <div
                            class="grid gap-px bg-slate-100 text-sm sm:grid-cols-2 lg:grid-cols-3 dark:bg-white/10"
                        >
                            <div
                                v-for="item in section.items"
                                :key="`${section.title}-${item.label}`"
                                class="min-w-0 bg-white p-3 dark:bg-slate-950"
                            >
                                <p
                                    class="text-[11px] font-bold tracking-wide text-slate-400 uppercase"
                                >
                                    {{ item.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs font-semibold break-words text-slate-700 dark:text-slate-200"
                                >
                                    {{ item.value }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    v-else-if="activeStudentTab === 'report-of-grades'"
                    class="space-y-4"
                >
                    <div
                        v-if="result.evaluation_error"
                        class="rounded-xl border p-4 shadow-sm"
                        :class="{
                            'border-red-200 bg-red-50 dark:border-red-400/30 dark:bg-red-500/10':
                                result.evaluation_error_type === 'connectivity',
                            'border-amber-200 bg-amber-50 dark:border-amber-400/30 dark:bg-amber-500/10':
                                result.evaluation_error_type !== 'connectivity',
                        }"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                                :class="{
                                    'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400':
                                        result.evaluation_error_type ===
                                        'connectivity',
                                    'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400':
                                        result.evaluation_error_type ===
                                            'no_data' ||
                                        !result.evaluation_error_type,
                                    'bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400':
                                        result.evaluation_error_type ===
                                        'missing_context',
                                }"
                            >
                                <WifiOff
                                    v-if="
                                        result.evaluation_error_type ===
                                        'connectivity'
                                    "
                                    class="size-5 shrink-0"
                                />
                                <ServerCrash
                                    v-else-if="
                                        result.evaluation_error_type ===
                                        'no_data'
                                    "
                                    class="size-5 shrink-0"
                                />
                                <UserX
                                    v-else-if="
                                        result.evaluation_error_type ===
                                        'missing_context'
                                    "
                                    class="size-5 shrink-0"
                                />
                                <AlertCircle v-else class="size-5 shrink-0" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-semibold"
                                    :class="{
                                        'text-red-800 dark:text-red-300':
                                            result.evaluation_error_type ===
                                            'connectivity',
                                        'text-amber-800 dark:text-amber-200':
                                            result.evaluation_error_type !==
                                            'connectivity',
                                    }"
                                >
                                    <span
                                        v-if="
                                            result.evaluation_error_type ===
                                            'connectivity'
                                        "
                                        >Evaluation Service Unavailable</span
                                    >
                                    <span
                                        v-else-if="
                                            result.evaluation_error_type ===
                                            'no_data'
                                        "
                                        >Evaluation Data Not Found</span
                                    >
                                    <span
                                        v-else-if="
                                            result.evaluation_error_type ===
                                            'missing_context'
                                        "
                                        >Account Setup Incomplete</span
                                    >
                                    <span v-else
                                        >Grades Temporarily Locked</span
                                    >
                                </p>
                                <p
                                    class="mt-1 text-xs"
                                    :class="{
                                        'text-red-700 dark:text-red-400':
                                            result.evaluation_error_type ===
                                            'connectivity',
                                        'text-amber-700 dark:text-amber-300':
                                            result.evaluation_error_type !==
                                            'connectivity',
                                    }"
                                >
                                    {{ result.evaluation_error }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <p
                            class="text-[11px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300"
                        >
                            Grade Report Result
                        </p>
                        <div class="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                            <div>
                                <span class="text-xs text-slate-500"
                                    >Student Number</span
                                >
                                <p
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    {{ result.student_no }}
                                </p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500"
                                    >Selected Campus</span
                                >
                                <p
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    {{ result.campus.name }}
                                </p>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500"
                                    >Tenant ID</span
                                >
                                <p
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    {{ result.campus.tenant_id }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="result.terms.length === 0"
                        class="p-10 text-center"
                    >
                        <FileSearch class="mx-auto h-10 w-10 text-slate-300" />
                        <p
                            class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                        >
                            No grade report found
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            The Academic API returned no term records for this
                            student and campus.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="term in result.terms"
                            :key="`${term.termId ?? term.regId ?? termTitle(term)}`"
                            :id="`registrar-term-${term.termId ?? term.regId ?? ''}`"
                            class="overflow-hidden rounded-lg border border-slate-200 dark:border-white/10"
                        >
                            <div
                                class="bg-slate-50 px-4 py-3 dark:bg-white/[0.03]"
                            >
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <h2
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ termTitle(term) }}
                                        </h2>
                                        <p class="text-xs text-slate-500">
                                            Section:
                                            {{
                                                valueFrom(term, [
                                                    'sectionName',
                                                    'section_name',
                                                ])
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        <span
                                            class="rounded-full bg-white px-2.5 py-1 text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10"
                                        >
                                            Subjects:
                                            {{
                                                valueFrom(
                                                    term,
                                                    [
                                                        'enrolledSubjects',
                                                        'subjectsEnrolled',
                                                    ],
                                                    '0',
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="rounded-full bg-white px-2.5 py-1 text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10"
                                        >
                                            Counted Units:
                                            {{
                                                valueFrom(
                                                    term,
                                                    [
                                                        'computed_counted_units_display',
                                                        'totalCreditUnits',
                                                        'total_credit_units',
                                                    ],
                                                    '0',
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                termHasPendingEvaluations(term)
                                            "
                                            class="rounded-full bg-amber-50 px-2.5 py-1 font-bold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20"
                                        >
                                            GPA Hidden (Evaluation Required)
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20"
                                        >
                                            GPA:
                                            {{
                                                valueFrom(
                                                    term,
                                                    [
                                                        'computed_gpa_display',
                                                        'gpa',
                                                        'GPA',
                                                    ],
                                                    '0.0000',
                                                )
                                            }}
                                        </span>
                                        <button
                                            type="button"
                                            class="no-print inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                                            :disabled="printPreviewLoading"
                                            @click="printTerm(term)"
                                        >
                                            <Printer class="h-3.5 w-3.5" />
                                            {{
                                                printPreviewLoading
                                                    ? 'Preparing...'
                                                    : 'Print Grade Per Sem'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table
                                    class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10"
                                >
                                    <thead class="bg-white dark:bg-slate-950">
                                        <tr>
                                            <th class="registrar-th">
                                                Course Code
                                            </th>
                                            <th class="registrar-th">
                                                Course Title
                                            </th>
                                            <th class="registrar-th">
                                                Class Section
                                            </th>
                                            <th class="registrar-th">Units</th>
                                            <th class="registrar-th">
                                                Midterm
                                            </th>
                                            <th class="registrar-th">Final</th>
                                            <th class="registrar-th">Reexam</th>
                                            <th class="registrar-th">
                                                Remarks
                                            </th>
                                            <th class="registrar-th">
                                                Date Posted
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-white/10"
                                    >
                                        <tr
                                            v-if="termGrades(term).length === 0"
                                        >
                                            <td
                                                colspan="9"
                                                class="px-4 py-8 text-center text-xs text-slate-500"
                                            >
                                                No course-level grades in this
                                                term.
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="grade in termGrades(term)"
                                            :key="`${grade.courseId ?? grade.courseCode}:${grade.schedId ?? grade.regId}`"
                                            :class="[
                                                rowStatusClass(grade),
                                                'hover:bg-slate-50/70 dark:hover:bg-white/[0.03]',
                                            ]"
                                        >
                                            <td
                                                class="registrar-td font-bold text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    valueFrom(grade, [
                                                        'courseCode',
                                                        'course_code',
                                                    ])
                                                }}
                                            </td>
                                            <td class="registrar-td min-w-64">
                                                {{
                                                    valueFrom(grade, [
                                                        'courseTitle',
                                                        'course_title',
                                                    ])
                                                }}
                                            </td>
                                            <td class="registrar-td">
                                                {{
                                                    valueFrom(grade, [
                                                        'classSection',
                                                        'class_section',
                                                    ])
                                                }}
                                            </td>
                                            <td class="registrar-td">
                                                {{
                                                    valueFrom(grade, [
                                                        'unit',
                                                        'units',
                                                    ])
                                                }}
                                            </td>
                                            <td class="registrar-td">
                                                <template
                                                    v-if="
                                                        grade.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        valueFrom(grade, [
                                                            'midTerm',
                                                            'midterm',
                                                            'mid_term',
                                                        ])
                                                    }}
                                                </template>
                                            </td>
                                            <td class="registrar-td">
                                                <template
                                                    v-if="
                                                        grade.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        valueFrom(grade, [
                                                            'finalGrade',
                                                            'final_grade',
                                                            'grade',
                                                            'final',
                                                        ])
                                                    }}
                                                </template>
                                            </td>
                                            <td class="registrar-td">
                                                <template
                                                    v-if="
                                                        grade.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="text-[10px] font-bold text-slate-300 dark:text-slate-600"
                                                        >LOCKED</span
                                                    >
                                                </template>
                                                <template v-else>
                                                    {{
                                                        valueFrom(grade, [
                                                            'reeExam',
                                                            'reExam',
                                                            'reexam',
                                                        ])
                                                    }}
                                                </template>
                                            </td>
                                            <td class="registrar-td">
                                                <template
                                                    v-if="
                                                        grade.requires_evaluation
                                                    "
                                                >
                                                    <span
                                                        class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 uppercase dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                                                    >
                                                        Evaluate faculty first
                                                    </span>
                                                </template>
                                                <template v-else>
                                                    <span
                                                        class="rounded-full px-2 py-1 text-[11px] font-bold ring-1"
                                                        :class="
                                                            remarkBadgeClass(
                                                                grade,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            valueFrom(grade, [
                                                                'remarks',
                                                                'remark',
                                                                'status',
                                                            ])
                                                        }}
                                                    </span>
                                                </template>
                                            </td>
                                            <td class="registrar-td">
                                                {{ formatDateTime(grade) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </div>

                <div
                    v-else-if="activeStudentTab === 'curriculum'"
                    class="space-y-4"
                >
                    <section
                        class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 md:flex-row md:items-center md:justify-between dark:border-white/10"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                >
                                    <Library class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        class="truncate text-lg font-bold text-slate-950 dark:text-white"
                                    >
                                        Curriculum
                                    </h3>
                                    <p
                                        class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Program structure, subjects, units,
                                        prerequisites, and academic sequence.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="inline-flex h-8 w-fit items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                >
                                    <BookOpen class="size-4" />
                                    Academic Program
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="
                                        Boolean(curriculumData.error) ||
                                        curriculumTotalSubjects === 0
                                    "
                                    @click="printCurriculum"
                                >
                                    <Printer class="size-4" />
                                    Print Curriculum
                                </button>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <div
                                class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-sky-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-sky-500/10"
                            >
                                <div
                                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <Layers class="size-4 text-sky-600" />
                                    Subjects
                                </div>
                                <div
                                    class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                                >
                                    {{ curriculumTotalSubjects }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-emerald-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-emerald-500/10"
                            >
                                <div
                                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <CheckCircle2
                                        class="size-4 text-emerald-600"
                                    />
                                    Total Units
                                </div>
                                <div
                                    class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                                >
                                    {{
                                        formatCurriculumUnit(
                                            curriculumTotalUnits,
                                        )
                                    }}
                                </div>
                                <div
                                    class="mt-2 flex flex-wrap gap-1.5 text-[10px] font-bold uppercase"
                                >
                                    <span
                                        class="rounded-full border border-emerald-200 bg-white/80 px-2 py-0.5 text-emerald-700 dark:border-emerald-400/30 dark:bg-white/10 dark:text-emerald-300"
                                    >
                                        Lec
                                        {{
                                            formatCurriculumUnit(
                                                curriculumTotalLectureUnits,
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="rounded-full border border-cyan-200 bg-white/80 px-2 py-0.5 text-cyan-700 dark:border-cyan-400/30 dark:bg-white/10 dark:text-cyan-300"
                                    >
                                        Lab
                                        {{
                                            formatCurriculumUnit(
                                                curriculumTotalLabUnits,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-amber-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-amber-500/10"
                            >
                                <div
                                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <GraduationCap
                                        class="size-4 text-amber-600"
                                    />
                                    Year Levels
                                </div>
                                <div
                                    class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                                >
                                    {{ curriculumProcessedData.length }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 bg-gradient-to-br from-white to-violet-50/70 p-3 dark:border-white/10 dark:from-white/5 dark:to-violet-500/10"
                            >
                                <div
                                    class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <CalendarDays
                                        class="size-4 text-violet-600"
                                    />
                                    Terms
                                </div>
                                <div
                                    class="mt-2 text-2xl font-bold text-slate-950 dark:text-white"
                                >
                                    {{ curriculumTotalSemesters }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10"
                        >
                            <div>
                                <h3
                                    class="text-sm font-bold text-slate-950 dark:text-white"
                                >
                                    Program Sequence
                                </h3>
                                <p
                                    class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Subjects are grouped by year level and term.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="curriculumData.error"
                            class="m-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <span>{{ curriculumData.error }}</span>
                        </div>

                        <div
                            v-else-if="!curriculumProcessedData.length"
                            class="flex min-h-[320px] flex-col items-center justify-center gap-2 p-6 text-center"
                        >
                            <Library
                                class="size-10 text-slate-300 dark:text-slate-700"
                            />
                            <h3
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                No curriculum data
                            </h3>
                            <p
                                class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                No curriculum records are currently available
                                for this student's program.
                            </p>
                        </div>

                        <div
                            v-else
                            class="divide-y divide-slate-200 dark:divide-white/10"
                        >
                            <div
                                v-for="year in curriculumProcessedData"
                                :key="year.year"
                                class="bg-white dark:bg-slate-950"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-2.5 dark:bg-white/5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <GraduationCap
                                            class="size-4 shrink-0 text-slate-500 dark:text-slate-400"
                                        />
                                        <h3
                                            class="truncate text-xs font-bold text-slate-700 uppercase dark:text-slate-200"
                                        >
                                            {{ year.year }}
                                        </h3>
                                    </div>
                                    <span
                                        class="text-[11px] font-bold text-slate-500 dark:text-slate-400"
                                    >
                                        {{ year.semesters.length }} terms
                                    </span>
                                </div>

                                <Collapsible
                                    v-for="sem in year.semesters"
                                    :key="sem.semester"
                                    v-model:open="
                                        expandedCurriculumSems[
                                            `${year.year}-${sem.semester}`
                                        ]
                                    "
                                    class="border-t border-slate-100 first:border-t-0 dark:border-white/10"
                                >
                                    <CollapsibleTrigger as-child>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                                        >
                                            <div class="min-w-0">
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <h4
                                                        class="truncate text-sm font-bold text-slate-950 dark:text-white"
                                                    >
                                                        {{ sem.semester }}
                                                    </h4>
                                                    <span
                                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-white/10 dark:text-slate-300"
                                                    >
                                                        {{ sem.rows.length }}
                                                        subjects
                                                    </span>
                                                </div>
                                                <p
                                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                                >
                                                    <Clock class="size-3" />
                                                    {{
                                                        curriculumSemesterUnits(
                                                            sem.rows,
                                                        )
                                                    }}
                                                    units
                                                </p>
                                            </div>
                                            <ChevronDown
                                                class="size-4 shrink-0 text-slate-400 transition"
                                                :class="
                                                    expandedCurriculumSems[
                                                        `${year.year}-${sem.semester}`
                                                    ]
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
                                            <table
                                                class="w-full min-w-[920px] text-sm"
                                            >
                                                <thead
                                                    class="bg-slate-50 text-left text-[11px] font-bold text-slate-500 uppercase dark:bg-white/5 dark:text-slate-400"
                                                >
                                                    <tr>
                                                        <th class="px-4 py-3">
                                                            Subject
                                                        </th>
                                                        <th class="px-4 py-3">
                                                            Description
                                                        </th>
                                                        <th
                                                            class="px-4 py-3 text-center"
                                                        >
                                                            Lecture Unit
                                                        </th>
                                                        <th
                                                            class="px-4 py-3 text-center"
                                                        >
                                                            Lab Unit
                                                        </th>
                                                        <th class="px-4 py-3">
                                                            Prerequisites
                                                        </th>
                                                        <th
                                                            class="px-4 py-3 text-center"
                                                        >
                                                            Is Taken
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody
                                                    class="divide-y divide-slate-100 dark:divide-white/10"
                                                >
                                                    <tr
                                                        v-for="(
                                                            row, index
                                                        ) in sem.rows"
                                                        :key="index"
                                                        class="hover:bg-slate-50/80 dark:hover:bg-white/5"
                                                    >
                                                        <td
                                                            class="px-4 py-3 align-top text-xs font-bold text-slate-900 dark:text-white"
                                                        >
                                                            {{
                                                                curriculumPick(
                                                                    row,
                                                                    curriculumColumns[0]
                                                                        .keys,
                                                                )
                                                            }}
                                                        </td>
                                                        <td
                                                            class="max-w-md px-4 py-3 align-top text-xs font-medium text-slate-600 dark:text-slate-300"
                                                        >
                                                            {{
                                                                curriculumPick(
                                                                    row,
                                                                    curriculumColumns[1]
                                                                        .keys,
                                                                )
                                                            }}
                                                        </td>
                                                        <td
                                                            class="px-4 py-3 text-center align-top"
                                                        >
                                                            <span
                                                                class="inline-flex min-w-9 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800 dark:bg-white/10 dark:text-slate-100"
                                                            >
                                                                {{
                                                                    curriculumSubjectLectureUnitsDisplay(
                                                                        row,
                                                                    )
                                                                }}
                                                            </span>
                                                        </td>
                                                        <td
                                                            class="px-4 py-3 text-center align-top"
                                                        >
                                                            <span
                                                                class="inline-flex min-w-9 items-center justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800 dark:bg-white/10 dark:text-slate-100"
                                                            >
                                                                {{
                                                                    curriculumSubjectLabUnitsDisplay(
                                                                        row,
                                                                    )
                                                                }}
                                                            </span>
                                                        </td>
                                                        <td
                                                            class="px-4 py-3 align-top text-xs font-medium text-slate-500 dark:text-slate-400"
                                                        >
                                                            <div
                                                                v-if="
                                                                    curriculumPrerequisiteItems(
                                                                        row,
                                                                    ).length
                                                                "
                                                                class="flex max-w-xs flex-wrap gap-1.5"
                                                            >
                                                                <span
                                                                    v-for="prerequisite in curriculumPrerequisiteItems(
                                                                        row,
                                                                    )"
                                                                    :key="
                                                                        prerequisite
                                                                    "
                                                                    class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-300"
                                                                >
                                                                    {{
                                                                        prerequisite
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <span
                                                                v-else
                                                                class="text-slate-400"
                                                            >
                                                                None
                                                            </span>
                                                        </td>
                                                        <td
                                                            class="px-4 py-3 text-center align-top"
                                                        >
                                                            <span
                                                                v-if="
                                                                    curriculumTakenStatus(
                                                                        row,
                                                                    ) !== ''
                                                                "
                                                                class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                                :class="
                                                                    curriculumTakenStatusClass(
                                                                        curriculumTakenStatus(
                                                                            row,
                                                                        ),
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    curriculumTakenStatus(
                                                                        row,
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
                        </div>
                    </section>
                </div>

                <div
                    v-else-if="activeStudentTab === 'documents'"
                    class="space-y-4"
                >
                    <section
                        class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                >
                                    <FileText class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        class="truncate text-lg font-bold text-slate-950 dark:text-white"
                                    >
                                        Documents
                                    </h3>
                                    <p
                                        class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Admission requirements matched from the
                                        CEE portal by student number and campus.
                                    </p>
                                </div>
                            </div>

                            <span
                                class="inline-flex h-8 w-fit items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                            >
                                <FileText class="size-4" />
                                {{ ceeDocumentCount }} file{{
                                    ceeDocumentCount === 1 ? '' : 's'
                                }}
                            </span>
                        </div>

                        <div
                            v-if="ceeDocuments.error"
                            class="m-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <span>{{ ceeDocuments.error }}</span>
                        </div>

                        <div
                            v-if="ceeDocumentCount === 0"
                            class="flex min-h-[260px] flex-col items-center justify-center gap-2 p-6 text-center"
                        >
                            <FileText
                                class="size-10 text-slate-300 dark:text-slate-700"
                            />
                            <h3
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                No CEE uploaded files found
                            </h3>
                            <p
                                class="max-w-md text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                Uploaded admission requirements from the CEE
                                portal will appear here once matched by student
                                number and campus.
                            </p>
                        </div>

                        <div v-else class="grid gap-4 p-4">
                            <section
                                v-for="group in groupedCeeDocuments"
                                :key="group.key"
                                class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                        >
                                            <FileText class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <h4
                                                class="truncate text-sm font-bold text-slate-950 dark:text-white"
                                            >
                                                {{ group.label }}
                                            </h4>
                                            <p
                                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                {{ group.documents.length }}
                                                file{{
                                                    group.documents.length === 1
                                                        ? ''
                                                        : 's'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-3 p-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <div
                                        v-for="document in group.documents"
                                        :key="document.key"
                                        class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-950"
                                    >
                                        <div
                                            class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-slate-50 dark:bg-white/5"
                                        >
                                            <img
                                                v-if="
                                                    document.exists &&
                                                    isImageDocument(document)
                                                "
                                                :src="document.url"
                                                :alt="document.label"
                                                class="size-full object-contain"
                                                loading="lazy"
                                            />
                                            <iframe
                                                v-else-if="
                                                    document.exists &&
                                                    isPdfDocument(document)
                                                "
                                                :src="document.url"
                                                :title="document.label"
                                                class="size-full border-0"
                                            />
                                            <div
                                                v-else
                                                class="flex flex-col items-center gap-2 p-6 text-center"
                                            >
                                                <FileText
                                                    class="size-10 text-slate-300"
                                                />
                                                <p
                                                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        document.exists
                                                            ? 'Preview is not available for this file type.'
                                                            : 'File not found in linked storage.'
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 p-3">
                                            <a
                                                :href="document.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-50 px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20"
                                            >
                                                <ExternalLink class="size-4" />
                                                View
                                            </a>
                                            <a
                                                :href="document.url"
                                                :download="document.name"
                                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-sky-50 px-3 text-xs font-bold text-sky-700 transition hover:bg-sky-100 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                                            >
                                                <Download class="size-4" />
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>
                </div>

                <div v-else class="p-10 text-center">
                    <component
                        :is="
                            studentTabs.find(
                                (tab) => tab.id === activeStudentTab,
                            )?.icon ?? FileText
                        "
                        class="mx-auto h-10 w-10 text-slate-300"
                    />
                    <p
                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            studentTabs.find(
                                (tab) => tab.id === activeStudentTab,
                            )?.label
                        }}
                        will be developed soon.
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="printPreviewOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300"
                        >
                            Report Preview
                        </p>
                        <h2
                            class="truncate text-sm font-bold text-slate-900 dark:text-white"
                        >
                            {{ printPreviewTitle }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                            @click="closePrintPreview"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"
                            @click="printPreviewFrame"
                        >
                            <Printer class="h-4 w-4" />
                            Print / Save PDF
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 bg-slate-100 p-3 dark:bg-slate-900">
                    <iframe
                        id="grade-report-preview-frame"
                        title="Report of Grades preview"
                        class="h-full w-full rounded-md border border-slate-200 bg-white dark:border-white/10"
                        :srcdoc="printPreviewHtml"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="curriculumPreviewOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl dark:bg-slate-950"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-bold tracking-wide text-emerald-600 uppercase dark:text-emerald-300"
                        >
                            Curriculum Preview
                        </p>
                        <h2
                            class="truncate text-sm font-bold text-slate-900 dark:text-white"
                        >
                            {{ curriculumPreviewTitle }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-white/5"
                            @click="closeCurriculumPreview"
                        >
                            Close
                        </button>
                        <a
                            :href="curriculumDownloadUrl"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-3 text-xs font-bold text-sky-700 transition hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                        >
                            <Download class="h-4 w-4" />
                            Save PDF
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"
                            @click="printCurriculumPreviewFrame"
                        >
                            <Printer class="h-4 w-4" />
                            Print
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 bg-slate-100 p-3 dark:bg-slate-900">
                    <iframe
                        id="curriculum-preview-frame"
                        title="Curriculum preview"
                        class="h-full w-full rounded-md border border-slate-200 bg-white dark:border-white/10"
                        :src="curriculumPreviewUrl"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.registrar-th {
    padding: 0.75rem;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.registrar-td {
    padding: 0.75rem;
    font-size: 0.75rem;
    color: rgb(71 85 105);
}

.registrar-row-failed {
    background-color: rgb(254 242 242);
}

.registrar-row-ip {
    background-color: rgb(254 252 232);
}

.registrar-row-inc {
    background-color: rgb(255 247 237);
}

:global(.dark) .registrar-th {
    color: rgb(148 163 184);
}

:global(.dark) .registrar-td {
    color: rgb(203 213 225);
}

:global(.dark) .registrar-row-failed {
    background-color: rgb(127 29 29 / 0.22);
}

:global(.dark) .registrar-row-ip {
    background-color: rgb(113 63 18 / 0.22);
}

:global(.dark) .registrar-row-inc {
    background-color: rgb(124 45 18 / 0.22);
}
</style>
